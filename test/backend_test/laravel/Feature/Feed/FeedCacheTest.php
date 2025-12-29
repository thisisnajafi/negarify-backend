<?php

namespace Test\BackendTest\Laravel\Feature\Feed;

use App\Models\FeedViewLimit;
use App\Models\GalleryPost;
use App\Models\GenerationJob;
use App\Models\Model as AiModel;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class FeedCacheTest extends BackendTestCase
{
    /** @test */
    public function it_caches_feed_posts(): void
    {
        Cache::flush(); // Clear cache before test
        
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
        
        $post = GalleryPost::create([
            'user_id' => $postOwner->id,
            'generation_job_id' => $job->id,
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
        
        // First request - should cache
        $response1 = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/gallery/feed');
        
        $response1->assertStatus(200);
        $posts1 = $response1->json('data');
        $this->assertGreaterThan(0, count($posts1));
        
        // Verify cache exists
        $cacheKey = 'feed:posts:first';
        $this->assertTrue(Cache::tags(['feed'])->has($cacheKey));
        
        // Second request - should use cache (delete post to verify cache is used)
        $post->delete();
        
        $response2 = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/gallery/feed');
        
        $response2->assertStatus(200);
        $posts2 = $response2->json('data');
        // Should still return cached posts (even though post was deleted)
        $this->assertGreaterThan(0, count($posts2));
        
        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_invalidates_cache_when_post_is_curated(): void
    {
        Cache::flush(); // Clear cache before test
        
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->refresh(); // Ensure role is set
        $adminToken = $admin->createToken('auth-token')->plainTextToken;
        
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
        
        $post = GalleryPost::create([
            'user_id' => $postOwner->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'is_curated' => false, // Not curated yet
        ]);
        
        FeedViewLimit::create([
            'user_id' => $user->id,
            'content_type' => 'image',
            'views_remaining' => 10,
            'reset_at' => now()->addDay(),
        ]);
        
        // Request feed - should not include uncurated post
        $response1 = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/gallery/feed');
        
        $response1->assertStatus(200);
        $posts1 = $response1->json('data');
        $this->assertCount(0, $posts1);
        
        // Curate the post (this should invalidate cache)
        $this->actingAs($admin, 'sanctum');
        $response = $this->makeRequest('POST', '/api/v1/admin/gallery/' . $post->id . '/curate');
        
        $response->assertStatus(200);
        
        // Verify cache was invalidated
        $cacheKey = 'feed:posts:first';
        $this->assertFalse(Cache::tags(['feed'])->has($cacheKey));
        
        // Request feed again - should include newly curated post
        $response2 = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/gallery/feed');
        
        $response2->assertStatus(200);
        $posts2 = $response2->json('data');
        $this->assertGreaterThan(0, count($posts2));
        
        $foundPost = collect($posts2)->firstWhere('id', $post->id);
        $this->assertNotNull($foundPost);
        
        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_caches_different_cursor_pages_separately(): void
    {
        Cache::flush(); // Clear cache before test
        
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        // Create multiple posts
        $postIds = [];
        for ($i = 0; $i < 10; $i++) {
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
            
            $post = GalleryPost::create([
                'user_id' => $postOwner->id,
                'generation_job_id' => $job->id,
                'visibility' => 'public',
                'is_curated' => true,
                'curated_at' => now()->subMinutes($i),
            ]);
            $postIds[] = $post->id;
        }
        
        FeedViewLimit::create([
            'user_id' => $user->id,
            'content_type' => 'image',
            'views_remaining' => 20,
            'reset_at' => now()->addDay(),
        ]);
        
        // First page
        $response1 = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/gallery/feed');
        
        $response1->assertStatus(200);
        $cursor1 = $response1->json('meta.cursor');
        $this->assertNotNull($cursor1);
        
        // Verify first page cache exists
        $this->assertTrue(Cache::tags(['feed'])->has('feed:posts:first'));
        
        // Second page
        $response2 = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/gallery/feed', [
            'cursor' => $cursor1,
        ]);
        
        $response2->assertStatus(200);
        $cursor2 = $response2->json('meta.cursor');
        
        // Verify second page cache exists with different key
        $this->assertTrue(Cache::tags(['feed'])->has('feed:posts:' . $cursor1));
        $this->assertNotEquals($cursor1, $cursor2);
        
        $this->assertNoErrorLogs();
    }
}

