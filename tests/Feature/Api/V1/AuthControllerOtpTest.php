<?php

namespace Tests\Feature\Api\V1;

use App\Contracts\OtpSender;
use App\Exceptions\SmsProviderException;
use App\Models\OtpVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class AuthControllerOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock OtpSender
        $this->otpSender = Mockery::mock(OtpSender::class);
        $this->app->instance(OtpSender::class, $this->otpSender);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_requests_otp_successfully()
    {
        $this->otpSender->shouldReceive('sendOtp')
            ->once()
            ->with('09123456789', Mockery::pattern('/^\d{6}$/'))
            ->andReturnNull();

        $response = $this->postJson('/api/v1/auth/request-otp', [
            'phone' => '09123456789',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'request_id',
                    'expires_at',
                ],
            ]);

        $this->assertDatabaseHas('otp_verifications', [
            'phone' => '09123456789',
        ]);
    }

    /** @test */
    public function it_handles_otp_send_failure()
    {
        $this->otpSender->shouldReceive('sendOtp')
            ->once()
            ->andThrow(new SmsProviderException('SMS provider error', 500));

        $response = $this->postJson('/api/v1/auth/request-otp', [
            'phone' => '09123456789',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again later.',
            ]);

        // OTP should be deleted on failure
        $this->assertDatabaseMissing('otp_verifications', [
            'phone' => '09123456789',
        ]);
    }

    /** @test */
    public function it_respects_rate_limiting()
    {
        RateLimiter::clear('otp_request:' . hash('sha256', '09123456789' . config('app.key')));

        // First request should succeed
        $this->otpSender->shouldReceive('sendOtp')->times(3)->andReturnNull();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/request-otp', [
                'phone' => '09123456789',
            ]);
        }

        // Fourth request should be rate limited
        $response = $this->postJson('/api/v1/auth/request-otp', [
            'phone' => '09123456789',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function it_verifies_otp_successfully()
    {
        // Create OTP
        $result = OtpVerification::generate('09123456789');
        $otp = $result['otp'];
        $code = $result['code'];

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'request_id' => $otp->request_id,
            'code' => $code,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'phone',
                        'tokens_balance',
                    ],
                    'token',
                    'token_type',
                ],
            ]);

        // User should be created
        $this->assertDatabaseHas('users', [
            'phone' => '09123456789',
            'is_verified' => true,
        ]);

        // OTP should be marked as verified
        $otp->refresh();
        $this->assertNotNull($otp->verified_at);
    }

    /** @test */
    public function it_resends_otp_successfully()
    {
        // Create initial OTP
        $result = OtpVerification::generate('09123456789');
        $otp = $result['otp'];

        $this->otpSender->shouldReceive('sendOtp')
            ->once()
            ->with('09123456789', Mockery::pattern('/^\d{6}$/'))
            ->andReturnNull();

        $response = $this->postJson('/api/v1/auth/resend-otp', [
            'request_id' => $otp->request_id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'request_id',
                    'expires_at',
                ],
            ]);

        // Old OTP should be deleted
        $this->assertDatabaseMissing('otp_verifications', [
            'id' => $otp->id,
        ]);

        // New OTP should exist
        $this->assertDatabaseHas('otp_verifications', [
            'phone' => '09123456789',
        ]);
    }
}

