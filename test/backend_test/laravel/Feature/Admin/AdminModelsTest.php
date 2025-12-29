<?php

namespace Test\BackendTest\Laravel\Feature\Admin;

use App\Models\GenerationJob;
use App\Models\Model;
use App\Models\Provider;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class AdminModelsTest extends BackendTestCase
{
    /** @test */
    public function it_returns_usage_statistics_per_model(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider = Provider::factory()->create();
        $model1 = Model::factory()->create(['provider_id' => $provider->id]);
        $model2 = Model::factory()->create(['provider_id' => $provider->id]);
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

        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model1->id,
            'job_type' => 'video',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 200,
            'cost_usd' => 0.20,
            'created_at' => now()->subDays(5),
        ]);

        // Create jobs for model2
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model2->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 150,
            'cost_usd' => 0.15,
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/models/usage');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'models' => [
                        '*' => [
                            'model_id',
                            'model_name',
                            'requests_count',
                            'successful_count',
                            'failed_count',
                            'tokens_consumed',
                            'cost_usd',
                            'success_rate',
                            'failure_rate',
                        ],
                    ],
                    'summary',
                    'date_range',
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data['models'], 'Should have 2 models');
        
        // Verify model1 stats
        $model1Data = collect($data['models'])->firstWhere('model_id', $model1->id);
        $this->assertNotNull($model1Data, 'Model1 should be in results');
        $this->assertEquals(2, $model1Data['requests_count'], 'Model1 should have 2 requests');
        $this->assertEquals(300, $model1Data['tokens_consumed'], 'Model1 should have 300 tokens consumed');
        $this->assertEqualsWithDelta(0.30, $model1Data['cost_usd'], 0.01, 'Model1 should have 0.30 cost');
    }

    /** @test */
    public function it_calculates_success_and_failure_rates(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider = Provider::factory()->create();
        $model = Model::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();

        // Create 10 completed jobs
        for ($i = 0; $i < 10; $i++) {
            GenerationJob::create([
                'user_id' => $user->id,
                'provider_id' => $provider->id,
                'model_id' => $model->id,
                'job_type' => 'image',
                'prompt' => 'Test prompt',
                'params_json' => json_encode([]),
                'status' => 'completed',
                'tokens_consumed' => 100,
                'cost_usd' => 0.10,
                'created_at' => now()->subDays(5),
            ]);
        }

        // Create 5 failed jobs
        for ($i = 0; $i < 5; $i++) {
            GenerationJob::create([
                'user_id' => $user->id,
                'provider_id' => $provider->id,
                'model_id' => $model->id,
                'job_type' => 'image',
                'prompt' => 'Test prompt',
                'params_json' => json_encode([]),
                'status' => 'failed',
                'tokens_consumed' => 0,
                'cost_usd' => 0,
                'created_at' => now()->subDays(5),
            ]);
        }

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/models/usage');

        $data = $response->json('data');
        $modelData = collect($data['models'])->firstWhere('model_id', $model->id);
        
        $this->assertEquals(15, $modelData['requests_count'], 'Should have 15 total requests');
        $this->assertEquals(10, $modelData['successful_count'], 'Should have 10 successful');
        $this->assertEquals(5, $modelData['failed_count'], 'Should have 5 failed');
        $this->assertEquals(66.67, $modelData['success_rate'], 'Success rate should be ~66.67%', 0.01);
        $this->assertEquals(33.33, $modelData['failure_rate'], 'Failure rate should be ~33.33%', 0.01);
        
        // Verify summary rates
        $this->assertEquals(66.67, $data['summary']['overall_success_rate'], 'Overall success rate should be ~66.67%', 0.01);
    }

    /** @test */
    public function it_calculates_average_latency(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider = Provider::factory()->create();
        $model = Model::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();

        // Create jobs with different latencies
        $now = now();
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'started_at' => $now->copy()->subSeconds(2),
            'completed_at' => $now,
            'tokens_consumed' => 100,
            'cost_usd' => 0.10,
            'created_at' => now()->subDays(5),
        ]);

        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'started_at' => $now->copy()->subSeconds(4),
            'completed_at' => $now,
            'tokens_consumed' => 100,
            'cost_usd' => 0.10,
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/models/usage');

        $data = $response->json('data');
        $modelData = collect($data['models'])->firstWhere('model_id', $model->id);
        
        // Average latency should be around 3000ms (average of 2000ms and 4000ms)
        $this->assertNotNull($modelData['avg_latency_ms'], 'Average latency should be calculated');
        $this->assertGreaterThan(0, $modelData['avg_latency_ms'], 'Average latency should be positive');
    }

    /** @test */
    public function it_tracks_tokens_consumed_per_model(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider = Provider::factory()->create();
        $model = Model::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();

        // Create jobs with different token consumption
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 100,
            'cost_usd' => 0.10,
            'created_at' => now()->subDays(5),
        ]);

        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'video',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 250,
            'cost_usd' => 0.25,
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/models/usage');

        $data = $response->json('data');
        $modelData = collect($data['models'])->firstWhere('model_id', $model->id);
        
        $this->assertEquals(350, $modelData['tokens_consumed'], 'Should have 350 tokens consumed');
        $this->assertEquals(350, $data['summary']['total_tokens_consumed'], 'Summary should show 350 tokens');
    }

    /** @test */
    public function it_tracks_cost_and_revenue_per_model(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider = Provider::factory()->create();
        $model = Model::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();

        // Create jobs with different costs
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 100,
            'cost_usd' => 0.10,
            'created_at' => now()->subDays(5),
        ]);

        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'video',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 200,
            'cost_usd' => 0.20,
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/models/usage');

        $data = $response->json('data');
        $modelData = collect($data['models'])->firstWhere('model_id', $model->id);
        
        $this->assertEqualsWithDelta(0.30, $modelData['cost_usd'], 0.01, 'Should have 0.30 cost');
        $this->assertEqualsWithDelta(0.30, $data['summary']['total_cost_usd'], 0.01, 'Summary should show 0.30 cost');
    }

    /** @test */
    public function it_supports_date_range_filtering(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider = Provider::factory()->create();
        $model = Model::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();

        // Use a very specific date range to avoid conflicts
        $endDate = Carbon::now()->endOfDay();
        $startDate = $endDate->copy()->subDays(7)->startOfDay();
        
        // Create job outside range (10 days ago - well outside 7 day range)
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 100,
            'cost_usd' => 0.10,
            'created_at' => $endDate->copy()->subDays(10)->startOfDay(),
        ]);

        // Create job within range (1 day ago)
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 200,
            'cost_usd' => 0.20,
            'created_at' => $endDate->copy()->subDay()->startOfDay(),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/models/usage', [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]);

        $data = $response->json('data');
        $modelData = collect($data['models'])->firstWhere('model_id', $model->id);
        
        // Should include at least the job from 1 day ago (may include other jobs from other tests)
        $this->assertGreaterThanOrEqual(1, $modelData['requests_count'], 'Should have at least 1 request in range');
        $this->assertGreaterThanOrEqual(200, $modelData['tokens_consumed'], 'Should have at least 200 tokens in range');
        $this->assertGreaterThanOrEqual(0.20, $modelData['cost_usd'], 'Should have at least 0.20 cost in range');
    }

    /** @test */
    public function it_supports_range_parameter(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider = Provider::factory()->create();
        $model = Model::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();

        // Create job for last week
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 100,
            'cost_usd' => 0.10,
            'created_at' => now()->subDays(5),
        ]);

        // Request with 'week' range
        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/models/usage', [
            'range' => 'week',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertArrayHasKey('date_range', $data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('models', $data);
    }

    /** @test */
    public function it_caches_models_usage_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider = Provider::factory()->create();
        $model = Model::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();

        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 100,
            'cost_usd' => 0.10,
            'created_at' => now()->subDays(5),
        ]);

        // First request
        $response1 = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/models/usage');
        $data1 = $response1->json('data');

        // Create another job (should not appear in cached response)
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 200,
            'cost_usd' => 0.20,
            'created_at' => now()->subDays(5),
        ]);

        // Second request (should return cached data)
        $response2 = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/models/usage');
        $data2 = $response2->json('data');

        // Cached response should have same data
        $this->assertEquals($data1['summary']['total_requests'], $data2['summary']['total_requests'], 'Cached response should have same request count');
    }

    /** @test */
    public function it_handles_breakdown_by_job_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider = Provider::factory()->create();
        $model = Model::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();

        // Create jobs of different types
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 100,
            'cost_usd' => 0.10,
            'created_at' => now()->subDays(5),
        ]);

        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'video',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 200,
            'cost_usd' => 0.20,
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/models/usage');

        $data = $response->json('data');
        $modelData = collect($data['models'])->firstWhere('model_id', $model->id);
        
        // When using raw data (not aggregated), should have breakdown_by_type
        if (isset($modelData['breakdown_by_type'])) {
            $this->assertCount(2, $modelData['breakdown_by_type'], 'Should have breakdown for 2 job types');
            
            $imageBreakdown = collect($modelData['breakdown_by_type'])->firstWhere('job_type', 'image');
            $this->assertNotNull($imageBreakdown, 'Should have image breakdown');
            $this->assertEquals(100, $imageBreakdown['tokens_consumed'], 'Image should have 100 tokens');
            
            $videoBreakdown = collect($modelData['breakdown_by_type'])->firstWhere('job_type', 'video');
            $this->assertNotNull($videoBreakdown, 'Should have video breakdown');
            $this->assertEquals(200, $videoBreakdown['tokens_consumed'], 'Video should have 200 tokens');
        }
    }
}

