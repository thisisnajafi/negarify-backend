<?php

namespace Test\BackendTest\Laravel\Feature\Reports;

use App\Models\GalleryPost;
use App\Models\GenerationJob;
use App\Models\Model as AiModel;
use App\Models\Provider;
use App\Models\Report;
use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;
use Carbon\Carbon;

class ReportTest extends BackendTestCase
{
    /** @test */
    public function it_creates_report_for_post(): void
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
            'user_id' => User::factory()->create()->id, // Different user
            'generation_job_id' => $job->id,
            'visibility' => 'public',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/gallery/posts/{$post->id}/report", [
            'reason' => 'inappropriate_content',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'report_id',
                    'status',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Report submitted successfully',
                'data' => [
                    'status' => 'pending',
                ],
            ]);

        // Verify report created
        $this->assertDatabaseHas('reports', [
            'user_id' => $user->id,
            'gallery_post_id' => $post->id,
            'reason' => 'inappropriate_content',
            'status' => 'pending',
        ]);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_rejects_reporting_own_content(): void
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
            'user_id' => $user->id, // Same user
            'generation_job_id' => $job->id,
            'visibility' => 'public',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/gallery/posts/{$post->id}/report", [
            'reason' => 'inappropriate_content',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'You cannot report your own content',
            ]);
    }

    /** @test */
    public function it_prevents_duplicate_reports_within_24_hours(): void
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
        ]);
        
        // Create first report
        Report::create([
            'user_id' => $user->id,
            'gallery_post_id' => $post->id,
            'reason' => 'inappropriate_content',
            'status' => 'pending',
            'created_at' => now()->subHour(), // Within 24 hours
        ]);
        
        // Try to report again
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/gallery/posts/{$post->id}/report", [
            'reason' => 'spam',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'You have already reported this content recently',
            ]);
    }

    /** @test */
    public function it_enforces_daily_report_limit(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $provider = Provider::factory()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id]);
        
        // Create 5 reports today (limit)
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
            ]);
            
            Report::create([
                'user_id' => $user->id,
                'gallery_post_id' => $post->id,
                'reason' => 'inappropriate_content',
                'status' => 'pending',
                'created_at' => now()->startOfDay()->addHours($i),
            ]);
        }
        
        // Create another post to report
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
        
        // Try to report (6th report)
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/gallery/posts/{$post->id}/report", [
            'reason' => 'inappropriate_content',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'message' => 'You have reached the daily report limit (5 reports per day)',
            ]);
    }

    /** @test */
    public function it_adds_post_to_moderation_queue_on_threshold(): void
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
        ]);
        
        // Create 2 existing reports
        Report::create([
            'user_id' => User::factory()->create()->id,
            'gallery_post_id' => $post->id,
            'reason' => 'inappropriate_content',
            'status' => 'pending',
        ]);
        
        Report::create([
            'user_id' => User::factory()->create()->id,
            'gallery_post_id' => $post->id,
            'reason' => 'spam',
            'status' => 'pending',
        ]);
        
        // 3rd report should trigger moderation queue
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/gallery/posts/{$post->id}/report", [
            'reason' => 'inappropriate_content',
        ]);

        $response->assertStatus(201);

        // Verify post added to moderation queue
        $this->assertDatabaseHas('moderation_queue', [
            'gallery_post_id' => $post->id,
            'reason' => 'multiple_reports',
        ]);
    }

    /** @test */
    public function it_validates_report_reason(): void
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
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/gallery/posts/{$post->id}/report", [
            // Missing reason
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }
}

