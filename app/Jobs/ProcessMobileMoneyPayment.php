<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\Transaction;
use App\Services\LencoService;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMobileMoneyPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Mobile money is best-effort — retry 5 times with back-off */
    public int $tries = 5;

    /** Exponential back-off: 30 s, 60 s, 120 s, 240 s */
    public array $backoff = [30, 60, 120, 240];

    public function __construct(
        public readonly Payment $payment,
        public readonly string  $phoneNumber,
        public readonly string  $provider,
    ) {}

    public function handle(LencoService $lencoService, WebhookService $webhookService): void
    {
        Log::info("[ProcessMobileMoneyPayment] Starting for {$this->payment->reference}");

        $payment = $this->payment->fresh();

        // Guard: skip if already processed
        if (!in_array($payment->status, ['initialized', 'failed'])) {
            Log::info("[ProcessMobileMoneyPayment] Skipping — status: {$payment->status}");
            return;
        }

        // Mark pending so the checkout page can start polling
        $payment->update([
            'status'         => 'pending',
            'payment_method' => 'mobile_money',
            'metadata'       => array_merge($payment->metadata ?? [], [
                'mobile_money_provider' => $this->provider,
                'phone_number'          => $this->phoneNumber,
            ]),
        ]);

        try {
            $lencoData = [
                'amount'    => $payment->amount,
                'currency'  => $payment->currency,
                'phone'     => $this->phoneNumber,
                'operator'  => $this->provider,
                'reference' => $payment->reference,
                'email'     => $payment->customer_email,
            ];

            $response = $lencoService->initiateMobileMoneyCollection($lencoData);

            // Store Lenco transaction ID for status polling
            if (isset($response['data']['id'])) {
                $payment->update([
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'lenco_id' => $response['data']['id'],
                    ]),
                ]);
            }

            Log::info("[ProcessMobileMoneyPayment] Initiated successfully for {$payment->reference}");
            // Status remains 'pending' — the webhook from Lenco will update it to succeeded/failed

        } catch (\Exception $e) {
            Log::error("[ProcessMobileMoneyPayment] Lenco error for {$payment->reference}: " . $e->getMessage());

            $payment->update([
                'status'         => 'failed',
                'failure_reason' => $e->getMessage(),
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

            // Re-throw so the queue retries (exponential back-off applied)
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("[ProcessMobileMoneyPayment] Permanently failed for {$this->payment->reference}: " . $e->getMessage());
        $this->payment->update([
            'status'         => 'failed',
            'failure_reason' => 'Could not reach mobile money provider. Please try again.',
        ]);
    }
}
