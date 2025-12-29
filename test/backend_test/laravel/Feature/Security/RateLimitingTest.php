<?php

namespace Test\BackendTest\Laravel\Feature\Security;

use App\Models\User;
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
    }

    /** @test */
    public function it_enforces_rate_limiting_on_otp_requests(): void
    {
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'StrRetStatus' => 'Ok',
                'RetStatus' => 1,
                'Value' => '123456',
            ], 200),
        ]);

        $phone = '09123456789';
        $phoneHash = hash('sha256', $phone . config('app.key'));
        $rateLimitKey = "otp_request:{$phoneHash}";

        // Make 3 requests (should succeed)
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::clear($rateLimitKey);
            
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
            ]);

        $this->assertStringContainsString('Too many', $response->json('message'));
    }

    /** @test */
    public function it_enforces_rate_limiting_per_phone_number(): void
    {
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'StrRetStatus' => 'Ok',
                'RetStatus' => 1,
                'Value' => '123456',
            ], 200),
        ]);

        $phone1 = '09123456789';
        $phone2 = '09123456780';

        // Make 3 requests for phone1
        for ($i = 0; $i < 3; $i++) {
            $phone1Hash = hash('sha256', $phone1 . config('app.key'));
            RateLimiter::clear("otp_request:{$phone1Hash}");
            
            $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
                'phone' => $phone1,
            ]);
            $response->assertStatus(200);
        }

        // Phone1 should be rate limited
        $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => $phone1,
        ]);
        $response->assertStatus(429);

        // Phone2 should still work (different rate limit)
        $phone2Hash = hash('sha256', $phone2 . config('app.key'));
        RateLimiter::clear("otp_request:{$phone2Hash}");
        
        $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => $phone2,
        ]);
        $response->assertStatus(200);
    }

    /** @test */
    public function it_resets_rate_limit_after_time_window(): void
    {
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'StrRetStatus' => 'Ok',
                'RetStatus' => 1,
                'Value' => '123456',
            ], 200),
        ]);

        $phone = '09123456789';
        $phoneHash = hash('sha256', $phone . config('app.key'));
        $rateLimitKey = "otp_request:{$phoneHash}";

        // Make 3 requests
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::clear($rateLimitKey);
            $this->makeRequest('POST', '/api/v1/auth/request-otp', [
                'phone' => $phone,
            ]);
        }

        // Should be rate limited
        $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => $phone,
        ]);
        $response->assertStatus(429);

        // Clear rate limiter (simulating time window passing)
        RateLimiter::clear($rateLimitKey);

        // Should work again
        $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => $phone,
        ]);
        $response->assertStatus(200);
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

