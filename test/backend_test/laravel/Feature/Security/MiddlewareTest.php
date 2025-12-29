<?php

namespace Test\BackendTest\Laravel\Feature\Security;

use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class MiddlewareTest extends BackendTestCase
{
    /** @test */
    public function it_requires_authentication_for_protected_routes(): void
    {
        // Test various protected endpoints
        $protectedRoutes = [
            ['GET', '/api/v1/user'],
            ['PUT', '/api/v1/user'],
            ['POST', '/api/v1/user/avatar'],
            ['GET', '/api/v1/tokens/history'],
            ['GET', '/api/v1/tokens/balance'],
            ['POST', '/api/v1/generate/image'],
            ['GET', '/api/v1/generate/jobs'],
            ['GET', '/api/v1/gallery/my-posts'],
        ];

        foreach ($protectedRoutes as [$method, $uri]) {
            $response = $this->makeRequest($method, $uri);
            
            $response->assertStatus(401)
                ->assertJson([
                    'message' => 'Unauthenticated.',
                ]);
        }
    }

    /** @test */
    public function it_rejects_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-12345',
        ])->makeRequest('GET', '/api/v1/user');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_rejects_expired_token(): void
    {
        $user = User::factory()->create();
        
        // Create a token and manually expire it by deleting it
        $token = $user->createToken('test-token')->plainTextToken;
        
        // Delete the token (simulating expiration)
        $user->tokens()->delete();
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/user');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_rejects_missing_token(): void
    {
        $response = $this->makeRequest('GET', '/api/v1/user');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_admin_role_for_admin_endpoints(): void
    {
        $regularUser = User::factory()->create(['role' => 'user']);
        $token = $regularUser->createToken('auth-token')->plainTextToken;

        $adminRoutes = [
            ['GET', '/api/v1/admin/sales/summary'],
            ['GET', '/api/v1/admin/users/summary'],
            ['GET', '/api/v1/admin/models/usage'],
            ['GET', '/api/v1/admin/tokens/summary'],
            ['GET', '/api/v1/admin/cost-profit/summary'],
            ['GET', '/api/v1/admin/system-health'],
            ['POST', '/api/v1/admin/gallery/1/curate'],
        ];

        foreach ($adminRoutes as [$method, $uri]) {
            $response = $this->withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->makeRequest($method, $uri);

            $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Access denied. Admin privileges required.',
                ]);
        }
    }

    /** @test */
    public function it_allows_admin_access_to_admin_endpoints(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/admin/system-health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function it_rejects_regular_user_from_admin_endpoints(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/admin/sales/summary');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Access denied. Admin privileges required.',
            ]);
    }

    /** @test */
    public function it_allows_unauthenticated_access_to_public_endpoints(): void
    {
        $publicRoutes = [
            ['POST', '/api/v1/auth/request-otp', ['phone' => '09123456789']],
            ['GET', '/api/v1/tokens/bundles'],
            ['GET', '/api/v1/currency/rate'],
        ];

        foreach ($publicRoutes as $route) {
            $method = $route[0];
            $uri = $route[1];
            $data = $route[2] ?? [];
            $response = $this->makeRequest($method, $uri, $data);
            
            // These should not return 401 (may return other status codes like 422 for validation)
            $this->assertNotEquals(401, $response->status(), "Route {$method} {$uri} should be public");
        }
    }

    /** @test */
    public function it_validates_token_format(): void
    {
        // Test various invalid token formats
        $invalidTokens = [
            'not-a-token',
            'Bearer',
            'Bearer ',
            '12345',
            '',
        ];

        foreach ($invalidTokens as $token) {
            $headers = [];
            if (!empty($token)) {
                $headers['Authorization'] = $token;
            }
            
            $response = $this->withHeaders($headers)->makeRequest('GET', '/api/v1/user');
            $response->assertStatus(401);
        }
    }

    /** @test */
    public function it_handles_malformed_authorization_header(): void
    {
        $malformedHeaders = [
            ['Authorization' => 'Basic dXNlcjpwYXNz'],
            ['Authorization' => 'Digest username="user"'],
            ['Authorization' => 'Bearer token1 token2'],
        ];

        foreach ($malformedHeaders as $headers) {
            $response = $this->withHeaders($headers)->makeRequest('GET', '/api/v1/user');
            $response->assertStatus(401);
        }
    }
}

