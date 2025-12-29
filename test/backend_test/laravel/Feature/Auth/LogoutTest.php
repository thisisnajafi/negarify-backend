<?php

namespace Test\BackendTest\Laravel\Feature\Auth;

use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class LogoutTest extends BackendTestCase
{
    /** @test */
    public function it_logs_out_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/auth/logout', []);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);

        // Verify token revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'auth-token',
        ]);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_rejects_logout_for_unauthenticated_user(): void
    {
        $response = $this->makeRequest('POST', '/api/v1/auth/logout', []);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_rejects_logout_with_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->makeRequest('POST', '/api/v1/auth/logout', []);

        $response->assertStatus(401);
    }
}

