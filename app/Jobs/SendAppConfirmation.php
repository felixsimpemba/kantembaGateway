<?php

namespace App\Jobs;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fires an HTTP POST to the app's confirmation_url whenever a payment is confirmed.
 *
 * Payload sent to the client app:
 * {
 *   "event":            "payment.confirmed",
 *   "reference":        "PAY-XXXX",
 *   "external_user_id": "user_42",   ← the ID from the client app
 *   "amount":           250.00,
 *   "currency":         "ZMW",
 *   "app_id":           "uuid",
 *   "paid_at":          "2026-02-26T22:00:00Z",
 *   "signature":        "hmac-sha256 of the payload"
 * }
 */
class SendAppConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [30, 60, 120, 300, 600]; // 30s → 10 min

    public function __construct(
        public readonly Payment $payment,
        public readonly string  $confirmationUrl,
        public readonly string  $appSecret,
    ) {}

    public function handle(): void
    {
        $payment  = $this->payment->fresh();
        $appUser  = $payment->appUser;
        $app      = $payment->app;

        $payload = [
            'event'            => 'payment.confirmed',
            'reference'        => $payment->reference,
            'external_user_id' => $appUser?->external_user_id,
            'user_name'        => $appUser?->name,
            'user_email'       => $appUser?->email,
            'user_phone'       => $appUser?->phone,
            'amount'           => (float) $payment->amount,
            'fee'              => (float) $payment->fee,
            'net_amount'       => (float) $payment->net_amount,
            'currency'         => $payment->currency,
            'payment_method'   => $payment->payment_method,
            'app_id'           => $app?->app_id,
            'paid_at'          => $payment->updated_at?->toIso8601String(),
        ];

        $timestamp   = time();
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature   = hash_hmac('sha256', $timestamp . '.' . $payloadJson, $this->appSecret);

        Log::info("[SendAppConfirmation] Hitting {$this->confirmationUrl} for payment {$payment->reference}");
        Log::info("[SendAppConfirmation] Payload: " . $payloadJson);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type'  => 'application/json',
                    'X-Signature'   => $signature,
                    'X-Timestamp'   => $timestamp,
                    'X-Event'       => 'payment.confirmed',
                    'X-App-Id'      => $app?->app_id ?? '',
                ])
                ->post($this->confirmationUrl, $payload);

            Log::info("[SendAppConfirmation] Received {$response->status()} from {$this->confirmationUrl}");
            Log::info("[SendAppConfirmation] Response Body: " . $response->json());

            if ($response->successful()) {
                Log::info("[SendAppConfirmation] Success ({$response->status()}) for {$payment->reference}");
                return;
            }

            // Non-2xx → retry
            Log::warning("[SendAppConfirmation] Received {$response->status()} from {$this->confirmationUrl} — will retry (attempt {$this->attempts()})");
            $this->release($this->backoff[$this->attempts() - 1] ?? 600);

        } catch (\Exception $e) {
            Log::error("[SendAppConfirmation] Exception for {$payment->reference}: " . $e->getMessage());
            $this->release($this->backoff[$this->attempts() - 1] ?? 600);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error(
            "[SendAppConfirmation] Permanently failed for payment {$this->payment->reference} → {$this->confirmationUrl}: " . $e->getMessage()
        );
    }
}
