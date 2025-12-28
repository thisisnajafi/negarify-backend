<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    /** @test */
    public function it_can_get_current_user_profile(): void
    {
        $user = User::factory()->create([
            'tokens_balance' => 100.50,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user');

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
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'tokens_balance' => 100.50,
                ],
            ]);
    }

    /** @test */
    public function it_requires_authentication_to_get_profile(): void
    {
        $response = $this->getJson('/api/v1/user');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_can_update_user_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/user', [
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

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    /** @test */
    public function it_validates_email_uniqueness_on_update(): void
    {
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        $token = $user1->createToken('test-token')->plainTextToken;

        // Try to use user2's email
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/user', [
                'email' => 'user2@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function it_can_upload_avatar(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-token')->plainTextToken;

        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/user/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'avatar_url',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Verify file was stored
        Storage::disk('s3')->assertExists("avatars/{$user->id}/" . basename($response->json('data.avatar_url')));

        // Verify user avatar_url was updated
        $user->refresh();
        $this->assertNotNull($user->avatar_url);
    }

    /** @test */
    public function it_validates_avatar_file_type(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-token')->plainTextToken;

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/user/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    /** @test */
    public function it_validates_avatar_file_size(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-token')->plainTextToken;

        // Create a file larger than 5MB
        $file = UploadedFile::fake()->image('avatar.jpg')->size(6000); // 6MB in KB

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/user/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }
}

