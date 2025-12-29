<?php

namespace Test\BackendTest\Laravel\Feature\Gallery;

use App\Models\GenerationJob;
use App\Models\GalleryPost;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class GalleryPostTest extends BackendTestCase
{
    use DatabaseMigrations;

    /**
     * Helper method to create a provider using DB::table to avoid factory/Model class conflicts
     */
    private function createProvider(array $attributes = []): int
    {
        $apiKey = $attributes['api_key'] ?? 'test-api-key';
        unset($attributes['api_key']);
        
        $provider = new \App\Models\Provider(array_merge([
            'name' => 'Test Provider',
            'api_base_url' => 'https://api.segmind.com',
            'enabled' => true,
        ], $attributes));
        
        $provider->setApiKey($apiKey);
        $provider->save();
        
        return $provider->id;
    }

    /**
     * Helper method to create a model using DB::table to avoid Model class name conflict
     */
    private function createModel(int $providerId, array $attributes = []): int
    {
        return DB::table('models')->insertGetId(array_merge([
            'provider_id' => $providerId,
            'model_name' => 'Test Model',
            'model_type' => 'image',
            'api_endpoint' => '/test',
            'default_tokens' => 10,
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }
    /** @test */
    public function it_creates_gallery_post_from_completed_job(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/gallery/post', [
            'generation_job_id' => $job->id,
            'title' => 'My Artwork',
            'description' => 'A beautiful image',
            'tags' => ['art', 'nature'],
            'visibility' => 'public',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'tags',
                    'visibility',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Gallery post created successfully',
            ]);

        // Verify post created
        $post = GalleryPost::where('user_id', $user->id)->first();
        $this->assertNotNull($post);
        $this->assertEquals($job->id, $post->generation_job_id);
        $this->assertEquals('My Artwork', $post->title);
        $this->assertEquals('public', $post->visibility);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_rejects_post_from_incomplete_job(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'pending', // Not completed
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/gallery/post', [
            'generation_job_id' => $job->id,
            'title' => 'My Artwork',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Generation job must be completed before creating a gallery post',
            ]);
    }

    /** @test */
    public function it_rejects_post_from_other_user_job(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $token1 = $user1->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
        $job = GenerationJob::create([
            'user_id' => $user2->id, // Different user
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
            'tokens_consumed' => 10,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token1}",
        ])->makeRequest('POST', '/api/v1/gallery/post', [
            'generation_job_id' => $job->id,
            'title' => 'My Artwork',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Generation job not found or does not belong to you',
            ]);
    }

    /** @test */
    public function it_prevents_duplicate_posts_for_same_job(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
            'tokens_consumed' => 10,
        ]);
        
        // Create first post
        GalleryPost::create([
            'user_id' => $user->id,
            'generation_job_id' => $job->id,
            'title' => 'First Post',
            'visibility' => 'public',
        ]);
        
        // Try to create duplicate
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/gallery/post', [
            'generation_job_id' => $job->id,
            'title' => 'Second Post',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'A gallery post already exists for this generation job',
            ]);
    }
}

