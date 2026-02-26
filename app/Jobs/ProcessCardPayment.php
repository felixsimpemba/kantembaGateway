<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\Transaction;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCardPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry up to 3 times total */
    public int $tries = 3;

    /** Back-off: 30 seconds between retries */
    public int $backoff = 30;

    /** Card numbers that always succeed in test mode */
    private const TEST_CARDS = [
        '4242424242424242' => 'success',
        '5555555555554444' => 'success',
        '4000000000000002' => 'card_declined',
        '4000000000009995' => 'insufficient_funds',
        '4000000000000069' => 'expired_card',
        '4000000000000127' => 'incorrect_cvc',
        '4000000000000119' => 'processing_error',
    ];

    public function __construct(
        public readonly Payment $payment,
        public readonly array   $cardData,
    ) {}

    public function handle(WebhookService $webhookService): void
    {
        Log::info("[ProcessCardPayment] Starting job for payment {$this->payment->reference}");

        // Re-fetch to get current state (avoids stale model from queue serialisation)
        $payment = $this->payment->fresh();

        // Guard: skip if already processed
        if (!in_array($payment->status, ['initialized', 'failed'])) {
            Log::info("[ProcessCardPayment] Skipping — already in status: {$payment->status}");
            return;
        }

        // ── Mark as processing so duplicate jobs are ignored ─────────
        $payment->update(['status' => 'pending']);

        // ── Simulate processing (replace with real PSP call) ─────────
        usleep(500_000); // 0.5 s — safe inside a worker, NOT on the HTTP thread

        $outcome = self::TEST_CARDS[$this->cardData['card_number']] ?? 'success';

        if ($outcome === 'success') {
            $this->handleSuccess($payment, $webhookService);
        } else {
            $this->handleFailure($payment, $outcome, $webhookService);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────

    private function handleSuccess(Payment $payment, WebhookService $webhookService): void
    {
        $payment->update([
            'status'        => 'succeeded',
            'card_last4'    => substr($this->cardData['card_number'], -4),
            'card_brand'    => $this->detectBrand($this->cardData['card_number']),
            'card_exp_month'=> $this->cardData['exp_month'],
            'card_exp_year' => $this->cardData['exp_year'],
        ]);

        $merchant = $payment->merchant;
        $merchant->increment('balance', (float) $payment->net_amount);

        Transaction::create([
            'payment_id'     => $payment->id,
            'merchant_id'    => $payment->merchant_id,
            'type'           => 'charge',
            'amount'         => $payment->net_amount,
            'balance_before' => $merchant->balance - $payment->net_amount,
            'balance_after'  => $merchant->balance,
        ]);

        $webhookService->dispatch($merchant, 'payment.succeeded', $payment->fresh()->toArray());

        Log::info("[ProcessCardPayment] Succeeded: {$payment->reference}");
    }

    private function handleFailure(Payment $payment, string $reason, WebhookService $webhookService): void
    {
        $payment->update([
            'status'         => 'failed',
            'failure_reason' => $reason,
        ]);

        $merchant = $payment->merchant;

        Transaction::create([
            'payment_id'     => $payment->id,
            'merchant_id'    => $payment->merchant_id,
            'type'           => 'failed',
            'amount'         => 0,
            'balance_before' => $merchant->balance,
            'balance_after'  => $merchant->balance,
        ]);

        $webhookService->dispatch($merchant, 'payment.failed', $payment->toArray());

        Log::warning("[ProcessCardPayment] Failed: {$payment->reference} — {$reason}");
    }

    private function detectBrand(string $number): string
    {
        if (str_starts_with($number, '4'))         return 'visa';
        if (preg_match('/^5[1-5]/', $number))      return 'mastercard';
        if (preg_match('/^3[47]/', $number))       return 'amex';
        return 'unknown';
    }

    public function failed(\Throwable $e): void
    {
        Log::error("[ProcessCardPayment] Job permanently failed for {$this->payment->reference}: " . $e->getMessage());
        $this->payment->update(['status' => 'failed', 'failure_reason' => 'Processing error. Please try again.']);
    }
}
