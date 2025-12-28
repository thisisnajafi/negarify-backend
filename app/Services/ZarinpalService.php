<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZarinpalService
{
    private string $merchantId;
    private bool $sandbox;
    private string $baseUrl;

    public function __construct()
    {
        $this->merchantId = config('services.zarinpal.merchant_id');
        $this->sandbox = config('services.zarinpal.sandbox', false);
        $this->baseUrl = $this->sandbox
            ? 'https://sandbox.zarinpal.com/pg/v4/payment'
            : 'https://api.zarinpal.com/pg/v4/payment';
    }

    /**
     * Request payment from Zarinpal
     * 
     * @param int $amount Amount in Toman
     * @param string $description Payment description
     * @param string $callbackUrl Callback URL
     * @param string|null $mobile User mobile (optional)
     * @param string|null $email User email (optional)
     * @return array ['authority' => string, 'payment_url' => string] or ['error' => string]
     */
    public function requestPayment(
        int $amount,
        string $description,
        string $callbackUrl,
        ?string $mobile = null,
        ?string $email = null
    ): array {
        try {
            $data = [
                'merchant_id' => $this->merchantId,
                'amount' => $amount,
                'description' => $description,
                'callback_url' => $callbackUrl,
            ];

            if ($mobile) {
                $data['mobile'] = $mobile;
            }

            if ($email) {
                $data['email'] = $email;
            }

            Log::info('Zarinpal payment request', [
                'amount' => $amount,
                'merchant_id' => $this->merchantId,
                'sandbox' => $this->sandbox,
            ]);

            $response = Http::timeout(10)->post("{$this->baseUrl}/request.json", $data);

            if (!$response->successful()) {
                Log::error('Zarinpal payment request failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return [
                    'error' => 'Payment request failed',
                    'status' => $response->status(),
                ];
            }

            $result = $response->json();

            // Zarinpal API v4 response structure
            if (isset($result['data']['code']) && $result['data']['code'] == 100) {
                $authority = $result['data']['authority'];
                $paymentUrl = $this->sandbox
                    ? "https://sandbox.zarinpal.com/pg/StartPay/{$authority}"
                    : "https://www.zarinpal.com/pg/StartPay/{$authority}";

                Log::info('Zarinpal payment request successful', [
                    'authority' => $authority,
                ]);

                return [
                    'authority' => $authority,
                    'payment_url' => $paymentUrl,
                ];
            }

            $errorCode = $result['errors']['code'] ?? 'unknown';
            $errorMessage = $result['errors']['message'] ?? 'Unknown error';

            Log::error('Zarinpal payment request error', [
                'code' => $errorCode,
                'message' => $errorMessage,
            ]);

            return [
                'error' => $errorMessage,
                'code' => $errorCode,
            ];
        } catch (\Exception $e) {
            Log::error('Zarinpal payment request exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => 'Payment request failed',
                'exception' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify payment with Zarinpal
     * 
     * @param string $authority Payment authority
     * @param int $amount Amount in Toman (must match original request)
     * @return array ['status' => 'success', 'ref_id' => string] or ['error' => string]
     */
    public function verifyPayment(string $authority, int $amount): array
    {
        try {
            $data = [
                'merchant_id' => $this->merchantId,
                'authority' => $authority,
                'amount' => $amount,
            ];

            Log::info('Zarinpal payment verification', [
                'authority' => $authority,
                'amount' => $amount,
            ]);

            $response = Http::timeout(10)->post("{$this->baseUrl}/verify.json", $data);

            if (!$response->successful()) {
                Log::error('Zarinpal payment verification failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return [
                    'error' => 'Payment verification failed',
                    'status' => $response->status(),
                ];
            }

            $result = $response->json();

            // Zarinpal API v4 response structure
            if (isset($result['data']['code']) && $result['data']['code'] == 100) {
                $refId = $result['data']['ref_id'];

                Log::info('Zarinpal payment verification successful', [
                    'authority' => $authority,
                    'ref_id' => $refId,
                ]);

                return [
                    'status' => 'success',
                    'ref_id' => (string) $refId,
                ];
            }

            $errorCode = $result['errors']['code'] ?? 'unknown';
            $errorMessage = $result['errors']['message'] ?? 'Unknown error';

            Log::error('Zarinpal payment verification error', [
                'authority' => $authority,
                'code' => $errorCode,
                'message' => $errorMessage,
            ]);

            return [
                'error' => $errorMessage,
                'code' => $errorCode,
            ];
        } catch (\Exception $e) {
            Log::error('Zarinpal payment verification exception', [
                'authority' => $authority,
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => 'Payment verification failed',
                'exception' => $e->getMessage(),
            ];
        }
    }
}

