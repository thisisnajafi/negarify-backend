<?php

namespace Tests\Unit\Integrations\Melipayamak;

use App\Exceptions\SmsProviderException;
use App\Integrations\Melipayamak\TokenClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TokenClientTest extends TestCase
{
    private TokenClient $client;
    private string $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseUrl = 'https://rest.payamak-panel.com/api';
        $this->client = new TokenClient('test-token', $this->baseUrl, 10);
    }

    /** @test */
    public function it_sends_otp_template_successfully()
    {
        Http::fake([
            $this->baseUrl . '/SendSMS/BaseServiceNumber' => Http::response([
                'RetStatus' => 1,
                'StrRetStatus' => 'Ok',
                'Value' => '12345678',
            ], 200),
        ]);

        $result = $this->client->sendOtpTemplate('09123456789', '123456', 372382);

        $this->assertTrue($result['success']);
        $this->assertEquals('12345678', $result['message_id']);

        Http::assertSent(function ($request) {
            return $request->url() === $this->baseUrl . '/SendSMS/BaseServiceNumber'
                && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
                && $request['username'] === 'test-token'
                && $request['password'] === 'test-token'
                && $request['text'] === '123456'
                && $request['to'] === '09123456789'
                && $request['bodyId'] == 372382;
        });
    }

    /** @test */
    public function it_throws_exception_on_authentication_failure()
    {
        Http::fake([
            $this->baseUrl . '/SendSMS/BaseServiceNumber' => Http::response([
                'RetStatus' => 0,
                'StrRetStatus' => 'UserNameAndPasswordFailed',
            ], 200),
        ]);

        $this->expectException(SmsProviderException::class);
        $this->expectExceptionMessage('SMS provider authentication failed');

        $this->client->sendOtpTemplate('09123456789', '123456', 372382);
    }

    /** @test */
    public function it_throws_exception_on_insufficient_credit()
    {
        Http::fake([
            $this->baseUrl . '/SendSMS/BaseServiceNumber' => Http::response([
                'RetStatus' => 0,
                'StrRetStatus' => 'InsufficientCredit',
            ], 200),
        ]);

        $this->expectException(SmsProviderException::class);
        $this->expectExceptionMessage('SMS provider account has insufficient credit');

        $this->client->sendOtpTemplate('09123456789', '123456', 372382);
    }

    /** @test */
    public function it_throws_exception_on_invalid_phone_number()
    {
        Http::fake([
            $this->baseUrl . '/SendSMS/BaseServiceNumber' => Http::response([
                'RetStatus' => 0,
                'StrRetStatus' => 'InvalidPhoneNumber',
            ], 200),
        ]);

        $this->expectException(SmsProviderException::class);
        $this->expectExceptionMessage('Invalid phone number format');

        $this->client->sendOtpTemplate('09123456789', '123456', 372382);
    }

    /** @test */
    public function it_throws_exception_on_template_not_found()
    {
        Http::fake([
            $this->baseUrl . '/SendSMS/BaseServiceNumber' => Http::response([
                'RetStatus' => 0,
                'StrRetStatus' => 'TemplateNotFound',
            ], 200),
        ]);

        $this->expectException(SmsProviderException::class);
        $this->expectExceptionMessage('SMS template not found or not approved');

        $this->client->sendOtpTemplate('09123456789', '123456', 372382);
    }

    /** @test */
    public function it_throws_exception_on_parameter_mismatch()
    {
        Http::fake([
            $this->baseUrl . '/SendSMS/BaseServiceNumber' => Http::response([
                'RetStatus' => 0,
                'StrRetStatus' => 'ParameterMismatch',
            ], 200),
        ]);

        $this->expectException(SmsProviderException::class);
        $this->expectExceptionMessage('SMS template parameter mismatch');

        $this->client->sendOtpTemplate('09123456789', '123456', 372382);
    }

    /** @test */
    public function it_throws_exception_on_network_error()
    {
        Http::fake([
            $this->baseUrl . '/SendSMS/BaseServiceNumber' => Http::response([], 500),
        ]);

        $this->expectException(SmsProviderException::class);
        $this->expectExceptionMessage('SMS provider network error');

        $this->client->sendOtpTemplate('09123456789', '123456', 372382);
    }

    /** @test */
    public function it_throws_exception_on_invalid_response_structure()
    {
        Http::fake([
            $this->baseUrl . '/SendSMS/BaseServiceNumber' => Http::response([
                'invalid' => 'response',
            ], 200),
        ]);

        $this->expectException(SmsProviderException::class);
        $this->expectExceptionMessage('Invalid response structure');

        $this->client->sendOtpTemplate('09123456789', '123456', 372382);
    }

    /** @test */
    public function it_sends_plain_sms_successfully()
    {
        Http::fake([
            $this->baseUrl . '/SendSMS/SendSMS' => Http::response([
                'RetStatus' => 1,
                'StrRetStatus' => 'Ok',
                'Value' => '87654321',
            ], 200),
        ]);

        $result = $this->client->sendPlainSms('09123456789', 'Test message', '50002710008883');

        $this->assertTrue($result['success']);
        $this->assertEquals('87654321', $result['message_id']);

        Http::assertSent(function ($request) {
            return $request->url() === $this->baseUrl . '/SendSMS/SendSMS'
                && $request['username'] === 'test-token'
                && $request['password'] === 'test-token'
                && $request['to'] === '09123456789'
                && $request['from'] === '50002710008883'
                && $request['text'] === 'Test message';
        });
    }
}

