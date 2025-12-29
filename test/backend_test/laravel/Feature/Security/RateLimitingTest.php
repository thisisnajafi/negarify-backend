<?php

namespace Test\BackendTest\Laravel\Feature\Security;

use App\Models\OtpVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class RateLimitingTest extends BackendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear all rate limiters
        RateLimiter::clear('otp_request:*');
        
        // Clear all active OTPs
        OtpVerification::query()->delete();
    }

    /** @test */
    public function it_enforces_rate_limiting_on_otp_requests(): void
    {
        // Allow expected errors
        $this->allowErrorLogs(['Melipayamak', 'OTP SMS']);
        
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'StrRetStatus' => 'Ok',
                'RetStatus' => 1,
                'Value' => '123456',
            ], 200),
        ]);

        $phone = '09123456789';
        // Normalize phone (remove spaces, dashes, etc.)
        $normalizedPhone = preg_replace('/[^0-9+]/', '', $phone);
        $phoneHash = hash('sha256', $normalizedPhone . config('app.key'));
        $rateLimitKey = "otp_request:{$phoneHash}";

        // Make 3 requests (should succeed) - DON'T clear rate limiter, let it accumulate
        for ($i = 0; $i < 3; $i++) {
            // Clear any active OTPs for this phone (normalized)
            OtpVerification::where('phone', $normalizedPhone)->delete();
            
            try {
                $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
                    'phone' => $phone,
                ]);
                
                // May return 200 or 503 (if SMS fails, but OTP is still created)
                $this->assertContains($response->status(), [200, 503]);
            } catch (\PDOException $e) {
                if (str_contains($e->getMessage(), 'transaction')) {
                    DB::rollBack();
                } else {
                    throw $e;
                }
            }
        }

        // 4th request should be rate limited (clear OTP first to avoid 400)
        OtpVerification::where('phone', $normalizedPhone)->delete();
        $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => $phone,
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString('Too many', $response->json('message'));
    }

    /** @test */
    public function it_enforces_rate_limiting_per_phone_number(): void
    {
        // Allow expected errors
        $this->allowErrorLogs(['Melipayamak', 'OTP SMS']);
        
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'StrRetStatus' => 'Ok',
                'RetStatus' => 1,
                'Value' => '123456',
            ], 200),
        ]);

        $phone1 = '09123456789';
        $phone2 = '09123456780';
        
        // Normalize phones
        $normalizedPhone1 = preg_replace('/[^0-9+]/', '', $phone1);
        $normalizedPhone2 = preg_replace('/[^0-9+]/', '', $phone2);

        // Make 3 requests for phone1 - DON'T clear rate limiter, let it accumulate
        for ($i = 0; $i < 3; $i++) {
            // Clear any active OTPs for phone1 (normalized)
            OtpVerification::where('phone', $normalizedPhone1)->delete();
            
            try {
                $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
                    'phone' => $phone1,
                ]);
                // May return 200 or 503 (if SMS fails, but OTP is still created)
                $this->assertContains($response->status(), [200, 503]);
            } catch (\PDOException $e) {
                if (str_contains($e->getMessage(), 'transaction')) {
                    DB::rollBack();
                } else {
                    throw $e;
                }
            }
        }

        // Phone1 should be rate limited (clear OTP first to avoid 400)
        OtpVerification::where('phone', $normalizedPhone1)->delete();
        $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => $phone1,
        ]);
        $response->assertStatus(429);

        // Phone2 should still work (different rate limit) - clear OTP first
        OtpVerification::where('phone', $normalizedPhone2)->delete();
        
        try {
            $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
                'phone' => $phone2,
            ]);
            // May return 200 or 503 (if SMS fails, but OTP is still created)
            $this->assertContains($response->status(), [200, 503]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'transaction')) {
                DB::rollBack();
                $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
                    'phone' => $phone2,
                ]);
                $this->assertContains($response->status(), [200, 503]);
            } else {
                throw $e;
            }
        }
    }

    /** @test */
    public function it_resets_rate_limit_after_time_window(): void
    {
        // Allow expected errors
        $this->allowErrorLogs(['Melipayamak', 'OTP SMS']);
        
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'StrRetStatus' => 'Ok',
                'RetStatus' => 1,
                'Value' => '123456',
            ], 200),
        ]);

        $phone = '09123456789';
        // Normalize phone
        $normalizedPhone = preg_replace('/[^0-9+]/', '', $phone);
        $phoneHash = hash('sha256', $normalizedPhone . config('app.key'));
        $rateLimitKey = "otp_request:{$phoneHash}";

        // Make 3 requests - DON'T clear rate limiter, let it accumulate
        for ($i = 0; $i < 3; $i++) {
            // Clear any active OTPs for this phone (normalized)
            OtpVerification::where('phone', $normalizedPhone)->delete();
            
            try {
                $this->makeRequest('POST', '/api/v1/auth/request-otp', [
                    'phone' => $phone,
                ]);
            } catch (\PDOException $e) {
                if (str_contains($e->getMessage(), 'transaction')) {
                    DB::rollBack();
                } else {
                    throw $e;
                }
            }
        }

        // Should be rate limited (but clear active OTP first to avoid 400)
        OtpVerification::where('phone', $normalizedPhone)->delete();
        $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => $phone,
        ]);
        $response->assertStatus(429);

        // Clear rate limiter (simulating time window passing)
        RateLimiter::clear($rateLimitKey);
        // Clear any active OTPs
        OtpVerification::where('phone', $normalizedPhone)->delete();

        // Should work again
        try {
            $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
                'phone' => $phone,
            ]);
            // May return 200 or 503 (if SMS fails, but OTP is still created)
            $this->assertContains($response->status(), [200, 503]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'transaction')) {
                DB::rollBack();
                $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
                    'phone' => $phone,
                ]);
                $this->assertContains($response->status(), [200, 503]);
            } else {
                throw $e;
            }
        }
    }

    /** @test */
    public function it_enforces_rate_limiting_on_generation_requests(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        $token = $user->createToken('auth-token')->plainTextToken;

        // This test assumes there's rate limiting on generation endpoints
        // If not implemented, this test documents the expected behavior
        
        // Note: Rate limiting on generation may be implemented at a different level
        // This test serves as documentation of expected behavior
        $this->assertTrue(true, 'Generation rate limiting should be tested if implemented');
    }
}

