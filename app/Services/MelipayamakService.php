<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MelipayamakService
{
    private string $username;
    private string $password;
    private string $from;
    private string $baseUrl;

    public function __construct()
    {
        $this->username = config('services.melipayamak.username');
        $this->password = config('services.melipayamak.password');
        $this->from = config('services.melipayamak.from');
        $this->baseUrl = config('services.melipayamak.base_url', 'https://rest.payamak-panel.com/api/SendSMS/SendSMS');
    }

    /**
     * Send OTP SMS with retry logic
     * 
     * @param string $phone Phone number (normalized)
     * @param string $code OTP code (6 digits)
     * @return bool True if SMS sent successfully, false otherwise
     */
    public function sendOtp(string $phone, string $code): bool
    {
        $maxRetries = 3;
        $retryDelays = [1, 2, 4]; // Exponential backoff in seconds
        
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                $response = Http::timeout(10)->post($this->baseUrl, [
                    'username' => $this->username,
                    'password' => $this->password,
                    'to' => $phone,
                    'from' => $this->from,
                    'text' => "Your verification code is: {$code}. Valid for 5 minutes.",
                ]);

                if ($response->successful()) {
                    $result = $response->json();
                    
                    // Log success (without OTP code)
                    Log::info('Melipayamak OTP SMS sent', [
                        'phone_hash' => $this->hashPhone($phone),
                        'attempt' => $attempt + 1,
                        'ret_status' => $result['RetStatus'] ?? null,
                    ]);

                    // Check if SMS was sent successfully
                    if (isset($result['RetStatus']) && $result['RetStatus'] == 1) {
                        return true;
                    }
                }

                // Log failure (without OTP code)
                Log::warning('Melipayamak SMS failed', [
                    'phone_hash' => $this->hashPhone($phone),
                    'attempt' => $attempt + 1,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                // If not last attempt, wait before retry
                if ($attempt < $maxRetries - 1) {
                    sleep($retryDelays[$attempt]);
                }
            } catch (\Exception $e) {
                Log::error('Melipayamak SMS exception', [
                    'phone_hash' => $this->hashPhone($phone),
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);

                // If not last attempt, wait before retry
                if ($attempt < $maxRetries - 1) {
                    sleep($retryDelays[$attempt]);
                }
            }
        }

        // All retries exhausted
        Log::error('Melipayamak SMS all retries exhausted', [
            'phone_hash' => $this->hashPhone($phone),
            'max_retries' => $maxRetries,
        ]);

        return false;
    }

    /**
     * Send custom SMS
     * 
     * @param string $phone Phone number (normalized)
     * @param string $message SMS message
     * @return bool True if SMS sent successfully, false otherwise
     */
    public function sendSms(string $phone, string $message): bool
    {
        try {
            $response = Http::timeout(10)->post($this->baseUrl, [
                'username' => $this->username,
                'password' => $this->password,
                'to' => $phone,
                'from' => $this->from,
                'text' => $message,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                
                Log::info('Melipayamak SMS sent', [
                    'phone_hash' => $this->hashPhone($phone),
                    'ret_status' => $result['RetStatus'] ?? null,
                ]);
                
                return isset($result['RetStatus']) && $result['RetStatus'] == 1;
            }

            Log::warning('Melipayamak SMS failed', [
                'phone_hash' => $this->hashPhone($phone),
                'status' => $response->status(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Melipayamak SMS exception', [
                'phone_hash' => $this->hashPhone($phone),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get SMS delivery status
     */
    public function getDeliveryStatus(string $messageId): array
    {
        try {
            $response = Http::post('https://rest.payamak-panel.com/api/SendSMS/GetDeliveries2', [
                'username' => $this->username,
                'password' => $this->password,
                'recId' => $messageId,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Melipayamak delivery status failed', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Hash phone number for logging (privacy protection)
     * 
     * @param string $phone Phone number
     * @return string Hashed phone number
     */
    private function hashPhone(string $phone): string
    {
        return hash('sha256', $phone . config('app.key'));
    }
}
