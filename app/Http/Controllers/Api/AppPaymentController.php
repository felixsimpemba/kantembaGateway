<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\AppUser;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AppPaymentController extends Controller
{
    /**
     * Initialize a payment on behalf of an app + an app user.
     *
     * POST /api/apps/{app_id}/payments/initialize
     * {
     *   "amount": 250.00,
     *   "currency": "ZMW",
     *   "app_user": {
     *     "external_user_id": "user_42",
     *     "name": "Jane Doe",          // optional
     *     "email": "jane@example.com", // optional
     *     "phone": "260970000000"      // optional
     *   },
     *   "description": "Top-up",      // optional
     *   "callback_url": "https://…",  // optional
     *   "metadata": {}                // optional
     * }
     */
    public function initialize(Request $request, string $appId)
    {
        $merchant = $request->attributes->get('merchant');

        // Find the app and confirm it belongs to this merchant
        $app = App::where('app_id', $appId)
            ->where('merchant_id', $merchant->id)
            ->where('status', 'active')
            ->firstOrFail();

        $data = $request->validate([
            'amount'                        => 'required|numeric|min:0.01',
            'currency'                      => 'required|string|size:3',
            'description'                   => 'nullable|string|max:255',
            'callback_url'                  => 'nullable|url|max:500',
            'metadata'                      => 'nullable|array',
            'app_user'                      => 'required|array',
            'app_user.external_user_id'     => 'required|string|max:191',
            'app_user.name'                 => 'nullable|string|max:191',
            'app_user.email'                => 'nullable|email|max:191',
            'app_user.phone'                => 'nullable|string|max:30',
            'app_user.metadata'             => 'nullable|array',
        ]);

        $payment = DB::transaction(function () use ($app, $merchant, $data) {
            // Upsert the app user
            $appUser = AppUser::upsertForApp($app->id, $data['app_user']);

            // Build metadata — merge provided metadata with gateway context
            $meta = array_merge($data['metadata'] ?? [], [
                'app_id'           => $app->app_id,
                'app_name'         => $app->name,
                'external_user_id' => $appUser->external_user_id,
                'description'      => $data['description'] ?? null,
                'callback_url'     => $data['callback_url'] ?? null,
            ]);

            // Create the payment record
            $payment = Payment::create([
                'merchant_id'    => $merchant->id,
                'app_id'         => $app->id,
                'app_user_id'    => $appUser->id,
                'reference'      => 'PAY-' . strtoupper(Str::random(12)),
                'amount'         => $data['amount'],
                'currency'       => strtoupper($data['currency']),
                'status'         => 'initialized',
                'customer_name'  => $appUser->name,
                'customer_email' => $appUser->email,
                'metadata'       => $meta,
                'idempotency_key' => $meta['callback_url'] ?? null,
            ]);

            return $payment;
        });

        // Build the hosted checkout URL
        $callbackParam = !empty($data['callback_url'])
            ? '?callback_url=' . urlencode($data['callback_url'])
            : '';

        $payUrl = url("/pay/{$payment->reference}") . $callbackParam;

        return response()->json([
            'success'   => true,
            'reference' => $payment->reference,
            'pay_url'   => $payUrl,
            'amount'    => $payment->amount,
            'currency'  => $payment->currency,
            'status'    => $payment->status,
            'app_user'  => [
                'external_user_id' => $payment->appUser->external_user_id,
                'name'             => $payment->appUser->name,
                'email'            => $payment->appUser->email,
                'phone'            => $payment->appUser->phone,
            ],
        ], 201);
    }

    /**
     * List all payments for a specific app.
     *
     * GET /api/apps/{app_id}/payments
     */
    public function index(Request $request, string $appId)
    {
        $merchant = $request->attributes->get('merchant');

        $app = App::where('app_id', $appId)
            ->where('merchant_id', $merchant->id)
            ->firstOrFail();

        $query = Payment::where('app_id', $app->id)
            ->with(['appUser:id,external_user_id,name,email,phone'])
            ->latest();

        // Optional filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('external_user_id')) {
            $query->whereHas('appUser', fn ($q) =>
                $q->where('external_user_id', $request->external_user_id)
            );
        }

        $payments = $query->paginate(50);

        return response()->json($payments);
    }
}
