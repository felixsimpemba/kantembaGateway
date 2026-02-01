<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LencoService
{
    protected $baseUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.lenco.base_url', 'https://api.lenco.co/v2.0');
        $this->apiKey = config('services.lenco.api_key');
    }

    /**
     * Initiate a mobile money collection
     */
    public function initiateMobileMoneyCollection(array $data)
    {
        try {
            Log::info('Initiating Lenco Mobile Money Collection', $data);

            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/collections/mobile-money", $data);

            if ($response->successful()) {
                Log::info('Lenco Collection Initialized', $response->json());
                return $response->json();
            }

            Log::error('Lenco Collection Failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            throw new \Exception('Lenco Mobile Money Collection failed: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Lenco Service Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verify a collection status
     */
    public function verifyCollection(string $reference)
    {
        try {
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get("{$this->baseUrl}/collections/status/{$reference}");

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('Lenco verification failed');

        } catch (\Exception $e) {
            Log::error('Lenco Verification Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
