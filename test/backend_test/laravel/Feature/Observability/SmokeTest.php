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
        $response = $this->makeRequest('GET', '/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
            ]);
    }

    /** @test */
    public function it_public_endpoints_are_accessible(): void
    {
        // Token bundles (public)
        TokenBundle::create([
            'name' => 'Test Bundle',
            'token_amount' => 100,
            'price_usd' => 1.00,
            'is_active' => true,
        ]);
        
        $response = $this->makeRequest('GET', '/api/v1/tokens/bundles');
        $response->assertStatus(200);

        // Currency rate (public)
        CurrencyRate::create([
            'currency_from' => 'USD',
            'currency_to' => 'IRR',
            'rate' => 500000,
            'source' => 'tgju',
            'fetched_at' => now(),
        ]);
        
        $response = $this->makeRequest('GET', '/api/v1/currency/rate');
        $response->assertStatus(200);
    }

    /** @test */
    public function it_protected_endpoints_require_authentication(): void
    {
        $protectedEndpoints = [
            'GET' => [
                '/api/v1/user',
                '/api/v1/tokens/history',
                '/api/v1/generation/jobs',
            ],
            'POST' => [
                '/api/v1/tokens/purchase',
                '/api/v1/generation/image',
            ],
        ];

        foreach ($protectedEndpoints as $method => $endpoints) {
            foreach ($endpoints as $endpoint) {
                $response = $this->makeRequest($method, $endpoint, []);
                $response->assertStatus(401);
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
        // Test that all endpoints return consistent format
        $endpoints = [
            ['GET', '/api/v1/tokens/bundles'],
            ['GET', '/api/v1/currency/rate'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
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
        }
    }

    /** @test */
    public function it_handles_invalid_routes_gracefully(): void
    {
        $response = $this->makeRequest('GET', '/api/v1/invalid/route');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_handles_invalid_methods_gracefully(): void
    {
        // Try POST on GET-only endpoint
        $response = $this->makeRequest('POST', '/api/v1/tokens/bundles', []);

        $response->assertStatus(405); // Method Not Allowed
    }
}

