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

    /** @test */
    public function it_respects_prompt_and_model_visibility_flags(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $ownerToken = $owner->createToken('auth-token')->plainTextToken;
        $viewerToken = $viewer->createToken('auth-token')->plainTextToken;
        
        $providerId = $this->createProvider();
        $modelId = $this->createModel($providerId, [
            'model_name' => 'Test Model Name',
        ]);
        
        $job = GenerationJob::create([
            'user_id' => $owner->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Secret prompt text',
            'negative_prompt' => 'Secret negative prompt',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
            'tokens_consumed' => 10,
        ]);
        
        // Create post with hidden prompt and model
        $response = $this->actingAs($owner)->makeRequest('POST', '/api/v1/gallery/post', [
            'generation_job_id' => $job->id,
            'title' => 'Post with hidden prompt/model',
            'visibility' => 'public',
            'prompt_visible' => false,
            'model_visible' => false,
        ]);

        $response->assertStatus(201);
        $post = GalleryPost::where('user_id', $owner->id)->first();
        $this->assertNotNull($post);
        // Refresh to ensure we have the latest values from the database
        $post->refresh();
        // Use assertSame for strict boolean comparison
        $this->assertSame(false, $post->prompt_visible, 'prompt_visible should be false');
        $this->assertSame(false, $post->model_visible, 'model_visible should be false');
        
        // Owner should see prompt and model even if flags are false
        $ownerResponse = $this->actingAs($owner)->makeRequest('GET', "/api/v1/gallery/posts/{$post->id}");

        $ownerResponse->assertStatus(200);
        $ownerData = $ownerResponse->json('data');
        $this->assertArrayHasKey('generation_job', $ownerData);
        $this->assertArrayHasKey('prompt', $ownerData['generation_job']);
        $this->assertEquals('Secret prompt text', $ownerData['generation_job']['prompt']);
        $this->assertArrayHasKey('model', $ownerData['generation_job']);
        $this->assertEquals('Test Model Name', $ownerData['generation_job']['model']['name']);
        
        // Non-owner should NOT see prompt and model when flags are false
        // Double-check the post flags before making the request
        $post->refresh();
        $this->assertSame(false, (bool)$post->prompt_visible, 'prompt_visible must be false before non-owner view');
        $this->assertSame(false, (bool)$post->model_visible, 'model_visible must be false before non-owner view');
        
        $viewerResponse = $this->actingAs($viewer)->makeRequest('GET', "/api/v1/gallery/posts/{$post->id}");

        $viewerResponse->assertStatus(200);
        $viewerData = $viewerResponse->json('data');
        $this->assertArrayHasKey('generation_job', $viewerData);
        
        // Debug: check what's actually in the response
        if (isset($viewerData['generation_job']['prompt'])) {
            $this->fail('Prompt should not be visible to non-owner when prompt_visible=false. Post ID: ' . $post->id . ', prompt_visible: ' . var_export($post->prompt_visible, true));
        }
        if (isset($viewerData['generation_job']['model'])) {
            $this->fail('Model should not be visible to non-owner when model_visible=false. Post ID: ' . $post->id . ', model_visible: ' . var_export($post->model_visible, true));
        }
        $this->assertArrayNotHasKey('prompt', $viewerData['generation_job']);
        $this->assertArrayNotHasKey('model', $viewerData['generation_job']);
        
        // Now update post to make prompt and model visible
        $updateResponse = $this->actingAs($owner)->makeRequest('PUT', "/api/v1/gallery/posts/{$post->id}", [
            'prompt_visible' => true,
            'model_visible' => true,
        ]);

        $updateResponse->assertStatus(200);
        $post->refresh();
        $this->assertTrue($post->prompt_visible);
        $this->assertTrue($post->model_visible);
        
        // Now non-owner should see prompt and model
        $viewerResponse2 = $this->withHeaders([
            'Authorization' => "Bearer {$viewerToken}",
        ])->makeRequest('GET', "/api/v1/gallery/posts/{$post->id}");

        $viewerResponse2->assertStatus(200);
        $viewerData2 = $viewerResponse2->json('data');
        $this->assertArrayHasKey('generation_job', $viewerData2);
        $this->assertArrayHasKey('prompt', $viewerData2['generation_job']);
        $this->assertEquals('Secret prompt text', $viewerData2['generation_job']['prompt']);
        $this->assertArrayHasKey('model', $viewerData2['generation_job']);
        $this->assertEquals('Test Model Name', $viewerData2['generation_job']['model']['name']);

        $this->assertNoErrorLogs();
    }
}

