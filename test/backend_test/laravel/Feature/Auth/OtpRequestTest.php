<?php

namespace Test\BackendTest\Laravel\Feature\Auth;

use App\Models\OtpVerification;
use App\Services\MelipayamakService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;
use Carbon\Carbon;

class OtpRequestTest extends BackendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear rate limiters before each test
        RateLimiter::clear('otp_request:*');
        
        // Fake HTTP for Melipayamak service
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'StrRetStatus' => 'Ok',
                'RetStatus' => 1,
                'Value' => '123456',
            ], 200),
        ]);
    }

    /** @test */
    public function it_can_request_otp_with_valid_phone_number(): void
    {
        $phoneInput = '+989373264601';
        // Phone is normalized to 09373264601 (removes +98 prefix, adds 0)
        $normalizedPhone = '09373264601';
        
        $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => $phoneInput,
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
                'message' => 'OTP sent successfully',
            ]);

        // Verify OTP stored in database (phone is normalized)
        $this->assertDatabaseHas('otp_verifications', [
            'phone' => $normalizedPhone,
        ]);

        $otp = OtpVerification::where('phone', $normalizedPhone)->first();
        $this->assertNotNull($otp);
        $this->assertNotNull($otp->request_id);
        $this->assertNotNull($otp->code_hash);
        $this->assertEquals(0, $otp->attempts);
        $this->assertEquals(3, $otp->max_attempts);
        $this->assertTrue($otp->expires_at->isFuture());
        $this->assertTrue($otp->expires_at->diffInMinutes(Carbon::now()) <= 5);
        $this->assertNull($otp->verified_at);

        // Verify Melipayamak was called (check if any request matches the pattern)
        Http::assertSent(function ($request) use ($normalizedPhone) {
            $url = $request->url();
            $body = $request->body();
            // Check if URL contains melipayamak domain and body contains the phone
            return str_contains($url, 'rest.payamak-panel.com') &&
                   str_contains($body, $normalizedPhone);
        });

        // Verify no error logs
        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_rejects_invalid_phone_format(): void
    {
        $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);

        // Verify no OTP created
        $this->assertDatabaseMissing('otp_verifications', [
            'phone' => 'invalid',
        ]);
    }

    /** @test */
    public function it_enforces_rate_limiting_on_otp_requests(): void
    {
        $phone = '+989373264601';
        $phoneHash = hash('sha256', $phone . config('app.key'));
        $rateLimitKey = "otp_request:{$phoneHash}";

        // Make 3 requests (should succeed)
        for ($i = 0; $i < 3; $i++) {
            $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
                'phone' => $phone,
            ]);
            $response->assertStatus(200);
        }

        // 4th request should be rate limited
        $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => $phone,
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonStructure([
                'message',
            ]);

        // Verify rate limit message
        $this->assertStringContainsString('Too many OTP requests', $response->json('message'));

        // Allow warning logs for rate limiting (expected behavior)
        $this->allowErrorLogs(['OTP request rate limited']);
    }

    /** @test */
    public function it_prevents_multiple_active_otps_for_same_phone(): void
    {
        $phone = '+989373264601';
        
        // Create first OTP
        $firstResponse = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => $phone,
        ]);
        $firstResponse->assertStatus(200);
        $firstRequestId = $firstResponse->json('data.request_id');

        // Try to create second OTP immediately (should fail)
        $secondResponse = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => $phone,
        ]);

        $secondResponse->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'An active OTP already exists. Please wait for it to expire or use the existing one.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'request_id',
                    'expires_at',
                ],
            ]);

        // Verify first OTP still exists (phone is normalized to 09373264601)
        $normalizedPhone = '09373264601';
        $this->assertDatabaseHas('otp_verifications', [
            'phone' => $normalizedPhone,
            'request_id' => $firstRequestId,
        ]);

        // Verify only one OTP exists
        $this->assertEquals(1, OtpVerification::where('phone', $normalizedPhone)->count());
    }

    /** @test */
    public function it_allows_new_otp_after_previous_one_expires(): void
    {
        $phone = '+989373264601';
        
        // Create first OTP
        $firstResponse = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => $phone,
        ]);
        $firstResponse->assertStatus(200);
        $firstRequestId = $firstResponse->json('data.request_id');

        // Expire the first OTP
        $otp = OtpVerification::where('request_id', $firstRequestId)->first();
        $otp->update(['expires_at' => Carbon::now()->subMinute()]);

        // Now should be able to create new OTP
        $secondResponse = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => $phone,
        ]);

        $secondResponse->assertStatus(200);
        $secondRequestId = $secondResponse->json('data.request_id');

        // Verify new OTP is different
        $this->assertNotEquals($firstRequestId, $secondRequestId);

        // Verify both OTPs exist (old expired, new active)
        $this->assertDatabaseHas('otp_verifications', [
            'request_id' => $firstRequestId,
        ]);
        $this->assertDatabaseHas('otp_verifications', [
            'request_id' => $secondRequestId,
        ]);
    }

    /** @test */
    public function it_handles_melipayamak_service_failure_gracefully(): void
    {
        // Fake HTTP to return failure
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'StrRetStatus' => 'Error',
                'RetStatus' => 0,
            ], 500),
        ]);

        $phone = '+989373264601';
        
        $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => $phone,
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again later.',
            ]);

        // Verify OTP was cleaned up (deleted after SMS failure)
        $normalizedPhone = '09373264601';
        $this->assertDatabaseMissing('otp_verifications', [
            'phone' => $normalizedPhone,
        ]);

        // Allow error logs for SMS failure (expected in this test)
        $this->allowErrorLogs(['OTP SMS send failed']);
    }

    /** @test */
    public function it_normalizes_phone_number_format(): void
    {
        // Test various phone formats that should normalize to 09373264601
        $formats = [
            '09373264601',
            '9373264601',
            '989373264601',
            '+989373264601',
            '+98 937 326 4601',
        ];
        $normalizedPhone = '09373264601';
        $normalizedPhoneHash = hash('sha256', $normalizedPhone . config('app.key'));
        $rateLimitKey = "otp_request:{$normalizedPhoneHash}";

        foreach ($formats as $format) {
            // Clear rate limiter before each format test
            RateLimiter::clear($rateLimitKey);
            
            $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
                'phone' => $format,
            ]);

            $response->assertStatus(200);

            // Verify normalized phone stored (removes +98, adds 0 prefix)
            $this->assertDatabaseHas('otp_verifications', [
                'phone' => $normalizedPhone,
            ]);

            // Clean up for next iteration
            OtpVerification::where('phone', $normalizedPhone)->delete();
        }
    }

    /** @test */
    public function it_sets_otp_expiration_to_5_minutes(): void
    {
        $phone = '+989373264601';
        $normalizedPhone = '09373264601';
        $now = Carbon::now();
        Carbon::setTestNow($now);
        
        $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => $phone,
        ]);

        $response->assertStatus(200);

        $otp = OtpVerification::where('phone', $normalizedPhone)->first();
        $this->assertNotNull($otp);
        
        // Verify expiration is approximately 5 minutes from now
        $expectedExpiration = $now->copy()->addMinutes(5);
        $this->assertEqualsWithDelta(
            $expectedExpiration->timestamp,
            $otp->expires_at->timestamp,
            5 // Allow 5 second tolerance
        );
    }

    /** @test */
    public function it_generates_unique_request_ids(): void
    {
        $phone = '+989373264601';
        $normalizedPhone = '09373264601';
        $requestIds = [];

        // Create multiple OTPs (by expiring previous ones)
        for ($i = 0; $i < 3; $i++) {
            if ($i > 0) {
                // Expire previous OTP
                $previousOtp = OtpVerification::where('phone', $normalizedPhone)->first();
                if ($previousOtp) {
                    $previousOtp->update(['expires_at' => Carbon::now()->subMinute()]);
                }
            }

            $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
                'phone' => $phone,
            ]);

            $response->assertStatus(200);
            $requestId = $response->json('data.request_id');
            
            // Verify request ID is unique
            $this->assertNotContains($requestId, $requestIds);
            $this->assertTrue(\Illuminate\Support\Str::isUuid($requestId));
            
            $requestIds[] = $requestId;
        }
    }

    /** @test */
    public function it_requires_phone_parameter(): void
    {
        $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /** @test */
    public function it_validates_phone_minimum_length(): void
    {
        $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => '123', // Too short
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /** @test */
    public function it_validates_phone_maximum_length(): void
    {
        $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => str_repeat('1', 25), // Too long
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }
}

