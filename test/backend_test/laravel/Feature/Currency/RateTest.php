<?php

namespace Test\BackendTest\Laravel\Feature\Currency;

use App\Models\CurrencyRate;
use App\Services\CurrencyRateService;
use Illuminate\Support\Facades\Cache;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class RateTest extends BackendTestCase
{
    /** @test */
    public function it_returns_current_currency_rate(): void
    {
        CurrencyRate::create([
            'currency_from' => 'USD',
            'currency_to' => 'IRR',
            'rate' => 500000, // Rials
            'source' => 'tgju',
            'fetched_at' => now(),
        ]);
        
        $response = $this->makeRequest('GET', '/api/v1/currency/rate');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'rate',
                    'rate_in_toman',
                    'currency_from',
                    'currency_to',
                    'source',
                    'fetched_at',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'rate' => 500000.0,
                    'rate_in_toman' => 50000.0, // Rials / 10
                    'currency_from' => 'USD',
                    'currency_to' => 'IRR',
                    'source' => 'tgju',
                ],
            ]);

        $this->assertNoErrorLogs();
    }

    /** @test */
    public function it_returns_503_when_no_rate_available(): void
    {
        // No currency rate in database
        $response = $this->makeRequest('GET', '/api/v1/currency/rate');

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'Currency rate not available',
            ]);
    }

    /** @test */
    public function it_uses_cached_rate_from_service(): void
    {
        // Create old rate in database
        CurrencyRate::create([
            'currency_from' => 'USD',
            'currency_to' => 'IRR',
            'rate' => 400000, // Old rate
            'source' => 'tgju',
            'fetched_at' => now()->subHour(),
        ]);
        
        // Set cached rate (newer)
        app(CurrencyRateService::class)->cacheRate(60000); // 60,000 Toman
        
        $response = $this->makeRequest('GET', '/api/v1/currency/rate');

        $response->assertStatus(200);
        // Should use cached rate (60,000) not database rate (40,000)
        $this->assertEquals(60000.0, $response->json('data.rate_in_toman'));
    }

    /** @test */
    public function it_is_public_endpoint_no_auth_required(): void
    {
        CurrencyRate::create([
            'currency_from' => 'USD',
            'currency_to' => 'IRR',
            'rate' => 500000,
            'source' => 'tgju',
            'fetched_at' => now(),
        ]);
        
        // No auth header
        $response = $this->makeRequest('GET', '/api/v1/currency/rate');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}

