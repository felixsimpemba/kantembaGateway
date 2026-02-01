<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Merchant;
use App\Models\Transaction;

class MerchantController extends Controller
{
    /**
     * Get the current balance of the authenticated merchant.
     */
    public function balance(Request $request)
    {
        $merchant = $request->user(); // Assuming authenticated via Sanctum or API Key middleware populates user/merchant

        // If using API Key middleware, the merchant might be attached to request attribute
        // Let's assume standard Sanctum for dashboard access OR API Key for S2S.
        // For Store Admin (backend_app) talking to Gateway, it likely uses API Key.

        $merchant = $request->attributes->get('merchant') ?? $request->user();

        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Calculate balance: (Total Charges) - (Total Refunds)
        // Note: Transactions table has 'type' enum: charge, refund, fee, failed
        // We use balance_after from the latest transaction or calculate from charges and refunds

        $credits = Transaction::where('merchant_id', $merchant->id)
            ->where('type', 'charge')
            ->sum('amount');

        $debits = Transaction::where('merchant_id', $merchant->id)
            ->where('type', 'refund')
            ->sum('amount');

        $fees = Transaction::where('merchant_id', $merchant->id)
            ->where('type', 'fee')
            ->sum('amount');

        $available = $credits - $debits - $fees;
        $pending = 0; // Can be enhanced if you have pending payments

        return response()->json([
            'available' => max(0, $available),
            'pending' => $pending,
            'total' => max(0, $available) + $pending,
            'currency' => 'ZMW',
            'breakdown' => [
                'charges' => $credits,
                'refunds' => $debits,
                'fees' => $fees
            ]
        ]);
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'account_details' => 'required|string'
        ]);

        $merchant = $request->attributes->get('merchant') ?? $request->user();

        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check balance (using correct transaction types)
        $credits = Transaction::where('merchant_id', $merchant->id)
            ->where('type', 'charge')
            ->sum('amount');

        $debits = Transaction::where('merchant_id', $merchant->id)
            ->where('type', 'refund')
            ->sum('amount');

        $fees = Transaction::where('merchant_id', $merchant->id)
            ->where('type', 'fee')
            ->sum('amount');

        $current_balance = $credits - $debits - $fees;

        if ($request->amount > $current_balance) {
            return response()->json(['message' => 'Insufficient funds'], 400);
        }

        // Get latest transaction for balance tracking
        $lastTransaction = Transaction::where('merchant_id', $merchant->id)
            ->latest()
            ->first();

        $balance_before = $lastTransaction ? $lastTransaction->balance_after : $current_balance;
        $balance_after = $balance_before - $request->amount;

        // Record Withdrawal as a fee/debit transaction
        $transaction = Transaction::create([
            'merchant_id' => $merchant->id,
            'payment_id' => 0, // Withdrawal not tied to specific payment
            'type' => 'fee', // Using fee type for withdrawals
            'amount' => $request->amount,
            'balance_before' => $balance_before,
            'balance_after' => $balance_after,
        ]);

        return response()->json([
            'message' => 'Withdrawal request received',
            'transaction' => $transaction,
            'new_balance' => $balance_after
        ]);
    }
}
