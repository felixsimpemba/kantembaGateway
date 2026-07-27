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
use OpenApi\Attributes as OA;

#[OA\Tag(name: "App Payments", description: "API Endpoints for initializing and listing app-based payments")]
class AppPaymentController extends Controller
{
    /**
     * Initialize a payment on behalf of an app + an app user.
     */
    #[OA\Post(
        path: "/api/apps/{appId}/payments/initialize",
        summary: "Initialize an app payment",
        security: [["apiKey" => []]],
        tags: ["App Payments"],
        parameters: [
            new OA\Parameter(name: "appId", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["amount", "currency", "app_user"],
                properties: [
                    new OA\Property(property: "amount", type: "number", format: "float", example: 250.00),
                    new OA\Property(property: "currency", type: "string", example: "ZMW"),
                    new OA\Property(property: "description", type: "string", example: "Top-up"),
                    new OA\Property(property: "callback_url", type: "string", example: "https://yourapp.com/callback"),
                    new OA\Property(
                        property: "app_user",
                        type: "object",
                        required: ["external_user_id"],
                        properties: [
                            new OA\Property(property: "external_user_id", type: "string", example: "user_42"),
                            new OA\Property(property: "name", type: "string", example: "Jane Doe"),
                            new OA\Property(property: "email", type: "string", example: "jane@example.com"),
                            new OA\Property(property: "phone", type: "string", example: "260970000000"),
                        ]
                    ),
                    new OA\Property(property: "metadata", type: "object", example: ["order_id" => "123"])
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Payment initialized successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean"),
                        new OA\Property(property: "reference", type: "string"),
                        new OA\Property(property: "pay_url", type: "string"),
                        new OA\Property(property: "amount", type: "number", format: "float"),
                        new OA\Property(property: "currency", type: "string"),
                        new OA\Property(property: "status", type: "string"),
                        new OA\Property(property: "app_user", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function initialize(Request $request, string $appId, PaymentService $paymentService)
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

        $payment = DB::transaction(function () use ($app, $merchant, $data, $paymentService) {
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

            // Calculate fees
            $fee = $paymentService->calculateFee($data['amount']);
            $netAmount = $data['amount'] - $fee;

            // Create the payment record
            $payment = Payment::create([
                'merchant_id'    => $merchant->id,
                'app_id'         => $app->id,
                'app_user_id'    => $appUser->id,
                'reference'      => 'PAY-' . strtoupper(Str::random(12)),
                'amount'         => $data['amount'],
                'currency'       => strtoupper($data['currency']),
                'fee'            => $fee,
                'net_amount'     => $netAmount,
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
     */
    #[OA\Get(
        path: "/api/apps/{appId}/payments",
        summary: "List app payments",
        security: [["apiKey" => []]],
        tags: ["App Payments"],
        parameters: [
            new OA\Parameter(name: "appId", in: "path", required: true, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "external_user_id", in: "query", required: false, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of payments retrieved successfully"
            ),
            new OA\Response(response: 404, description: "App not found")
        ]
    )]
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
