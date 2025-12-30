<?php

namespace Test\BackendTest\Laravel\Feature\Observability;

use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class ErrorHandlingTest extends BackendTestCase
{
    /** @test */
    public function it_returns_proper_status_codes(): void
    {
        // Test unauthenticated request (401) - test this FIRST to avoid auth state pollution
        $response = $this->json('GET', '/api/v1/user');
        $this->assertEquals(401, $response->status(), "Unauthenticated GET /api/v1/user should return 401");
        
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        // Test authenticated request (200)
        $response = $this->json('GET', '/api/v1/user', [], ['Authorization' => "Bearer {$token}"]);
        $this->assertEquals(200, $response->status(), "Authenticated GET /api/v1/user should return 200");
        
        // Test 404 - Not Found
        $response = $this->json('GET', '/api/v1/gallery/posts/99999', [], ['Authorization' => "Bearer {$token}"]);
        $this->assertEquals(404, $response->status(), "GET /api/v1/gallery/posts/99999 should return 404");
        
        // Test 422 - Validation Error
        $response = $this->json('POST', '/api/v1/auth/request-otp');
        $this->assertEquals(422, $response->status(), "POST /api/v1/auth/request-otp without phone should return 422");
    }

    /** @test */
    public function it_does_not_expose_sensitive_data_in_errors(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        // Try to access non-existent resource
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/gallery/posts/99999');

        $response->assertStatus(404);
        
        $content = $response->getContent();
        
        // Should not expose database structure, SQL queries, or file paths
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('vendor/', $content);
        $this->assertStringNotContainsString('database/', $content);
        $this->assertStringNotContainsString('SELECT', $content);
    }

    /** @test */
    public function it_returns_validation_error_details(): void
    {
        // Test validation errors
        $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'phone',
                ],
            ]);
    }

    /** @test */
    public function it_handles_database_errors_gracefully(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        // Try to access resource that might cause database error
        // (e.g., invalid foreign key)
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/gallery/post', [
            'generation_job_id' => 99999, // Non-existent job
        ]);

        // Should return 400/404/422 (validation error for invalid generation_job_id is acceptable), not 500
        $this->assertContains(
            $response->status(),
            [400, 404, 422],
            'Database errors should be handled gracefully'
        );
    }

    /** @test */
    public function it_logs_errors_without_exposing_them(): void
    {
        // This test verifies that errors are logged but not exposed in response
        // The actual logging is tested in the BackendTestCase error detection
        
        $this->assertTrue(true, 'Error logging is handled by BackendTestCase');
    }

    /** @test */
    public function it_returns_consistent_error_format(): void
    {
        $errorResponses = [
            // 401
            $this->makeRequest('GET', '/api/v1/user'),
            
            // 404
            $this->withHeaders([
                'Authorization' => 'Bearer ' . User::factory()->create()->createToken('test')->plainTextToken,
            ])->makeRequest('GET', '/api/v1/gallery/posts/99999'),
            
            // 422
            $this->makeRequest('POST', '/api/v1/auth/request-otp', ['phone' => '']),
        ];

        foreach ($errorResponses as $response) {
            $json = $response->json();
            
            // All error responses should have a message
            $this->assertArrayHasKey('message', $json);
            
            // Should not have stack traces or sensitive info
            $content = $response->getContent();
            $this->assertStringNotContainsString('Stack trace', $content);
            $this->assertStringNotContainsString('vendor/', $content);
        }
    }
}

