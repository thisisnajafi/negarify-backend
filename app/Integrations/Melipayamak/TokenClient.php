<?php

namespace App\Integrations\Melipayamak;

use App\Exceptions\SmsProviderException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Melipayamak Token-Based REST Client
 *
 * Handles REST API communication with Melipayamak using token authentication.
 * Used for template/pattern SMS (preferred for OTP).
 */
class TokenClient
{
    private string $token;
    private string $baseUrl;
    private int $timeout;

    public function __construct(string $token, string $baseUrl, int $timeout = 10)
    {
        $this->token = $token;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    /**
     * Send OTP using template/pattern SMS
     *
     * @param string $phone Recipient phone number
     * @param string $code OTP code
     * @param int $templateId Template/bodyId
     * @return array Response data with message_id
     * @throws SmsProviderException
     */
    public function sendOtpTemplate(string $phone, string $code, int $templateId): array
    {
        $endpoint = $this->baseUrl . '/SendSMS/BaseServiceNumber';

        try {
            $response = Http::timeout($this->timeout)
                ->asForm()
                ->post($endpoint, [
                    'username' => $this->token,
                    'password' => $this->token,
                    'text' => $code, // OTP code as single parameter
                    'to' => $phone,
                    'bodyId' => $templateId,
                ]);

            if (!$response->successful()) {
                Log::warning('Melipayamak template SMS HTTP error', [
                    'phone_hash' => $this->hashPhone($phone),
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                throw SmsProviderException::networkError('HTTP ' . $response->status());
            }

            $result = $response->json();

            // Check response structure
            if (!isset($result['RetStatus'])) {
                Log::error('Melipayamak invalid response structure', [
                    'phone_hash' => $this->hashPhone($phone),
                    'response' => $result,
                ]);

                throw SmsProviderException::unknownError('Invalid response structure');
            }

            // Success case
            if ($result['RetStatus'] == 1) {
                Log::info('Melipayamak template OTP sent', [
                    'phone_hash' => $this->hashPhone($phone),
                    'message_id' => $result['Value'] ?? null,
                    'template_id' => $templateId,
                ]);

                return [
                    'success' => true,
                    'message_id' => $result['Value'] ?? null,
                    'response' => $result,
                ];
            }

            // Error case - parse specific error
            $this->handleErrorResponse($result, $phone);

        } catch (SmsProviderException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Melipayamak template SMS exception', [
                'phone_hash' => $this->hashPhone($phone),
                'error' => $e->getMessage(),
            ]);

            throw SmsProviderException::networkError($e->getMessage());
        }
    }

    /**
     * Send plain SMS (fallback)
     *
     * @param string $phone Recipient phone number
     * @param string $message Full message text
     * @param string $from Sender number
     * @return array Response data with message_id
     * @throws SmsProviderException
     */
    public function sendPlainSms(string $phone, string $message, string $from): array
    {
        $endpoint = $this->baseUrl . '/SendSMS/SendSMS';

        try {
            $response = Http::timeout($this->timeout)
                ->asForm()
                ->post($endpoint, [
                    'username' => $this->token,
                    'password' => $this->token,
                    'to' => $phone,
                    'from' => $from,
                    'text' => $message,
                ]);

            if (!$response->successful()) {
                Log::warning('Melipayamak plain SMS HTTP error', [
                    'phone_hash' => $this->hashPhone($phone),
                    'status' => $response->status(),
                ]);

                throw SmsProviderException::networkError('HTTP ' . $response->status());
            }

            $result = $response->json();

            if (!isset($result['RetStatus'])) {
                throw SmsProviderException::unknownError('Invalid response structure');
            }

            if ($result['RetStatus'] == 1) {
                Log::info('Melipayamak plain SMS sent', [
                    'phone_hash' => $this->hashPhone($phone),
                    'message_id' => $result['Value'] ?? null,
                ]);

                return [
                    'success' => true,
                    'message_id' => $result['Value'] ?? null,
                    'response' => $result,
                ];
            }

            $this->handleErrorResponse($result, $phone);

        } catch (SmsProviderException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Melipayamak plain SMS exception', [
                'phone_hash' => $this->hashPhone($phone),
                'error' => $e->getMessage(),
            ]);

            throw SmsProviderException::networkError($e->getMessage());
        }
    }

    /**
     * Handle error response from API
     *
     * @param array $result API response
     * @param string $phone Phone number (for logging)
     * @return void
     * @throws SmsProviderException
     */
    private function handleErrorResponse(array $result, string $phone): void
    {
        $errorCode = $result['StrRetStatus'] ?? 'UnknownError';
        $retStatus = $result['RetStatus'] ?? 0;

        Log::warning('Melipayamak SMS error response', [
            'phone_hash' => $this->hashPhone($phone),
            'ret_status' => $retStatus,
            'error_code' => $errorCode,
        ]);

        // Map error codes to specific exceptions
        switch ($errorCode) {
            case 'UserNameAndPasswordFailed':
                throw SmsProviderException::authenticationFailed();

            case 'InsufficientCredit':
                throw SmsProviderException::insufficientCredit();

            case 'InvalidPhoneNumber':
                throw SmsProviderException::invalidPhoneNumber();

            case 'TemplateNotFound':
                throw SmsProviderException::templateNotFound();

            case 'ParameterMismatch':
                throw SmsProviderException::parameterMismatch();

            default:
                throw SmsProviderException::unknownError("Provider error: {$errorCode}");
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

