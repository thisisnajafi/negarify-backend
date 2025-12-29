<?php

namespace Test\BackendTest\Laravel\Feature\Generation;

use App\Models\GenerationJob;
use App\Models\Model as AiModel;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class ImageGenerationTest extends BackendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** @test */
    public function it_creates_image_generation_job(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create([
            'provider_id' => $provider->id,
            'model_type' => 'image',
            'default_tokens' => 10,
            'is_available' => true,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/generation/image', [
            'model_id' => $model->id,
            'prompt' => 'A beautiful sunset over mountains',
            'negative_prompt' => 'blurry, low quality',
            'size' => '1024x1024',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'job_id',
                    'status',
                    'estimated_tokens',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Image generation job created',
                'data' => [
                    'status' => 'pending',
                    'estimated_tokens' => 10,
                ],
            ]);

        // Verify job created
        $job = GenerationJob::where('user_id', $user->id)->first();
        $this->assertNotNull($job);
        $this->assertEquals('image', $job->job_type);
        $this->assertEquals('pending', $job->status);
        $this->assertEquals(10, $job->tokens_consumed);

        // Verify tokens deducted
        $user->refresh();
        $this->assertEquals(990, $user->tokens_balance); // 1000 - 10

        // Verify job dispatched
        Queue::assertPushed(\App\Jobs\GenerateImageJob::class, function ($queuedJob) use ($job) {
            return $queuedJob->generationJobId === $job->id;
        });

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_rejects_generation_with_insufficient_tokens(): void
    {
        $user = User::factory()->create(['tokens_balance' => 5]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create([
            'provider_id' => $provider->id,
            'model_type' => 'image',
            'default_tokens' => 10,
            'is_available' => true,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/generation/image', [
            'model_id' => $model->id,
            'prompt' => 'A beautiful sunset',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient token balance',
                'data' => [
                    'required' => 10,
                    'available' => 5,
                ],
            ]);

        // Verify tokens not deducted
        $user->refresh();
        $this->assertEquals(5, $user->tokens_balance);

        // Verify no job created
        $this->assertDatabaseMissing('generation_jobs', [
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function it_rejects_unavailable_model(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create([
            'provider_id' => $provider->id,
            'model_type' => 'image',
            'default_tokens' => 10,
            'is_available' => false, // Unavailable
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/generation/image', [
            'model_id' => $model->id,
            'prompt' => 'A beautiful sunset',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Selected model is not available',
            ]);
    }

    /** @test */
    public function it_rejects_wrong_model_type(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create([
            'provider_id' => $provider->id,
            'model_type' => 'video', // Wrong type
            'default_tokens' => 10,
            'is_available' => true,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/generation/image', [
            'model_id' => $model->id,
            'prompt' => 'A beautiful sunset',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Selected model is not an image generation model',
            ]);
    }

    /** @test */
    public function it_validates_required_prompt(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create([
            'provider_id' => $provider->id,
            'model_type' => 'image',
            'default_tokens' => 10,
            'is_available' => true,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/generation/image', [
            'model_id' => $model->id,
            // Missing prompt
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prompt']);
    }

    /** @test */
    public function it_stores_generation_parameters(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create([
            'provider_id' => $provider->id,
            'model_type' => 'image',
            'default_tokens' => 10,
            'is_available' => true,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/generation/image', [
            'model_id' => $model->id,
            'prompt' => 'A beautiful sunset',
            'negative_prompt' => 'blurry',
            'size' => '1024x1024',
            'style' => 'realistic',
            'seed' => 12345,
            'steps' => 50,
            'guidance_scale' => 7.5,
        ]);

        $response->assertStatus(201);

        $job = GenerationJob::where('user_id', $user->id)->first();
        $this->assertEquals('A beautiful sunset', $job->prompt);
        $this->assertEquals('blurry', $job->negative_prompt);
        $this->assertEquals('1024x1024', $job->params_json['size']);
        $this->assertEquals('realistic', $job->params_json['style']);
        $this->assertEquals(12345, $job->params_json['seed']);
        $this->assertEquals(50, $job->params_json['steps']);
        $this->assertEquals(7.5, $job->params_json['guidance_scale']);
    }
}

