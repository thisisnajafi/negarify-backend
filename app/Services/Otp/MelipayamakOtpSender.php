<?php

namespace App\Services\Otp;

use App\Contracts\OtpSender;
use App\Exceptions\SmsProviderException;
use App\Integrations\Melipayamak\ClassicClient;
use App\Integrations\Melipayamak\TokenClient;
use Illuminate\Support\Facades\Log;

/**
 * Melipayamak OTP Sender Service
 *
 * Implements OtpSender interface using Melipayamak SMS gateway.
 * Uses template/pattern SMS (preferred) with plain SMS fallback.
 */
class MelipayamakOtpSender implements OtpSender
{
    private ClassicClient|TokenClient $client;
    private string $otpMode;
    private int $templateId;
    private string $fromNumber;
    private string $messageTemplate;
    private bool $enabled;

    public function __construct()
    {
        $config = config('melipayamak');

        $this->enabled = $config['enabled'] ?? true;
        $this->otpMode = $config['otp']['mode'] ?? 'pattern';
        $this->templateId = (int) ($config['otp']['template_id'] ?? 372382);
        $this->fromNumber = $config['otp']['from_number'] ?? $config['from'] ?? '50002710008883';
        $this->messageTemplate = $config['otp']['message_text'] ?? 'کد ورود شما: {CODE} این کد 5 دقیقه اعتبار دارد سروکست';

        // Initialize client based on auth mode
        $authMode = $config['auth_mode'] ?? 'classic';
        $baseUrl = $config['base_url'] ?? 'https://rest.payamak-panel.com/api';
        $timeout = $config['timeout'] ?? 10;

        if ($authMode === 'token') {
            $token = $config['token'] ?? '';
            if (empty($token)) {
                throw new \RuntimeException('MELIPAYAMAK_AUTH_TOKEN is required when auth_mode is "token"');
            }
            $this->client = new TokenClient($token, $baseUrl, $timeout);
        } else {
            $username = $config['username'] ?? '';
            $password = $config['password'] ?? '';
            if (empty($username) || empty($password)) {
                throw new \RuntimeException('MELIPAYAMAK_USERNAME and MELIPAYAMAK_PASSWORD are required when auth_mode is "classic"');
            }
            $this->client = new ClassicClient($username, $password, $baseUrl, $timeout);
        }
    }

    /**
     * Send OTP code to the specified phone number
     *
     * @param string $phone Phone number (normalized, e.g., 09123456789)
     * @param string $code OTP code (6 digits)
     * @return void
     * @throws SmsProviderException
     */
    public function sendOtp(string $phone, string $code): void
    {
        if (!$this->enabled) {
            Log::warning('Melipayamak OTP sender is disabled', [
                'phone_hash' => $this->hashPhone($phone),
            ]);
            throw new SmsProviderException('SMS service is disabled', 503);
        }

        // Validate phone number format (Iranian mobile: 11 digits, starts with 09)
        if (!preg_match('/^09\d{9}$/', $phone)) {
            throw SmsProviderException::invalidPhoneNumber();
        }

        // Validate OTP code format (6 digits)
        if (!preg_match('/^\d{6}$/', $code)) {
            throw new SmsProviderException('Invalid OTP code format. Must be 6 digits.', 400);
        }

        try {
            // Try template/pattern SMS first (preferred)
            if ($this->otpMode === 'pattern') {
                try {
                    $this->client->sendOtpTemplate($phone, $code, $this->templateId);
                    return; // Success
                } catch (SmsProviderException $e) {
                    // If template fails with specific errors, try fallback
                    if (in_array($e->getCode(), [404, 400])) { // TemplateNotFound or ParameterMismatch
                        Log::warning('Melipayamak template SMS failed, falling back to plain SMS', [
                            'phone_hash' => $this->hashPhone($phone),
                            'error' => $e->getMessage(),
                        ]);
                        // Fall through to plain SMS
                    } else {
                        // Re-throw other errors (auth, credit, network)
                        throw $e;
                    }
                }
            }

            // Fallback to plain SMS
            $message = str_replace('{CODE}', $code, $this->messageTemplate);
            $this->client->sendPlainSms($phone, $message, $this->fromNumber);

        } catch (SmsProviderException $e) {
            // Re-throw SmsProviderException as-is (already sanitized)
            throw $e;
        } catch (\Exception $e) {
            // Wrap unexpected exceptions
            Log::error('Melipayamak OTP send unexpected error', [
                'phone_hash' => $this->hashPhone($phone),
                'error' => $e->getMessage(),
            ]);

            throw SmsProviderException::networkError($e->getMessage());
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

