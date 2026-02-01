<?php

namespace App\Services;

use App\Models\Refund;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Str;

class RefundService
{
    protected $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Create a refund for a payment
     */
    public function createRefund(Payment $payment, array $data): array
    {
        // Validate payment can be refunded
        if ($payment->status !== 'succeeded') {
            return [
                'success' => false,
                'error' => 'Only succeeded payments can be refunded',
            ];
        }

        // Check if already fully refunded
        $totalRefunded = $payment->refunds()->where('status', 'succeeded')->sum('amount');

        if ($totalRefunded >= $payment->amount) {
            return [
                'success' => false,
                'error' => 'Payment has already been fully refunded',
            ];
        }

        // Validate refund amount
        $refundAmount = $data['amount'] ?? $payment->amount;
        $availableForRefund = $payment->amount - $totalRefunded;

        if ($refundAmount > $availableForRefund) {
            return [
                'success' => false,
                'error' => "Refund amount exceeds available amount. Available: {$availableForRefund}",
            ];
        }

        // Generate unique reference
        $reference = 'ref_' . Str::random(24);

        // Create refund record
        $refund = Refund::create([
            'payment_id' => $payment->id,
            'merchant_id' => $payment->merchant_id,
            'reference' => $reference,
            'amount' => $refundAmount,
            'status' => 'pending',
            'reason' => $data['reason'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);

        // Process refund
        $refund->update(['status' => 'succeeded']);

        // Calculate refund fee (refund the net amount)
        $feeRefund = ($refundAmount / $payment->amount) * $payment->fee;
        $netRefund = $refundAmount - $feeRefund;

        // Update merchant balance
        $payment->merchant->decrement('balance', $netRefund);

        // Create transaction
        Transaction::create([
            'payment_id' => $payment->id,
            'merchant_id' => $payment->merchant_id,
            'type' => 'refund',
            'amount' => -$netRefund,
            'balance_before' => $payment->merchant->balance + $netRefund,
            'balance_after' => $payment->merchant->balance,
        ]);

        // Update payment status if fully refunded
        $newTotalRefunded = $totalRefunded + $refundAmount;
        if ($newTotalRefunded >= $payment->amount) {
            $payment->update(['status' => 'refunded']);
        }

        // Dispatch Webhook
        $this->webhookService->dispatch($payment->merchant, 'refund.succeeded', $refund->load('payment.merchant')->toArray());


        return [
            'success' => true,
            'refund' => $refund->fresh(),
        ];
    }

    /**
     * Get refund by reference
     */
    public function getRefund(string $reference): ?Refund
    {
        return Refund::where('reference', $reference)->first();
    }
}
