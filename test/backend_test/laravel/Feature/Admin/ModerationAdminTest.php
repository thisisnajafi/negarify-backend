<?php

namespace Test\BackendTest\Laravel\Feature\Admin;

use App\Models\GalleryPost;
use App\Models\GenerationJob;
use App\Models\Model as AiModel;
use App\Models\ModerationQueue;
use App\Models\Provider;
use App\Models\Report;
use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class ModerationAdminTest extends BackendTestCase
{
    /** @test */
    public function it_returns_moderation_queue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
            'tokens_consumed' => 10,
        ]);
        
        $post = GalleryPost::create([
            'user_id' => $user->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
        ]);
        
        ModerationQueue::create([
            'gallery_post_id' => $post->id,
            'reason' => 'multiple_reports',
            'status' => 'pending',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/admin/moderation/queue');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'post_id',
                        'reason',
                        'status',
                        'post',
                    ],
                ],
                'meta',
            ])
            ->assertJson([
                'success' => true,
            ]);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_returns_reports_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
            'tokens_consumed' => 10,
        ]);
        
        $post = GalleryPost::create([
            'user_id' => $user->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
        ]);
        
        Report::create([
            'user_id' => User::factory()->create()->id,
            'gallery_post_id' => $post->id,
            'reason' => 'inappropriate_content',
            'status' => 'pending',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/admin/moderation/reports');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'post_id',
                        'reason',
                        'status',
                    ],
                ],
                'meta',
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function it_approves_post_in_moderation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
            'tokens_consumed' => 10,
        ]);
        
        $post = GalleryPost::create([
            'user_id' => $user->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
        ]);
        
        $queueItem = ModerationQueue::create([
            'gallery_post_id' => $post->id,
            'reason' => 'multiple_reports',
            'status' => 'pending',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/admin/moderation/posts/{$post->id}/approve", [
            'notes' => 'Content is appropriate',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Content approved successfully',
            ]);

        $queueItem->refresh();
        $this->assertEquals('approved', $queueItem->status);
    }

    /** @test */
    public function it_rejects_post_in_moderation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
            'tokens_consumed' => 10,
        ]);
        
        $post = GalleryPost::create([
            'user_id' => $user->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
        ]);
        
        $queueItem = ModerationQueue::create([
            'gallery_post_id' => $post->id,
            'reason' => 'multiple_reports',
            'status' => 'pending',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/admin/moderation/posts/{$post->id}/reject", [
            'notes' => 'Inappropriate content',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Content rejected successfully',
            ]);

        $queueItem->refresh();
        $this->assertEquals('rejected', $queueItem->status);
        
        // Verify post hidden (set to private)
        $post->refresh();
        $this->assertEquals('private', $post->visibility);
    }

    /** @test */
    public function it_resolves_report(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create();
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
            'tokens_consumed' => 10,
        ]);
        
        $post = GalleryPost::create([
            'user_id' => $user->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
        ]);
        
        $report = Report::create([
            'user_id' => User::factory()->create()->id,
            'gallery_post_id' => $post->id,
            'reason' => 'inappropriate_content',
            'status' => 'pending',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/admin/moderation/reports/{$report->id}/resolve", [
            'action' => 'approved',
            'notes' => 'Content reviewed and approved',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Report resolved successfully',
            ]);

        $report->refresh();
        $this->assertEquals('resolved', $report->status);
    }
}

