<?php

namespace Test\BackendTest\Laravel\Feature\Admin;

use App\Models\GenerationJob;
use App\Models\Order;
use App\Models\TokenBundle;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class DashboardSummaryTest extends BackendTestCase
{
    /** @test */
    public function it_returns_sales_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        // Create test orders
        Order::create([
            'user_id' => User::factory()->create()->id,
            'token_bundle_id' => TokenBundle::factory()->create()->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'status' => 'paid',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/admin/sales/summary', [
            'range' => 'month',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'revenue',
                    'orders',
                    'top_bundles',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function it_returns_users_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        // Create test users and jobs
        $user = User::factory()->create();
        GenerationJob::create([
            'user_id' => $user->id,
            'job_type' => 'image',
            'status' => 'completed',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/admin/users/summary', [
            'range' => 'month',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'dau',
                    'wau',
                    'mau',
                    'top_users',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function it_returns_tokens_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        GenerationJob::create([
            'job_type' => 'image',
            'status' => 'completed',
            'tokens_consumed' => 100,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/admin/tokens/summary', [
            'range' => 'month',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function it_returns_cost_profit_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        // Create revenue
        Order::create([
            'user_id' => User::factory()->create()->id,
            'token_bundle_id' => TokenBundle::factory()->create()->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'status' => 'paid',
        ]);
        
        // Create costs
        GenerationJob::create([
            'job_type' => 'image',
            'status' => 'completed',
            'cost_usd' => 0.50,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/admin/cost-profit/summary', [
            'range' => 'month',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'revenue',
                    'cost',
                    'profit',
                    'profit_margin',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function it_returns_models_usage_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        GenerationJob::create([
            'job_type' => 'image',
            'status' => 'completed',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/admin/models/usage', [
            'range' => 'month',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function it_returns_users_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        User::factory()->count(5)->create();
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/admin/users/list');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'phone',
                        'email',
                    ],
                ],
                'meta',
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function it_filters_users_list_by_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        User::factory()->create(['role' => 'user']);
        User::factory()->create(['role' => 'admin']);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/admin/users/list', [
            'role' => 'user',
        ]);

        $response->assertStatus(200);
        $users = $response->json('data');
        foreach ($users as $user) {
            $this->assertEquals('user', $user['role']);
        }
    }

    /** @test */
    public function it_rejects_admin_endpoints_for_non_admin(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $adminEndpoints = [
            'GET' => [
                '/api/v1/admin/sales/summary',
                '/api/v1/admin/users/summary',
                '/api/v1/admin/tokens/summary',
                '/api/v1/admin/cost-profit/summary',
                '/api/v1/admin/models/usage',
                '/api/v1/admin/users/list',
                '/api/v1/admin/system-health',
                '/api/v1/admin/moderation/queue',
            ],
        ];

        foreach ($adminEndpoints['GET'] as $endpoint) {
            $response = $this->withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->makeRequest('GET', $endpoint);

            $response->assertStatus(403);
        }
    }
}

