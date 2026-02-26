<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppController extends Controller
{
    /**
     * List all apps belonging to the authenticated merchant.
     */
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
    public function store(Request $request)
    {
        $merchant = $request->attributes->get('merchant');

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'webhook_url' => 'nullable|url|max:500',
            'metadata'    => 'nullable|array',
        ]);

        $app = App::create([
            'merchant_id' => $merchant->id,
            'name'        => $data['name'],
            'webhook_url' => $data['webhook_url'] ?? null,
            'metadata'    => $data['metadata'] ?? null,
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
    public function update(Request $request, string $appId)
    {
        $merchant = $request->attributes->get('merchant');

        $app = App::where('app_id', $appId)
            ->where('merchant_id', $merchant->id)
            ->firstOrFail();

        $data = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'webhook_url' => 'sometimes|nullable|url|max:500',
            'metadata'    => 'sometimes|nullable|array',
        ]);

        $app->update($data);

        return response()->json(['message' => 'App updated.', 'app' => $app]);
    }

    /**
     * Suspend / deactivate an app (soft-disable without deletion).
     */
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
