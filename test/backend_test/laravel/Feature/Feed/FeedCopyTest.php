<?php

namespace Test\BackendTest\Laravel\Feature\Feed;

use App\Models\GalleryPost;
use App\Models\GenerationJob;
use App\Models\Model as AiModel;
use App\Models\Provider;
use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class FeedCopyTest extends BackendTestCase
{
    /** @test */
    public function it_copies_prompt_from_feed_post(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $job = GenerationJob::create([
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'A beautiful sunset',
            'negative_prompt' => 'blurry, low quality',
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        $post = GalleryPost::create([
            'user_id' => User::factory()->create()->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'is_curated' => true,
            'prompt_visible' => true, // Prompt visible
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/gallery/feed/copy-prompt', [
            'post_id' => $post->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'prompt',
                    'negative_prompt',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'prompt' => 'A beautiful sunset',
                    'negative_prompt' => 'blurry, low quality',
                ],
            ]);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_rejects_copying_prompt_when_not_visible(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $job = GenerationJob::create([
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'A beautiful sunset',
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        $post = GalleryPost::create([
            'user_id' => User::factory()->create()->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'is_curated' => true,
            'prompt_visible' => false, // Prompt not visible
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/gallery/feed/copy-prompt', [
            'post_id' => $post->id,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Prompt is not visible for this post',
            ]);
    }

    /** @test */
    public function it_copies_model_from_feed_post(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create([
            'provider_id' => $provider->id,
            'model_name' => 'Stable Diffusion XL',
        ]);
        
        $job = GenerationJob::create([
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        $post = GalleryPost::create([
            'user_id' => User::factory()->create()->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'is_curated' => true,
            'model_visible' => true, // Model visible
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/gallery/feed/copy-model', [
            'post_id' => $post->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'model_id',
                    'model_name',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'model_id' => $model->id,
                    'model_name' => 'Stable Diffusion XL',
                ],
            ]);
    }

    /** @test */
    public function it_rejects_copying_model_when_not_visible(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $job = GenerationJob::create([
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        $post = GalleryPost::create([
            'user_id' => User::factory()->create()->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'is_curated' => true,
            'model_visible' => false, // Model not visible
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/gallery/feed/copy-model', [
            'post_id' => $post->id,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Model information is not visible for this post',
            ]);
    }

    /** @test */
    public function it_rejects_copying_from_non_curated_post(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $job = GenerationJob::create([
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        $post = GalleryPost::create([
            'user_id' => User::factory()->create()->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'is_curated' => false, // Not curated
            'prompt_visible' => true,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/gallery/feed/copy-prompt', [
            'post_id' => $post->id,
        ]);

        $response->assertStatus(404); // Post not found (not in feed)
    }
}

