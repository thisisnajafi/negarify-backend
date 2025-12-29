<?php

namespace Test\BackendTest\Laravel\Feature\Payments;

use App\Models\CurrencyRate;
use App\Models\Order;
use App\Models\TokenBundle;
use App\Models\User;
use App\Services\CurrencyRateService;
use App\Services\ZarinpalService;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class PurchaseTest extends BackendTestCase
{
    // Override RefreshDatabase trait to avoid transaction conflicts
    // The issue: OrderController uses lockForUpdate() and DB::beginTransaction()
    // which conflicts with RefreshDatabase's transaction wrapping in SQLite
    // Solution: Use DatabaseMigrations instead which doesn't wrap in transactions
    use \Illuminate\Foundation\Testing\DatabaseMigrations;
    
    // Remove RefreshDatabase from parent to avoid trait conflict
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set Zarinpal config for tests
        config(['services.zarinpal.merchant_id' => 'test-merchant-id']);
        config(['services.zarinpal.sandbox' => true]);
        
        // Create currency rate
        CurrencyRate::create([
            'currency_from' => 'USD',
            'currency_to' => 'IRR',
            'rate' => 500000,
            'source' => 'tgju',
            'fetched_at' => now(),
        ]);
        
        app(CurrencyRateService::class)->cacheRate(50000);
    }
    
    /**
     * Set up successful HTTP fake for Zarinpal (can be overridden in tests)
     */
    protected function setUpZarinpalFake(): void
    {
        Http::fake([
            'sandbox.zarinpal.com/*' => Http::response([
                'data' => [
                    'code' => 100,
                    'authority' => 'A00000000000000000000000000000000000',
                ],
            ], 200),
            'api.zarinpal.com/*' => Http::response([
                'data' => [
                    'code' => 100,
                    'authority' => 'A00000000000000000000000000000000000',
                ],
            ], 200),
        ]);
    }

    /** @test */
    public function it_creates_order_and_requests_payment(): void
    {
        $this->setUpZarinpalFake();
        
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $bundle = TokenBundle::create([
            'name' => 'Starter Pack',
            'token_amount' => 100,
            'price_usd' => 1.00,
            'bonus_tokens' => 0,
            'is_active' => true,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/tokens/purchase', [
            'token_bundle_id' => $bundle->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'order_id',
                    'payment_url',
                    'authority',
                    'amount_toman',
                    'amount_tokens',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Payment request created successfully',
            ]);

        // Verify order created
        $order = Order::where('user_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals(100, $order->amount_tokens);
        $this->assertEquals(50000, $order->price_toman); // 1.00 * 50000
        $this->assertEquals(1.00, $order->price_usd);
        $this->assertEquals(50000, $order->dollar_rate);
        $this->assertNotNull($order->zarinpal_authority);

        // Verify Zarinpal called
        Http::assertSent(function ($request) {
            $data = $request->data();
            return str_contains($request->url(), 'zarinpal.com') &&
                   isset($data['amount']) &&
                   $data['amount'] == 50000;
        });

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_rejects_inactive_bundle_purchase(): void
    {
        $this->setUpZarinpalFake();
        
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $bundle = TokenBundle::create([
            'name' => 'Inactive Pack',
            'token_amount' => 100,
            'price_usd' => 1.00,
            'is_active' => false,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/tokens/purchase', [
            'token_bundle_id' => $bundle->id,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Token bundle is not available for purchase',
            ]);
    }

    /** @test */
    public function it_calculates_price_using_current_rate(): void
    {
        $this->setUpZarinpalFake();
        
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        // Update rate
        app(CurrencyRateService::class)->cacheRate(60000); // New rate
        
        $bundle = TokenBundle::create([
            'name' => 'Test Pack',
            'token_amount' => 100,
            'price_usd' => 1.00,
            'is_active' => true,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/tokens/purchase', [
            'token_bundle_id' => $bundle->id,
        ]);

        $response->assertStatus(200);

        $order = Order::where('user_id', $user->id)->first();
        $this->assertEquals(60000, $order->price_toman); // Uses new rate
        $this->assertEquals(60000, $order->dollar_rate); // Snapshot stored
    }

    /** @test */
    public function it_includes_bonus_tokens_in_order(): void
    {
        $this->setUpZarinpalFake();
        
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $bundle = TokenBundle::create([
            'name' => 'Bonus Pack',
            'token_amount' => 100,
            'bonus_tokens' => 50,
            'price_usd' => 1.00,
            'is_active' => true,
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/tokens/purchase', [
            'token_bundle_id' => $bundle->id,
        ]);

        $response->assertStatus(200);

        $order = Order::where('user_id', $user->id)->first();
        $this->assertEquals(150, $order->amount_tokens); // 100 + 50 bonus
    }

    /** @test */
    public function it_handles_zarinpal_failure_gracefully(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        $bundle = TokenBundle::create([
            'name' => 'Test Pack',
            'token_amount' => 100,
            'price_usd' => 1.00,
            'is_active' => true,
        ]);
        
        // Override Http fake for failure - Http::fake() replaces previous fakes
        // ZarinpalService checks: if isset($result['data']['code']) && $result['data']['code'] == 100 -> success
        // Error response should have 'errors' key instead of 'data' key with code 100
        Http::fake([
            '*' => Http::response([
                'errors' => [
                    'code' => -9,
                    'message' => 'Invalid merchant',
                ],
            ], 200),
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->makeRequest('POST', '/api/v1/tokens/purchase', [
            'token_bundle_id' => $bundle->id,
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to initiate payment. Please try again later.',
            ]);

        // Verify order not created (transaction rolled back)
        $this->assertDatabaseMissing('orders', [
            'user_id' => $user->id,
        ]);

        $this->allowErrorLogs(['Zarinpal payment request failed']);
    }

    /** @test */
    public function it_rejects_purchase_for_unauthenticated_user(): void
    {
        $bundle = TokenBundle::create([
            'name' => 'Test Pack',
            'token_amount' => 100,
            'price_usd' => 1.00,
            'is_active' => true,
        ]);
        
        $response = $this->makeRequest('POST', '/api/v1/tokens/purchase', [
            'token_bundle_id' => $bundle->id,
        ]);

        $response->assertStatus(401);
    }
}

