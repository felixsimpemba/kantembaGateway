<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Jose\Component\Core\JWK;
use Jose\Component\Encryption\Algorithm\KeyEncryption\RSAOAEP256;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Encryption\Compression\CompressionMethodManager;
use Jose\Component\Encryption\Compression\Deflate;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Encryption\Serializer\CompactSerializer;

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
     * Get the RSA public key from Lenco for payload encryption
     */
    public function getEncryptionKey()
    {
        try {
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get("{$this->baseUrl}/encryption-key");

            if ($response->successful()) {
                return $response->json()['data'];
            }

            throw new \Exception('Failed to fetch Lenco encryption key: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('Lenco Encryption Key Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Encrypt payload using JWE (RSA-OAEP-256 and A256GCM)
     */
    public function encryptPayload(array $payload)
    {
        // 1. Fetch the JWK data from Lenco
        $jwkData = $this->getEncryptionKey();

        // 2. Create a JWK object
        $jwk = new JWK($jwkData);

        // 3. Setup the JWE Builder with required algorithms
        $keyEncryptionAlgorithmManager = new \Jose\Component\Core\AlgorithmManager([
            new RSAOAEP256(),
        ]);
        $contentEncryptionAlgorithmManager = new \Jose\Component\Core\AlgorithmManager([
            new A256GCM(),
        ]);
        $compressionMethodManager = new CompressionMethodManager([
            new Deflate(),
        ]);

        $jweBuilder = new JWEBuilder(
            $keyEncryptionAlgorithmManager,
            $contentEncryptionAlgorithmManager,
            $compressionMethodManager
        );

        // 4. Construct the JWE
        $jwe = $jweBuilder
            ->create()              // Create a new JWE
            ->withPayload(json_encode($payload)) // Set the payload
            ->withSharedProtectedHeader([
                'alg' => 'RSA-OAEP-256',
                'enc' => 'A256GCM',
                'cty' => 'application/json',
                'kid' => $jwkData['kid']
            ])
            ->addRecipient($jwk)    // Provide the recipient's key
            ->build();              // Build the JWE

        // 5. Serialize the JWE into a Compact String
        $serializer = new CompactSerializer();
        return $serializer->serialize($jwe, 0); // index 0 for the first recipient
    }

    /**
     * Initiate a card collection
     */
    public function initiateCardCollection(array $data)
    {
        try {
            Log::info('Initiating Lenco Card Collection (preparing payload)');

            $encryptedPayload = $this->encryptPayload($data);

            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/collections/card", [
                'encryptedPayload' => $encryptedPayload
            ]);

            if ($response->successful()) {
                Log::info('Lenco Card Collection Initialized', $response->json());
                return $response->json();
            }

            Log::error('Lenco Card Collection Failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            throw new \Exception('Lenco Card Collection failed: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('Lenco Card Collection Error: ' . $e->getMessage());
            throw $e;
        }
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
