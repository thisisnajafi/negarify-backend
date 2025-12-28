<?php

namespace Tests\Feature\Api\V1;

use App\Models\OtpVerification;
use App\Models\User;
use App\Services\MelipayamakService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock MelipayamakService to avoid real SMS sending
        $this->mock(MelipayamakService::class, function ($mock) {
            $mock->shouldReceive('sendOtp')
                ->andReturn(true);
        });
    }

    /** @test */
    public function it_can_request_otp_with_valid_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/request-otp', [
            'phone' => '+989123456789',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'request_id',
                    'expires_at',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('otp_verifications', [
            'phone' => '+989123456789',
        ]);
    }

    /** @test */
    public function it_rejects_invalid_phone_format(): void
    {
        $response = $this->postJson('/api/v1/auth/request-otp', [
            'phone' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /** @test */
    public function it_enforces_rate_limiting_on_otp_requests(): void
    {
        $phone = '+989123456789';
        $phoneHash = hash('sha256', $phone . config('app.key'));
        $rateLimitKey = "otp_request:{$phoneHash}";

        // Make 3 requests (should succeed)
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/v1/auth/request-otp', [
                'phone' => $phone,
            ]);
            $response->assertStatus(200);
        }

        // 4th request should be rate limited
        $response = $this->postJson('/api/v1/auth/request-otp', [
            'phone' => $phone,
        ]);

        $response->assertStatus(429);
    }

    /** @test */
    public function it_can_verify_otp_with_correct_code(): void
    {
        $phone = '+989123456789';
        $code = '123456';
        $codeHash = hash_hmac('sha256', $code, config('app.key'));

        $otp = OtpVerification::create([
            'phone' => $phone,
            'code_hash' => $codeHash,
            'request_id' => \Illuminate\Support\Str::uuid()->toString(),
            'max_attempts' => 3,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'request_id' => $otp->request_id,
            'code' => $code,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'token',
                    'token_type',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'token_type' => 'Bearer',
                ],
            ]);

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'phone' => $phone,
            'is_verified' => true,
        ]);
    }

    /** @test */
    public function it_rejects_incorrect_otp_code(): void
    {
        $phone = '+989123456789';
        $code = '123456';
        $codeHash = hash_hmac('sha256', $code, config('app.key'));

        $otp = OtpVerification::create([
            'phone' => $phone,
            'code_hash' => $codeHash,
            'request_id' => \Illuminate\Support\Str::uuid()->toString(),
            'max_attempts' => 3,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'request_id' => $otp->request_id,
            'code' => '000000', // Wrong code
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid OTP code',
            ]);
    }

    /** @test */
    public function it_rejects_expired_otp(): void
    {
        $phone = '+989123456789';
        $code = '123456';
        $codeHash = hash_hmac('sha256', $code, config('app.key'));

        $otp = OtpVerification::create([
            'phone' => $phone,
            'code_hash' => $codeHash,
            'request_id' => \Illuminate\Support\Str::uuid()->toString(),
            'max_attempts' => 3,
            'expires_at' => now()->subMinutes(10), // Expired
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'request_id' => $otp->request_id,
            'code' => $code,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'OTP has expired',
            ]);
    }

    /** @test */
    public function it_enforces_max_attempts_on_otp_verification(): void
    {
        $phone = '+989123456789';
        $code = '123456';
        $codeHash = hash_hmac('sha256', $code, config('app.key'));

        $otp = OtpVerification::create([
            'phone' => $phone,
            'code_hash' => $codeHash,
            'request_id' => \Illuminate\Support\Str::uuid()->toString(),
            'max_attempts' => 3,
            'attempts' => 3, // Already at max
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'request_id' => $otp->request_id,
            'code' => $code,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Maximum verification attempts exceeded',
            ]);
    }

    /** @test */
    public function it_can_logout_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);

        // Verify token was deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'test-token',
        ]);
    }
}

