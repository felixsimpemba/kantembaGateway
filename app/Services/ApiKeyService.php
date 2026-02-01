<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Merchant;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class ApiKeyService
{
    /**
     * Generate a new API key for a merchant
     *
     * @param Merchant $merchant
     * @param string $type 'live' or 'test'
     * @return array ['key' => raw_key, 'api_key' => ApiKey model]
     */
    public function generateKey(Merchant $merchant, string $type = 'test'): array
    {
        // Generate a random API key
        $rawKey = 'pk_' . ($type === 'live' ? 'live' : 'test') . '_' . Str::random(32);

        // Hash the key for storage
        $hashedKey = Hash::make($rawKey);

        // Create the API key record
        $apiKey = ApiKey::create([
            'merchant_id' => $merchant->id,
            'key' => $hashedKey,
            'type' => $type,
        ]);

        // Return both the plain key (to show user once) and the model
        return [
            'key' => $rawKey,
            'api_key' => $apiKey,
        ];
    }

    /**
     * Validate an API key and return the associated merchant
     *
     * @param string $key
     * @return Merchant|null
     */
    public function validateKey(string $key): ?Merchant
    {
        // Get all API keys (we need to check hash)
        $apiKeys = ApiKey::with('merchant')->get();

        foreach ($apiKeys as $apiKey) {
            if (Hash::check($key, $apiKey->key)) {
                // Check if key is expired
                if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
                    return null;
                }

                // Update last used timestamp
                $apiKey->update(['last_used_at' => now()]);

                return $apiKey->merchant;
            }
        }

        return null;
    }

    /**
     * Revoke an API key
     *
     * @param int $apiKeyId
     * @return bool
     */
    public function revokeKey(int $apiKeyId): bool
    {
        $apiKey = ApiKey::find($apiKeyId);

        if ($apiKey) {
            return $apiKey->delete();
        }

        return false;
    }
}
