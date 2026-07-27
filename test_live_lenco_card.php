<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Create a dummy payment and try to process it through Lenco
$merchant = App\Models\Merchant::first();

if (!$merchant) {
    echo "No merchant found.\n";
    exit;
}

$payment = App\Models\Payment::create([
    'merchant_id' => $merchant->id,
    'reference' => 'pay_test_' . \Illuminate\Support\Str::random(10),
    'amount' => 500,
    'currency' => 'ZMW',
    'fee' => 10,
    'net_amount' => 490,
    'status' => 'initialized',
    'payment_method' => 'card',
    'customer_email' => 'test@lenco.co',
    'customer_name' => 'John Doe'
]);

echo "Created test payment: {$payment->reference}\n";

// Dispatch job sync for testing purposes
try {
    echo "Dispatching ProcessCardPayment...\n";
    $job = new App\Jobs\ProcessCardPayment($payment, [
        'card_number' => '5555555555554444', // Lenco 3DS test card
        'exp_month' => '12',
        'exp_year' => '25',
        'cvc' => '838'
    ]);
    
    $webhookService = app(\App\Services\WebhookService::class);
    $lencoService = app(\App\Services\LencoService::class);
    
    $job->handle($webhookService, $lencoService);
    
    $payment->refresh();
    echo "Job completed. Payment Status: {$payment->status}\n";
    if ($payment->status === 'requires_action') {
        echo "3DS Redirect URL: " . ($payment->metadata['redirect_url'] ?? 'Not found') . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
