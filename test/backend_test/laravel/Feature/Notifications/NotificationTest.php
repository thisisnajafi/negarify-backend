<?php

namespace Test\BackendTest\Laravel\Feature\Notifications;

use App\Models\Notification;
use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;
use Carbon\Carbon;

class NotificationTest extends BackendTestCase
{
    /** @test */
    public function it_returns_user_notifications(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        Notification::create([
            'user_id' => $user->id,
            'type' => 'post_liked',
            'data_json' => ['post_id' => 1, 'liker_id' => 2],
        ]);
        
        Notification::create([
            'user_id' => $user->id,
            'type' => 'comment_added',
            'data_json' => ['post_id' => 1, 'comment_id' => 3],
            'read_at' => now(),
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'data',
                        'read_at',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'unread_count',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $notifications = $response->json('data');
        $this->assertCount(2, $notifications);
        $this->assertEquals(1, $response->json('meta.unread_count')); // One unread

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_filters_notifications_by_read_status(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        Notification::create([
            'user_id' => $user->id,
            'type' => 'post_liked',
            'data_json' => [],
        ]);
        
        Notification::create([
            'user_id' => $user->id,
            'type' => 'comment_added',
            'data_json' => [],
            'read_at' => now(),
        ]);
        
        // Get only unread
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/notifications', [
            'read' => false,
        ]);

        $notifications = $response->json('data');
        $this->assertCount(1, $notifications);
        $this->assertEquals('post_liked', $notifications[0]['type']);
    }

    /** @test */
    public function it_filters_notifications_by_type(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        Notification::create([
            'user_id' => $user->id,
            'type' => 'post_liked',
            'data_json' => [],
        ]);
        
        Notification::create([
            'user_id' => $user->id,
            'type' => 'comment_added',
            'data_json' => [],
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/notifications', [
            'type' => 'post_liked',
        ]);

        $notifications = $response->json('data');
        $this->assertCount(1, $notifications);
        $this->assertEquals('post_liked', $notifications[0]['type']);
    }

    /** @test */
    public function it_returns_single_notification(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'post_liked',
            'data_json' => ['post_id' => 1],
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', "/api/v1/notifications/{$notification->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $notification->id,
                    'type' => 'post_liked',
                ],
            ]);
    }

    /** @test */
    public function it_rejects_access_to_other_user_notification(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $token1 = $user1->createToken('auth-token')->plainTextToken;
        
        $notification = Notification::create([
            'user_id' => $user2->id, // Different user
            'type' => 'post_liked',
            'data_json' => [],
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token1}",
        ])->makeRequest('GET', "/api/v1/notifications/{$notification->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_marks_notification_as_read(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'post_liked',
            'data_json' => [],
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('PUT', "/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notification marked as read',
            ]);

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    /** @test */
    public function it_marks_notification_as_unread(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'post_liked',
            'data_json' => [],
            'read_at' => now(),
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('PUT', "/api/v1/notifications/{$notification->id}/unread");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notification marked as unread',
            ]);

        $notification->refresh();
        $this->assertNull($notification->read_at);
    }

    /** @test */
    public function it_marks_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        Notification::create([
            'user_id' => $user->id,
            'type' => 'post_liked',
            'data_json' => [],
        ]);
        
        Notification::create([
            'user_id' => $user->id,
            'type' => 'comment_added',
            'data_json' => [],
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('PUT', '/api/v1/notifications/read-all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'updated_count' => 2,
                ],
            ]);

        $unreadCount = Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
        $this->assertEquals(0, $unreadCount);
    }

    /** @test */
    public function it_rejects_notification_access_for_unauthenticated_user(): void
    {
        $response = $this->makeRequest('GET', '/api/v1/notifications');

        $response->assertStatus(401);
    }
}

