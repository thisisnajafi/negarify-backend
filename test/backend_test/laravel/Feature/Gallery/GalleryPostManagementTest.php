<?php

namespace Test\BackendTest\Laravel\Feature\Gallery;

use App\Models\GalleryPost;
use App\Models\GenerationJob;
use App\Models\Model as AiModel;
use App\Models\Provider;
use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class GalleryPostManagementTest extends BackendTestCase
{
    /** @test */
    public function it_returns_gallery_post_details(): void
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
            'title' => 'My Artwork',
            'visibility' => 'public',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', "/api/v1/gallery/posts/{$post->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title',
                    'user',
                    'generation_job',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $post->id,
                    'title' => 'My Artwork',
                ],
            ]);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_hides_private_posts_from_other_users(): void
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
            'user_id' => $user2->id,
            'generation_job_id' => $job->id,
            'visibility' => 'private', // Private
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token1}",
        ])->makeRequest('GET', "/api/v1/gallery/posts/{$post->id}");

        $response->assertStatus(404); // Don't reveal existence
    }

    /** @test */
    public function it_allows_owner_to_view_private_post(): void
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
            'user_id' => $user->id, // Owner
            'generation_job_id' => $job->id,
            'visibility' => 'private',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', "/api/v1/gallery/posts/{$post->id}");

        $response->assertStatus(200); // Owner can view
    }

    /** @test */
    public function it_updates_gallery_post(): void
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
            'user_id' => $user->id,
            'generation_job_id' => $job->id,
            'title' => 'Old Title',
            'visibility' => 'public',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('PUT', "/api/v1/gallery/posts/{$post->id}", [
            'title' => 'New Title',
            'description' => 'New description',
            'tags' => ['art', 'nature'],
            'visibility' => 'private',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Gallery post updated successfully',
            ]);

        $post->refresh();
        $this->assertEquals('New Title', $post->title);
        $this->assertEquals('New description', $post->description);
        $this->assertEquals('private', $post->visibility);
    }

    /** @test */
    public function it_rejects_updating_other_user_post(): void
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
            'user_id' => $user2->id, // Different user
            'generation_job_id' => $job->id,
            'visibility' => 'public',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token1}",
        ])->makeRequest('PUT', "/api/v1/gallery/posts/{$post->id}", [
            'title' => 'Hacked Title',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to update this post',
            ]);
    }

    /** @test */
    public function it_deletes_gallery_post(): void
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
            'user_id' => $user->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('DELETE', "/api/v1/gallery/posts/{$post->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Gallery post deleted successfully',
            ]);

        $this->assertDatabaseMissing('gallery_posts', [
            'id' => $post->id,
        ]);
    }

    /** @test */
    public function it_rejects_deleting_other_user_post(): void
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
            'user_id' => $user2->id, // Different user
            'generation_job_id' => $job->id,
            'visibility' => 'public',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token1}",
        ])->makeRequest('DELETE', "/api/v1/gallery/posts/{$post->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to delete this post',
            ]);
    }

    /** @test */
    public function it_returns_user_own_posts(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        // Create user's posts
        for ($i = 0; $i < 3; $i++) {
            $job = GenerationJob::create([
                'user_id' => $user->id,
                'provider_id' => $provider->id,
                'model_id' => $model->id,
                'job_type' => 'image',
                'status' => 'completed',
                'result_url' => 'https://example.com/image.jpg',
            ]);
            
            GalleryPost::create([
                'user_id' => $user->id,
                'generation_job_id' => $job->id,
                'visibility' => 'public',
            ]);
        }
        
        // Create other user's post
        $otherJob = GenerationJob::create([
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        GalleryPost::create([
            'user_id' => User::factory()->create()->id,
            'generation_job_id' => $otherJob->id,
            'visibility' => 'public',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/gallery/my-posts');

        $response->assertStatus(200);
        $posts = $response->json('data');
        $this->assertCount(3, $posts); // Only user's posts
        
        foreach ($posts as $post) {
            $this->assertEquals($user->id, $post['user']['id']);
        }
    }
}

