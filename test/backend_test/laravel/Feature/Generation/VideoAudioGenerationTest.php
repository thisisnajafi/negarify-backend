<?php

namespace Test\BackendTest\Laravel\Feature\Generation;

use App\Models\GenerationJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class VideoAudioGenerationTest extends BackendTestCase
{
    use DatabaseMigrations; // Use DatabaseMigrations to avoid transaction conflicts

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

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
    public function it_creates_video_generation_job(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId, [
            'model_type' => 'video',
            'default_tokens' => 50,
            'enabled' => true,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/generate/video', [
            'model_id' => $modelId,
            'prompt' => 'A beautiful sunset video',
            'duration' => 10,
            'resolution' => '1920x1080',
            'fps' => 30,
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
                'message' => 'Video generation job created',
                'data' => [
                    'status' => 'pending',
                    'estimated_tokens' => 50,
                ],
            ]);

        // Verify job created
        $job = GenerationJob::where('user_id', $user->id)->first();
        $this->assertNotNull($job);
        $this->assertEquals('video', $job->job_type);
        $this->assertEquals(50, $job->tokens_consumed);

        // Verify tokens deducted
        $user->refresh();
        $this->assertEquals(950, $user->tokens_balance);

        // Verify job dispatched
        Queue::assertPushed(\App\Jobs\GenerateVideoJob::class);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_rejects_video_generation_with_wrong_model_type(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId, [
            'model_type' => 'image', // Wrong type
            'default_tokens' => 50,
            'enabled' => true,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/generate/video', [
            'model_id' => $modelId,
            'prompt' => 'Test video',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Model not available',
            ]);
    }

    /** @test */
    public function it_creates_audio_generation_job(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId, [
            'model_type' => 'audio',
            'default_tokens' => 30,
            'enabled' => true,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/generate/audio', [
            'model_id' => $modelId,
            'prompt' => 'A beautiful melody',
            'duration' => 30,
            'format' => 'mp3',
            'sample_rate' => 44100,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Audio generation job created',
                'data' => [
                    'status' => 'pending',
                    'estimated_tokens' => 30,
                ],
            ]);

        // Verify job created
        $job = GenerationJob::where('user_id', $user->id)->first();
        $this->assertNotNull($job);
        $this->assertEquals('audio', $job->job_type);
        $this->assertEquals(30, $job->tokens_consumed);

        // Verify tokens deducted
        $user->refresh();
        $this->assertEquals(970, $user->tokens_balance);

        // Verify job dispatched
        Queue::assertPushed(\App\Jobs\GenerateAudioJob::class);
    }

    /** @test */
    public function it_rejects_audio_generation_with_wrong_model_type(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId, [
            'model_type' => 'image', // Wrong type
            'default_tokens' => 30,
            'enabled' => true,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/generate/audio', [
            'model_id' => $modelId,
            'prompt' => 'Test audio',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Model not available',
            ]);
    }

    /** @test */
    public function it_stores_video_generation_parameters(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId, [
            'model_type' => 'video',
            'default_tokens' => 50,
            'enabled' => true,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/generate/video', [
            'model_id' => $modelId,
            'prompt' => 'Test video',
            'duration' => 10,
            'resolution' => '1920x1080',
            'fps' => 30,
            'seed' => 12345,
        ]);

        $response->assertStatus(201);

        $job = GenerationJob::where('user_id', $user->id)->first();
        $this->assertEquals(10, $job->params_json['duration']);
        $this->assertEquals('1920x1080', $job->params_json['resolution']);
        $this->assertEquals(30, $job->params_json['fps']);
        $this->assertEquals(12345, $job->params_json['seed']);
    }

    /** @test */
    public function it_stores_audio_generation_parameters(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId, [
            'model_type' => 'audio',
            'default_tokens' => 30,
            'enabled' => true,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/generate/audio', [
            'model_id' => $modelId,
            'prompt' => 'Test audio',
            'duration' => 30,
            'format' => 'mp3',
            'sample_rate' => 44100,
            'seed' => 54321,
        ]);

        $response->assertStatus(201);

        $job = GenerationJob::where('user_id', $user->id)->first();
        $this->assertEquals(30, $job->params_json['duration']);
        $this->assertEquals('mp3', $job->params_json['format']);
        $this->assertEquals(44100, $job->params_json['sample_rate']);
        $this->assertEquals(54321, $job->params_json['seed']);
    }
}

