<?php

namespace Test\BackendTest\Laravel\Feature\Social;

use App\Models\Comment;
use App\Models\GalleryPost;
use App\Models\GenerationJob;
use App\Models\Model as AiModel;
use App\Models\Provider;
use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class CommentAccuracyTest extends BackendTestCase
{
    /** @test */
    public function it_maintains_accurate_comments_count(): void
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
            'comments_count' => 0,
        ]);
        
        // Create 3 comments
        for ($i = 0; $i < 3; $i++) {
            $response = $this->actingAs($user)->makeRequest('POST', "/api/v1/gallery/{$post->id}/comment", [
                'body' => "Comment {$i}",
            ]);
            
            $response->assertStatus(201);
        }
        
        $post->refresh();
        $this->assertEquals(3, $post->comments_count);
        
        // Verify actual count matches
        $actualCount = Comment::where('gallery_post_id', $post->id)->count();
        $this->assertEquals(3, $actualCount);
        $this->assertEquals($actualCount, $post->comments_count);
    }

    /** @test */
    public function it_decrements_comments_count_when_deleting_comment(): void
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
            'comments_count' => 0,
        ]);
        
        // Create 2 comments
        $comment1 = Comment::create([
            'user_id' => $user->id,
            'gallery_post_id' => $post->id,
            'body' => 'Comment 1',
        ]);
        $post->increment('comments_count');
        
        $comment2 = Comment::create([
            'user_id' => $user->id,
            'gallery_post_id' => $post->id,
            'body' => 'Comment 2',
        ]);
        $post->increment('comments_count');
        
        $post->refresh();
        $this->assertEquals(2, $post->comments_count);
        
        // Delete one comment
        $response = $this->actingAs($user)->makeRequest('DELETE', "/api/v1/gallery/comments/{$comment1->id}");
        
        $response->assertStatus(200);
        
        $post->refresh();
        $this->assertEquals(1, $post->comments_count);
        
        // Verify actual count matches
        $actualCount = Comment::where('gallery_post_id', $post->id)->count();
        $this->assertEquals(1, $actualCount);
        $this->assertEquals($actualCount, $post->comments_count);
    }

    /** @test */
    public function it_decrements_comments_count_when_deleting_comment_with_replies(): void
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
            'comments_count' => 0,
        ]);
        
        // Create parent comment
        $parentComment = Comment::create([
            'user_id' => $user->id,
            'gallery_post_id' => $post->id,
            'body' => 'Parent comment',
        ]);
        $post->increment('comments_count');
        
        // Create 2 replies
        Comment::create([
            'user_id' => $user->id,
            'gallery_post_id' => $post->id,
            'parent_id' => $parentComment->id,
            'body' => 'Reply 1',
        ]);
        $post->increment('comments_count');
        
        Comment::create([
            'user_id' => $user->id,
            'gallery_post_id' => $post->id,
            'parent_id' => $parentComment->id,
            'body' => 'Reply 2',
        ]);
        $post->increment('comments_count');
        
        $post->refresh();
        $this->assertEquals(3, $post->comments_count);
        
        // Delete parent comment (should delete replies too via cascade)
        $response = $this->actingAs($user)->makeRequest('DELETE', "/api/v1/gallery/comments/{$parentComment->id}");
        
        $response->assertStatus(200);
        
        $post->refresh();
        $this->assertEquals(0, $post->comments_count);
        
        // Verify all comments deleted
        $actualCount = Comment::where('gallery_post_id', $post->id)->count();
        $this->assertEquals(0, $actualCount);
    }

    /** @test */
    public function it_handles_nested_replies_correctly(): void
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
            'comments_count' => 0,
        ]);
        
        // Create parent comment
        $parentResponse = $this->actingAs($user)->makeRequest('POST', "/api/v1/gallery/{$post->id}/comment", [
            'body' => 'Parent comment',
        ]);
        
        $parentResponse->assertStatus(201);
        $parentId = $parentResponse->json('data.id');
        
        // Create reply
        $replyResponse = $this->actingAs($user)->makeRequest('POST', "/api/v1/gallery/{$post->id}/comment", [
            'body' => 'Reply to parent',
            'parent_id' => $parentId,
        ]);
        
        $replyResponse->assertStatus(201);
        $this->assertEquals($parentId, $replyResponse->json('data.parent_id'));
        
        $post->refresh();
        $this->assertEquals(2, $post->comments_count);
        
        // Verify both comments exist
        $this->assertDatabaseHas('comments', [
            'id' => $parentId,
            'parent_id' => null,
        ]);
        
        $this->assertDatabaseHas('comments', [
            'parent_id' => $parentId,
        ]);
    }
}

