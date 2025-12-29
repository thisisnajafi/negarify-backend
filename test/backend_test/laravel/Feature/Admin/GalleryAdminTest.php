<?php

namespace Test\BackendTest\Laravel\Feature\Admin;

use App\Models\GalleryPost;
use App\Models\GenerationJob;
use App\Models\Model as AiModel;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class GalleryAdminTest extends BackendTestCase
{
    /** @test */
    public function it_curates_post_as_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $postOwner = User::factory()->create();
        $job = GenerationJob::create([
            'user_id' => $postOwner->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        $post = GalleryPost::create([
            'user_id' => $postOwner->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'is_curated' => false,
        ]);
        
        $response = $this->actingAs($admin)->makeRequest('POST', "/api/v1/admin/gallery/{$post->id}/curate");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Post curated successfully',
                'data' => [
                    'is_curated' => true,
                ],
            ]);

        $post->refresh();
        $this->assertTrue($post->is_curated);
        $this->assertNotNull($post->curated_at);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_rejects_curating_private_post(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $postOwner = User::factory()->create();
        $job = GenerationJob::create([
            'user_id' => $postOwner->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        $post = GalleryPost::create([
            'user_id' => $postOwner->id,
            'generation_job_id' => $job->id,
            'visibility' => 'private', // Private
            'is_curated' => false,
        ]);
        
        $response = $this->actingAs($admin)->makeRequest('POST', "/api/v1/admin/gallery/{$post->id}/curate");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Private posts cannot be curated',
            ]);
    }

    /** @test */
    public function it_uncurates_post(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $postOwner = User::factory()->create();
        $job = GenerationJob::create([
            'user_id' => $postOwner->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        $post = GalleryPost::create([
            'user_id' => $postOwner->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'is_curated' => true,
            'curated_at' => now(),
        ]);
        
        $response = $this->actingAs($admin)->makeRequest('POST', "/api/v1/admin/gallery/{$post->id}/uncurate");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Post uncurated successfully',
                'data' => [
                    'is_curated' => false,
                ],
            ]);

        $post->refresh();
        $this->assertFalse($post->is_curated);
        $this->assertNull($post->curated_at);
    }

    /** @test */
    public function it_features_post(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $postOwner = User::factory()->create();
        $job = GenerationJob::create([
            'user_id' => $postOwner->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        $post = GalleryPost::create([
            'user_id' => $postOwner->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'is_featured' => false,
        ]);
        
        $response = $this->actingAs($admin)->makeRequest('POST', "/api/v1/admin/gallery/{$post->id}/feature");

        $response->assertStatus(200);

        $post->refresh();
        $this->assertTrue($post->is_featured);
    }

    /** @test */
    public function it_performs_bulk_curation(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $postIds = [];
        for ($i = 0; $i < 3; $i++) {
            $postOwner = User::factory()->create();
            $job = GenerationJob::create([
                'user_id' => $postOwner->id,
                'provider_id' => $provider->id,
                'model_id' => $model->id,
                'job_type' => 'image',
                'prompt' => 'Test prompt',
                'params_json' => json_encode([]),
                'status' => 'completed',
                'result_url' => 'https://example.com/image.jpg',
            ]);
            
            $post = GalleryPost::create([
                'user_id' => $postOwner->id,
                'generation_job_id' => $job->id,
                'visibility' => 'public',
                'is_curated' => false,
            ]);
            $postIds[] = $post->id;
        }
        
        $response = $this->actingAs($admin)->makeRequest('POST', '/api/v1/admin/gallery/bulk-curate', [
            'post_ids' => $postIds,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify all posts curated
        foreach ($postIds as $postId) {
            $post = GalleryPost::find($postId);
            $this->assertTrue($post->is_curated);
        }
    }

    /** @test */
    public function it_lists_curated_posts(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        // Create curated posts
        $curatedPosts = [];
        for ($i = 0; $i < 5; $i++) {
            $postOwner = User::factory()->create();
            $job = GenerationJob::create([
                'user_id' => $postOwner->id,
                'provider_id' => $provider->id,
                'model_id' => $model->id,
                'job_type' => 'image',
                'prompt' => 'Test prompt',
                'params_json' => json_encode([]),
                'status' => 'completed',
                'result_url' => 'https://example.com/image.jpg',
            ]);
            
            $post = GalleryPost::create([
                'user_id' => $postOwner->id,
                'generation_job_id' => $job->id,
                'visibility' => 'public',
                'is_curated' => true,
                'curated_at' => now()->subDays($i), // Different curation times
            ]);
            $curatedPosts[] = $post;
        }
        
        // Create non-curated post (should not appear)
        $postOwner = User::factory()->create();
        $job = GenerationJob::create([
            'user_id' => $postOwner->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => json_encode([]),
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        GalleryPost::create([
            'user_id' => $postOwner->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'is_curated' => false,
        ]);
        
        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/gallery/curated');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'is_curated',
                        'curated_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);

        $data = $response->json('data');
        
        // Should have at least 5 curated posts (may include other curated posts from other tests)
        $this->assertGreaterThanOrEqual(5, count($data), 'Should have at least 5 curated posts');
        
        // Verify all returned posts are curated
        foreach ($data as $post) {
            $this->assertTrue($post['is_curated'], 'All returned posts should be curated');
        }
    }
}

