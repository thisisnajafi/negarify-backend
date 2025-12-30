<?php

namespace Test\BackendTest\Laravel\Feature\Tokens;

use App\Models\CurrencyRate;
use App\Models\TokenBundle;
use App\Services\CurrencyRateService;
use Illuminate\Support\Facades\Cache;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class TokenBundlesTest extends BackendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test currency rate
        CurrencyRate::create([
            'currency_from' => 'USD',
            'currency_to' => 'IRR',
            'rate' => 500000, // 500,000 Rials = 50,000 Toman
            'source' => 'tgju',
            'fetched_at' => now(),
        ]);
        
        // Cache the rate
        app(CurrencyRateService::class)->cacheRate(50000); // 50,000 Toman
    }

    /** @test */
    public function it_returns_active_token_bundles_with_toman_prices(): void
    {
        // Clean up any existing bundles to ensure test isolation
        TokenBundle::query()->delete();
        
        // Create test bundles
        $bundle1 = TokenBundle::create([
            'name' => 'Starter Pack',
            'token_amount' => 100,
            'price_usd' => 1.00,
            'bonus_tokens' => 0,
            'is_active' => true,
            'display_order' => 1,
        ]);
        
        $bundle2 = TokenBundle::create([
            'name' => 'Standard Pack',
            'token_amount' => 500,
            'price_usd' => 4.50,
            'bonus_tokens' => 50,
            'is_active' => true,
            'display_order' => 2,
        ]);
        
        // Create inactive bundle (should not appear)
        TokenBundle::create([
            'name' => 'Inactive Pack',
            'token_amount' => 1000,
            'price_usd' => 8.00,
            'is_active' => false,
        ]);
        
        $response = $this->makeRequest('GET', '/api/v1/tokens/bundles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'token_amount',
                        'bonus_tokens',
                        'total_tokens',
                        'price_usd',
                        'price_toman',
                        'dollar_rate',
                        'is_active',
                    ],
                ],
                'meta' => [
                    'dollar_rate',
                    'rate_source',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $bundles = $response->json('data');
        $this->assertCount(2, $bundles); // Only active bundles
        
        // Verify price calculation: price_toman = price_usd * dollar_rate
        $bundle1Data = collect($bundles)->firstWhere('id', $bundle1->id);
        $this->assertEquals(50000.0, $bundle1Data['price_toman']); // 1.00 * 50000
        
        $bundle2Data = collect($bundles)->firstWhere('id', $bundle2->id);
        $this->assertEquals(225000.0, $bundle2Data['price_toman']); // 4.50 * 50000
        
        // Verify total_tokens includes bonus
        $this->assertEquals(550, $bundle2Data['total_tokens']); // 500 + 50

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_returns_bundles_ordered_by_display_order(): void
    {
        // Clean up any existing bundles to ensure test isolation
        TokenBundle::query()->delete();
        
        TokenBundle::create([
            'name' => 'Third',
            'token_amount' => 100,
            'price_usd' => 1.00,
            'is_active' => true,
            'display_order' => 3,
        ]);
        
        TokenBundle::create([
            'name' => 'First',
            'token_amount' => 100,
            'price_usd' => 1.00,
            'is_active' => true,
            'display_order' => 1,
        ]);
        
        TokenBundle::create([
            'name' => 'Second',
            'token_amount' => 100,
            'price_usd' => 1.00,
            'is_active' => true,
            'display_order' => 2,
        ]);
        
        $response = $this->makeRequest('GET', '/api/v1/tokens/bundles');

        $bundles = $response->json('data');
        $this->assertEquals('First', $bundles[0]['name']);
        $this->assertEquals('Second', $bundles[1]['name']);
        $this->assertEquals('Third', $bundles[2]['name']);
    }

    /** @test */
    public function it_uses_cached_currency_rate(): void
    {
        // Set cached rate
        Cache::put('currency_rate:usd_toman', 60000, 300);
        
        TokenBundle::create([
            'name' => 'Test Pack',
            'token_amount' => 100,
            'price_usd' => 1.00,
            'is_active' => true,
        ]);
        
        $response = $this->makeRequest('GET', '/api/v1/tokens/bundles');

        $bundle = $response->json('data.0');
        $this->assertEquals(60000.0, $bundle['price_toman']); // Uses cached rate
    }

    /** @test */
    public function it_falls_back_to_database_rate_if_cache_missing(): void
    {
        Cache::forget('currency_rate:usd_toman');
        
        TokenBundle::create([
            'name' => 'Test Pack',
            'token_amount' => 100,
            'price_usd' => 1.00,
            'is_active' => true,
        ]);
        
        $response = $this->makeRequest('GET', '/api/v1/tokens/bundles');

        $response->assertStatus(200);
        // Should use rate from database (50000 from setUp)
        $bundle = $response->json('data.0');
        $this->assertEquals(50000.0, $bundle['price_toman']);
    }

    /** @test */
    public function it_is_public_endpoint_no_auth_required(): void
    {
        TokenBundle::create([
            'name' => 'Test Pack',
            'token_amount' => 100,
            'price_usd' => 1.00,
            'is_active' => true,
        ]);
        
        // No auth header
        $response = $this->makeRequest('GET', '/api/v1/tokens/bundles');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}

