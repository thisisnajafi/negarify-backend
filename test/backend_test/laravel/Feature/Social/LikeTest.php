<?php

namespace Test\BackendTest\Laravel\Feature\Social;

use App\Models\GalleryPost;
use App\Models\GenerationJob;
use App\Models\Like;
use App\Models\Model as AiModel;
use App\Models\Provider;
use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class LikeTest extends BackendTestCase
{
    /** @test */
    public function it_likes_a_post(): void
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
            'likes_count' => 0,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/posts/{$post->id}/like");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'liked',
                    'likes_count',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Post liked successfully',
                'data' => [
                    'liked' => true,
                ],
            ]);

        // Verify like created
        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'gallery_post_id' => $post->id,
        ]);

        // Verify likes count incremented
        $post->refresh();
        $this->assertEquals(1, $post->likes_count);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_is_idempotent_prevents_duplicate_likes(): void
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
            'likes_count' => 0,
        ]);
        
        // First like
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/posts/{$post->id}/like");
        $response->assertStatus(200);
        
        // Second like (should be idempotent)
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/posts/{$post->id}/like");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Post already liked',
            ]);

        // Verify only one like exists
        $likesCount = Like::where('user_id', $user->id)
            ->where('gallery_post_id', $post->id)
            ->count();
        $this->assertEquals(1, $likesCount);

        // Verify likes count not incremented twice
        $post->refresh();
        $this->assertEquals(1, $post->likes_count);
    }

    /** @test */
    public function it_unlikes_a_post(): void
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
            'likes_count' => 1,
        ]);
        
        // Create like first
        Like::create([
            'user_id' => $user->id,
            'gallery_post_id' => $post->id,
        ]);
        
        // Unlike
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('DELETE', "/api/v1/posts/{$post->id}/like");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Post unliked successfully',
                'data' => [
                    'liked' => false,
                ],
            ]);

        // Verify like deleted
        $this->assertDatabaseMissing('likes', [
            'user_id' => $user->id,
            'gallery_post_id' => $post->id,
        ]);

        // Verify likes count decremented
        $post->refresh();
        $this->assertEquals(0, $post->likes_count);
    }

    /** @test */
    public function it_rejects_like_for_unauthenticated_user(): void
    {
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
        ]);
        
        $response = $this->makeRequest('POST', "/api/v1/posts/{$post->id}/like");

        $response->assertStatus(401);
    }
}

