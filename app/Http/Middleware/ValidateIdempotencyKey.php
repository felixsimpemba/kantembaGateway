<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ValidateIdempotencyKey
{
    /**
     * Handle an incoming request.
     * 
     * Idempotency keys prevent duplicate requests. If the same key is sent twice,
     * the cached response from the first request is returned.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check idempotency for POST requests
        if ($request->method() !== 'POST') {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        // If no idempotency key provided, continue normally
        if (!$idempotencyKey) {
            return $next($request);
        }

        // Validate key format (must be alphanumeric, dashes, underscores)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $idempotencyKey)) {
            return response()->json([
                'error' => 'Invalid idempotency key format',
                'message' => 'Idempotency key must contain only alphanumeric characters, dashes, and underscores'
            ], 400);
        }

        // Get merchant from request (set by auth middleware)
        $merchant = $request->attributes->get('merchant');
        $merchantId = $merchant ? $merchant->id : 'guest';

        // Create a unique cache key combining merchant and idempotency key
        $cacheKey = "idempotency:{$merchantId}:{$idempotencyKey}";

        // Check if this request has been processed before
        if (Cache::has($cacheKey)) {
            // Return cached response
            $cachedResponse = Cache::get($cacheKey);

            return response()->json($cachedResponse['data'], $cachedResponse['status'])
                ->header('X-Idempotent-Replayed', 'true');
        }

        // Process the request
        $response = $next($request);

        // Cache successful responses (2xx status codes) for 24 hours
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $responseData = [
                'data' => json_decode($response->getContent(), true),
                'status' => $response->getStatusCode(),
            ];

            // Store for 24 hours (86400 seconds)
            Cache::put($cacheKey, $responseData, 86400);
        }

        return $response;
    }
}
