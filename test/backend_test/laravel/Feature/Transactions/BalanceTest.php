<?php

namespace Test\BackendTest\Laravel\Feature\Transactions;

use App\Models\TokenTransaction;
use App\Models\User;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class BalanceTest extends BackendTestCase
{
    /** @test */
    public function it_returns_user_token_balance(): void
    {
        $user = User::factory()->create(['tokens_balance' => 150]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        // Create transactions that match balance
        TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => 200,
            'type' => 'purchase',
        ]);
        
        TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => -50,
            'type' => 'consume',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/tokens/balance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'balance',
                    'calculated_balance',
                    'balance_match',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'balance' => 150,
                    'calculated_balance' => 150, // 200 - 50
                    'balance_match' => true,
                ],
            ]);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_detects_balance_mismatch(): void
    {
        $user = User::factory()->create(['tokens_balance' => 100]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        // Create transactions that don't match stored balance
        TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => 200,
            'type' => 'purchase',
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/tokens/balance');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(100, $data['balance']);
        $this->assertEquals(200, $data['calculated_balance']);
        $this->assertFalse($data['balance_match']); // Mismatch detected
    }

    /** @test */
    public function it_handles_user_with_no_transactions(): void
    {
        $user = User::factory()->create(['tokens_balance' => 0]);
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('GET', '/api/v1/tokens/balance');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'balance' => 0,
                    'calculated_balance' => 0,
                    'balance_match' => true,
                ],
            ]);
    }

    /** @test */
    public function it_rejects_balance_access_for_unauthenticated_user(): void
    {
        $response = $this->makeRequest('GET', '/api/v1/tokens/balance');

        $response->assertStatus(401);
    }
}

