<?php

namespace Test\BackendTest\Laravel\Feature\Social;

use App\Models\Comment;
use App\Models\GalleryPost;
use App\Models\GenerationJob;
use App\Models\Model as AiModel;
use App\Models\Provider;
use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class CommentTest extends BackendTestCase
{
    /** @test */
    public function it_creates_comment_on_post(): void
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
            'comments_count' => 0,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/posts/{$post->id}/comments", [
            'body' => 'Great artwork!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'body',
                    'user',
                    'created_at',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Comment created successfully',
                'data' => [
                    'body' => 'Great artwork!',
                ],
            ]);

        // Verify comment created
        $this->assertDatabaseHas('comments', [
            'user_id' => $user->id,
            'gallery_post_id' => $post->id,
            'body' => 'Great artwork!',
        ]);

        // Verify comments count incremented
        $post->refresh();
        $this->assertEquals(1, $post->comments_count);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_creates_nested_reply_comment(): void
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
        ]);
        
        $parentComment = Comment::create([
            'user_id' => User::factory()->create()->id,
            'gallery_post_id' => $post->id,
            'body' => 'Parent comment',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/posts/{$post->id}/comments", [
            'body' => 'Reply to parent',
            'parent_id' => $parentComment->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'body' => 'Reply to parent',
                    'parent_id' => $parentComment->id,
                ],
            ]);

        // Verify reply created
        $this->assertDatabaseHas('comments', [
            'user_id' => $user->id,
            'gallery_post_id' => $post->id,
            'parent_id' => $parentComment->id,
            'body' => 'Reply to parent',
        ]);
    }

    /** @test */
    public function it_rejects_reply_to_invalid_parent_comment(): void
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
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/posts/{$post->id}/comments", [
            'body' => 'Reply',
            'parent_id' => 99999, // Invalid parent
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Parent comment not found or does not belong to this post',
            ]);
    }

    /** @test */
    public function it_validates_comment_body(): void
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
        ]);
        
        // Empty body
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/posts/{$post->id}/comments", [
            'body' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    /** @test */
    public function it_rejects_comment_for_unauthenticated_user(): void
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
        
        $response = $this->makeRequest('POST', "/api/v1/posts/{$post->id}/comments", [
            'body' => 'Test comment',
        ]);

        $response->assertStatus(401);
    }
}

