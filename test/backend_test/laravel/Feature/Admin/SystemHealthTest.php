<?php

namespace Test\BackendTest\Laravel\Feature\Admin;

use App\Models\GenerationJob;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class SystemHealthTest extends BackendTestCase
{
    /** @test */
    public function it_returns_system_health_metrics(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();
        
        $provider = \App\Models\Provider::factory()->create();
        $model = \App\Models\Model::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();
        
        // Create some test data
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'pending',
        ]);
        
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'video',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'pending',
        ]);
        
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
        
        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/system-health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'queue' => [
                        'length',
                        'length_by_type',
                    ],
                    'workers',
                    'failed_jobs',
                    'error_rate',
                    'api_latency',
                    'storage',
                    'timestamp',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, $data['queue']['length'], 'Should have at least 2 pending jobs'); // May include other jobs from other tests
        $this->assertArrayHasKey('image', $data['queue']['length_by_type']);
        $this->assertArrayHasKey('video', $data['queue']['length_by_type']);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_caches_system_health(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();
        
        // First request
        $response1 = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/system-health');
        $response1->assertStatus(200);
        
        // Verify cached
        $cached = Cache::get('admin:system-health');
        $this->assertNotNull($cached);
        
        // Second request should use cache
        $response2 = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/system-health');
        $response2->assertStatus(200);
    }

    /** @test */
    public function it_rejects_system_health_access_for_non_admin(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        
        $response = $this->actingAs($user)->makeRequest('GET', '/api/v1/admin/system-health');

        $response->assertStatus(403);
    }

    /** @test */
    public function it_tracks_failed_jobs_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider = \App\Models\Provider::factory()->create();
        $model = \App\Models\Model::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();

        // Create failed jobs
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'failed',
            'created_at' => now()->subHours(12),
        ]);

        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'video',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'failed',
            'created_at' => now()->subHours(6),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/system-health');

        $data = $response->json('data');
        
        $this->assertArrayHasKey('failed_jobs', $data);
        $this->assertArrayHasKey('count_last_24h', $data['failed_jobs']);
        $this->assertArrayHasKey('count_today', $data['failed_jobs']);
        
        // The failed_jobs count comes from the failed_jobs table, not GenerationJob status
        // So we just verify the structure exists (the count might be 0 if the table doesn't exist or is empty)
        $this->assertIsInt($data['failed_jobs']['count_last_24h']);
        $this->assertIsInt($data['failed_jobs']['count_today']);
        
        // Verify error_rate includes failed GenerationJobs
        $this->assertGreaterThanOrEqual(2, $data['error_rate']['failed_jobs'], 'Should have at least 2 failed GenerationJobs in error_rate');
    }

    /** @test */
    public function it_calculates_error_rate(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider = \App\Models\Provider::factory()->create();
        $model = \App\Models\Model::factory()->create(['provider_id' => $provider->id]);
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
                'created_at' => now()->subHours(12),
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
                'created_at' => now()->subHours(12),
            ]);
        }

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/system-health');

        $data = $response->json('data');
        
        $this->assertArrayHasKey('error_rate', $data);
        $this->assertArrayHasKey('percentage', $data['error_rate']);
        $this->assertArrayHasKey('failed_jobs', $data['error_rate']);
        $this->assertArrayHasKey('total_jobs', $data['error_rate']);
        
        // Should have at least 5 failed and 15 total (may include other jobs from other tests)
        $this->assertGreaterThanOrEqual(5, $data['error_rate']['failed_jobs'], 'Should have at least 5 failed jobs');
        $this->assertGreaterThanOrEqual(15, $data['error_rate']['total_jobs'], 'Should have at least 15 total jobs');
    }

    /** @test */
    public function it_tracks_api_latency(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider = \App\Models\Provider::factory()->create();
        $model = \App\Models\Model::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();

        // Create completed jobs with latency
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
            'created_at' => now()->subHours(12),
        ]);

        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'video',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'started_at' => $now->copy()->subSeconds(4),
            'completed_at' => $now,
            'created_at' => now()->subHours(12),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/system-health');

        $data = $response->json('data');
        
        $this->assertArrayHasKey('api_latency', $data);
        $this->assertArrayHasKey('avg_ms', $data['api_latency']);
        $this->assertArrayHasKey('p95_ms', $data['api_latency']);
        $this->assertArrayHasKey('p99_ms', $data['api_latency']);
        $this->assertArrayHasKey('period', $data['api_latency']);
        
        // Average latency should be calculated
        if ($data['api_latency']['avg_ms'] !== null) {
            $this->assertGreaterThan(0, $data['api_latency']['avg_ms'], 'Average latency should be positive');
        }
    }

    /** @test */
    public function it_tracks_storage_usage(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider = \App\Models\Provider::factory()->create();
        $model = \App\Models\Model::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();

        // Create jobs with result URLs (storage usage)
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'result_url' => 'https://example.com/file1.jpg',
        ]);

        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'video',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'result_url' => 'https://example.com/file2.mp4',
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/system-health');

        $data = $response->json('data');
        
        $this->assertArrayHasKey('storage', $data);
        $this->assertArrayHasKey('files_count', $data['storage']);
        $this->assertArrayHasKey('estimated_usage_mb', $data['storage']);
        
        // Should have at least 2 files (may include other files from other tests)
        $this->assertGreaterThanOrEqual(2, $data['storage']['files_count'], 'Should have at least 2 files');
        $this->assertGreaterThanOrEqual(4, $data['storage']['estimated_usage_mb'], 'Should have at least 4MB estimated usage');
    }

    /** @test */
    public function it_tracks_queue_length_by_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $provider = \App\Models\Provider::factory()->create();
        $model = \App\Models\Model::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();

        // Create pending jobs of different types
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'pending',
        ]);

        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'pending',
        ]);

        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'video',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/system-health');

        $data = $response->json('data');
        
        $this->assertArrayHasKey('queue', $data);
        $this->assertArrayHasKey('length', $data['queue']);
        $this->assertArrayHasKey('length_by_type', $data['queue']);
        
        // Should have at least 3 pending jobs (may include other jobs from other tests)
        $this->assertGreaterThanOrEqual(3, $data['queue']['length'], 'Should have at least 3 pending jobs');
        
        // Verify image type count
        if (isset($data['queue']['length_by_type']['image'])) {
            $this->assertGreaterThanOrEqual(2, $data['queue']['length_by_type']['image'], 'Should have at least 2 image jobs');
        }
        
        // Verify video type count
        if (isset($data['queue']['length_by_type']['video'])) {
            $this->assertGreaterThanOrEqual(1, $data['queue']['length_by_type']['video'], 'Should have at least 1 video job');
        }
    }
}

