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
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        // Create some test data
        GenerationJob::create([
            'job_type' => 'image',
            'status' => 'pending',
        ]);
        
        GenerationJob::create([
            'job_type' => 'video',
            'status' => 'pending',
        ]);
        
        GenerationJob::create([
            'job_type' => 'image',
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/admin/system-health');

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
        $this->assertEquals(2, $data['queue']['length']); // 2 pending jobs
        $this->assertArrayHasKey('image', $data['queue']['length_by_type']);
        $this->assertArrayHasKey('video', $data['queue']['length_by_type']);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_caches_system_health(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        Cache::forget('admin:system-health');
        
        // First request
        $response1 = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/admin/system-health');
        $response1->assertStatus(200);
        
        // Verify cached
        $cached = Cache::get('admin:system-health');
        $this->assertNotNull($cached);
        
        // Second request should use cache
        $response2 = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/admin/system-health');
        $response2->assertStatus(200);
    }

    /** @test */
    public function it_rejects_system_health_access_for_non_admin(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/admin/system-health');

        $response->assertStatus(403);
    }
}

