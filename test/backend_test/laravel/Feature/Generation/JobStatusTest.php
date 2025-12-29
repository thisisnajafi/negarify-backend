<?php

namespace Test\BackendTest\Laravel\Feature\Generation;

use App\Models\GenerationJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Carbon\Carbon;

class JobStatusTest extends BackendTestCase
{
    use DatabaseMigrations; // Use DatabaseMigrations to avoid transaction conflicts

    /**
     * Helper method to create a provider using DB::table to avoid factory/Model class conflicts
     */
    private function createProvider(array $attributes = []): int
    {
        return DB::table('providers')->insertGetId(array_merge([
            'name' => 'Test Provider',
            'api_base_url' => 'https://api.example.com',
            'api_key_encrypted' => 'test-api-key-encrypted',
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    /**
     * Helper method to create a model using DB::table to avoid Model class name conflict
     */
    private function createModel(int $providerId, array $attributes = []): int
    {
        return DB::table('models')->insertGetId(array_merge([
            'provider_id' => $providerId,
            'model_name' => 'Test Model',
            'model_type' => 'image',
            'api_endpoint' => '/test',
            'default_tokens' => 10,
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    /** @test */
    public function it_returns_user_generation_jobs(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'tokens_consumed' => 10,
        ]);
        
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test prompt 2',
            'params_json' => [],
            'status' => 'pending',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/generate/jobs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'job_type',
                        'status',
                        'prompt',
                        'tokens_consumed',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $jobs = $response->json('data');
        $this->assertCount(2, $jobs);
        $this->assertEquals('completed', $jobs[0]['status']); // Ordered desc

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_filters_jobs_by_type(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test',
            'params_json' => [],
            'status' => 'completed',
            'tokens_consumed' => 10,
        ]);
        
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'video',
            'prompt' => 'Test',
            'params_json' => [],
            'status' => 'completed',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/generate/jobs', [
            'type' => 'image',
        ]);

        $jobs = $response->json('data');
        $this->assertCount(1, $jobs);
        $this->assertEquals('image', $jobs[0]['job_type']);
    }

    /** @test */
    public function it_filters_jobs_by_status(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test',
            'params_json' => [],
            'status' => 'completed',
            'tokens_consumed' => 10,
        ]);
        
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test',
            'params_json' => [],
            'status' => 'pending',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/generate/jobs', [
            'status' => 'completed',
        ]);

        $jobs = $response->json('data');
        $this->assertCount(1, $jobs);
        $this->assertEquals('completed', $jobs[0]['status']);
    }

    /** @test */
    public function it_returns_single_job_details(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'negative_prompt' => 'Test negative',
            'params_json' => ['size' => '1024x1024'],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
            'tokens_consumed' => 10,
            'cost_usd' => 0.01,
            'started_at' => Carbon::now()->subMinute(),
            'completed_at' => Carbon::now(),
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', "/api/v1/generate/jobs/{$job->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'job_type',
                    'status',
                    'prompt',
                    'negative_prompt',
                    'params',
                    'result_url',
                    'tokens_consumed',
                    'cost_usd',
                    'created_at',
                    'started_at',
                    'completed_at',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $job->id,
                    'status' => 'completed',
                    'result_url' => 'https://example.com/image.jpg',
                ],
            ]);
    }

    /** @test */
    public function it_rejects_access_to_other_user_job(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $token1 = $user1->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
        $job =         GenerationJob::create([
            'user_id' => $user2->id, // Different user
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test',
            'params_json' => [],
            'status' => 'completed',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token1}",
        ])->makeRequest('GET', "/api/v1/generate/jobs/{$job->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_cancels_pending_job_and_refunds_tokens(): void
    {
        $user = User::factory()->create(['tokens_balance' => 990]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId, ['default_tokens' => 10]);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test',
            'params_json' => [],
            'status' => 'pending',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/generate/jobs/{$job->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Job cancelled successfully',
                'data' => [
                    'status' => 'cancelled',
                    'tokens_refunded' => 10,
                ],
            ]);

        $job->refresh();
        $this->assertEquals('cancelled', $job->status);
        
        $user->refresh();
        $this->assertEquals(1000, $user->tokens_balance); // 990 + 10 refund
    }

    /** @test */
    public function it_rejects_cancelling_completed_job(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test',
            'params_json' => [],
            'status' => 'completed',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/generate/jobs/{$job->id}/cancel");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot cancel a completed job',
            ]);
    }

    /** @test */
    public function it_retries_failed_job(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId, ['default_tokens' => 10]);
        
        $oldJob = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'failed',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/generate/jobs/{$oldJob->id}/retry");

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Job retried successfully',
                'data' => [
                    'old_job_id' => $oldJob->id,
                    'status' => 'pending',
                ],
            ]);

        // Verify new job created
        $newJob = GenerationJob::where('user_id', $user->id)
            ->where('id', '!=', $oldJob->id)
            ->first();
        $this->assertNotNull($newJob);
        $this->assertEquals('pending', $newJob->status);
        $this->assertEquals('Test prompt', $newJob->prompt);

        // Verify tokens deducted again
        $user->refresh();
        $this->assertEquals(990, $user->tokens_balance); // 1000 - 10
    }

    /** @test */
    public function it_rejects_retrying_non_failed_job(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test',
            'params_json' => [],
            'status' => 'completed',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/generate/jobs/{$job->id}/retry");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Only failed jobs can be retried',
            ]);
    }

    /** @test */
    public function it_shows_status_transitions(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
        // Create jobs with different statuses
        $pendingJob = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Pending job',
            'params_json' => [],
            'status' => 'pending',
            'tokens_consumed' => 10,
        ]);
        
        $processingJob = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Processing job',
            'params_json' => [],
            'status' => 'processing',
            'tokens_consumed' => 10,
            'started_at' => Carbon::now()->subMinutes(5),
        ]);
        
        $completedJob = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Completed job',
            'params_json' => [],
            'status' => 'completed',
            'tokens_consumed' => 10,
            'started_at' => Carbon::now()->subMinutes(10),
            'completed_at' => Carbon::now()->subMinutes(2),
        ]);
        
        $failedJob = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Failed job',
            'params_json' => [],
            'status' => 'failed',
            'tokens_consumed' => 10,
            'started_at' => Carbon::now()->subMinutes(10),
            'completed_at' => Carbon::now()->subMinutes(2),
            'error_message' => 'Generation failed',
        ]);
        
        $cancelledJob = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Cancelled job',
            'params_json' => [],
            'status' => 'cancelled',
            'tokens_consumed' => 10,
            'completed_at' => Carbon::now()->subMinutes(1),
        ]);
        
        // Verify each status is correctly returned
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', "/api/v1/generate/jobs/{$pendingJob->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $pendingJob->id,
                    'status' => 'pending',
                ],
            ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', "/api/v1/generate/jobs/{$processingJob->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $processingJob->id,
                    'status' => 'processing',
                ],
            ])
            ->assertJsonPath('data.started_at', function ($value) {
                return $value !== null;
            });
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', "/api/v1/generate/jobs/{$completedJob->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $completedJob->id,
                    'status' => 'completed',
                ],
            ])
            ->assertJsonPath('data.started_at', function ($value) {
                return $value !== null;
            })
            ->assertJsonPath('data.completed_at', function ($value) {
                return $value !== null;
            });
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', "/api/v1/generate/jobs/{$failedJob->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $failedJob->id,
                    'status' => 'failed',
                    'error_message' => 'Generation failed',
                ],
            ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', "/api/v1/generate/jobs/{$cancelledJob->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $cancelledJob->id,
                    'status' => 'cancelled',
                ],
            ]);
        
        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_tracks_progress_with_timestamps(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
        $startedAt = Carbon::now()->subMinutes(5);
        $completedAt = Carbon::now()->subMinutes(1);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test progress',
            'params_json' => [],
            'status' => 'completed',
            'tokens_consumed' => 10,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', "/api/v1/generate/jobs/{$job->id}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'status',
                    'started_at',
                    'completed_at',
                    'created_at',
                ],
            ])
            ->assertJsonPath('data.started_at', function ($value) {
                return $value !== null && is_string($value);
            })
            ->assertJsonPath('data.completed_at', function ($value) {
                return $value !== null && is_string($value);
            });
        
        // Verify started_at is before completed_at
        $data = $response->json('data');
        $startedTimestamp = strtotime($data['started_at']);
        $completedTimestamp = strtotime($data['completed_at']);
        $this->assertLessThan($completedTimestamp, $startedTimestamp);
        
        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_includes_result_urls_when_available(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
        // Job without results
        $pendingJob = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Pending',
            'params_json' => [],
            'status' => 'pending',
            'tokens_consumed' => 10,
        ]);
        
        // Job with results
        $completedJob = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Completed',
            'params_json' => [],
            'status' => 'completed',
            'tokens_consumed' => 10,
            'result_url' => 'https://s3.example.com/generations/image/1/123.png',
            'result_thumbnail_url' => 'https://s3.example.com/generations/image/1/123_thumb.png',
            'completed_at' => Carbon::now(),
        ]);
        
        // Pending job should not have result URLs
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', "/api/v1/generate/jobs/{$pendingJob->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $pendingJob->id,
                    'status' => 'pending',
                    'result_url' => null,
                    'result_thumbnail_url' => null,
                ],
            ]);
        
        // Completed job should have result URLs
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', "/api/v1/generate/jobs/{$completedJob->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $completedJob->id,
                    'status' => 'completed',
                    'result_url' => 'https://s3.example.com/generations/image/1/123.png',
                    'result_thumbnail_url' => 'https://s3.example.com/generations/image/1/123_thumb.png',
                ],
            ]);
        
        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_includes_error_message_when_job_failed(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
        // Job without error
        $pendingJob = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Pending',
            'params_json' => [],
            'status' => 'pending',
            'tokens_consumed' => 10,
        ]);
        
        // Job with error
        $failedJob = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Failed',
            'params_json' => [],
            'status' => 'failed',
            'tokens_consumed' => 10,
            'error_message' => 'API request failed: timeout',
            'completed_at' => Carbon::now(),
        ]);
        
        // Pending job should not have error message
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', "/api/v1/generate/jobs/{$pendingJob->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $pendingJob->id,
                    'status' => 'pending',
                    'error_message' => null,
                ],
            ]);
        
        // Failed job should have error message
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', "/api/v1/generate/jobs/{$failedJob->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $failedJob->id,
                    'status' => 'failed',
                    'error_message' => 'API request failed: timeout',
                ],
            ]);
        
        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_paginates_job_listing(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
        // Create 20 jobs (more than default per_page of 15)
        for ($i = 0; $i < 20; $i++) {
            GenerationJob::create([
                'user_id' => $user->id,
                'provider_id' => $providerId,
                'model_id' => $modelId,
                'job_type' => 'image',
                'prompt' => "Test job {$i}",
                'params_json' => [],
                'status' => 'completed',
                'tokens_consumed' => 10,
                'created_at' => Carbon::now()->subMinutes(20 - $i), // Ensure ordering
            ]);
        }
        
        // First page
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/generate/jobs');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJson([
                'success' => true,
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 15,
                    'total' => 20,
                    'last_page' => 2,
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertCount(15, $data);
        
        // Second page
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/generate/jobs', ['page' => 2]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'current_page' => 2,
                    'per_page' => 15,
                    'total' => 20,
                    'last_page' => 2,
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertCount(5, $data); // Remaining 5 jobs
        
        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_handles_cancelling_processing_job(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId, ['default_tokens' => 50]);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Processing job',
            'params_json' => [],
            'status' => 'processing',
            'tokens_consumed' => 50,
            'started_at' => Carbon::now()->subMinutes(2),
        ]);
        
        // Reserve tokens (simulate what happens when job starts)
        $user->tokens_balance -= 50;
        $user->save();
        $initialBalance = $user->tokens_balance;
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/generate/jobs/{$job->id}/cancel");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Job cancelled successfully',
                'data' => [
                    'job_id' => $job->id,
                    'status' => 'cancelled',
                    'tokens_refunded' => 50,
                ],
            ]);
        
        // Verify job status
        $job->refresh();
        $this->assertEquals('cancelled', $job->status);
        $this->assertNotNull($job->completed_at);
        
        // Verify tokens refunded
        $user->refresh();
        $this->assertEquals($initialBalance + 50, $user->tokens_balance);
        
        $this->assertNoErrorLogs();
    }
}

