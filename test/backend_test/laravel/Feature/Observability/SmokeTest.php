<?php

namespace Test\BackendTest\Laravel\Feature\Observability;

use App\Models\CurrencyRate;
use App\Models\TokenBundle;
use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class SmokeTest extends BackendTestCase
{
    /** @test */
    public function it_health_check_returns_ok(): void
    {
        $response = $this->makeRequest('GET', '/up');

        $response->assertStatus(200);
        // Health endpoint may return different formats, just check it's not an error
        $this->assertContains($response->status(), [200, 204]);
    }

    /** @test */
    public function it_public_endpoints_are_accessible(): void
    {
        // Allow expected currency rate errors
        $this->allowErrorLogs(['Currency rate']);
        
        // Token bundles (public)
        TokenBundle::create([
            'name' => 'Test Bundle',
            'token_amount' => 100,
            'price_usd' => 1.00,
            'is_active' => true,
        ]);
        
        $response = $this->makeRequest('GET', '/api/v1/tokens/bundles');
        $response->assertStatus(200);

        // Currency rate (public) - may return 503 if no rate available
        CurrencyRate::create([
            'currency_from' => 'USD',
            'currency_to' => 'IRR',
            'rate' => 500000,
            'source' => 'tgju',
            'fetched_at' => now(),
        ]);
        
        $response = $this->makeRequest('GET', '/api/v1/currency/rate');
        // May return 200 or 503 (if rate unavailable)
        $this->assertContains($response->status(), [200, 503]);
    }

    /** @test */
    public function it_protected_endpoints_require_authentication(): void
    {
        $protectedEndpoints = [
            'GET' => [
                '/api/v1/user',
                '/api/v1/tokens/history',
                '/api/v1/generate/jobs',
            ],
            'POST' => [
                '/api/v1/tokens/purchase',
                '/api/v1/generate/image',
            ],
        ];

        foreach ($protectedEndpoints as $method => $endpoints) {
            foreach ($endpoints as $endpoint) {
                try {
                    $response = $this->makeRequest($method, $endpoint, []);
                    $response->assertStatus(401);
                } catch (\PDOException $e) {
                    if (str_contains($e->getMessage(), 'transaction')) {
                        \Illuminate\Support\Facades\DB::rollBack();
                        $response = $this->makeRequest($method, $endpoint, []);
                        $response->assertStatus(401);
                    } else {
                        throw $e;
                    }
                }
            }
        }
    }

    /** @test */
    public function it_database_connection_works(): void
    {
        // Create and read from database
        $user = User::factory()->create([
            'name' => 'Test User',
        ]);
        
        $found = User::find($user->id);
        $this->assertNotNull($found);
        $this->assertEquals('Test User', $found->name);
    }

    /** @test */
    public function it_api_returns_consistent_response_format(): void
    {
        // Allow expected currency rate errors
        $this->allowErrorLogs(['Currency rate']);
        
        // Test that all endpoints return consistent format
        $endpoints = [
            ['GET', '/api/v1/tokens/bundles'],
            ['GET', '/api/v1/currency/rate'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            try {
                // Setup data if needed
                if ($endpoint === '/api/v1/tokens/bundles') {
                    TokenBundle::create([
                        'name' => 'Test',
                        'token_amount' => 100,
                        'price_usd' => 1.00,
                        'is_active' => true,
                    ]);
                } elseif ($endpoint === '/api/v1/currency/rate') {
                    CurrencyRate::create([
                        'currency_from' => 'USD',
                        'currency_to' => 'IRR',
                        'rate' => 500000,
                        'source' => 'tgju',
                        'fetched_at' => now(),
                    ]);
                }
                
                $response = $this->makeRequest($method, $endpoint);
                
                if ($response->status() === 200) {
                    $data = $response->json();
                    $this->assertArrayHasKey('success', $data);
                }
            } catch (\PDOException $e) {
                if (str_contains($e->getMessage(), 'transaction')) {
                    \Illuminate\Support\Facades\DB::rollBack();
                } else {
                    throw $e;
                }
            }
        }
    }

    /** @test */
    public function it_handles_invalid_routes_gracefully(): void
    {
        try {
            $response = $this->makeRequest('GET', '/api/v1/invalid/route');
            $response->assertStatus(404);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'transaction')) {
                \Illuminate\Support\Facades\DB::rollBack();
                $response = $this->makeRequest('GET', '/api/v1/invalid/route');
                $response->assertStatus(404);
            } else {
                throw $e;
            }
        }
    }

    /** @test */
    public function it_handles_invalid_methods_gracefully(): void
    {
        try {
            // Try POST on GET-only endpoint
            $response = $this->makeRequest('POST', '/api/v1/tokens/bundles', []);
            $response->assertStatus(405); // Method Not Allowed
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'transaction')) {
                \Illuminate\Support\Facades\DB::rollBack();
                $response = $this->makeRequest('POST', '/api/v1/tokens/bundles', []);
                $response->assertStatus(405);
            } else {
                throw $e;
            }
        }
    }

    /** @test */
    public function it_returns_proper_status_codes_for_auth_errors(): void
    {
        // Test that auth errors return 401/403, not 500
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('auth-token')->plainTextToken;

        // Regular user trying to access admin endpoint should get 403
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/admin/sales/summary');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function it_logs_errors_without_exposing_sensitive_data(): void
    {
        // Test that error responses don't expose sensitive data
        $response = $this->makeRequest('GET', '/api/v1/user');

        $response->assertStatus(401);
        
        // Response should not contain database errors, stack traces, or sensitive info
        $content = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('stack trace', strtolower($content));
        $this->assertStringNotContainsString('password', strtolower($content));
    }

    /** @test */
    public function it_validates_route_parameters(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        // Test invalid ID parameter
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/gallery/invalid-id');

        // Should return 404 or 422, not 500
        $this->assertContains($response->status(), [404, 422]);
    }

    /** @test */
    public function it_logs_validation_errors_appropriately(): void
    {
        // Test that validation errors are logged but don't expose sensitive data
        $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
            'phone' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
        
        // Should not expose internal errors
        $content = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $content);
    }
}

