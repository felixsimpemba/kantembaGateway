<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Apps", description: "API Endpoints for managing Merchant Apps")]
class AppController extends Controller
{
    /**
     * List all apps belonging to the authenticated merchant.
     */
    #[OA\Get(
        path: "/api/apps",
        summary: "List all apps",
        security: [["apiKey" => []]],
        tags: ["Apps"],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of apps retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "apps", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function index(Request $request)
    {
        $merchant = $request->attributes->get('merchant');

        $apps = App::where('merchant_id', $merchant->id)
            ->withCount('users', 'payments')
            ->latest()
            ->get();

        return response()->json(['apps' => $apps]);
    }

    /**
     * Create a new app for the authenticated merchant.
     */
    #[OA\Post(
        path: "/api/apps",
        summary: "Create a new app",
        security: [["apiKey" => []]],
        tags: ["Apps"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Mobile Storefront"),
                    new OA\Property(property: "webhook_url", type: "string", example: "https://example.com/webhook"),
                    new OA\Property(property: "confirmation_url", type: "string", example: "https://example.com/confirm"),
                    new OA\Property(property: "metadata", type: "object", example: ["platform" => "ios"])
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "App created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "app", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function store(Request $request)
    {
        $merchant = $request->attributes->get('merchant');

        $data = $request->validate([
            'name'             => 'required|string|max:100',
            'webhook_url'      => 'nullable|url|max:500',
            'confirmation_url' => 'nullable|url|max:500',
            'metadata'         => 'nullable|array',
        ]);

        $app = App::create([
            'merchant_id'      => $merchant->id,
            'name'             => $data['name'],
            'webhook_url'      => $data['webhook_url'] ?? null,
            'confirmation_url' => $data['confirmation_url'] ?? null,
            'metadata'         => $data['metadata'] ?? null,
        ]);

        return response()->json([
            'message' => 'App created successfully.',
            'app'     => array_merge($app->toArray(), [
                'app_secret' => $app->app_secret, // shown only on creation
            ]),
        ], 201);
    }

    /**
     * Show a single app (must belong to merchant).
     */
    #[OA\Get(
        path: "/api/apps/{appId}",
        summary: "Show app details",
        security: [["apiKey" => []]],
        tags: ["Apps"],
        parameters: [
            new OA\Parameter(name: "appId", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "App details retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "app", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "App not found")
        ]
    )]
    public function show(Request $request, string $appId)
    {
        $merchant = $request->attributes->get('merchant');

        $app = App::where('app_id', $appId)
            ->where('merchant_id', $merchant->id)
            ->withCount('users', 'payments')
            ->firstOrFail();

        return response()->json(['app' => $app]);
    }

    /**
     * Update app settings.
     */
    #[OA\Put(
        path: "/api/apps/{appId}",
        summary: "Update app settings",
        security: [["apiKey" => []]],
        tags: ["Apps"],
        parameters: [
            new OA\Parameter(name: "appId", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Web Storefront"),
                    new OA\Property(property: "webhook_url", type: "string", example: "https://example.com/new-webhook"),
                    new OA\Property(property: "confirmation_url", type: "string", example: "https://example.com/new-confirm"),
                    new OA\Property(property: "metadata", type: "object", example: ["platform" => "web"])
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "App updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "app", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "App not found")
        ]
    )]
    public function update(Request $request, string $appId)
    {
        $merchant = $request->attributes->get('merchant');

        $app = App::where('app_id', $appId)
            ->where('merchant_id', $merchant->id)
            ->firstOrFail();

        $data = $request->validate([
            'name'             => 'sometimes|string|max:100',
            'webhook_url'      => 'sometimes|nullable|url|max:500',
            'confirmation_url' => 'sometimes|nullable|url|max:500',
            'metadata'         => 'sometimes|nullable|array',
        ]);

        $app->update($data);

        return response()->json(['message' => 'App updated.', 'app' => $app]);
    }

    /**
     * Suspend / deactivate an app (soft-disable without deletion).
     */
    #[OA\Delete(
        path: "/api/apps/{appId}",
        summary: "Suspend an app",
        security: [["apiKey" => []]],
        tags: ["Apps"],
        parameters: [
            new OA\Parameter(name: "appId", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "App suspended successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "App not found")
        ]
    )]
    public function destroy(Request $request, string $appId)
    {
        $merchant = $request->attributes->get('merchant');

        $app = App::where('app_id', $appId)
            ->where('merchant_id', $merchant->id)
            ->firstOrFail();

        $app->update(['status' => 'suspended']);

        return response()->json(['message' => 'App suspended successfully.']);
    }

    /**
     * List users registered under a specific app.
     */
    #[OA\Get(
        path: "/api/apps/{appId}/users",
        summary: "List app users",
        security: [["apiKey" => []]],
        tags: ["Apps"],
        parameters: [
            new OA\Parameter(name: "appId", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of users retrieved successfully"
            ),
            new OA\Response(response: 404, description: "App not found")
        ]
    )]
    public function users(Request $request, string $appId)
    {
        $merchant = $request->attributes->get('merchant');

        $app = App::where('app_id', $appId)
            ->where('merchant_id', $merchant->id)
            ->firstOrFail();

        $users = $app->users()
            ->withCount('payments')
            ->latest()
            ->paginate(50);

        return response()->json($users);
    }

    /**
     * Show a single app user by external_user_id.
     */
    #[OA\Get(
        path: "/api/apps/{appId}/users/{externalUserId}",
        summary: "Show app user details",
        security: [["apiKey" => []]],
        tags: ["Apps"],
        parameters: [
            new OA\Parameter(name: "appId", in: "path", required: true, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "externalUserId", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "User details retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "user", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "App or user not found")
        ]
    )]
    public function showUser(Request $request, string $appId, string $externalUserId)
    {
        $merchant = $request->attributes->get('merchant');

        $app = App::where('app_id', $appId)
            ->where('merchant_id', $merchant->id)
            ->firstOrFail();

        $user = $app->users()
            ->where('external_user_id', $externalUserId)
            ->withCount('payments')
            ->firstOrFail();

        return response()->json(['user' => $user]);
    }
}
