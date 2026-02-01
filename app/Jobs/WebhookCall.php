<?php

namespace App\Jobs;

use App\Models\WebhookLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookCall implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $url;
    public $payload;
    public $secret;
    public $merchantId;
    public $eventType;
    public $webhookId; // Optional, if linked to specific webhook record

    /**
     * Create a new job instance.
     */
    public function __construct(string $url, array $payload, string $secret, int $merchantId, string $eventType, ?int $webhookId = null)
    {
        $this->url = $url;
        $this->payload = $payload;
        $this->secret = $secret;
        $this->merchantId = $merchantId;
        $this->eventType = $eventType;
        $this->webhookId = $webhookId;
    }

    /**
     * Calculate signature
     */
    protected function calculateSignature(string $payloadJson, int $timestamp): string
    {
        // Signature = HMAC-SHA256(timestamp + "." + payload, secret)
        return hash_hmac('sha256', $timestamp . '.' . $payloadJson, $this->secret);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $timestamp = time();
        $payloadJson = json_encode($this->payload);
        $signature = $this->calculateSignature($payloadJson, $timestamp);

        try {
            Log::info("Sending webhook to {$this->url} for event {$this->eventType}");

            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Signature' => $signature,
                    'X-Timestamp' => $timestamp,
                    'X-Event' => $this->eventType,
                ])
                ->post($this->url, $this->payload);

            // Log attempt
            WebhookLog::create([
                'merchant_id' => $this->merchantId,
                'event_type' => $this->eventType,
                'payload' => $this->payload,
                'url' => $this->url,
                'status_code' => $response->status(),
                'response' => $response->body(),
                'signature' => $signature,
                'attempts' => $this->attempts(),
            ]);

            if (!$response->successful()) {
                Log::warning("Webhook failed with status {$response->status()}");
                // Job will be retried automatically if configured, or we can manually fail
                if ($this->attempts() < 3) {
                    $this->release(pow(2, $this->attempts()) * 10); // Exponential backoff: 20s, 40s
                } else {
                    $this->fail(new \Exception("Webhook failed after {$this->attempts()} attempts with status {$response->status()}"));
                }
            }

        } catch (\Exception $e) {
            Log::error("Webhook exception: " . $e->getMessage());

            WebhookLog::create([
                'merchant_id' => $this->merchantId,
                'event_type' => $this->eventType,
                'payload' => $this->payload,
                'url' => $this->url,
                'status_code' => 0, // 0 indicates exception/connection failure
                'response' => $e->getMessage(),
                'signature' => $signature,
                'attempts' => $this->attempts(),
            ]);

            if ($this->attempts() < 3) {
                $this->release(pow(2, $this->attempts()) * 10);
            } else {
                $this->fail($e);
            }
        }
    }
}
