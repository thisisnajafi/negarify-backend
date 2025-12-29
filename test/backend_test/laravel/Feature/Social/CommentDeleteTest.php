<?php

namespace Test\BackendTest\Laravel\Feature\Social;

use App\Models\Comment;
use App\Models\GalleryPost;
use App\Models\GenerationJob;
use App\Models\Model as AiModel;
use App\Models\Provider;
use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class CommentDeleteTest extends BackendTestCase
{
    /** @test */
    public function it_deletes_own_comment(): void
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
            'comments_count' => 1,
        ]);
        
        $comment = Comment::create([
            'user_id' => $user->id,
            'gallery_post_id' => $post->id,
            'body' => 'Test comment',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('DELETE', "/api/v1/gallery/comments/{$comment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Comment deleted successfully',
            ]);

        // Verify comment deleted
        $this->assertDatabaseMissing('comments', [
            'id' => $comment->id,
        ]);

        // Verify comments count decremented
        $post->refresh();
        $this->assertEquals(0, $post->comments_count);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_deletes_comment_with_replies(): void
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
            'comments_count' => 3, // 1 parent + 2 replies
        ]);
        
        $parentComment = Comment::create([
            'user_id' => $user->id,
            'gallery_post_id' => $post->id,
            'body' => 'Parent comment',
        ]);
        
        // Create replies
        Comment::create([
            'user_id' => User::factory()->create()->id,
            'gallery_post_id' => $post->id,
            'parent_id' => $parentComment->id,
            'body' => 'Reply 1',
        ]);
        
        Comment::create([
            'user_id' => User::factory()->create()->id,
            'gallery_post_id' => $post->id,
            'parent_id' => $parentComment->id,
            'body' => 'Reply 2',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('DELETE', "/api/v1/gallery/comments/{$parentComment->id}");

        $response->assertStatus(200);

        // Verify parent and replies deleted
        $this->assertDatabaseMissing('comments', [
            'id' => $parentComment->id,
        ]);
        $this->assertEquals(0, Comment::where('parent_id', $parentComment->id)->count());

        // Verify comments count decremented (1 + 2 replies = 3)
        $post->refresh();
        $this->assertEquals(0, $post->comments_count);
    }

    /** @test */
    public function it_rejects_deleting_other_user_comment(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $token1 = $user1->createToken('auth-token')->plainTextToken;
        
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
        
        $comment = Comment::create([
            'user_id' => $user2->id, // Different user
            'gallery_post_id' => $post->id,
            'body' => 'Test comment',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token1}",
        ])->makeRequest('DELETE', "/api/v1/gallery/comments/{$comment->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to delete this comment',
            ]);
    }

    /** @test */
    public function it_allows_admin_to_delete_any_comment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $token = $admin->createToken('auth-token')->plainTextToken;
        
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
            'comments_count' => 1,
        ]);
        
        $comment = Comment::create([
            'user_id' => $user->id, // Different user
            'gallery_post_id' => $post->id,
            'body' => 'Test comment',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('DELETE', "/api/v1/gallery/comments/{$comment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Comment deleted successfully',
            ]);

        // Verify comment deleted
        $this->assertDatabaseMissing('comments', [
            'id' => $comment->id,
        ]);
    }
}

