<?php

namespace Test\BackendTest\Laravel\Feature\User;

use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class ProfileTest extends BackendTestCase
{
    /** @test */
    public function it_returns_authenticated_user_profile(): void
    {
        $user = User::factory()->create([
            'tokens_balance' => 1000,
        ]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'phone',
                    'name',
                    'email',
                    'avatar_url',
                    'tokens_balance',
                    'role',
                    'is_verified',
                    'phone_verified_at',
                    'email_verified_at',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'phone' => $user->phone,
                    'tokens_balance' => 1000.0,
                ],
            ]);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_rejects_profile_access_for_unauthenticated_user(): void
    {
        $response = $this->makeRequest('GET', '/api/v1/user');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_updates_user_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('PUT', '/api/v1/user', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'name' => 'New Name',
                    'email' => 'new@example.com',
                ],
            ]);

        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertEquals('new@example.com', $user->email);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_resets_email_verification_when_email_changes(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('PUT', '/api/v1/user', [
            'email' => 'new@example.com',
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertEquals('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    /** @test */
    public function it_validates_email_format(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('PUT', '/api/v1/user', [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function it_rejects_updating_forbidden_fields(): void
    {
        $user = User::factory()->create([
            'tokens_balance' => 1000,
            'role' => 'user',
        ]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('PUT', '/api/v1/user', [
            'tokens_balance' => 9999,
            'role' => 'admin',
        ]);

        $response->assertStatus(200);

        // Verify forbidden fields not updated
        $user->refresh();
        $this->assertEquals(1000, $user->tokens_balance);
        $this->assertEquals('user', $user->role);
    }
}

