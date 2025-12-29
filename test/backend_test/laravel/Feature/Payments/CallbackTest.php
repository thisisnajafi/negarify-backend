<?php

namespace Test\BackendTest\Laravel\Feature\Payments;

use App\Models\Order;
use App\Models\TokenBundle;
use App\Models\TokenTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class CallbackTest extends BackendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock Zarinpal verification
        Http::fake([
            'api.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => [
                    'code' => 100,
                    'ref_id' => 123456789,
                ],
            ], 200),
        ]);
    }

    /** @test */
    public function it_processes_successful_payment_callback(): void
    {
        $user = User::factory()->create(['tokens_balance' => 0]);
        $bundle = TokenBundle::create([
            'name' => 'Test Pack',
            'token_amount' => 100,
            'bonus_tokens' => 0,
            'price_usd' => 1.00,
        ]);
        
        $order = Order::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000,
            'status' => 'pending',
            'zarinpal_authority' => 'A00000000000000000000000000000000000',
        ]);
        
        $response = $this->makeRequest('GET', '/api/v1/tokens/purchase/callback', [
            'Authority' => $order->zarinpal_authority,
            'Status' => 'OK',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'order_id',
                    'ref_id',
                    'tokens_credited',
                    'new_balance',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Payment processed successfully',
            ]);

        // Verify order updated
        $order->refresh();
        $this->assertEquals('paid', $order->status);
        $this->assertEquals(123456789, $order->zarinpal_ref_id);
        $this->assertNotNull($order->paid_at);

        // Verify tokens credited
        $user->refresh();
        $this->assertEquals(100, $user->tokens_balance);

        // Verify transaction created
        $this->assertDatabaseHas('token_transactions', [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'amount_tokens' => 100,
            'type' => 'purchase',
        ]);

        // Verify Zarinpal verification called
        Http::assertSent(function ($request) use ($order) {
            return str_contains($request->url(), 'verify.json') &&
                   $request->has('authority', $order->zarinpal_authority) &&
                   $request->has('amount', 50000);
        });

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_is_idempotent_prevents_double_credit(): void
    {
        $user = User::factory()->create(['tokens_balance' => 0]);
        $bundle = TokenBundle::create([
            'name' => 'Test Pack',
            'token_amount' => 100,
            'price_usd' => 1.00,
        ]);
        
        $order = Order::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000,
            'status' => 'paid', // Already paid
            'zarinpal_authority' => 'A00000000000000000000000000000000000',
            'zarinpal_ref_id' => 123456789,
            'paid_at' => now(),
        ]);
        
        // First callback (already processed)
        $response = $this->makeRequest('GET', '/api/v1/tokens/purchase/callback', [
            'Authority' => $order->zarinpal_authority,
            'Status' => 'OK',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment already processed',
            ]);

        // Verify tokens not credited again
        $user->refresh();
        $this->assertEquals(0, $user->tokens_balance); // Still 0 (no double credit)

        // Verify no duplicate transaction
        $transactions = TokenTransaction::where('order_id', $order->id)->count();
        $this->assertEquals(0, $transactions); // No transaction created (already processed)
    }

    /** @test */
    public function it_handles_failed_payment_status(): void
    {
        $user = User::factory()->create();
        $bundle = TokenBundle::create([
            'name' => 'Test Pack',
            'token_amount' => 100,
            'price_usd' => 1.00,
        ]);
        
        $order = Order::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000,
            'status' => 'pending',
            'zarinpal_authority' => 'A00000000000000000000000000000000000',
        ]);
        
        $response = $this->makeRequest('GET', '/api/v1/tokens/purchase/callback', [
            'Authority' => $order->zarinpal_authority,
            'Status' => 'NOK', // Failed
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Payment was not completed',
            ]);

        $order->refresh();
        $this->assertEquals('failed', $order->status);
        
        // Verify tokens not credited
        $user->refresh();
        $this->assertEquals(0, $user->tokens_balance);
    }

    /** @test */
    public function it_handles_missing_authority_parameter(): void
    {
        $response = $this->makeRequest('GET', '/api/v1/tokens/purchase/callback', [
            'Status' => 'OK',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid callback parameters',
            ]);

        $this->allowErrorLogs(['Zarinpal callback missing authority']);
    }

    /** @test */
    public function it_handles_invalid_authority(): void
    {
        $response = $this->makeRequest('GET', '/api/v1/tokens/purchase/callback', [
            'Authority' => 'INVALID_AUTHORITY',
            'Status' => 'OK',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Order not found',
            ]);

        $this->allowErrorLogs(['Zarinpal callback - order not found']);
    }

    /** @test */
    public function it_handles_zarinpal_verification_failure(): void
    {
        Http::fake([
            'api.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'errors' => [
                    'code' => -11,
                    'message' => 'Payment not found',
                ],
            ], 200),
        ]);
        
        $user = User::factory()->create();
        $bundle = TokenBundle::create([
            'name' => 'Test Pack',
            'token_amount' => 100,
            'price_usd' => 1.00,
        ]);
        
        $order = Order::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000,
            'status' => 'pending',
            'zarinpal_authority' => 'A00000000000000000000000000000000000',
        ]);
        
        $response = $this->makeRequest('GET', '/api/v1/tokens/purchase/callback', [
            'Authority' => $order->zarinpal_authority,
            'Status' => 'OK',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Payment verification failed',
            ]);

        $order->refresh();
        $this->assertEquals('failed', $order->status);

        $this->allowErrorLogs(['Zarinpal payment verification failed']);
    }

    /** @test */
    public function it_prevents_processing_non_pending_orders(): void
    {
        $user = User::factory()->create();
        $bundle = TokenBundle::create([
            'name' => 'Test Pack',
            'token_amount' => 100,
            'price_usd' => 1.00,
        ]);
        
        $order = Order::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000,
            'status' => 'failed', // Not pending
            'zarinpal_authority' => 'A00000000000000000000000000000000000',
        ]);
        
        $response = $this->makeRequest('GET', '/api/v1/tokens/purchase/callback', [
            'Authority' => $order->zarinpal_authority,
            'Status' => 'OK',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Order is not in a valid state for processing',
            ]);

        $this->allowErrorLogs(['Zarinpal callback - order in invalid state']);
    }
}

