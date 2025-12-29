<?php

namespace Test\BackendTest\Laravel\Feature\Generation;

use App\Models\GenerationJob;
use App\Models\Model as AiModel;
use App\Models\Provider;
use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;
use Carbon\Carbon;

class JobStatusTest extends BackendTestCase
{
    /** @test */
    public function it_returns_user_generation_jobs(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'status' => 'completed',
            'tokens_consumed' => 10,
        ]);
        
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt 2',
            'status' => 'pending',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/generation/jobs');

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
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test',
            'status' => 'completed',
            'tokens_consumed' => 10,
        ]);
        
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'video',
            'prompt' => 'Test',
            'status' => 'completed',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/generation/jobs', [
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
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test',
            'status' => 'completed',
            'tokens_consumed' => 10,
        ]);
        
        GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test',
            'status' => 'pending',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/generation/jobs', [
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
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
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
        ])->makeRequest('GET', "/api/v1/generation/jobs/{$job->id}");

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
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $job = GenerationJob::create([
            'user_id' => $user2->id, // Different user
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test',
            'status' => 'completed',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token1}",
        ])->makeRequest('GET', "/api/v1/generation/jobs/{$job->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_cancels_pending_job_and_refunds_tokens(): void
    {
        $user = User::factory()->create(['tokens_balance' => 990]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create([
            'provider_id' => $provider->id,
            'default_tokens' => 10,
        ]);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test',
            'status' => 'pending',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/generation/jobs/{$job->id}/cancel");

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
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test',
            'status' => 'completed',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/generation/jobs/{$job->id}/cancel");

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
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create([
            'provider_id' => $provider->id,
            'default_tokens' => 10,
        ]);
        
        $oldJob = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'status' => 'failed',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/generation/jobs/{$oldJob->id}/retry");

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
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test',
            'status' => 'completed',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/generation/jobs/{$job->id}/retry");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Only failed jobs can be retried',
            ]);
    }
}

