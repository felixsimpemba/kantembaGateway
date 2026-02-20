<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/pay/{reference}', function ($reference) {
    $payment = \App\Models\Payment::where('reference', $reference)->firstOrFail();
    return view('pay', ['payment' => $payment]);
});

Route::post('/pay/submit', function (\Illuminate\Http\Request $request, \App\Services\PaymentService $paymentService) {
    // Validate request
    $data = $request->validate([
        'payment_reference' => 'required|string|exists:payments,reference',
        'card_number' => 'required|string',
        'exp_month' => 'required|string|size:2',
        'exp_year' => 'required|string|size:2',
        'cvc' => 'required|string|min:3|max:4',
    ]);

    $payment = \App\Models\Payment::where('reference', $request->payment_reference)->firstOrFail();

    // Check status - Allow retry if failed
    if (!in_array($payment->status, ['initialized', 'failed'])) {
        return response()->json(['error' => 'Payment already processed'], 400);
    }

    // Process
    $result = $paymentService->processPayment($payment, [
        'card_number' => $request->card_number,
        'exp_month' => $request->exp_month,
        'exp_year' => $request->exp_year,
        'cvc' => $request->cvc,
    ]);

    if ($result['success']) {
        return response()->json(['success' => true, 'payment' => $result['payment']]);
    } else {
        return response()->json(['success' => false, 'error' => $result['error']], 400);
    }
});

Route::post('/pay/submit-mobile-money', function (\Illuminate\Http\Request $request, \App\Services\PaymentService $paymentService) {
    // Validate request
    $request->validate([
        'payment_reference' => 'required|string|exists:payments,reference',
        'phone_number' => 'required|string',
        'provider' => 'required|string|in:mtn,airtel,zamtel',
    ]);

    $payment = \App\Models\Payment::where('reference', $request->payment_reference)->firstOrFail();

    // Check status - Allow retry if failed
    if (!in_array($payment->status, ['initialized', 'failed'])) {
        return response()->json(['error' => 'Payment already processed'], 400);
    }

    // Process
    $result = $paymentService->processMobileMoneyPayment($payment, [
        'phone_number' => $request->phone_number,
        'provider' => $request->provider,
    ]);

    if ($result['success']) {
        return response()->json([
            'success' => true,
            'payment' => $result['payment'],
            'message' => $result['message'],
            'status' => $result['status']
        ]);
    } else {
        return response()->json(['success' => false, 'error' => $result['error']], 400);
    }
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
