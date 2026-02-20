<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateSecureHeaders
{
    /**
     * Handle an incoming request.
     * 
     * Validates security headers to prevent replay attacks and ensure request freshness.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only validate for sensitive operations (POST, PUT, DELETE)
        if (!in_array($request->method(), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return $next($request);
        }

        // 1. Validate X-Timestamp header (prevent replay attacks)
        $timestamp = $request->header('X-Timestamp');

        if ($timestamp) {
            // Check if timestamp is a valid integer
            if (!is_numeric($timestamp)) {
                return response()->json([
                    'error' => 'Invalid timestamp format',
                    'message' => 'X-Timestamp must be a Unix timestamp'
                ], 400);
            }

            $currentTime = time();
            $requestTime = (int) $timestamp;

            // Allow requests within 5 minutes window (300 seconds)
            $maxAge = 300;

            if (abs($currentTime - $requestTime) > $maxAge) {
                return response()->json([
                    'error' => 'Request expired',
                    'message' => 'Request timestamp is too old or too far in the future. Please ensure your system clock is synchronized.'
                ], 401);
            }
        }

        // 2. Validate Content-Type for requests with body
        if ($request->getContent() && !$request->header('Content-Type')) {
            return response()->json([
                'error' => 'Missing Content-Type',
                'message' => 'Content-Type header is required for requests with body'
            ], 400);
        }

        // 3. Validate User-Agent (optional but recommended)
        $userAgent = $request->header('User-Agent');

        if (!$userAgent) {
            // Just log a warning, don't reject the request
            \Log::warning('Request without User-Agent header', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
        }

        // 4. Check for common security headers
        $requiredHeaders = ['Accept'];
        $missingHeaders = [];

        foreach ($requiredHeaders as $header) {
            if (!$request->header($header)) {
                $missingHeaders[] = $header;
            }
        }

        if (!empty($missingHeaders)) {
            return response()->json([
                'error' => 'Missing required headers',
                'message' => 'The following headers are required: ' . implode(', ', $missingHeaders)
            ], 400);
        }

        return $next($request);
    }
}
