<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Merchant;
use App\Models\Transaction;
use Illuminate\Support\Str;

class PaymentService
{
    protected $webhookService;
    protected $lencoService;

    public function __construct(WebhookService $webhookService, LencoService $lencoService)
    {
        $this->webhookService = $webhookService;
        $this->lencoService = $lencoService;
    }

    /**
     * Test card numbers for simulation
     */
    const TEST_CARDS = [
        // Success scenarios
        '4242424242424242' => 'success',
        '5555555555554444' => 'success',

        // Failure scenarios
        '4000000000000002' => 'card_declined',
        '4000000000009995' => 'insufficient_funds',
        '4000000000000069' => 'expired_card',
        '4000000000000127' => 'incorrect_cvc',
        '4000000000000119' => 'processing_error',
    ];

    /**
     * Transaction fee percentage (2.9% + $0.30)
     */
    const FEE_PERCENTAGE = 2.9;
    const FEE_FIXED = 0.30;

    /**
     * Initialize a payment
     */
    public function initializePayment(Merchant $merchant, array $data): Payment
    {
        // Calculate fees
        $amount = $data['amount'];
        $fee = $this->calculateFee($amount);
        $netAmount = $amount - $fee;

        // Generate unique reference
        $reference = 'pay_' . Str::random(24);

        // Create payment record
        $payment = Payment::create([
            'merchant_id' => $merchant->id,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $data['currency'] ?? 'USD',
            'fee' => $fee,
            'net_amount' => $netAmount,
            'status' => 'initialized',
            'payment_method' => $data['payment_method'] ?? 'card',
            'customer_email' => $data['customer_email'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);

        return $payment;
    }

    /**
     * Process a card payment
     */
    public function processPayment(Payment $payment, array $cardData): array
    {
        // Validate card
        $validationResult = $this->validateCard($cardData);

        if (!$validationResult['valid']) {
            $payment->update([
                'status' => 'failed',
                'failure_reason' => $validationResult['error'],
            ]);

            $this->createTransaction($payment, 'failed');

            // Dispatch Webhook
            $this->webhookService->dispatch($payment->merchant, 'payment.failed', $payment->toArray());

            return [
                'success' => false,
                'payment' => $payment->fresh(),
                'error' => $validationResult['error'],
            ];
        }

        // Update payment with card details (masked)
        $payment->update([
            'status' => 'pending',
            'card_last4' => substr($cardData['card_number'], -4),
            'card_brand' => $this->getCardBrand($cardData['card_number']),
            'card_exp_month' => $cardData['exp_month'],
            'card_exp_year' => $cardData['exp_year'],
        ]);

        // Simulate processing delay
        usleep(500000); // 0.5 seconds

        // Process based on test card
        $result = $this->simulateCardProcessing($cardData['card_number']);

        if ($result === 'success') {
            $payment->update(['status' => 'succeeded']);

            // Update merchant balance
            $payment->merchant->increment('balance', (float) $payment->net_amount);

            // Create transaction
            $this->createTransaction($payment, 'charge');

            // Dispatch Webhook
            $this->webhookService->dispatch($payment->merchant, 'payment.succeeded', $payment->fresh()->toArray());

            return [
                'success' => true,
                'payment' => $payment->fresh(),
            ];
        } else {
            $payment->update([
                'status' => 'failed',
                'failure_reason' => $result,
            ]);

            $this->createTransaction($payment, 'failed');

            // Dispatch Webhook
            $this->webhookService->dispatch($payment->merchant, 'payment.failed', $payment->toArray());

            return [
                'success' => false,
                'payment' => $payment->fresh(),
                'error' => $result,
            ];
        }
    }

    public function processMobileMoneyPayment(Payment $payment, array $data): array
    {
        try {
            // Update payment to pending/pay-offline
            $payment->update([
                'status' => 'pending', // Lenco "pay-offline"
                'payment_method' => 'mobile_money',
                'metadata' => array_merge($payment->metadata ?? [], ['mobile_money_provider' => $data['provider']])
            ]);

            // Call Lenco API
            // We need to map our data to Lenco's expected payload
            // Lenco expects: amount, currency, phone, operator...
            $lencoData = [
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'phone' => $data['phone_number'],
                'operator' => $data['provider'], // e.g., mtn, airtel
                'reference' => $payment->reference,
                'email' => $payment->customer_email
            ];

            // Initiate
            $response = $this->lencoService->initiateMobileMoneyCollection($lencoData);

            // If we are here, initiation was su ccessful (LencoService throws exception on error)
            // Lenco usually returns "status: true" and data with "status: pay-offline"

            // We can store Lenco ID if needed
            if (isset($response['data']['id'])) {
                $payment->update(['metadata' => array_merge($payment->metadata ?? [], ['lenco_id' => $response['data']['id']])]);
            }

            return [
                'success' => true,
                'payment' => $payment->fresh(),
                'message' => 'Payment initiated. Please check your phone to authorize.',
                'status' => 'pay-offline'
            ];

        } catch (\Exception $e) {
            $payment->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);

            $this->createTransaction($payment, 'failed');

            // Dispatch Webhook
            $this->webhookService->dispatch($payment->merchant, 'payment.failed', $payment->toArray());

            return [
                'success' => false,
                'payment' => $payment->fresh(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate card details
     */
    private function validateCard(array $cardData): array
    {
        // Check required fields
        $required = ['card_number', 'exp_month', 'exp_year', 'cvc'];
        foreach ($required as $field) {
            if (empty($cardData[$field])) {
                return [
                    'valid' => false,
                    'error' => "Missing required field: {$field}",
                ];
            }
        }

        // Validate card number (Luhn algorithm)
        if (!$this->luhnCheck($cardData['card_number'])) {
            return [
                'valid' => false,
                'error' => 'Invalid card number',
            ];
        }

        // Validate expiry
        $currentYear = (int) date('y');
        $currentMonth = (int) date('m');
        $expYear = (int) $cardData['exp_year'];
        $expMonth = (int) $cardData['exp_month'];

        if ($expYear < $currentYear || ($expYear === $currentYear && $expMonth < $currentMonth)) {
            return [
                'valid' => false,
                'error' => 'Card has expired',
            ];
        }

        // Validate CVC
        if (!preg_match('/^\d{3,4}$/', $cardData['cvc'])) {
            return [
                'valid' => false,
                'error' => 'Invalid CVC',
            ];
        }

        return ['valid' => true];
    }

    /**
     * Luhn algorithm for card validation
     */
    private function luhnCheck(string $cardNumber): bool
    {
        $cardNumber = preg_replace('/\D/', '', $cardNumber);
        $sum = 0;
        $length = strlen($cardNumber);

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $cardNumber[$length - $i - 1];

            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    /**
     * Simulate card processing based on test card numbers
     */
    private function simulateCardProcessing(string $cardNumber): string
    {
        return self::TEST_CARDS[$cardNumber] ?? 'success';
    }

    /**
     * Get card brand from card number
     */
    private function getCardBrand(string $cardNumber): string
    {
        if (preg_match('/^4/', $cardNumber)) {
            return 'visa';
        } elseif (preg_match('/^5[1-5]/', $cardNumber)) {
            return 'mastercard';
        } elseif (preg_match('/^3[47]/', $cardNumber)) {
            return 'amex';
        }

        return 'unknown';
    }

    /**
     * Calculate transaction fee
     */
    public function calculateFee(float $amount): float
    {
        return round(($amount * self::FEE_PERCENTAGE / 100) + self::FEE_FIXED, 2);
    }

    /**
     * Create transaction record
     */
    private function createTransaction(Payment $payment, string $type): Transaction
    {
        $merchant = $payment->merchant;
        $amount = $type === 'charge' ? $payment->net_amount : 0;

        return Transaction::create([
            'payment_id' => $payment->id,
            'merchant_id' => $payment->merchant_id,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $merchant->balance - $amount,
            'balance_after' => $merchant->balance,
        ]);
    }

    /**
     * Verify a payment by reference
     */
    public function verifyPayment(string $reference): ?Payment
    {
        return Payment::where('reference', $reference)->first();
    }

    /**
     * Verify payment status with Lenco (Sync)
     */
    public function verifyWithLenco(Payment $payment)
    {
        if (!in_array($payment->status, ['pending', 'initialized'])) {
            return $payment;
        }

        try {
            $response = $this->lencoService->verifyCollection($payment->reference);
            $data = $response['data'] ?? [];

            if (($data['status'] ?? '') === 'successful') {
                $payment->update([
                    'status' => 'succeeded',
                    'metadata' => array_merge($payment->metadata ?? [], ['lenco_data' => $data])
                ]);

                // Update merchant balance
                $payment->merchant->increment('balance', (float) $payment->net_amount);

                // Create transaction
                $this->createTransaction($payment, 'charge');

                // Dispatch Webhook
                $this->webhookService->dispatch($payment->merchant, 'payment.succeeded', $payment->fresh()->toArray());
            } elseif (($data['status'] ?? '') === 'failed') {
                $payment->update([
                    'status' => 'failed',
                    'failure_reason' => $data['reasonForFailure'] ?? 'Failed at Lenco',
                    'metadata' => array_merge($payment->metadata ?? [], ['lenco_data' => $data])
                ]);

                $this->createTransaction($payment, 'failed');

                $this->webhookService->dispatch($payment->merchant, 'payment.failed', $payment->toArray());
            }

            return $payment->fresh();

        } catch (\Exception $e) {
            // Log error but don't fail hard, just return current state
            return $payment;
        }
    }
}
