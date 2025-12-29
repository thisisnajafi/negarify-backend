<?php

namespace Test\BackendTest\Laravel\Feature\Generation;

use App\Jobs\GenerateAudioJob;
use App\Jobs\GenerateImageJob;
use App\Jobs\GenerateVideoJob;
use App\Models\GenerationJob;
use App\Models\TokenTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class QueueJobProcessingTest extends BackendTestCase
{
    use DatabaseMigrations;

    /**
     * Helper method to create a provider using Provider model to handle encryption properly
     */
    private function createProvider(array $attributes = []): int
    {
        $apiKey = $attributes['api_key'] ?? 'test-api-key';
        unset($attributes['api_key']); // Remove if present, we'll handle it separately
        
        // Create provider with API key set via mutator (which handles encryption)
        $provider = new \App\Models\Provider(array_merge([
            'name' => 'Test Provider',
            'api_base_url' => 'https://api.segmind.com',
            'enabled' => true,
        ], $attributes));
        
        // Set API key using the model's mutator which handles encryption
        $provider->setApiKey($apiKey);
        $provider->save();
        
        return $provider->id;
    }

    /**
     * Helper method to create a model using DB::table to avoid Model class name conflict
     */
    private function createModel(int $providerId, array $attributes = []): int
    {
        return DB::table('models')->insertGetId(array_merge([
            'provider_id' => $providerId,
            'model_name' => 'test-model',
            'model_type' => 'image',
            'api_endpoint' => '/v1/test-model',
            'default_tokens' => 10,
            'base_cost_usd' => 0.01,
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Fake storage for S3
        Storage::fake('s3');
    }

    /** @test */
    public function it_processes_image_generation_job_successfully(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId, [
            'model_type' => 'image',
            'default_tokens' => 10,
            'base_cost_usd' => 0.01,
        ]);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'A beautiful sunset',
            'params_json' => ['size' => '1024x1024'],
            'status' => 'pending',
            'tokens_consumed' => 10,
        ]);
        
        // Reserve tokens (simulate what happens when job is created)
        $user->tokens_balance -= 10;
        $user->save();
        $initialBalance = $user->tokens_balance;
        
        // Create a minimal valid PNG image (1x1 pixel PNG)
        // PNG header + minimal IHDR + IEND chunks
        $pngContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        
        // Mock Segmind API response (for generateImage) and image download
        $imageUrl = 'https://segmind.com/generated/image123.png';
        Http::fake([
            'api.segmind.com/*' => Http::response(['image_url' => $imageUrl], 200), // generateImage response
            'segmind.com/*' => Http::response($pngContent, 200, ['Content-Type' => 'image/png']), // downloadImage response
        ]);
        
        // Process the job
        $generateJob = new GenerateImageJob($job->id);
        $generateJob->handle();
        
        // Verify job status updated
        $job->refresh();
        $this->assertEquals('completed', $job->status);
        $this->assertNotNull($job->result_url);
        $this->assertNotNull($job->result_thumbnail_url);
        $this->assertNotNull($job->started_at);
        $this->assertNotNull($job->completed_at);
        $this->assertEquals(10, $job->tokens_consumed);
        $this->assertEquals(0.01, (float) $job->cost_usd);
        
        // Verify token transaction created
        $transaction = TokenTransaction::where('generation_job_id', $job->id)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('consume', $transaction->type);
        $this->assertEquals(-10, $transaction->amount_tokens);
        $this->assertEquals($user->id, $transaction->user_id);
        
        // Verify image stored in S3
        $path = "generations/image/{$user->id}/{$job->id}.png";
        Storage::disk('s3')->assertExists($path);
        
        // Verify thumbnail stored
        $thumbPath = "generations/image/{$user->id}/{$job->id}_thumb.png";
        Storage::disk('s3')->assertExists($thumbPath);
        
        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_handles_image_generation_failure_and_refunds_tokens(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId, [
            'model_type' => 'image',
            'default_tokens' => 10,
        ]);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'A beautiful sunset',
            'params_json' => [],
            'status' => 'pending',
            'tokens_consumed' => 10,
        ]);
        
        // Reserve tokens
        $user->tokens_balance -= 10;
        $user->save();
        $initialBalance = $user->tokens_balance;
        
        // Mock Segmind API failure
        Http::fake([
            'api.segmind.com/*' => Http::response([
                'error' => 'API request failed: timeout',
            ], 500),
        ]);
        
        // Allow expected error logs
        $this->allowErrorLogs(['Image generation failed', 'Segmind API', 'API request failed']);
        
        // Process the job
        $generateJob = new GenerateImageJob($job->id);
        $generateJob->handle();
        
        // Verify job status updated to failed
        $job->refresh();
        $this->assertEquals('failed', $job->status);
        $this->assertNotNull($job->error_message);
        $this->assertNotNull($job->completed_at);
        
        // Verify tokens refunded
        $user->refresh();
        $this->assertEquals($initialBalance + 10, $user->tokens_balance);
        
        // Verify no consume transaction created
        $transaction = TokenTransaction::where('generation_job_id', $job->id)->first();
        $this->assertNull($transaction);
    }

    /** @test */
    public function it_processes_video_generation_job_successfully(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId, [
            'model_type' => 'video',
            'default_tokens' => 50,
            'base_cost_usd' => 0.05,
        ]);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'video',
            'prompt' => 'A beautiful sunset video',
            'params_json' => ['duration' => 10, 'resolution' => '1920x1080'],
            'status' => 'pending',
            'tokens_consumed' => 50,
        ]);
        
        // Reserve tokens
        $user->tokens_balance -= 50;
        $user->save();
        
        // Mock Segmind API response (for generateVideo) and video download
        $videoUrl = 'https://segmind.com/generated/video123.mp4';
        Http::fake(function ($request) use ($videoUrl) {
            $url = $request->url();
            if (str_contains($url, 'api.segmind.com')) {
                return Http::response(['video_url' => $videoUrl], 200);
            }
            if (str_contains($url, 'segmind.com/generated/video')) {
                return Http::response('fake-video-binary-content', 200, ['Content-Type' => 'video/mp4']);
            }
            return Http::response('Not found', 404);
        });
        
        // Process the job
        $generateJob = new GenerateVideoJob($job->id);
        $generateJob->handle();
        
        // Verify job status updated
        $job->refresh();
        $this->assertEquals('completed', $job->status);
        $this->assertNotNull($job->result_url);
        $this->assertNotNull($job->started_at);
        $this->assertNotNull($job->completed_at);
        $this->assertEquals(50, $job->tokens_consumed);
        
        // Verify token transaction created
        $transaction = TokenTransaction::where('generation_job_id', $job->id)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('consume', $transaction->type);
        $this->assertEquals(-50, $transaction->amount_tokens);
        
        // Verify video stored in S3
        $path = "generations/video/{$user->id}/{$job->id}.mp4";
        Storage::disk('s3')->assertExists($path);
    }

    /** @test */
    public function it_processes_audio_generation_job_successfully(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId, [
            'model_type' => 'audio',
            'default_tokens' => 20,
            'base_cost_usd' => 0.02,
        ]);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'audio',
            'prompt' => 'A beautiful melody',
            'params_json' => ['duration' => 30, 'format' => 'mp3'],
            'status' => 'pending',
            'tokens_consumed' => 20,
        ]);
        
        // Reserve tokens
        $user->tokens_balance -= 20;
        $user->save();
        
        // Mock Segmind API response (for generateAudio) and audio download
        $audioUrl = 'https://segmind.com/generated/audio123.mp3';
        Http::fake(function ($request) use ($audioUrl) {
            $url = $request->url();
            if (str_contains($url, 'api.segmind.com')) {
                return Http::response(['audio_url' => $audioUrl], 200);
            }
            if (str_contains($url, 'segmind.com/generated/audio')) {
                return Http::response('fake-audio-binary-content', 200, ['Content-Type' => 'audio/mpeg']);
            }
            return Http::response('Not found', 404);
        });
        
        // Process the job
        $generateJob = new GenerateAudioJob($job->id);
        $generateJob->handle();
        
        // Verify job status updated
        $job->refresh();
        $this->assertEquals('completed', $job->status);
        $this->assertNotNull($job->result_url);
        $this->assertNotNull($job->started_at);
        $this->assertNotNull($job->completed_at);
        $this->assertEquals(20, $job->tokens_consumed);
        
        // Verify token transaction created
        $transaction = TokenTransaction::where('generation_job_id', $job->id)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('consume', $transaction->type);
        $this->assertEquals(-20, $transaction->amount_tokens);
        
        // Verify audio stored in S3
        $path = "generations/audio/{$user->id}/{$job->id}.mp3";
        Storage::disk('s3')->assertExists($path);
    }

    /** @test */
    public function it_is_idempotent_and_skips_already_completed_jobs(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        
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
            'result_url' => 'https://s3.example.com/result.png',
            'completed_at' => now(),
        ]);
        
        $initialTransactionCount = TokenTransaction::where('generation_job_id', $job->id)->count();
        
        // Process the job again (should be idempotent)
        $generateJob = new GenerateImageJob($job->id);
        $generateJob->handle();
        
        // Verify job still completed
        $job->refresh();
        $this->assertEquals('completed', $job->status);
        
        // Verify no new transaction created
        $finalTransactionCount = TokenTransaction::where('generation_job_id', $job->id)->count();
        $this->assertEquals($initialTransactionCount, $finalTransactionCount);
        
        // Verify no API calls made (idempotency check happens before API call)
        Http::assertNothingSent();
    }

    /** @test */
    public function it_is_idempotent_and_skips_already_failed_jobs(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test',
            'params_json' => [],
            'status' => 'failed',
            'tokens_consumed' => 10,
            'error_message' => 'Previous error',
            'completed_at' => now(),
        ]);
        
        // Process the job again (should be idempotent)
        $generateJob = new GenerateImageJob($job->id);
        $generateJob->handle();
        
        // Verify job still failed with same error
        $job->refresh();
        $this->assertEquals('failed', $job->status);
        $this->assertEquals('Previous error', $job->error_message);
        
        // Verify no API calls made
        Http::assertNothingSent();
    }

    /** @test */
    public function it_updates_job_status_to_processing_when_handling(): void
    {
        $user = User::factory()->create(['tokens_balance' => 1000]);
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
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
        
        // Reserve tokens
        $user->tokens_balance -= 10;
        $user->save();
        
        // Mock API response
        $imageUrl = 'https://example.com/image.png';
        $pngContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        Http::fake([
            'api.segmind.com/*' => Http::response(['image_url' => $imageUrl], 200),
            'example.com/image.png' => Http::response($pngContent, 200, ['Content-Type' => 'image/png']),
        ]);
        
        // Process the job
        $generateJob = new GenerateImageJob($job->id);
        $generateJob->handle();
        
        // Verify status was updated to processing (then to completed)
        // Since the job completes successfully, status should be completed
        $job->refresh();
        $this->assertEquals('completed', $job->status);
        $this->assertNotNull($job->started_at); // Started_at should be set when status changes to processing
    }
}

