<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TransactionController extends Controller
{
    #[OA\Get(
        path: "/api/transactions",
        summary: "Get transaction history",
        security: [["apiKey" => []]],
        tags: ["Transactions"],
        parameters: [
            new OA\Parameter(
                name: "type",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["charge", "refund", "failed"])
            ),
            new OA\Parameter(
                name: "limit",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", default: 20)
            ),
            new OA\Parameter(
                name: "offset",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", default: 0)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Transactions retrieved",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "transactions", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "total", type: "integer"),
                        new OA\Property(property: "limit", type: "integer"),
                        new OA\Property(property: "offset", type: "integer")
                    ]
                )
            )
        ]
    )]
    public function index(Request $request)
    {
        $merchant = $request->attributes->get('merchant');

        $query = Transaction::where('merchant_id', $merchant->id);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('payment', function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        // Filter by Status (via Payment)
        if ($request->has('status') && $request->status !== 'all') {
            $status = $request->status;
            $query->whereHas('payment', function ($q) use ($status) {
                $q->where('status', $status);
            });
        }

        // Filter by Date Range
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Get total count
        $total = $query->count();

        // Pagination
        $limit = min($request->input('limit', 20), 100); // Max 100
        $offset = $request->input('offset', 0);

        $transactions = $query->with('payment')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return response()->json([
            'transactions' => $transactions,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ], 200);
    }
}
