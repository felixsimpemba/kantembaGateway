<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RefundService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class RefundController extends Controller
{
    protected $refundService;
    protected $paymentService;

    public function __construct(RefundService $refundService, PaymentService $paymentService)
    {
        $this->refundService = $refundService;
        $this->paymentService = $paymentService;
    }

    #[OA\Post(
        path: "/api/refunds",
        summary: "Create a refund for a payment",
        security: [["apiKey" => []]],
        tags: ["Refunds"],
        parameters: [
            new OA\Parameter(
                name: "Idempotency-Key",
                in: "header",
                required: false,
                schema: new OA\Schema(type: "string", example: "unique-key-123"),
                description: "Unique key to prevent duplicate refunds"
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["payment_reference"],
                properties: [
                    new OA\Property(property: "payment_reference", type: "string", example: "pay_xxxxxxxxxxxxxxxxxxxx"),
                    new OA\Property(property: "amount", type: "number", format: "float", example: 50.00, description: "Partial refund amount (optional, defaults to full amount)"),
                    new OA\Property(property: "reason", type: "string", example: "Customer requested")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Refund created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean"),
                        new OA\Property(property: "refund", type: "object"),
                        new OA\Property(property: "message", type: "string")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Refund failed")
        ]
    )]
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_reference' => 'required|string',
            'amount' => 'nullable|numeric|min:0.01',
            'reason' => 'nullable|string|max:500',
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

        // Create refund
        $data = $request->all();
        $data['idempotency_key'] = $request->header('Idempotency-Key');

        $result = $this->refundService->createRefund($payment, $data);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'refund' => $result['refund'],
                'message' => 'Refund created successfully'
            ], 201);
        } else {
            return response()->json([
                'success' => false,
                'error' => $result['error']
            ], 400);
        }
    }

    #[OA\Get(
        path: "/api/refunds/{reference}",
        summary: "Get refund details",
        security: [["apiKey" => []]],
        tags: ["Refunds"],
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
                description: "Refund details retrieved",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "refund", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Refund not found")
        ]
    )]
    public function show(Request $request, string $reference)
    {
        $merchant = $request->attributes->get('merchant');

        $refund = $this->refundService->getRefund($reference);

        if (!$refund) {
            return response()->json([
                'error' => 'Refund not found'
            ], 404);
        }

        // Check ownership
        if ($refund->merchant_id !== $merchant->id) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'refund' => $refund->load('payment')
        ], 200);
    }
}
