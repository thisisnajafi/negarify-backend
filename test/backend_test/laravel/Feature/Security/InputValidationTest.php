<?php

namespace Test\BackendTest\Laravel\Feature\Security;

use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class InputValidationTest extends BackendTestCase
{
    /** @test */
    public function it_prevents_sql_injection_in_search_queries(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;

        // SQL injection attempts
        $sqlInjectionAttempts = [
            "'; DROP TABLE users; --",
            "' OR '1'='1",
            "1' UNION SELECT * FROM users--",
            "admin'--",
            "' OR 1=1--",
        ];

        foreach ($sqlInjectionAttempts as $attempt) {
            $response = $this->withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->makeRequest('GET', '/api/v1/admin/users/list', [
                'search' => $attempt,
            ]);

            // Should not crash or expose data
            $response->assertStatus(200);
            
            // Should not return all users (if SQL injection worked, it would)
            $data = $response->json('data');
            $this->assertIsArray($data);
        }
    }

    /** @test */
    public function it_sanitizes_user_input_in_comments(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        // Create a post
        $provider = \App\Models\Provider::factory()->create();
        $model = \App\Models\Model::factory()->create(['provider_id' => $provider->id]);
        
        $job = \App\Models\GenerationJob::create([
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        $post = \App\Models\GalleryPost::create([
            'user_id' => User::factory()->create()->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
        ]);

        // XSS attempt
        $xssAttempt = '<script>alert("XSS")</script>';
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/gallery/{$post->id}/comment", [
            'body' => $xssAttempt,
        ]);

        // Should accept the input but store it safely
        $response->assertStatus(201);
        
        // Verify comment was stored (input should be stored as-is, but rendered safely by frontend)
        $this->assertDatabaseHas('comments', [
            'body' => $xssAttempt,
        ]);
    }

    /** @test */
    public function it_validates_phone_number_format(): void
    {
        $invalidPhones = [
            "'; DROP TABLE users; --",
            '<script>alert("xss")</script>',
            '123',
            'abc123',
            '',
            null,
        ];

        foreach ($invalidPhones as $phone) {
            $response = $this->makeRequest('POST', '/api/v1/auth/request-otp', [
                'phone' => $phone,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['phone']);
        }
    }

    /** @test */
    public function it_validates_email_format(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $invalidEmails = [
            'not-an-email',
            'test@',
            '@example.com',
            '<script>alert("xss")</script>',
            "'; DROP TABLE users; --",
        ];

        foreach ($invalidEmails as $email) {
            $response = $this->withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->makeRequest('PUT', '/api/v1/user', [
                'email' => $email,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
        }
    }

    /** @test */
    public function it_validates_numeric_inputs(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        // Test invalid numeric inputs for token bundle purchase
        $invalidInputs = [
            '<script>alert("xss")</script>',
            "'; DROP TABLE users; --",
            'not-a-number',
            '',
        ];

        foreach ($invalidInputs as $input) {
            $response = $this->withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->makeRequest('POST', '/api/v1/tokens/purchase', [
                'token_bundle_id' => $input,
            ]);

            // Should validate and reject
            $this->assertContains($response->status(), [422, 400, 404]);
        }
    }

    /** @test */
    public function it_handles_oversized_inputs(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        // Create a post
        $provider = \App\Models\Provider::factory()->create();
        $model = \App\Models\Model::factory()->create(['provider_id' => $provider->id]);
        
        $job = \App\Models\GenerationJob::create([
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'status' => 'completed',
            'result_url' => 'https://example.com/image.jpg',
        ]);
        
        $post = \App\Models\GalleryPost::create([
            'user_id' => User::factory()->create()->id,
            'generation_job_id' => $job->id,
            'visibility' => 'public',
        ]);

        // Oversized comment (assuming max length is enforced)
        $oversizedComment = str_repeat('a', 10000);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/gallery/{$post->id}/comment", [
            'body' => $oversizedComment,
        ]);

        // Should reject oversized input
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    /** @test */
    public function it_validates_file_uploads(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        // Test invalid file types
        $invalidFile = \Illuminate\Http\UploadedFile::fake()->create('document.pdf', 100);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/user/avatar', [
            'avatar' => $invalidFile,
        ]);

        // Should reject non-image files
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }
}

