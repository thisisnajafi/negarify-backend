<?php

namespace Test\BackendTest\Laravel\Feature\Gallery;

use App\Models\GenerationJob;
use App\Models\GalleryPost;
use App\Models\Model as AiModel;
use App\Models\Provider;
use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class GalleryPostTest extends BackendTestCase
{
    /** @test */
    public function it_creates_gallery_post_from_completed_job(): void
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
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/gallery/posts', [
            'generation_job_id' => $job->id,
            'title' => 'My Artwork',
            'description' => 'A beautiful image',
            'tags' => ['art', 'nature'],
            'visibility' => 'public',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'tags',
                    'visibility',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Gallery post created successfully',
            ]);

        // Verify post created
        $post = GalleryPost::where('user_id', $user->id)->first();
        $this->assertNotNull($post);
        $this->assertEquals($job->id, $post->generation_job_id);
        $this->assertEquals('My Artwork', $post->title);
        $this->assertEquals('public', $post->visibility);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_rejects_post_from_incomplete_job(): void
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
            'status' => 'pending', // Not completed
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/gallery/posts', [
            'generation_job_id' => $job->id,
            'title' => 'My Artwork',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Generation job must be completed before creating a gallery post',
            ]);
    }

    /** @test */
    public function it_rejects_post_from_other_user_job(): void
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
            'prompt' => 'Test prompt',
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token1}",
        ])->makeRequest('POST', '/api/v1/gallery/posts', [
            'generation_job_id' => $job->id,
            'title' => 'My Artwork',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Generation job not found or does not belong to you',
            ]);
    }

    /** @test */
    public function it_prevents_duplicate_posts_for_same_job(): void
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
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
            'tokens_consumed' => 10,
        ]);
        
        // Create first post
        GalleryPost::create([
            'user_id' => $user->id,
            'generation_job_id' => $job->id,
            'title' => 'First Post',
            'visibility' => 'public',
        ]);
        
        // Try to create duplicate
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/gallery/posts', [
            'generation_job_id' => $job->id,
            'title' => 'Second Post',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'A gallery post already exists for this generation job',
            ]);
    }
}

