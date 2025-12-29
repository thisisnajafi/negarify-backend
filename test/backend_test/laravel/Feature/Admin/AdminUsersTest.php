<?php

namespace Test\BackendTest\Laravel\Feature\Admin;

use App\Models\GenerationJob;
use App\Models\Order;
use App\Models\TokenBundle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class AdminUsersTest extends BackendTestCase
{
    /** @test */
    public function it_calculates_dau_wau_mau_correctly(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        // Create users with different activity levels
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        
        $provider = \App\Models\Provider::factory()->create();
        $model = \App\Models\Model::factory()->create();
        
        // User1: Active today (DAU, WAU, MAU)
        GenerationJob::create([
            'user_id' => $user1->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'created_at' => now()->subHours(2),
        ]);
        
        // User2: Active 3 days ago (WAU, MAU, not DAU)
        GenerationJob::create([
            'user_id' => $user2->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'created_at' => now()->subDays(3),
        ]);
        
        // User3: Active 20 days ago (MAU only, not DAU or WAU)
        GenerationJob::create([
            'user_id' => $user3->id,
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'created_at' => now()->subDays(20),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/users/summary', [
            'range' => 'month',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'active_users' => [
                        'dau',
                        'wau',
                        'mau',
                    ],
                ],
            ]);

        $data = $response->json('data');
        
        // DAU should include at least user1 (active today)
        $this->assertGreaterThanOrEqual(1, $data['active_users']['dau'], 'DAU should include at least user1');
        
        // WAU should include at least user1 and user2
        $this->assertGreaterThanOrEqual(2, $data['active_users']['wau'], 'WAU should include at least user1 and user2');
        
        // MAU should include all 3 users
        $this->assertGreaterThanOrEqual(3, $data['active_users']['mau'], 'MAU should include all 3 users');
        
        // Verify the structure
        $this->assertIsInt($data['active_users']['dau']);
        $this->assertIsInt($data['active_users']['wau']);
        $this->assertIsInt($data['active_users']['mau']);
    }

    /** @test */
    public function it_returns_top_users_by_generation(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $provider = \App\Models\Provider::factory()->create();
        $model = \App\Models\Model::factory()->create();
        
        // User1: 5 generations
        for ($i = 0; $i < 5; $i++) {
            GenerationJob::create([
                'user_id' => $user1->id,
                'provider_id' => $provider->id,
                'model_id' => $model->id,
                'job_type' => 'image',
                'prompt' => 'Test prompt',
                'params_json' => [],
                'status' => 'completed',
                'created_at' => now()->subDays(10),
            ]);
        }
        
        // User2: 2 generations
        for ($i = 0; $i < 2; $i++) {
            GenerationJob::create([
                'user_id' => $user2->id,
                'provider_id' => $provider->id,
                'model_id' => $model->id,
                'job_type' => 'image',
                'prompt' => 'Test prompt',
                'params_json' => [],
                'status' => 'completed',
                'created_at' => now()->subDays(10),
            ]);
        }

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/users/summary', [
            'range' => 'month',
        ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('top_users_by_generation', $data);
        
        $topUsers = $data['top_users_by_generation'];
        $this->assertGreaterThanOrEqual(2, count($topUsers));
        
        // User1 should be first (more generations)
        $this->assertEquals(5, $topUsers[0]['generation_count']);
        $this->assertEquals($user1->id, $topUsers[0]['user_id']);
    }

    /** @test */
    public function it_returns_top_users_by_spending(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $bundle = TokenBundle::factory()->create();
        
        // User1: $10 spent
        Order::create([
            'user_id' => $user1->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 500000,
            'price_usd' => 10.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
            'created_at' => now()->subDays(10),
        ]);
        
        // User2: $5 spent
        Order::create([
            'user_id' => $user2->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 50,
            'price_toman' => 250000,
            'price_usd' => 5.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
            'created_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/users/summary', [
            'range' => 'month',
        ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('top_users_by_spending', $data);
        
        $topSpenders = $data['top_users_by_spending'];
        $this->assertGreaterThanOrEqual(2, count($topSpenders));
        
        // User1 should be first (more spending)
        $this->assertEquals(10.00, $topSpenders[0]['total_spending_usd']);
        $this->assertEquals($user1->id, $topSpenders[0]['user_id']);
    }

    /** @test */
    public function it_performs_cohort_analysis(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        // Create users in different months
        $user1 = User::factory()->create([
            'created_at' => now()->subMonths(2)->startOfMonth(),
        ]);
        
        $user2 = User::factory()->create([
            'created_at' => now()->subMonths(1)->startOfMonth(),
        ]);
        
        $user3 = User::factory()->create([
            'created_at' => now()->subMonths(1)->startOfMonth()->addDays(5),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/users/summary', [
            'range' => 'month',
        ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('cohort_analysis', $data);
        
        $cohorts = $data['cohort_analysis'];
        $this->assertIsArray($cohorts);
        
        // Should have at least 2 cohorts (2 months ago and 1 month ago)
        $this->assertGreaterThanOrEqual(2, count($cohorts));
        
        // Verify cohort structure
        foreach ($cohorts as $cohort) {
            $this->assertArrayHasKey('month', $cohort);
            $this->assertArrayHasKey('signups_count', $cohort);
            $this->assertIsString($cohort['month']);
            $this->assertIsInt($cohort['signups_count']);
        }
    }

    /** @test */
    public function it_searches_users_by_name_phone_or_email(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();

        $user1 = User::factory()->create(['name' => 'John Doe', 'phone' => '09123456789']);
        $user2 = User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
        $user3 = User::factory()->create(['name' => 'Bob Johnson']);

        // Search by name
        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/users/list', [
            'search' => 'John',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));
        $this->assertTrue(
            collect($data)->contains('name', 'John Doe'),
            'Should find user by name'
        );

        // Search by phone
        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/users/list', [
            'search' => '0912345',
        ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));
        $this->assertTrue(
            collect($data)->contains('phone', '09123456789'),
            'Should find user by phone'
        );

        // Search by email
        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/users/list', [
            'search' => 'jane@example.com',
        ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));
        $this->assertTrue(
            collect($data)->contains('email', 'jane@example.com'),
            'Should find user by email'
        );
    }

    /** @test */
    public function it_filters_users_by_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();

        $adminUser = User::factory()->create(['role' => 'admin']);
        $regularUser = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/users/list', [
            'role' => 'admin',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // All returned users should be admins
        foreach ($data as $user) {
            $this->assertEquals('admin', $user['role']);
        }
        
        // Should include the admin user we created
        $this->assertTrue(
            collect($data)->contains('id', $adminUser->id),
            'Should include admin user'
        );
    }

    /** @test */
    public function it_filters_users_by_date_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();

        $user1 = User::factory()->create([
            'created_at' => now()->subDays(5),
        ]);
        
        $user2 = User::factory()->create([
            'created_at' => now()->subDays(20),
        ]);

        $startDate = now()->subDays(10)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/users/list', [
            'date_from' => $startDate,
            'date_to' => $endDate,
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should include user1 (within range)
        $this->assertTrue(
            collect($data)->contains('id', $user1->id),
            'Should include user created within date range'
        );
    }

    /** @test */
    public function it_paginates_users_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();

        // Create more than 15 users (default per_page)
        for ($i = 0; $i < 20; $i++) {
            User::factory()->create();
        }

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/users/list', [
            'per_page' => 10,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);

        $data = $response->json('data');
        $meta = $response->json('meta');
        
        $this->assertLessThanOrEqual(10, count($data));
        $this->assertEquals(10, $meta['per_page']);
        $this->assertGreaterThan(1, $meta['last_page']);
    }

    /** @test */
    public function it_caches_users_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();
        Cache::flush();

        $response1 = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/users/summary', [
            'range' => 'month',
        ]);

        $response2 = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/users/summary', [
            'range' => 'month',
        ]);

        // Both responses should be identical (cached)
        $this->assertEquals(
            $response1->json('data.active_users.mau'),
            $response2->json('data.active_users.mau')
        );
    }
}

