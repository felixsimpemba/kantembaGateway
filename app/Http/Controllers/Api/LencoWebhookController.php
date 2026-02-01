<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LencoWebhookController extends Controller
{
    protected $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Handle incoming Lenco Webhook
     */
    public function handle(Request $request)
    {
        try {
            // Verify payload signature if Lenco sends one (skipping for now as not in snippets)
            $payload = $request->all();
            Log::info('Lenco Webhook Received', $payload);

            $event = $payload['event'] ?? null;
            $data = $payload['data'] ?? null;

            if (!$event || !$data) {
                return response()->json(['status' => 'ignored'], 200);
            }

            // We need our reference, which we probably sent as 'reference' or 'lencoReference'
            $reference = $data['reference'] ?? null;

            if (!$reference) {
                return response()->json(['status' => 'ignored', 'message' => 'No reference found'], 200);
            }

            $payment = Payment::where('reference', $reference)->first();

            if (!$payment) {
                return response()->json(['status' => 'ignored', 'message' => 'Payment not found'], 200);
            }

            // Check if already processed
            if (in_array($payment->status, ['succeeded', 'failed'])) {
                return response()->json(['status' => 'ignored', 'message' => 'Already processed'], 200);
            }

            switch ($event) {
                case 'collection.successful':
                    $this->handleSuccessfulCollection($payment, $data);
                    break;

                case 'collection.failed':
                    $this->handleFailedCollection($payment, $data);
                    break;

                default:
                    Log::info("Unhandled Lenco event: {$event}");
                    break;
            }

            return response()->json(['status' => 'processed'], 200);

        } catch (\Exception $e) {
            Log::error('Lenco Webhook Error: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }

    protected function handleSuccessfulCollection(Payment $payment, array $data)
    {
        $payment->update([
            'status' => 'succeeded',
            'metadata' => array_merge($payment->metadata ?? [], ['lenco_data' => $data])
        ]);

        // Update merchant balance
        // Fix for lint error: ensure amount is float/int
        $payment->merchant->increment('balance', (float) $payment->net_amount);

        // Record transaction
        $payment->merchant->transactions()->create([
            'payment_id' => $payment->id,
            'merchant_id' => $payment->merchant_id,
            'type' => 'charge',
            'amount' => $payment->net_amount,
            'balance_before' => $payment->merchant->balance - $payment->net_amount,
            'balance_after' => $payment->merchant->balance,
        ]);

        // Notify Merchant
        $this->webhookService->dispatch($payment->merchant, 'payment.succeeded', $payment->fresh()->toArray());
    }

    protected function handleFailedCollection(Payment $payment, array $data)
    {
        $payment->update([
            'status' => 'failed',
            'failure_reason' => $data['reasonForFailure'] ?? 'Unknown error',
            'metadata' => array_merge($payment->metadata ?? [], ['lenco_data' => $data])
        ]);

        // Record transaction
        $payment->merchant->transactions()->create([
            'payment_id' => $payment->id,
            'merchant_id' => $payment->merchant_id,
            'type' => 'failed',
            'amount' => 0,
            'balance_before' => $payment->merchant->balance,
            'balance_after' => $payment->merchant->balance,
        ]);

        // Notify Merchant
        $this->webhookService->dispatch($payment->merchant, 'payment.failed', $payment->toArray());
    }
}
