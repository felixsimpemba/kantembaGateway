<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ApiKeyService;

class AuthenticateApiKey
{
    protected $apiKeyService;

    public function __construct(ApiKeyService $apiKeyService)
    {
        $this->apiKeyService = $apiKeyService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API key from Authorization header
        $apiKey = $request->header('Authorization');

        // Check X-API-KEY if Authorization is missing
        if (!$apiKey) {
            $apiKey = $request->header('X-API-KEY');
        }

        // Remove "Bearer " prefix if present
        if ($apiKey && str_starts_with($apiKey, 'Bearer ')) {
            $apiKey = substr($apiKey, 7);
        }

        // Validate API key
        if (!$apiKey) {
            return response()->json([
                'error' => 'API key is required',
                'message' => 'Please provide an API key in the Authorization header'
            ], 401);
        }

        $merchant = $this->apiKeyService->validateKey($apiKey);

        if (!$merchant) {
            return response()->json([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is invalid or has expired'
            ], 401);
        }

        // Check merchant status
        if ($merchant->status !== 'active') {
            return response()->json([
                'error' => 'Account suspended',
                'message' => 'Your merchant account is not active. Please contact support.'
            ], 403);
        }

        // Attach merchant to request for use in controllers
        $request->merge(['merchant' => $merchant]);
        $request->attributes->set('merchant', $merchant);

        return $next($request);
    }
}
