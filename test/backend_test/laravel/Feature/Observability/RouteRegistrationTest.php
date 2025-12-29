<?php

namespace Test\BackendTest\Laravel\Feature\Observability;

use Illuminate\Support\Facades\Route;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class RouteRegistrationTest extends BackendTestCase
{
    /** @test */
    public function it_registers_all_public_routes(): void
    {
        $publicRoutes = [
            'POST /api/v1/auth/request-otp',
            'POST /api/v1/auth/verify-otp',
            'POST /api/v1/auth/resend-otp',
            'GET /api/v1/tokens/bundles',
            'GET /api/v1/currency/rate',
        ];

        foreach ($publicRoutes as $route) {
            [$method, $uri] = explode(' ', $route);
            
            $routes = Route::getRoutes();
            $found = false;
            
            foreach ($routes as $registeredRoute) {
                if ($registeredRoute->methods()[0] === $method && 
                    $registeredRoute->uri() === str_replace('/api', '', $uri)) {
                    $found = true;
                    break;
                }
            }
            
            $this->assertTrue($found, "Route {$route} should be registered");
        }
    }

    /** @test */
    public function it_registers_all_protected_routes(): void
    {
        $protectedRoutes = [
            'POST /api/v1/auth/logout',
            'GET /api/v1/user',
            'PUT /api/v1/user',
            'POST /api/v1/user/avatar',
            'GET /api/v1/tokens/history',
            'GET /api/v1/tokens/balance',
            'POST /api/v1/generate/image',
            'POST /api/v1/generate/video',
            'POST /api/v1/generate/audio',
            'GET /api/v1/generate/jobs',
            'GET /api/v1/gallery/my-posts',
        ];

        foreach ($protectedRoutes as $route) {
            [$method, $uri] = explode(' ', $route);
            
            $routes = Route::getRoutes();
            $found = false;
            
            foreach ($routes as $registeredRoute) {
                if ($registeredRoute->methods()[0] === $method && 
                    str_contains($registeredRoute->uri(), str_replace('/api/v1/', '', $uri))) {
                    $found = true;
                    break;
                }
            }
            
            $this->assertTrue($found, "Route {$route} should be registered");
        }
    }

    /** @test */
    public function it_registers_all_admin_routes(): void
    {
        $adminRoutes = [
            'GET /api/v1/admin/sales/summary',
            'GET /api/v1/admin/users/summary',
            'GET /api/v1/admin/models/usage',
            'GET /api/v1/admin/tokens/summary',
            'GET /api/v1/admin/cost-profit/summary',
            'GET /api/v1/admin/system-health',
        ];

        foreach ($adminRoutes as $route) {
            [$method, $uri] = explode(' ', $route);
            
            $routes = Route::getRoutes();
            $found = false;
            
            foreach ($routes as $registeredRoute) {
                if ($registeredRoute->methods()[0] === $method && 
                    str_contains($registeredRoute->uri(), str_replace('/api/v1/', '', $uri))) {
                    $found = true;
                    break;
                }
            }
            
            $this->assertTrue($found, "Route {$route} should be registered");
        }
    }

    /** @test */
    public function it_returns_404_for_non_existent_endpoints(): void
    {
        $nonExistentRoutes = [
            ['GET', '/api/v1/nonexistent'],
            ['POST', '/api/v1/invalid/endpoint'],
            ['PUT', '/api/v1/fake/route'],
        ];

        foreach ($nonExistentRoutes as [$method, $uri]) {
            $response = $this->makeRequest($method, $uri);
            $response->assertStatus(404);
        }
    }

    /** @test */
    public function it_returns_401_for_unauthenticated_protected_routes(): void
    {
        $protectedRoutes = [
            ['GET', '/api/v1/user'],
            ['GET', '/api/v1/tokens/balance'],
            ['GET', '/api/v1/generate/jobs'],
        ];

        foreach ($protectedRoutes as [$method, $uri]) {
            $response = $this->makeRequest($method, $uri);
            
            // Should return 401, not 500 or 404
            $this->assertContains(
                $response->status(),
                [401, 403],
                "Route {$method} {$uri} should return 401/403 for unauthenticated requests, got {$response->status()}"
            );
        }
    }

    /** @test */
    public function it_returns_403_for_non_admin_on_admin_routes(): void
    {
        $user = \App\Models\User::factory()->create(['role' => 'user']);
        $token = $user->createToken('auth-token')->plainTextToken;

        $adminRoutes = [
            ['GET', '/api/v1/admin/sales/summary'],
            ['GET', '/api/v1/admin/users/summary'],
            ['GET', '/api/v1/admin/system-health'],
        ];

        foreach ($adminRoutes as [$method, $uri]) {
            $response = $this->withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->makeRequest($method, $uri);

            // Should return 403, not 500 or 401
            $this->assertEquals(
                403,
                $response->status(),
                "Route {$method} {$uri} should return 403 for non-admin users, got {$response->status()}"
            );
        }
    }

    /** @test */
    public function it_validates_route_parameters(): void
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        // Test invalid IDs
        $invalidIdRoutes = [
            ['GET', '/api/v1/generate/jobs/invalid'],
            ['GET', '/api/v1/gallery/posts/not-a-number'],
        ];

        foreach ($invalidIdRoutes as [$method, $uri]) {
            $response = $this->withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->makeRequest($method, $uri);

            // Should return 404 or 422, not 500
            $this->assertContains(
                $response->status(),
                [404, 422],
                "Route {$method} {$uri} should handle invalid parameters gracefully"
            );
        }
    }
}

