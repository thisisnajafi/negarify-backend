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
     * Send OTP SMS
     */
    public function sendOtp(string $phone, string $code): bool
    {
        try {
            $response = Http::post($this->baseUrl, [
                'username' => $this->username,
                'password' => $this->password,
                'to' => $phone,
                'from' => $this->from,
                'text' => "Your verification code is: {$code}. Valid for 5 minutes.",
            ]);

            if ($response->successful()) {
                $result = $response->json();
                
                // Log the response for debugging
                Log::info('Melipayamak SMS sent', [
                    'phone' => $phone,
                    'response' => $result,
                ]);

                // Check if SMS was sent successfully
                return isset($result['RetStatus']) && $result['RetStatus'] == 1;
            }

            Log::error('Melipayamak SMS failed', [
                'phone' => $phone,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Melipayamak SMS exception', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send custom SMS
     */
    public function sendSms(string $phone, string $message): bool
    {
        try {
            $response = Http::post($this->baseUrl, [
                'username' => $this->username,
                'password' => $this->password,
                'to' => $phone,
                'from' => $this->from,
                'text' => $message,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                return isset($result['RetStatus']) && $result['RetStatus'] == 1;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Melipayamak custom SMS failed', [
                'phone' => $phone,
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
}
