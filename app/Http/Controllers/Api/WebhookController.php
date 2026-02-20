<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WebhookLog;

class WebhookController extends Controller
{
    /**
     * Get webhook logs for the authenticated merchant
     */
    public function index(Request $request)
    {
        $merchant = $request->attributes->get('merchant');

        $logs = WebhookLog::where('merchant_id', $merchant->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($logs);
    }

    /**
     * Update webhook settings
     */
    public function updateSettings(Request $request)
    {
        $merchant = $request->attributes->get('merchant');

        $request->validate([
            'webhook_url' => 'required|url',
            // 'webhook_secret' -> usually auto-generated but could be rotated here
        ]);

        $merchant->webhook_url = $request->webhook_url;
        $merchant->save();

        return response()->json([
            'message' => 'Webhook settings updated successfully',
            'merchant' => $merchant
        ]);
    }
}
