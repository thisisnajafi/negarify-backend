<?php

namespace Test\BackendTest\Laravel\Feature\Admin;

use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class AdminRbacTest extends BackendTestCase
{
    /** @test */
    public function it_requires_admin_role_for_admin_endpoints(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        
        $adminEndpoints = [
            'GET' => [
                '/api/v1/admin/sales/summary',
                '/api/v1/admin/users/summary',
                '/api/v1/admin/tokens/summary',
                '/api/v1/admin/cost-profit/summary',
                '/api/v1/admin/models/usage',
                '/api/v1/admin/users/list',
                '/api/v1/admin/system-health',
            ],
        ];

        foreach ($adminEndpoints['GET'] as $endpoint) {
            $response = $this->actingAs($admin)->makeRequest('GET', $endpoint);
            
            // Admin should be able to access (may return 200 or 404 if no data, but not 403)
            $this->assertNotEquals(403, $response->status(), "Admin should access {$endpoint}");
        }
    }

    /** @test */
    public function it_rejects_non_admin_users_with_403(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        
        $adminEndpoints = [
            'GET' => [
                '/api/v1/admin/sales/summary',
                '/api/v1/admin/users/summary',
                '/api/v1/admin/tokens/summary',
                '/api/v1/admin/cost-profit/summary',
                '/api/v1/admin/models/usage',
                '/api/v1/admin/users/list',
                '/api/v1/admin/system-health',
            ],
        ];

        foreach ($adminEndpoints['GET'] as $endpoint) {
            $response = $this->actingAs($user)->makeRequest('GET', $endpoint);
            
            $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Access denied. Admin privileges required.',
                ]);
        }
    }

    /** @test */
    public function it_rejects_unauthenticated_users_with_401(): void
    {
        $adminEndpoints = [
            'GET' => [
                '/api/v1/admin/sales/summary',
                '/api/v1/admin/users/summary',
                '/api/v1/admin/tokens/summary',
                '/api/v1/admin/cost-profit/summary',
                '/api/v1/admin/models/usage',
                '/api/v1/admin/users/list',
                '/api/v1/admin/system-health',
            ],
        ];

        foreach ($adminEndpoints['GET'] as $endpoint) {
            $response = $this->makeRequest('GET', $endpoint);
            
            $response->assertStatus(401);
        }
    }

    /** @test */
    public function it_verifies_admin_middleware_works(): void
    {
        // Test that middleware is applied by checking a protected endpoint
        $user = User::factory()->create(['role' => 'user']);
        
        $response = $this->actingAs($user)->makeRequest('GET', '/api/v1/admin/sales/summary');
        
        // Should be blocked by admin middleware, not by route not found
        $response->assertStatus(403);
        
        // Verify the response indicates admin middleware blocked it
        $response->assertJson([
            'success' => false,
            'message' => 'Access denied. Admin privileges required.',
        ]);
    }
}

