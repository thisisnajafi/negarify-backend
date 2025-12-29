<?php

namespace Test\BackendTest\Laravel\Feature\Admin;

use App\Models\TokenBundle;
use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class TokenBundleAdminTest extends BackendTestCase
{
    /** @test */
    public function it_creates_token_bundle_as_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/admin/tokens/bundles', [
            'name' => 'Premium Pack',
            'token_amount' => 1000,
            'price_usd' => 9.99,
            'bonus_tokens' => 100,
            'is_active' => true,
            'display_order' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'token_amount',
                    'bonus_tokens',
                    'price_usd',
                    'is_active',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Token bundle created successfully',
                'data' => [
                    'name' => 'Premium Pack',
                    'token_amount' => 1000,
                    'bonus_tokens' => 100,
                    'price_usd' => 9.99,
                ],
            ]);

        // Verify bundle created
        $this->assertDatabaseHas('token_bundles', [
            'name' => 'Premium Pack',
            'token_amount' => 1000,
            'price_usd' => 9.99,
        ]);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_rejects_bundle_creation_for_non_admin(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/admin/tokens/bundles', [
            'name' => 'Test Pack',
            'token_amount' => 100,
            'price_usd' => 1.00,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_updates_token_bundle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        $bundle = TokenBundle::create([
            'name' => 'Old Name',
            'token_amount' => 100,
            'price_usd' => 1.00,
            'is_active' => true,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('PUT', "/api/v1/admin/tokens/bundles/{$bundle->id}", [
            'name' => 'New Name',
            'price_usd' => 1.50,
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Token bundle updated successfully',
                'data' => [
                    'name' => 'New Name',
                    'price_usd' => 1.50,
                    'is_active' => false,
                ],
            ]);

        $bundle->refresh();
        $this->assertEquals('New Name', $bundle->name);
        $this->assertEquals(1.50, $bundle->price_usd);
        $this->assertFalse($bundle->is_active);
    }

    /** @test */
    public function it_deletes_token_bundle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        $bundle = TokenBundle::create([
            'name' => 'Test Pack',
            'token_amount' => 100,
            'price_usd' => 1.00,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('DELETE', "/api/v1/admin/tokens/bundles/{$bundle->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Token bundle deleted successfully',
            ]);

        $this->assertDatabaseMissing('token_bundles', [
            'id' => $bundle->id,
        ]);
    }

    /** @test */
    public function it_activates_token_bundle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        $bundle = TokenBundle::create([
            'name' => 'Test Pack',
            'token_amount' => 100,
            'price_usd' => 1.00,
            'is_active' => false,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/admin/tokens/bundles/{$bundle->id}/activate");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Token bundle activated successfully',
                'data' => [
                    'is_active' => true,
                ],
            ]);

        $bundle->refresh();
        $this->assertTrue($bundle->is_active);
    }

    /** @test */
    public function it_deactivates_token_bundle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;
        
        $bundle = TokenBundle::create([
            'name' => 'Test Pack',
            'token_amount' => 100,
            'price_usd' => 1.00,
            'is_active' => true,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', "/api/v1/admin/tokens/bundles/{$bundle->id}/deactivate");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Token bundle deactivated successfully',
                'data' => [
                    'is_active' => false,
                ],
            ]);

        $bundle->refresh();
        $this->assertFalse($bundle->is_active);
    }
}

