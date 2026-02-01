<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateRequestSignature
{
    /**
     * Handle an incoming request.
     * 
     * Validates HMAC signature to ensure request authenticity.
     * Signature is calculated as: HMAC-SHA256(timestamp + method + path + body, webhook_secret)
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get signature from header
        $providedSignature = $request->header('X-Signature');

        // If no signature provided, skip validation (optional for some endpoints)
        if (!$providedSignature) {
            return $next($request);
        }

        // Get merchant from request (set by auth middleware)
        $merchant = $request->attributes->get('merchant');

        if (!$merchant) {
            return response()->json([
                'error' => 'Authentication required',
                'message' => 'Signature verification requires authentication'
            ], 401);
        }

        // Get webhook secret for this merchant
        $secret = $merchant->webhook_secret;

        if (!$secret) {
            return response()->json([
                'error' => 'Configuration error',
                'message' => 'Merchant webhook secret not configured'
            ], 500);
        }

        // Get timestamp from header (required for signature)
        $timestamp = $request->header('X-Timestamp');

        if (!$timestamp) {
            return response()->json([
                'error' => 'Missing timestamp',
                'message' => 'X-Timestamp header is required for signature verification'
            ], 400);
        }

        // Build signature payload: timestamp + method + path + body
        $method = $request->method();
        $path = $request->path();
        $body = $request->getContent();

        $payload = $timestamp . $method . $path . $body;

        // Calculate expected signature
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        // Compare signatures (timing-safe comparison)
        if (!hash_equals($expectedSignature, $providedSignature)) {
            return response()->json([
                'error' => 'Invalid signature',
                'message' => 'Request signature verification failed'
            ], 401);
        }

        // Signature is valid
        return $next($request);
    }
}
