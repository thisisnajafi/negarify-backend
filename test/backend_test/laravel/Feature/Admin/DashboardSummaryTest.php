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
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        
        // Create test orders
        Order::create([
            'user_id' => User::factory()->create()->id,
            'token_bundle_id' => TokenBundle::factory()->create()->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
        ]);
        
        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/sales/summary', [
            'range' => 'month',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_revenue_toman',
                    'total_revenue_usd',
                    'total_orders',
                    'revenue_by_day',
                    'top_bundles',
                    'refunds',
                    'customer_ltv',
                    'date_range',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function it_returns_users_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        
        // Create test users and jobs
        $user = User::factory()->create();
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => \App\Models\Provider::factory()->create()->id,
            'model_id' => \App\Models\Model::factory()->create()->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
        ]);
        
        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/users/summary', [
            'range' => 'month',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'active_users' => [
                        'dau',
                        'wau',
                        'mau',
                    ],
                    'top_users_by_generation',
                    'top_users_by_spending',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function it_returns_tokens_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        $user = User::factory()->create();
        
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => \App\Models\Provider::factory()->create()->id,
            'model_id' => \App\Models\Model::factory()->create()->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'tokens_consumed' => 100,
        ]);
        
        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/tokens/summary', [
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
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        $user = User::factory()->create();
        
        // Create revenue
        Order::create([
            'user_id' => $user->id,
            'token_bundle_id' => TokenBundle::factory()->create()->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
        ]);
        
        // Create costs
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => \App\Models\Provider::factory()->create()->id,
            'model_id' => \App\Models\Model::factory()->create()->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'cost_usd' => 0.50,
        ]);
        
        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/cost-profit/summary', [
            'range' => 'month',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_revenue_usd',
                        'total_cost_usd',
                        'total_profit_usd',
                        'profit_margin',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function it_returns_models_usage_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        $user = User::factory()->create();
        $provider = \App\Models\Provider::factory()->create();
        $model = \App\Models\Model::factory()->create(['provider_id' => $provider->id]);
        
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
        ]);
        
        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/models/usage', [
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
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        
        User::factory()->count(5)->create();
        
        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/users/list');

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
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        
        User::factory()->create(['role' => 'user']);
        User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/users/list', [
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
            $response = $this->actingAs($user)->makeRequest('GET', $endpoint);

            $response->assertStatus(403);
        }
    }
}

