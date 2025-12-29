<?php

namespace Test\BackendTest\Laravel\Feature\Auth;

use App\Models\OtpVerification;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;
use Carbon\Carbon;

class OtpVerifyTest extends BackendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        RateLimiter::clear('otp_request:*');
        
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'StrRetStatus' => 'Ok',
                'RetStatus' => 1,
            ], 200),
        ]);
    }

    /** @test */
    public function it_verifies_valid_otp_code_and_creates_user(): void
    {
        $phone = '09373264601'; // Normalized format (what's stored in DB after +989373264601 normalization)
        
        // Create OTP
        $result = OtpVerification::generate($phone);
        $otp = $result['otp'];
        $code = $result['code'];
        
        // Verify OTP
        $response = $this->makeRequest('POST', '/api/v1/auth/verify-otp', [
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
                        'name',
                        'email',
                        'tokens_balance',
                        'role',
                        'is_verified',
                    ],
                    'token',
                    'token_type',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'OTP verified successfully',
                'data' => [
                    'user' => [
                        'phone' => $phone,
                        'tokens_balance' => 0.0,
                        'role' => 'user',
                        'is_verified' => true,
                    ],
                    'token_type' => 'Bearer',
                ],
            ]);

        // Verify user created
        $user = User::where('phone', $phone)->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_verified);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertEquals(0, $user->tokens_balance);

        // Verify OTP marked as verified
        $otp->refresh();
        $this->assertNotNull($otp->verified_at);

        // Verify token created
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_verifies_valid_otp_code_and_updates_existing_user(): void
    {
        $phone = '09373264601'; // Normalized format (what's stored in DB after +989373264601 normalization)
        
        // Create existing user
        $existingUser = User::create([
            'phone' => $phone,
            'is_verified' => false,
            'tokens_balance' => 100,
            'role' => 'user',
        ]);
        
        // Create OTP
        $result = OtpVerification::generate($phone);
        $otp = $result['otp'];
        $code = $result['code'];
        
        // Verify OTP
        $response = $this->makeRequest('POST', '/api/v1/auth/verify-otp', [
            'request_id' => $otp->request_id,
            'code' => $code,
        ]);

        $response->assertStatus(200);

        // Verify user updated (not created new)
        $this->assertEquals(1, User::where('phone', $phone)->count());
        $existingUser->refresh();
        $this->assertTrue($existingUser->is_verified);
        $this->assertNotNull($existingUser->phone_verified_at);
        $this->assertEquals(100, $existingUser->tokens_balance); // Balance preserved
    }

    /** @test */
    public function it_rejects_invalid_otp_code(): void
    {
        $phone = '09373264601'; // Normalized format (what's stored in DB after +989373264601 normalization)
        
        // Create OTP
        $result = OtpVerification::generate($phone);
        $otp = $result['otp'];
        
        // Try to verify with wrong code
        $response = $this->makeRequest('POST', '/api/v1/auth/verify-otp', [
            'request_id' => $otp->request_id,
            'code' => '000000',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid OTP code',
            ])
            ->assertJsonStructure([
                'data' => [
                    'attempts_remaining',
                ],
            ]);

        // Verify attempts incremented
        $otp->refresh();
        $this->assertEquals(1, $otp->attempts);

        // Allow warning logs for invalid code (expected)
        $this->allowErrorLogs(['OTP verification failed - invalid code']);
    }

    /** @test */
    public function it_enforces_max_attempts_limit(): void
    {
        $phone = '09373264601'; // Normalized format (what's stored in DB after +989373264601 normalization)
        
        // Create OTP
        $result = OtpVerification::generate($phone);
        $otp = $result['otp'];
        
        // Make 3 failed attempts
        for ($i = 0; $i < 3; $i++) {
            $response = $this->makeRequest('POST', '/api/v1/auth/verify-otp', [
                'request_id' => $otp->request_id,
                'code' => '000000',
            ]);
            $response->assertStatus(400);
        }

        // 4th attempt should fail with max attempts exceeded
        $response = $this->makeRequest('POST', '/api/v1/auth/verify-otp', [
            'request_id' => $otp->request_id,
            'code' => '000000',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Maximum verification attempts exceeded',
            ]);

        $otp->refresh();
        $this->assertEquals(3, $otp->attempts);

        $this->allowErrorLogs(['OTP verification failed - invalid code', 'OTP verification failed - max attempts exceeded']);
    }

    /** @test */
    public function it_rejects_expired_otp(): void
    {
        $phone = '09373264601'; // Normalized format (what's stored in DB after +989373264601 normalization)
        
        // Create OTP
        $result = OtpVerification::generate($phone);
        $otp = $result['otp'];
        $code = $result['code'];
        
        // Expire the OTP
        $otp->update(['expires_at' => Carbon::now()->subMinute()]);
        
        // Try to verify
        $response = $this->makeRequest('POST', '/api/v1/auth/verify-otp', [
            'request_id' => $otp->request_id,
            'code' => $code,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'OTP has expired',
            ]);

        $this->allowErrorLogs(['OTP verification failed - expired']);
    }

    /** @test */
    public function it_rejects_already_verified_otp(): void
    {
        $phone = '09373264601'; // Normalized format (what's stored in DB after +989373264601 normalization)
        
        // Create and verify OTP
        $result = OtpVerification::generate($phone);
        $otp = $result['otp'];
        $code = $result['code'];
        
        $otp->verify($code); // Verify once
        
        // Try to verify again
        $response = $this->makeRequest('POST', '/api/v1/auth/verify-otp', [
            'request_id' => $otp->request_id,
            'code' => $code,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'OTP has already been verified',
            ]);

        $this->allowErrorLogs(['OTP verification failed - already verified']);
    }

    /** @test */
    public function it_rejects_invalid_request_id(): void
    {
        $response = $this->makeRequest('POST', '/api/v1/auth/verify-otp', [
            'request_id' => '00000000-0000-0000-0000-000000000000',
            'code' => '123456',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid request ID',
            ]);

        $this->allowErrorLogs(['OTP verification failed - invalid request_id']);
    }

    /** @test */
    public function it_validates_request_id_format(): void
    {
        $response = $this->makeRequest('POST', '/api/v1/auth/verify-otp', [
            'request_id' => 'invalid-uuid',
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['request_id']);
    }

    /** @test */
    public function it_validates_code_format(): void
    {
        $phone = '09373264601'; // Normalized format (what's stored in DB after +989373264601 normalization)
        $result = OtpVerification::generate($phone);
        $otp = $result['otp'];
        
        // Test invalid code formats
        $invalidCodes = ['12345', '1234567', 'abcdef', '12-3456'];
        
        foreach ($invalidCodes as $invalidCode) {
            $response = $this->makeRequest('POST', '/api/v1/auth/verify-otp', [
                'request_id' => $otp->request_id,
                'code' => $invalidCode,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['code']);
        }
    }

    /** @test */
    public function it_requires_both_request_id_and_code(): void
    {
        // Missing request_id
        $response = $this->makeRequest('POST', '/api/v1/auth/verify-otp', [
            'code' => '123456',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['request_id']);

        // Missing code
        $response = $this->makeRequest('POST', '/api/v1/auth/verify-otp', [
            'request_id' => '00000000-0000-0000-0000-000000000000',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['code']);
    }
}

