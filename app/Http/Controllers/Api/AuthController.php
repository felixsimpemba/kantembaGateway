<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Services\ApiKeyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    protected $apiKeyService;

    public function __construct(ApiKeyService $apiKeyService)
    {
        $this->apiKeyService = $apiKeyService;
    }

    #[OA\Post(
        path: "/api/auth/register",
        summary: "Register a new merchant account",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password", "business_name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "business_name", type: "string", example: "Acme Corp"),
                    new OA\Property(property: "webhook_url", type: "string", format: "url", example: "https://example.com/webhook")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Merchant registered successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Merchant registered successfully"),
                        new OA\Property(property: "merchant", type: "object"),
                        new OA\Property(property: "api_key", type: "string", example: "pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"),
                        new OA\Property(property: "warning", type: "string", example: "Please save your API key. It will not be shown again.")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:merchants,email',
            'password' => 'required|string|min:8',
            'business_name' => 'required|string|max:255',
            'webhook_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create merchant
        $merchant = Merchant::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password), // Hash is imported implicitly or via facade
            'business_name' => $request->business_name,
            'webhook_url' => $request->webhook_url,
            'webhook_secret' => Str::random(40),
            'status' => 'active',
            'balance' => 0,
            'currency' => 'USD',
        ]);

        // Generate test API key
        $apiKeyData = $this->apiKeyService->generateKey($merchant, 'test');

        return response()->json([
            'message' => 'Merchant registered successfully',
            'merchant' => $merchant,
            'api_key' => $apiKeyData['key'],
            'warning' => 'Please save your API key. It will not be shown again.'
        ], 201);
    }

    #[OA\Post(
        path: "/api/auth/login",
        summary: "Login as a merchant",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Login successful",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Login successful"),
                        new OA\Property(property: "merchant", type: "object"),
                        new OA\Property(property: "api_key", type: "string", example: "pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"),
                        new OA\Property(property: "warning", type: "string", example: "Use this API key for authentication.")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Invalid credentials")
        ]
    )]
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $merchant = Merchant::where('email', $request->email)->first();

        if (!$merchant || !\Illuminate\Support\Facades\Hash::check($request->password, $merchant->password)) {
            return response()->json([
                'error' => 'Invalid credentials'
            ], 401);
        }

        // For dashboard simplification, we'll return the most recently created API key
        // In a real app, we'd use Sanctum/Passport tokens for session management.
        $apiKey = $merchant->apiKeys()->latest()->first();

        if (!$apiKey) {
            // Generate one if missing
            $keyData = $this->apiKeyService->generateKey($merchant, 'test');
            $keyString = $keyData['key'];
        } else {
            // We can't return the raw key if it's hashed (which it is).
            // This is a security limitation of our simple design.
            // If keys are hashed, we can't retrieve them.
            // So, for the dashboard login, we might need to issue a NEW key or use a different token.

            // Hack for MVP: Generate a NEW key on every login? No.
            // Correct approach: Use Sanctum.
            // BUT, user wanted to avoid complexity.
            // Let's check if we are hashing keys. Yes we are.

            // OK, let's create a temporary session token using Sanctum?
            // User rule: "Avoid writing project code files to tmp... or directly to the Desktop".

            // Let's just generate a NEW API Key for this session. It's not efficient but works.
            $keyData = $this->apiKeyService->generateKey($merchant, 'test');
            $keyString = $keyData['key'];
        }

        return response()->json([
            'message' => 'Login successful',
            'merchant' => $merchant,
            'api_key' => $keyString,
            'warning' => 'This is a new API key generated for your dashboard session.'
        ], 200);
    }

    #[OA\Get(
        path: "/api/auth/me",
        summary: "Get current merchant information",
        security: [["apiKey" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Merchant information retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "merchant", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function me(Request $request)
    {
        $merchant = $request->attributes->get('merchant');

        return response()->json([
            'merchant' => $merchant
        ]);
    }

    #[OA\Post(
        path: "/api/auth/generate-key",
        summary: "Generate a new API key",
        security: [["apiKey" => []]],
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "type", type: "string", enum: ["test", "live"], example: "test")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "API key generated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "api_key", type: "string"),
                        new OA\Property(property: "type", type: "string"),
                        new OA\Property(property: "warning", type: "string")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function generateKey(Request $request)
    {
        $merchant = $request->attributes->get('merchant');

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:test,live',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $apiKeyData = $this->apiKeyService->generateKey($merchant, $request->type);

        return response()->json([
            'message' => 'API key generated successfully',
            'api_key' => $apiKeyData['key'],
            'type' => $request->type,
            'warning' => 'Please save your API key. It will not be shown again.'
        ], 200);
    }
}
