<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// External Webhooks (Public)
Route::post('/webhooks/lenco', [App\Http\Controllers\Api\LencoWebhookController::class, 'handle']);

// Protected routes (require API key authentication + security features)
Route::middleware(['auth.apikey', 'throttle:60,1', 'idempotency', 'secure.headers'])->group(function () {
    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/generate-key', [AuthController::class, 'generateKey']);
    });

    // Payment routes
    Route::prefix('payments')->group(function () {
        Route::post('/initialize', [App\Http\Controllers\Api\PaymentController::class, 'initialize']);
        Route::post('/process', [App\Http\Controllers\Api\PaymentController::class, 'process']);
        Route::post('/process-mobile-money', [App\Http\Controllers\Api\PaymentController::class, 'processMobileMoney']);
        Route::get('/{reference}', [App\Http\Controllers\Api\PaymentController::class, 'verify']);
    });

    // Refund routes
    Route::prefix('refunds')->group(function () {
        Route::post('/', [App\Http\Controllers\Api\RefundController::class, 'create']);
        Route::get('/{reference}', [App\Http\Controllers\Api\RefundController::class, 'show']);
    });

    // Transaction routes
    Route::get('/transactions', [App\Http\Controllers\Api\TransactionController::class, 'index']);

    // Merchant Financial Endpoints
    Route::prefix('merchant')->group(function () {
        Route::get('/balance', [\App\Http\Controllers\Api\MerchantController::class, 'balance']);
        Route::post('/withdraw', [\App\Http\Controllers\Api\MerchantController::class, 'withdraw']);
    });

    // Webhook routes
    Route::prefix('webhooks')->group(function () {
        Route::get('/logs', [App\Http\Controllers\Api\WebhookController::class, 'index']);
        Route::put('/settings', [App\Http\Controllers\Api\WebhookController::class, 'updateSettings']);
    });
    // Dashboard routes
    Route::get('/dashboard/stats', [App\Http\Controllers\Api\DashboardController::class, 'stats']);

    // Admin Routes
    Route::middleware(['admin'])->prefix('admin')->group(function () {
        Route::get('/merchants', [App\Http\Controllers\Api\Admin\MerchantController::class, 'index']);
        Route::get('/merchants/{id}', [App\Http\Controllers\Api\Admin\MerchantController::class, 'show']);
        Route::put('/merchants/{id}', [App\Http\Controllers\Api\Admin\MerchantController::class, 'update']);
        Route::post('/merchants/{id}/toggle-block', [App\Http\Controllers\Api\Admin\MerchantController::class, 'toggleBlock']);

        Route::get('/gateway/stats', [App\Http\Controllers\Api\Admin\GatewayController::class, 'stats']);
    });
});
