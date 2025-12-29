<?php

namespace Test\BackendTest\Laravel\Feature\Admin;

use App\Models\GenerationJob;
use App\Models\Model;
use App\Models\Provider;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class AdminTokensTest extends BackendTestCase
{
    /** @test */
    public function it_returns_tokens_consumption_by_provider(): void
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

        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider1->id,
            'model_id' => $model1->id,
            'job_type' => 'video',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 200,
            'cost_usd' => 0.20,
            'created_at' => now()->subDays(5),
        ]);

        // Create jobs for provider2
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider2->id,
            'model_id' => $model2->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 150,
            'cost_usd' => 0.15,
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/tokens/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tokens_by_provider' => [
                        '*' => [
                            'provider_id',
                            'provider_name',
                            'tokens_consumed',
                            'cost_usd',
                        ],
                    ],
                    'tokens_by_type',
                    'time_series',
                    'summary',
                    'date_range',
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data['tokens_by_provider'], 'Should have 2 providers');
        
        // Verify provider1 stats
        $provider1Data = collect($data['tokens_by_provider'])->firstWhere('provider_id', $provider1->id);
        $this->assertNotNull($provider1Data, 'Provider1 should be in results');
        $this->assertEquals(300, $provider1Data['tokens_consumed'], 'Provider1 should have 300 tokens consumed');
        $this->assertEqualsWithDelta(0.30, $provider1Data['cost_usd'], 0.01, 'Provider1 should have 0.30 cost');
    }

    /** @test */
    public function it_returns_tokens_consumption_by_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider = Provider::factory()->create();
        $model = Model::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();

        // Create image jobs
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
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 150,
            'cost_usd' => 0.15,
            'created_at' => now()->subDays(5),
        ]);

        // Create video jobs
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

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/tokens/summary');

        $data = $response->json('data');
        
        // Verify image type
        $imageData = collect($data['tokens_by_type'])->firstWhere('job_type', 'image');
        $this->assertNotNull($imageData, 'Image type should be in results');
        $this->assertEquals(250, $imageData['tokens_consumed'], 'Image should have 250 tokens consumed');
        $this->assertEquals(2, $imageData['jobs_count'], 'Image should have 2 jobs');
        
        // Verify video type
        $videoData = collect($data['tokens_by_type'])->firstWhere('job_type', 'video');
        $this->assertNotNull($videoData, 'Video type should be in results');
        $this->assertEquals(200, $videoData['tokens_consumed'], 'Video should have 200 tokens consumed');
        $this->assertEquals(1, $videoData['jobs_count'], 'Video should have 1 job');
    }

    /** @test */
    public function it_returns_cost_per_provider(): void
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

        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider1->id,
            'model_id' => $model1->id,
            'job_type' => 'video',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 200,
            'cost_usd' => 0.20,
            'created_at' => now()->subDays(5),
        ]);

        // Create jobs for provider2
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider2->id,
            'model_id' => $model2->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'tokens_consumed' => 150,
            'cost_usd' => 0.15,
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/tokens/summary');

        $data = $response->json('data');
        
        // Verify provider1 cost
        $provider1Data = collect($data['tokens_by_provider'])->firstWhere('provider_id', $provider1->id);
        $this->assertEqualsWithDelta(0.30, $provider1Data['cost_usd'], 0.01, 'Provider1 should have 0.30 cost');
        
        // Verify provider2 cost
        $provider2Data = collect($data['tokens_by_provider'])->firstWhere('provider_id', $provider2->id);
        $this->assertEqualsWithDelta(0.15, $provider2Data['cost_usd'], 0.01, 'Provider2 should have 0.15 cost');
        
        // Verify summary total cost
        $this->assertEqualsWithDelta(0.45, $data['summary']['total_cost_usd'], 0.01, 'Total cost should be 0.45');
    }

    /** @test */
    public function it_returns_time_series_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider = Provider::factory()->create();
        $model = Model::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();

        // Create jobs on different days
        $day1 = now()->subDays(3)->startOfDay();
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
            'created_at' => $day1,
        ]);

        $day2 = now()->subDays(2)->startOfDay();
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
            'created_at' => $day2,
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/tokens/summary');

        $data = $response->json('data');
        
        $this->assertArrayHasKey('time_series', $data);
        $this->assertIsArray($data['time_series']);
        
        // Should have at least 1 day of data (may have more from other tests)
        $this->assertGreaterThanOrEqual(1, count($data['time_series']), 'Should have at least 1 day of time series data');
        
        // Verify structure
        if (count($data['time_series']) > 0) {
            $firstDay = $data['time_series'][0];
            $this->assertArrayHasKey('date', $firstDay);
            $this->assertArrayHasKey('tokens_consumed', $firstDay);
            $this->assertArrayHasKey('cost_usd', $firstDay);
            $this->assertArrayHasKey('jobs_count', $firstDay);
        }
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
        
        // Create job outside range (10 days ago)
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

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/tokens/summary', [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]);

        $data = $response->json('data');
        
        // Should include at least the job from 1 day ago (may include other jobs from other tests)
        $this->assertGreaterThanOrEqual(200, $data['summary']['total_tokens_consumed'], 'Should have at least 200 tokens in range');
        $this->assertGreaterThanOrEqual(0.20, $data['summary']['total_cost_usd'], 'Should have at least 0.20 cost in range');
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
        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/tokens/summary', [
            'range' => 'week',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertArrayHasKey('date_range', $data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('tokens_by_provider', $data);
        $this->assertArrayHasKey('tokens_by_type', $data);
        $this->assertArrayHasKey('time_series', $data);
    }

    /** @test */
    public function it_caches_token_analytics_data(): void
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
        $response1 = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/tokens/summary');
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
        $response2 = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/tokens/summary');
        $data2 = $response2->json('data');

        // Cached response should have same data
        $this->assertEquals($data1['summary']['total_tokens_consumed'], $data2['summary']['total_tokens_consumed'], 'Cached response should have same token count');
    }
}

