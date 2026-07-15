// app/Services/OneLinkService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneLinkService
{
    protected $apiUrl;
    protected $merchantId;
    protected $apiKey;
    protected $secretKey;

    public function __construct()
    {
        $this->apiUrl = config('services.onelink.api_url');
        $this->merchantId = config('services.onelink.merchant_id');
        $this->apiKey = config('services.onelink.api_key');
        $this->secretKey = config('services.onelink.secret_key');
    }

    /**
     * Generate signature for API request
     */
    protected function generateSignature($data): string
    {
        ksort($data);
        $string = http_build_query($data);
        return hash_hmac('sha256', $string, $this->secretKey);
    }

    /**
     * Encrypt sensitive data
     */
    protected function encryptData($data): string
    {
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt(
            json_encode($data),
            'AES-256-CBC',
            $this->secretKey,
            0,
            $iv
        );
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt response data
     */
    protected function decryptResponse($encryptedData): array
    {
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $this->secretKey,
            0,
            $iv
        );
        return json_decode($decrypted, true);
    }

    /**
     * Initiate IBFT transfer
     */
    public function initiateTransfer(array $params): array
    {
        $payload = [
            'merchant_id' => $this->merchantId,
            'transaction_id' => 'TXN' . time() . rand(1000, 9999),
            'amount' => $params['amount'],
            'account_number' => $params['account_number'],
            'bank_code' => $params['bank_code'],
            'reference' => $params['reference'] ?? '',
            'timestamp' => now()->format('YmdHis'),
        ];

        $payload['signature'] = $this->generateSignature($payload);

        try {
            // Encrypt payload for mock API (in production, use proper API call)
            $encryptedPayload = $this->encryptData($payload);

            // Mock API response for testing
            $response = $this->mockTransfer($encryptedPayload);

            Log::info('OneLink Transfer Initiated', [
                'payload' => $payload,
                'response' => $response
            ]);

            return $this->decryptResponse($response['data']);
        } catch (\Exception $e) {
            Log::error('OneLink Transfer Error', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            throw new \Exception('Bank transfer failed: ' . $e->getMessage());
        }
    }

    /**
     * Mock transfer for testing
     */
    protected function mockTransfer($encryptedPayload): array
    {
        // Simulate API call delay
        sleep(1);

        // Random success/failure (90% success rate for testing)
        $success = rand(1, 100) <= 90;

        $responseData = [
            'status' => $success ? 'success' : 'failed',
            'transaction_id' => 'IBFT' . time() . rand(1000, 9999),
            'message' => $success ? 'Transfer successful' : 'Transfer failed: Invalid account',
            'reference_number' => $success ? 'REF' . time() . rand(1000, 9999) : null,
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];

        if ($success) {
            // Mock bank response
            $responseData['bank_response'] = [
                'bank_reference' => 'BANK' . time() . rand(1000, 9999),
                'settlement_date' => now()->addDay()->format('Y-m-d'),
            ];
        }

        return [
            'status' => 200,
            'data' => $this->encryptData($responseData),
            'signature' => $this->generateSignature($responseData)
        ];
    }

    /**
     * Validate IBFT transaction status
     */
    public function checkStatus($transactionId): array
    {
        // Mock status check
        $statuses = ['pending', 'processing', 'success', 'failed'];
        $randomStatus = $statuses[array_rand($statuses)];

        return [
            'transaction_id' => $transactionId,
            'status' => $randomStatus,
            'message' => 'Transaction status retrieved',
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
