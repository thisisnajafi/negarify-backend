<?php

namespace App\Exceptions;

use Exception;

/**
 * SMS Provider Exception
 *
 * Thrown when SMS provider operations fail.
 * Messages are sanitized to prevent credential leakage.
 */
class SmsProviderException extends Exception
{
    /**
     * Create a new exception instance
     *
     * @param string $message Sanitized error message (no secrets)
     * @param int $code Error code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for authentication failure
     *
     * @return static
     */
    public static function authenticationFailed(): self
    {
        return new self('SMS provider authentication failed. Please check credentials.', 401);
    }

    /**
     * Create exception for insufficient credit
     *
     * @return static
     */
    public static function insufficientCredit(): self
    {
        return new self('SMS provider account has insufficient credit.', 402);
    }

    /**
     * Create exception for invalid phone number
     *
     * @return static
     */
    public static function invalidPhoneNumber(): self
    {
        return new self('Invalid phone number format.', 400);
    }

    /**
     * Create exception for template not found
     *
     * @return static
     */
    public static function templateNotFound(): self
    {
        return new self('SMS template not found or not approved.', 404);
    }

    /**
     * Create exception for parameter mismatch
     *
     * @return static
     */
    public static function parameterMismatch(): self
    {
        return new self('SMS template parameter mismatch.', 400);
    }

    /**
     * Create exception for network/connection error
     *
     * @return static
     */
    public static function networkError(string $details = ''): self
    {
        $message = 'SMS provider network error.';
        if ($details) {
            $message .= ' ' . $details;
        }
        return new self($message, 503);
    }

    /**
     * Create exception for unknown error
     *
     * @param string $providerMessage Provider error message (sanitized)
     * @return static
     */
    public static function unknownError(string $providerMessage = ''): self
    {
        $message = 'SMS provider error occurred.';
        if ($providerMessage) {
            $message .= ' ' . $providerMessage;
        }
        return new self($message, 500);
    }
}

