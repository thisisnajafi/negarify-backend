<?php

namespace App\Contracts;

/**
 * OTP Sender Interface
 *
 * Contract for sending OTP codes via SMS providers.
 * Implementations must handle OTP code delivery securely.
 */
interface OtpSender
{
    /**
     * Send OTP code to the specified phone number
     *
     * @param string $phone Phone number (normalized, e.g., 09123456789)
     * @param string $code OTP code (6 digits)
     * @return void
     * @throws \App\Exceptions\SmsProviderException If sending fails
     */
    public function sendOtp(string $phone, string $code): void;
}

