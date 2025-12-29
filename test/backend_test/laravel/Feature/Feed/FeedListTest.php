<?php

namespace Test\BackendTest\Laravel\Feature\Feed;

use App\Models\FeedViewLimit;
use App\Models\GalleryPost;
use App\Models\GenerationJob;
use App\Models\Model as AiModel;
use App\Models\Provider;
use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class FeedListTest extends BackendTestCase
{
    /** @test */
    public function it_returns_curated_public_feed(): void
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
            'is_curated' => true,
            'curated_at' => now(),
        ]);
        
        // Set view limits
        FeedViewLimit::create([
            'user_id' => $user->id,
            'content_type' => 'image',
            'views_remaining' => 10,
            'reset_at' => now()->addDay(),
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/feed');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'user',
                        'generation_job',
                    ],
                ],
                'meta' => [
                    'cursor',
                    'has_more',
                    'views_remaining',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_decrements_view_limits_when_viewing_feed(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        // Create 3 posts
        for ($i = 0; $i < 3; $i++) {
            $job = GenerationJob::create([
                'provider_id' => $provider->id,
                'model_id' => $model->id,
                'job_type' => 'image',
                'status' => 'completed',
                'result_url' => 'https://example.com/image.jpg',
            ]);
            
            GalleryPost::create([
                'user_id' => User::factory()->create()->id,
                'generation_job_id' => $job->id,
                'visibility' => 'public',
                'is_curated' => true,
                'curated_at' => now(),
            ]);
        }
        
        FeedViewLimit::create([
            'user_id' => $user->id,
            'content_type' => 'image',
            'views_remaining' => 10,
            'reset_at' => now()->addDay(),
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/feed');

        $response->assertStatus(200);
        
        // Verify views decremented
        $limit = FeedViewLimit::where('user_id', $user->id)
            ->where('content_type', 'image')
            ->first();
        $this->assertEquals(7, $limit->views_remaining); // 10 - 3
    }

    /** @test */
    public function it_returns_empty_feed_when_view_limit_reached(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        FeedViewLimit::create([
            'user_id' => $user->id,
            'content_type' => 'image',
            'views_remaining' => 0, // No views left
            'reset_at' => now()->addDay(),
        ]);
        
        FeedViewLimit::create([
            'user_id' => $user->id,
            'content_type' => 'video',
            'views_remaining' => 0,
            'reset_at' => now()->addDay(),
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/feed');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [],
                'meta' => [
                    'views_remaining' => [
                        'image' => 0,
                        'video' => 0,
                    ],
                    'message' => 'Daily view limit reached',
                ],
            ]);
    }

    /** @test */
    public function it_excludes_private_posts_from_feed(): void
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
        
        // Create private post
        GalleryPost::create([
            'user_id' => User::factory()->create()->id,
            'generation_job_id' => $job->id,
            'visibility' => 'private', // Private
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
        ])->makeRequest('GET', '/api/v1/feed');

        $posts = $response->json('data');
        $this->assertCount(0, $posts); // Private post excluded
    }

    /** @test */
    public function it_excludes_non_curated_posts_from_feed(): void
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
        
        // Create non-curated post
        GalleryPost::create([
            'user_id' => User::factory()->create()->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
            'is_curated' => false, // Not curated
            'curated_at' => null,
        ]);
        
        FeedViewLimit::create([
            'user_id' => $user->id,
            'content_type' => 'image',
            'views_remaining' => 10,
            'reset_at' => now()->addDay(),
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/feed');

        $posts = $response->json('data');
        $this->assertCount(0, $posts); // Non-curated post excluded
    }

    /** @test */
    public function it_supports_cursor_pagination(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        // Create multiple posts
        $postIds = [];
        for ($i = 0; $i < 5; $i++) {
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
                'is_curated' => true,
                'curated_at' => now()->subMinutes($i),
            ]);
            $postIds[] = $post->id;
        }
        
        FeedViewLimit::create([
            'user_id' => $user->id,
            'content_type' => 'image',
            'views_remaining' => 10,
            'reset_at' => now()->addDay(),
        ]);
        
        // First page
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/feed');

        $response->assertStatus(200);
        $cursor = $response->json('meta.cursor');
        $this->assertNotNull($cursor);
        
        // Second page with cursor
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/feed', [
            'cursor' => $cursor,
        ]);

        $response->assertStatus(200);
        $this->assertNotEquals($cursor, $response->json('meta.cursor'));
    }
}

