<?php

namespace Test\BackendTest\Laravel\Feature\Feed;

use App\Models\FeedViewLimit;
use App\Models\GalleryPost;
use App\Models\GenerationJob;
use App\Models\Model as AiModel;
use App\Models\Provider;
use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class FeedVisibilityTest extends BackendTestCase
{
    /** @test */
    public function it_respects_prompt_visibility_in_feed(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $postOwner = User::factory()->create();
        $job = GenerationJob::create([
            'user_id' => $postOwner->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Secret prompt',
            'negative_prompt' => 'Secret negative',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        // Post with hidden prompt
        $post = GalleryPost::create([
            'user_id' => $postOwner->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'is_curated' => true,
            'curated_at' => now(),
            'prompt_visible' => false,
        ]);
        
        FeedViewLimit::create([
            'user_id' => $user->id,
            'content_type' => 'image',
            'views_remaining' => 10,
            'reset_at' => now()->addDay(),
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/gallery/feed');
        
        $response->assertStatus(200);
        $posts = $response->json('data');
        $this->assertGreaterThan(0, count($posts));
        
        // Find our post
        $foundPost = collect($posts)->firstWhere('id', $post->id);
        $this->assertNotNull($foundPost);
        
        // Prompt should not be visible
        $this->assertArrayNotHasKey('prompt', $foundPost['generation_job'] ?? []);
    }

    /** @test */
    public function it_respects_model_visibility_in_feed(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create([
            'provider_id' => $provider->id,
            'model_name' => 'Secret Model',
        ]);
        
        $postOwner = User::factory()->create();
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
        
        // Post with hidden model
        $post = GalleryPost::create([
            'user_id' => $postOwner->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'is_curated' => true,
            'curated_at' => now(),
            'model_visible' => false,
        ]);
        
        FeedViewLimit::create([
            'user_id' => $user->id,
            'content_type' => 'image',
            'views_remaining' => 10,
            'reset_at' => now()->addDay(),
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/gallery/feed');
        
        $response->assertStatus(200);
        $posts = $response->json('data');
        
        // Find our post
        $foundPost = collect($posts)->firstWhere('id', $post->id);
        $this->assertNotNull($foundPost);
        
        // Model should not be visible
        $this->assertArrayNotHasKey('model', $foundPost['generation_job'] ?? []);
    }

    /** @test */
    public function it_excludes_audio_posts_from_feed(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $audioOwner = User::factory()->create();
        $imageOwner = User::factory()->create();
        
        // Create audio job
        $audioJob = GenerationJob::create([
            'user_id' => $audioOwner->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'audio',
            'prompt' => 'Test audio prompt',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/audio.mp3',
        ]);
        
        // Create image job
        $imageJob = GenerationJob::create([
            'user_id' => $imageOwner->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test image prompt',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        GalleryPost::create([
            'user_id' => $audioOwner->id,
            'generation_job_id' => $audioJob->id,
            'visibility' => 'public',
            'is_curated' => true,
            'curated_at' => now(),
        ]);
        
        GalleryPost::create([
            'user_id' => $imageOwner->id,
            'generation_job_id' => $imageJob->id,
            'visibility' => 'public',
            'is_curated' => true,
            'curated_at' => now(),
        ]);
        
        FeedViewLimit::create([
            'user_id' => $user->id,
            'content_type' => 'image',
            'views_remaining' => 10,
            'reset_at' => now()->addDay(),
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/gallery/feed');
        
        $response->assertStatus(200);
        $posts = $response->json('data');
        
        // Should only contain image/video posts, not audio
        foreach ($posts as $post) {
            $jobType = $post['generation_job']['job_type'] ?? null;
            $this->assertNotEquals('audio', $jobType);
        }
    }

    /** @test */
    public function it_allows_admin_unlimited_views(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        // Create many posts
        for ($i = 0; $i < 20; $i++) {
            $postOwner = User::factory()->create();
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
            
            GalleryPost::create([
                'user_id' => $postOwner->id,
                'generation_job_id' => $job->id,
                'visibility' => 'public',
                'is_curated' => true,
                'curated_at' => now()->subMinutes($i),
            ]);
        }
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/gallery/feed');
        
        $response->assertStatus(200);
        $posts = $response->json('data');
        
        // Admin should get full page (15 items)
        $this->assertGreaterThanOrEqual(15, count($posts));
        
        // Views remaining should be high for admin
        $meta = $response->json('meta');
        $this->assertGreaterThan(1000, $meta['views_remaining']['image']);
    }

    /** @test */
    public function it_resets_daily_view_limits(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        $postOwner = User::factory()->create();
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
        
        GalleryPost::create([
            'user_id' => $postOwner->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'is_curated' => true,
            'curated_at' => now(),
        ]);
        
        // Create limit with expired reset_at (should reset)
        $limit = FeedViewLimit::create([
            'user_id' => $user->id,
            'content_type' => 'image',
            'views_remaining' => 0,
            'daily_limit' => 10,
            'reset_at' => now()->subDay(), // Expired
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/gallery/feed');
        
        $response->assertStatus(200);
        
        // Limit should be reset to 10, then decremented by 1 (for the 1 post viewed)
        $limit->refresh();
        $this->assertEquals(9, $limit->views_remaining); // 10 (reset) - 1 (viewed) = 9
    }
}

