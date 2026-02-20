<?php

namespace App\Services;

use App\Jobs\WebhookCall;
use App\Models\Merchant;
use App\Models\Webhook;

class WebhookService
{
    /**
     * Dispatch a webhook event to the merchant
     */
    public function dispatch(Merchant $merchant, string $eventType, array $data): void
    {
        // 1. Check if merchant has configured webhooks in the webhooks table
        $webhooks = Webhook::where('merchant_id', $merchant->id)
            ->where('is_active', true)
            ->get();

        $destinations = [];

        // Filter webhooks that subscribe to this event (or all events if specific list is null/empty)
        foreach ($webhooks as $webhook) {
            $events = $webhook->events; // Assuming cast to array in model
            if (empty($events) || in_array($eventType, $events)) {
                $destinations[] = $webhook->url;
            }
        }

        // 2. Fallback: Check merchant's main webhook_url if no specific webhooks found
        if ($webhooks->isEmpty() && !empty($merchant->webhook_url)) {
            $destinations[] = $merchant->webhook_url;
        }

        // Deduplicate
        $destinations = array_unique($destinations);

        if (empty($destinations)) {
            return; // No endpoint to notify
        }

        // Prepare standard payload structure
        $payload = [
            'event' => $eventType,
            'data' => $data,
            'created_at' => now()->toIso8601String(),
        ];

        // Dispatch job for each destination
        foreach ($destinations as $url) {
            WebhookCall::dispatch(
                $url,
                $payload,
                $merchant->webhook_secret ?? '',
                $merchant->id,
                $eventType
            );
        }
    }
}
