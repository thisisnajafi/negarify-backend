<?php

namespace Tests\Unit\Services\Otp;

use App\Contracts\OtpSender;
use App\Exceptions\SmsProviderException;
use App\Services\Otp\MelipayamakOtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MelipayamakOtpSenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration
        Config::set('melipayamak', [
            'enabled' => true,
            'auth_mode' => 'token',
            'token' => 'test-token-123',
            'base_url' => 'https://rest.payamak-panel.com/api',
            'timeout' => 10,
            'otp' => [
                'mode' => 'pattern',
                'template_id' => 372382,
                'from_number' => '50002710008883',
                'message_text' => 'کد ورود شما: {CODE} این کد 5 دقیقه اعتبار دارد سروکست',
            ],
            'from' => '50002710008883',
        ]);
    }

    /** @test */
    public function it_validates_phone_number_format()
    {
        $sender = app(OtpSender::class);

        $this->expectException(SmsProviderException::class);
        $this->expectExceptionMessage('Invalid phone number format');

        $sender->sendOtp('1234567890', '123456');
    }

    /** @test */
    public function it_validates_otp_code_format()
    {
        $sender = app(OtpSender::class);

        $this->expectException(SmsProviderException::class);
        $this->expectExceptionMessage('Invalid OTP code format');

        $sender->sendOtp('09123456789', '12345'); // 5 digits instead of 6
    }

    /** @test */
    public function it_throws_exception_when_disabled()
    {
        Config::set('melipayamak.enabled', false);

        $sender = app(OtpSender::class);

        $this->expectException(SmsProviderException::class);
        $this->expectExceptionMessage('SMS service is disabled');

        $sender->sendOtp('09123456789', '123456');
    }

    /** @test */
    public function it_throws_exception_when_token_missing_in_token_mode()
    {
        Config::set('melipayamak.auth_mode', 'token');
        Config::set('melipayamak.token', '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MELIPAYAMAK_AUTH_TOKEN is required');

        app(OtpSender::class);
    }

    /** @test */
    public function it_throws_exception_when_credentials_missing_in_classic_mode()
    {
        Config::set('melipayamak.auth_mode', 'classic');
        Config::set('melipayamak.username', '');
        Config::set('melipayamak.password', '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MELIPAYAMAK_USERNAME and MELIPAYAMAK_PASSWORD are required');

        app(OtpSender::class);
    }
}

