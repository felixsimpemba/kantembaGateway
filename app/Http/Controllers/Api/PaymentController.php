<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    #[OA\Post(
        path: "/api/payments/initialize",
        summary: "Initialize a new payment",
        security: [["apiKey" => []]],
        tags: ["Payments"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["amount", "currency"],
                properties: [
                    new OA\Property(property: "amount", type: "number", format: "float", example: 100.00),
                    new OA\Property(property: "currency", type: "string", enum: ["USD", "ZMW"], example: "USD"),
                    new OA\Property(property: "customer_email", type: "string", format: "email", example: "customer@example.com"),
                    new OA\Property(property: "customer_name", type: "string", example: "John Doe"),
                    new OA\Property(property: "metadata", type: "object", example: ["order_id" => "12345"])
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Payment initialized successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "payment", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function initialize(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:USD,ZMW',
            'customer_email' => 'nullable|email',
            'customer_name' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $merchant = $request->attributes->get('merchant');

        $data = $request->all();
        $data['idempotency_key'] = $request->header('Idempotency-Key');

        $payment = $this->paymentService->initializePayment($merchant, $data);

        return response()->json([
            'message' => 'Payment initialized successfully',
            'payment' => $payment
        ], 201);
    }

    #[OA\Post(
        path: "/api/payments/process",
        summary: "Process a payment with card details",
        security: [["apiKey" => []]],
        tags: ["Payments"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["payment_reference", "card_number", "exp_month", "exp_year", "cvc"],
                properties: [
                    new OA\Property(property: "payment_reference", type: "string", example: "pay_xxxxxxxxxxxxxxxxxxxx"),
                    new OA\Property(property: "card_number", type: "string", example: "4242424242424242"),
                    new OA\Property(property: "exp_month", type: "string", example: "12"),
                    new OA\Property(property: "exp_year", type: "string", example: "25"),
                    new OA\Property(property: "cvc", type: "string", example: "123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Payment processed successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean"),
                        new OA\Property(property: "payment", type: "object"),
                        new OA\Property(property: "message", type: "string")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Payment processing failed")
        ]
    )]
    public function process(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_reference' => 'required|string',
            'card_number' => 'required|string',
            'exp_month' => 'required|string|size:2',
            'exp_year' => 'required|string|size:2',
            'cvc' => 'required|string|min:3|max:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $merchant = $request->attributes->get('merchant');

        // Find payment
        $payment = $this->paymentService->verifyPayment($request->payment_reference);

        if (!$payment) {
            return response()->json([
                'error' => 'Payment not found'
            ], 404);
        }

        // Check ownership
        if ($payment->merchant_id !== $merchant->id) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        // Check payment status
        if ($payment->status !== 'initialized') {
            return response()->json([
                'error' => 'Payment has already been processed'
            ], 400);
        }

        // Process payment
        $result = $this->paymentService->processPayment($payment, [
            'card_number' => $request->card_number,
            'exp_month' => $request->exp_month,
            'exp_year' => $request->exp_year,
            'cvc' => $request->cvc,
        ]);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'payment' => $result['payment'],
                'message' => 'Payment processed successfully'
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'payment' => $result['payment'],
                'error' => $result['error']
            ], 400);
        }
    }

    #[OA\Post(
        path: "/api/payments/process-mobile-money",
        summary: "Process a mobile money payment",
        security: [["apiKey" => []]],
        tags: ["Payments"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["payment_reference", "phone_number", "provider"],
                properties: [
                    new OA\Property(property: "payment_reference", type: "string", example: "pay_xxxxxxxxxxxxxxxxxxxx"),
                    new OA\Property(property: "phone_number", type: "string", example: "260970000000"),
                    new OA\Property(property: "provider", type: "string", enum: ["mtn", "airtel", "zamtel"], example: "mtn")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Payment initiated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean"),
                        new OA\Property(property: "payment", type: "object"),
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "status", type: "string", example: "pay-offline")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Payment processing failed")
        ]
    )]
    public function processMobileMoney(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_reference' => 'required|string',
            'phone_number' => 'required|string',
            'provider' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $merchant = $request->attributes->get('merchant');

        // Find payment
        $payment = $this->paymentService->verifyPayment($request->payment_reference);

        if (!$payment) {
            return response()->json([
                'error' => 'Payment not found'
            ], 404);
        }

        // Check ownership
        if ($payment->merchant_id !== $merchant->id) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        // Check payment status
        if ($payment->status !== 'initialized') {
            return response()->json([
                'error' => 'Payment has already been processed'
            ], 400);
        }

        // Process payment
        $result = $this->paymentService->processMobileMoneyPayment($payment, [
            'phone_number' => $request->phone_number,
            'provider' => $request->provider,
        ]);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'payment' => $result['payment'],
                'message' => $result['message'] ?? 'Payment initiated',
                'status' => $result['status'] ?? 'pending'
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'payment' => $result['payment'],
                'error' => $result['error']
            ], 400);
        }
    }

    #[OA\Get(
        path: "/api/payments/{reference}",
        summary: "Verify/Get payment details",
        security: [["apiKey" => []]],
        tags: ["Payments"],
        parameters: [
            new OA\Parameter(
                name: "reference",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Payment details retrieved",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "payment", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Payment not found")
        ]
    )]
    public function verify(Request $request, string $reference)
    {
        $merchant = $request->attributes->get('merchant');

        $payment = $this->paymentService->verifyPayment($reference);

        if (!$payment) {
            return response()->json([
                'error' => 'Payment not found'
            ], 404);
        }

        // Check ownership
        if ($payment->merchant_id !== $merchant->id) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        // Trigger Lenco Verification if status is pending/initialized
        if (in_array($payment->status, ['pending', 'initialized']) && $payment->payment_method === 'mobile_money') {
            $payment = $this->paymentService->verifyWithLenco($payment);
        }

        return response()->json([
            'payment' => $payment->load('transactions', 'refunds')
        ], 200);
    }
}
