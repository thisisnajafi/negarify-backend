<?php

namespace Test\BackendTest\Laravel\Feature\Auth;

use App\Models\OtpVerification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;
use Carbon\Carbon;

class OtpResendTest extends BackendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        RateLimiter::clear('otp_request:*');
        RateLimiter::clear('otp_resend:*');
        
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'StrRetStatus' => 'Ok',
                'RetStatus' => 1,
            ], 200),
        ]);
    }

    /** @test */
    public function it_resends_otp_successfully(): void
    {
        $phone = '+989123456789';
        
        // Create initial OTP
        $firstResult = OtpVerification::generate($phone);
        $firstOtp = $firstResult['otp'];
        $firstRequestId = $firstOtp->request_id;
        
        // Resend OTP
        $response = $this->makeRequest('POST', '/api/v1/auth/resend-otp', [
            'request_id' => $firstRequestId,
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
                'message' => 'OTP resent successfully',
            ]);

        $newRequestId = $response->json('data.request_id');
        
        // Verify new request ID is different
        $this->assertNotEquals($firstRequestId, $newRequestId);
        
        // Verify old OTP deleted
        $this->assertDatabaseMissing('otp_verifications', [
            'request_id' => $firstRequestId,
        ]);
        
        // Verify new OTP exists
        $this->assertDatabaseHas('otp_verifications', [
            'request_id' => $newRequestId,
            'phone' => $phone,
        ]);

        // Verify Melipayamak called for new OTP
        Http::assertSentCount(2); // Initial + resend

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_rejects_resend_for_invalid_request_id(): void
    {
        $response = $this->makeRequest('POST', '/api/v1/auth/resend-otp', [
            'request_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid request ID',
            ]);
    }

    /** @test */
    public function it_rejects_resend_for_already_verified_otp(): void
    {
        $phone = '+989123456789';
        
        // Create and verify OTP
        $result = OtpVerification::generate($phone);
        $otp = $result['otp'];
        $code = $result['code'];
        $otp->verify($code);
        
        // Try to resend
        $response = $this->makeRequest('POST', '/api/v1/auth/resend-otp', [
            'request_id' => $otp->request_id,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'OTP has already been verified',
            ]);
    }

    /** @test */
    public function it_enforces_resend_rate_limiting(): void
    {
        $phone = '+989123456789';
        
        // Create OTP
        $result = OtpVerification::generate($phone);
        $otp = $result['otp'];
        
        // Make 2 resend requests (should succeed)
        for ($i = 0; $i < 2; $i++) {
            $response = $this->makeRequest('POST', '/api/v1/auth/resend-otp', [
                'request_id' => $otp->request_id,
            ]);
            $response->assertStatus(200);
            
            // Get new request_id for next iteration
            $newOtp = OtpVerification::where('phone', $phone)->first();
            $otp = $newOtp;
        }
        
        // 3rd resend should be rate limited
        $response = $this->makeRequest('POST', '/api/v1/auth/resend-otp', [
            'request_id' => $otp->request_id,
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString('Too many resend attempts', $response->json('message'));

        $this->allowErrorLogs(['OTP resend rate limited']);
    }

    /** @test */
    public function it_validates_request_id_format(): void
    {
        $response = $this->makeRequest('POST', '/api/v1/auth/resend-otp', [
            'request_id' => 'invalid-uuid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['request_id']);
    }

    /** @test */
    public function it_handles_melipayamak_failure_on_resend(): void
    {
        // Fake HTTP to return failure
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'StrRetStatus' => 'Error',
                'RetStatus' => 0,
            ], 500),
        ]);

        $phone = '+989123456789';
        $result = OtpVerification::generate($phone);
        $otp = $result['otp'];
        
        $response = $this->makeRequest('POST', '/api/v1/auth/resend-otp', [
            'request_id' => $otp->request_id,
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to resend OTP. Please try again later.',
            ]);

        // Verify new OTP was cleaned up
        $this->assertDatabaseMissing('otp_verifications', [
            'phone' => $phone,
            'request_id' => '!=', $otp->request_id,
        ]);

        $this->allowErrorLogs(['OTP resend SMS send failed']);
    }
}

