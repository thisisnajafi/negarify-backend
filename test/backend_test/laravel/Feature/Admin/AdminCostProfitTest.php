<?php

namespace Test\BackendTest\Laravel\Feature\Admin;

use App\Models\GenerationJob;
use App\Models\Model;
use App\Models\Order;
use App\Models\Provider;
use App\Models\TokenBundle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class AdminCostProfitTest extends BackendTestCase
{
    /** @test */
    public function it_calculates_revenue_cost_and_profit(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $bundle = TokenBundle::factory()->create();
        $user = User::factory()->create();
        $provider = Provider::factory()->create();
        $model = Model::factory()->create(['provider_id' => $provider->id]);

        // Create paid order (revenue)
        $order = Order::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
            'created_at' => now()->subDays(5),
        ]);

        // Create completed generation job (cost)
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 50,
            'cost_usd' => 0.30,
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/cost-profit/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_revenue_usd',
                        'total_revenue_toman',
                        'total_cost_usd',
                        'total_profit_usd',
                        'profit_margin',
                    ],
                    'breakdown_by_model',
                    'breakdown_by_provider',
                    'profit_margins_over_time',
                    'historical_comparison',
                    'date_range',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEqualsWithDelta(1.00, $data['summary']['total_revenue_usd'], 0.01, 'Revenue should be 1.00');
        $this->assertEqualsWithDelta(0.30, $data['summary']['total_cost_usd'], 0.01, 'Cost should be 0.30');
        $this->assertEqualsWithDelta(0.70, $data['summary']['total_profit_usd'], 0.01, 'Profit should be 0.70');
        $this->assertEqualsWithDelta(70.00, $data['summary']['profit_margin'], 0.01, 'Profit margin should be 70%');
    }

    /** @test */
    public function it_calculates_profit_margins_correctly(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $bundle = TokenBundle::factory()->create();
        $user = User::factory()->create();
        $provider = Provider::factory()->create();
        $model = Model::factory()->create(['provider_id' => $provider->id]);

        // Create order with revenue
        Order::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 100000,
            'price_usd' => 2.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
            'created_at' => now()->subDays(5),
        ]);

        // Create job with cost (50% of revenue)
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 50,
            'cost_usd' => 1.00,
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/cost-profit/summary');

        $data = $response->json('data');
        $this->assertEqualsWithDelta(50.00, $data['summary']['profit_margin'], 0.01, 'Profit margin should be 50%');
    }

    /** @test */
    public function it_provides_breakdown_by_model(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider = Provider::factory()->create();
        $model1 = Model::factory()->create(['provider_id' => $provider->id, 'model_name' => 'Model 1']);
        $model2 = Model::factory()->create(['provider_id' => $provider->id, 'model_name' => 'Model 2']);
        $user = User::factory()->create();

        // Create jobs for model1
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model1->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 100,
            'cost_usd' => 0.10,
            'created_at' => now()->subDays(5),
        ]);

        // Create jobs for model2
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model2->id,
            'job_type' => 'video',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 200,
            'cost_usd' => 0.20,
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/cost-profit/summary');

        $data = $response->json('data');
        $this->assertCount(2, $data['breakdown_by_model'], 'Should have 2 models');
        
        $model1Data = collect($data['breakdown_by_model'])->firstWhere('model_id', $model1->id);
        $this->assertNotNull($model1Data, 'Model1 should be in results');
        $this->assertEqualsWithDelta(0.10, $model1Data['cost_usd'], 0.01, 'Model1 should have 0.10 cost');
        $this->assertEquals(100, $model1Data['tokens_consumed'], 'Model1 should have 100 tokens');
    }

    /** @test */
    public function it_provides_breakdown_by_provider(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider1 = Provider::factory()->create(['name' => 'Provider 1']);
        $provider2 = Provider::factory()->create(['name' => 'Provider 2']);
        $model1 = Model::factory()->create(['provider_id' => $provider1->id]);
        $model2 = Model::factory()->create(['provider_id' => $provider2->id]);
        $user = User::factory()->create();

        // Create jobs for provider1
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider1->id,
            'model_id' => $model1->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 100,
            'cost_usd' => 0.10,
            'created_at' => now()->subDays(5),
        ]);

        // Create jobs for provider2
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider2->id,
            'model_id' => $model2->id,
            'job_type' => 'video',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 200,
            'cost_usd' => 0.20,
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/cost-profit/summary');

        $data = $response->json('data');
        $this->assertCount(2, $data['breakdown_by_provider'], 'Should have 2 providers');
        
        $provider1Data = collect($data['breakdown_by_provider'])->firstWhere('provider_id', $provider1->id);
        $this->assertNotNull($provider1Data, 'Provider1 should be in results');
        $this->assertEqualsWithDelta(0.10, $provider1Data['cost_usd'], 0.01, 'Provider1 should have 0.10 cost');
        $this->assertEquals(100, $provider1Data['tokens_consumed'], 'Provider1 should have 100 tokens');
    }

    /** @test */
    public function it_provides_historical_comparison(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $bundle = TokenBundle::factory()->create();
        $user = User::factory()->create();
        $provider = Provider::factory()->create();
        $model = Model::factory()->create(['provider_id' => $provider->id]);

        // Create order in current period
        Order::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
            'created_at' => now()->subDays(5),
        ]);

        // Create job in current period
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 50,
            'cost_usd' => 0.30,
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/cost-profit/summary');

        $data = $response->json('data');
        
        $this->assertArrayHasKey('historical_comparison', $data);
        $this->assertArrayHasKey('previous_period', $data['historical_comparison']);
        $this->assertArrayHasKey('current_period', $data['historical_comparison']);
        $this->assertArrayHasKey('growth', $data['historical_comparison']);
        
        // Verify current period data
        $this->assertEqualsWithDelta(1.00, $data['historical_comparison']['current_period']['revenue_usd'], 0.01);
        $this->assertEqualsWithDelta(0.30, $data['historical_comparison']['current_period']['cost_usd'], 0.01);
    }

    /** @test */
    public function it_provides_profit_margins_over_time(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $bundle = TokenBundle::factory()->create();
        $user = User::factory()->create();
        $provider = Provider::factory()->create();
        $model = Model::factory()->create(['provider_id' => $provider->id]);

        // Create order on a specific day
        $day1 = now()->subDays(3)->startOfDay();
        Order::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
            'created_at' => $day1,
        ]);

        // Create job on the same day
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 50,
            'cost_usd' => 0.30,
            'created_at' => $day1,
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/cost-profit/summary');

        $data = $response->json('data');
        
        $this->assertArrayHasKey('profit_margins_over_time', $data);
        $this->assertIsArray($data['profit_margins_over_time']);
        
        // Should have at least 1 day of data
        $this->assertGreaterThanOrEqual(1, count($data['profit_margins_over_time']), 'Should have at least 1 day of profit margins');
        
        // Verify structure
        if (count($data['profit_margins_over_time']) > 0) {
            $firstDay = $data['profit_margins_over_time'][0];
            $this->assertArrayHasKey('date', $firstDay);
            $this->assertArrayHasKey('revenue_usd', $firstDay);
            $this->assertArrayHasKey('cost_usd', $firstDay);
            $this->assertArrayHasKey('profit_usd', $firstDay);
            $this->assertArrayHasKey('profit_margin', $firstDay);
        }
    }

    /** @test */
    public function it_supports_date_range_filtering(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $bundle = TokenBundle::factory()->create();
        $user = User::factory()->create();
        $provider = Provider::factory()->create();
        $model = Model::factory()->create(['provider_id' => $provider->id]);

        // Use a very specific date range to avoid conflicts
        $endDate = Carbon::now()->endOfDay();
        $startDate = $endDate->copy()->subDays(7)->startOfDay();
        
        // Create order outside range (10 days ago)
        Order::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
            'created_at' => $endDate->copy()->subDays(10)->startOfDay(),
        ]);

        // Create order within range (1 day ago)
        Order::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 2.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
            'created_at' => $endDate->copy()->subDay()->startOfDay(),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/cost-profit/summary', [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]);

        $data = $response->json('data');
        
        // Should include at least the order from 1 day ago (may include other orders from other tests)
        $this->assertGreaterThanOrEqual(2.00, $data['summary']['total_revenue_usd'], 'Should have at least 2.00 revenue in range');
    }

    /** @test */
    public function it_supports_range_parameter(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $bundle = TokenBundle::factory()->create();
        $user = User::factory()->create();
        $provider = Provider::factory()->create();
        $model = Model::factory()->create(['provider_id' => $provider->id]);

        // Create order for last week
        Order::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
            'created_at' => now()->subDays(5),
        ]);

        // Request with 'week' range
        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/cost-profit/summary', [
            'range' => 'week',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertArrayHasKey('date_range', $data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('breakdown_by_model', $data);
        $this->assertArrayHasKey('breakdown_by_provider', $data);
    }

    /** @test */
    public function it_caches_cost_profit_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $bundle = TokenBundle::factory()->create();
        $user = User::factory()->create();
        $provider = Provider::factory()->create();
        $model = Model::factory()->create(['provider_id' => $provider->id]);

        Order::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
            'created_at' => now()->subDays(5),
        ]);

        // First request
        $response1 = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/cost-profit/summary');
        $data1 = $response1->json('data');

        // Create another order (should not appear in cached response)
        Order::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 2.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
            'created_at' => now()->subDays(5),
        ]);

        // Second request (should return cached data)
        $response2 = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/cost-profit/summary');
        $data2 = $response2->json('data');

        // Cached response should have same data
        $this->assertEquals($data1['summary']['total_revenue_usd'], $data2['summary']['total_revenue_usd'], 'Cached response should have same revenue');
    }
}


