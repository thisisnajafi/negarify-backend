<?php

namespace Test\BackendTest\Laravel\Feature\Transactions;

use App\Models\TokenTransaction;
use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;
use Carbon\Carbon;

class HistoryTest extends BackendTestCase
{
    /** @test */
    public function it_returns_user_transaction_history(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        // Create transactions
        TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => 100,
            'type' => 'purchase',
            'description' => 'Token purchase',
        ]);
        
        TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => -10,
            'type' => 'consume',
            'description' => 'Image generation',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/tokens/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'amount_tokens',
                        'type',
                        'description',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $transactions = $response->json('data');
        $this->assertCount(2, $transactions);
        $this->assertEquals('purchase', $transactions[0]['type']);
        $this->assertEquals('consume', $transactions[1]['type']);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_filters_transactions_by_type(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => 100,
            'type' => 'purchase',
        ]);
        
        TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => -10,
            'type' => 'consume',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/tokens/history', [
            'type' => 'purchase',
        ]);

        $transactions = $response->json('data');
        $this->assertCount(1, $transactions);
        $this->assertEquals('purchase', $transactions[0]['type']);
    }

    /** @test */
    public function it_filters_transactions_by_date_range(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        Carbon::setTestNow(Carbon::parse('2024-01-15'));
        
        TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => 100,
            'type' => 'purchase',
            'created_at' => Carbon::parse('2024-01-10'),
        ]);
        
        TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => -10,
            'type' => 'consume',
            'created_at' => Carbon::parse('2024-01-20'),
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/tokens/history', [
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-15',
        ]);

        $transactions = $response->json('data');
        $this->assertCount(1, $transactions);
        $this->assertEquals('purchase', $transactions[0]['type']);
    }

    /** @test */
    public function it_paginates_transaction_history(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        // Create 20 transactions
        for ($i = 0; $i < 20; $i++) {
            TokenTransaction::create([
                'user_id' => $user->id,
                'amount_tokens' => 10,
                'type' => 'purchase',
            ]);
        }
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/tokens/history');

        $response->assertStatus(200);
        $transactions = $response->json('data');
        $this->assertCount(15, $transactions); // Default per page
        
        $meta = $response->json('meta');
        $this->assertEquals(20, $meta['total']);
        $this->assertEquals(2, $meta['last_page']);
    }

    /** @test */
    public function it_orders_transactions_by_created_at_desc(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $oldTransaction = TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => 100,
            'type' => 'purchase',
            'created_at' => Carbon::now()->subDay(),
        ]);
        
        $newTransaction = TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => 50,
            'type' => 'purchase',
            'created_at' => Carbon::now(),
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/tokens/history');

        $transactions = $response->json('data');
        $this->assertEquals($newTransaction->id, $transactions[0]['id']);
        $this->assertEquals($oldTransaction->id, $transactions[1]['id']);
    }

    /** @test */
    public function it_rejects_history_access_for_unauthenticated_user(): void
    {
        $response = $this->makeRequest('GET', '/api/v1/tokens/history');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_only_returns_current_user_transactions(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $token1 = $user1->createToken('auth-token')->plainTextToken;
        
        TokenTransaction::create([
            'user_id' => $user1->id,
            'amount_tokens' => 100,
            'type' => 'purchase',
        ]);
        
        TokenTransaction::create([
            'user_id' => $user2->id,
            'amount_tokens' => 200,
            'type' => 'purchase',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token1}",
        ])->makeRequest('GET', '/api/v1/tokens/history');

        $transactions = $response->json('data');
        $this->assertCount(1, $transactions);
        $this->assertEquals($user1->id, $transactions[0]['user_id']);
    }
}

