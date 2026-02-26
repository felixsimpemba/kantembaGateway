<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/pay/{reference}', function ($reference) {
    $payment = \App\Models\Payment::where('reference', $reference)->firstOrFail();
    return view('pay', ['payment' => $payment]);
});

Route::post('/pay/submit', function (\Illuminate\Http\Request $request) {
    $data = $request->validate([
        'payment_reference' => 'required|string|exists:payments,reference',
        'card_number'       => 'required|string',
        'exp_month'         => 'required|string|size:2',
        'exp_year'          => 'required|string|size:2',
        'cvc'               => 'required|string|min:3|max:4',
    ]);

    $payment = \App\Models\Payment::where('reference', $request->payment_reference)->firstOrFail();

    if (!in_array($payment->status, ['initialized', 'failed'])) {
        return response()->json(['error' => 'Payment already processed'], 400);
    }

    // ── Dispatch to queue — returns instantly ────────────────────────
    \App\Jobs\ProcessCardPayment::dispatch($payment, [
        'card_number' => $request->card_number,
        'exp_month'   => $request->exp_month,
        'exp_year'    => $request->exp_year,
        'cvc'         => $request->cvc,
    ])->onQueue('payments');

    // Immediately mark as processing so the UI can start polling
    $payment->update(['status' => 'pending']);

    return response()->json([
        'success' => true,
        'status'  => 'processing',
        'message' => 'Payment is being processed. Please wait…',
        'payment' => $payment->fresh(),
    ]);
});

Route::post('/pay/submit-mobile-money', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'payment_reference' => 'required|string|exists:payments,reference',
        'phone_number'      => 'required|string',
        'provider'          => 'required|string|in:mtn,airtel,zamtel',
    ]);

    $payment = \App\Models\Payment::where('reference', $request->payment_reference)->firstOrFail();

    if (!in_array($payment->status, ['initialized', 'failed'])) {
        return response()->json(['error' => 'Payment already processed'], 400);
    }

    // ── Dispatch to queue — returns instantly ────────────────────────
    \App\Jobs\ProcessMobileMoneyPayment::dispatch(
        $payment,
        $request->phone_number,
        $request->provider,
    )->onQueue('payments');

    $payment->update(['status' => 'pending']);

    return response()->json([
        'success' => true,
        'status'  => 'pay-offline',
        'message' => 'Please authorise the payment on your phone.',
        'payment' => $payment->fresh(),
    ]);
});

Route::get('/pay/status/{reference}', function ($reference, \App\Services\PaymentService $paymentService) {
    $payment = $paymentService->verifyPayment($reference);

    if (!$payment) {
        return response()->json(['error' => 'Payment not found'], 404);
    }

    // Trigger Lenco Verification if status is pending/initialized
    if (in_array($payment->status, ['pending', 'initialized']) && $payment->payment_method === 'mobile_money') {
        $payment = $paymentService->verifyWithLenco($payment);
    }

    return response()->json([
        'payment' => $payment
    ]);
});
