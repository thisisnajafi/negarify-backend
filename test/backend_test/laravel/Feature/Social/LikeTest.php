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
        $postOwner = User::factory()->create();
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $job = GenerationJob::create([
            'user_id' => $postOwner->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        $post = GalleryPost::create([
            'user_id' => $postOwner->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'likes_count' => 0,
        ]);
        
        $response = $this->actingAs($user)->makeRequest('POST', "/api/v1/gallery/{$post->id}/like");

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
        $postOwner = User::factory()->create();
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $job = GenerationJob::create([
            'user_id' => $postOwner->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        $post = GalleryPost::create([
            'user_id' => $postOwner->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'likes_count' => 0,
        ]);
        
        // First like
        $response = $this->actingAs($user)->makeRequest('POST', "/api/v1/gallery/{$post->id}/like");
        $response->assertStatus(200);
        
        // Second like (should be idempotent)
        $response = $this->actingAs($user)->makeRequest('POST', "/api/v1/gallery/{$post->id}/like");

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
        $postOwner = User::factory()->create();
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $job = GenerationJob::create([
            'user_id' => $postOwner->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        $post = GalleryPost::create([
            'user_id' => $postOwner->id,
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
        $response = $this->actingAs($user)->makeRequest('DELETE', "/api/v1/gallery/{$post->id}/like");

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
        $postOwner = User::factory()->create();
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $job = GenerationJob::create([
            'user_id' => $postOwner->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        $post = GalleryPost::create([
            'user_id' => $postOwner->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
        ]);
        
        $response = $this->makeRequest('POST', "/api/v1/gallery/{$post->id}/like");

        $response->assertStatus(401);
    }

    /** @test */
    public function it_allows_liking_own_post(): void
    {
        $user = User::factory()->create();
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        $post = GalleryPost::create([
            'user_id' => $user->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'likes_count' => 0,
        ]);
        
        $response = $this->actingAs($user)->makeRequest('POST', "/api/v1/gallery/{$post->id}/like");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Post liked successfully',
                'data' => [
                    'liked' => true,
                ],
            ]);

        // Verify like created (users can like their own posts)
        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'gallery_post_id' => $post->id,
        ]);

        // Verify likes count incremented
        $post->refresh();
        $this->assertEquals(1, $post->likes_count);
    }

    /** @test */
    public function it_maintains_accurate_likes_count(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $postOwner = User::factory()->create();
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $job = GenerationJob::create([
            'user_id' => $postOwner->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        $post = GalleryPost::create([
            'user_id' => $postOwner->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'likes_count' => 0,
        ]);
        
        // Like by user1
        $response1 = $this->actingAs($user1)->makeRequest('POST', "/api/v1/gallery/{$post->id}/like");
        $response1->assertStatus(200);
        
        // Like by user2
        $response2 = $this->actingAs($user2)->makeRequest('POST', "/api/v1/gallery/{$post->id}/like");
        $response2->assertStatus(200);
        
        // Like by user3
        $response3 = $this->actingAs($user3)->makeRequest('POST', "/api/v1/gallery/{$post->id}/like");
        $response3->assertStatus(200);
        
        $post->refresh();
        $this->assertEquals(3, $post->likes_count);
        
        // Verify actual count matches
        $actualCount = Like::where('gallery_post_id', $post->id)->count();
        $this->assertEquals(3, $actualCount);
        $this->assertEquals($actualCount, $post->likes_count);
        
        // Unlike by user2
        $response4 = $this->actingAs($user2)->makeRequest('DELETE', "/api/v1/gallery/{$post->id}/like");
        $response4->assertStatus(200);
        
        $post->refresh();
        $this->assertEquals(2, $post->likes_count);
        
        // Verify actual count matches
        $actualCount = Like::where('gallery_post_id', $post->id)->count();
        $this->assertEquals(2, $actualCount);
        $this->assertEquals($actualCount, $post->likes_count);
    }
}

