<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    #[OA\Get(
        path: "/api/dashboard/stats",
        summary: "Get dashboard statistics",
        security: [["apiKey" => []]],
        tags: ["Dashboard"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Dashboard stats retrieved",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "total_success", type: "integer"),
                        new OA\Property(property: "total_failed", type: "integer"),
                        new OA\Property(property: "failed_24h", type: "integer"),
                        new OA\Property(property: "volume_24h", type: "number")
                    ]
                )
            )
        ]
    )]
    public function stats(Request $request)
    {
        $merchant = $request->attributes->get('merchant');

        $totalSuccess = $merchant->payments()->where('status', 'succeeded')->count();
        $totalFailed = $merchant->payments()->where('status', 'failed')->count();

        $failed24h = $merchant->payments()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $volume24h = $merchant->payments()
            ->where('status', 'succeeded')
            ->where('created_at', '>=', now()->subDay())
            ->sum('amount');

        return response()->json([
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'failed_24h' => $failed24h,
            'volume_24h' => $volume24h
        ]);
    }
}
