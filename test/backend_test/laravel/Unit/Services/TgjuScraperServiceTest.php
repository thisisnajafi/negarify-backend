<?php

namespace Test\BackendTest\Laravel\Unit\Services;

use App\Models\CurrencyRate;
use App\Services\CurrencyRateService;
use App\Services\TgjuScraperService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class TgjuScraperServiceTest extends BackendTestCase
{
    // Override to use DatabaseMigrations instead of RefreshDatabase
    // Reason: Service creates CurrencyRate records which conflicts with RefreshDatabase's transaction wrapping in SQLite
    use DatabaseMigrations;
    /** @test */
    public function it_fetches_and_stores_usd_rate_from_tgju(): void
    {
        // Use a valid rate within parseRate validation range (10000-200000)
        // 50000 Rials = 5000 Toman
        $rialsRate = 50000;
        $htmlFixture = $this->getTgjuHtmlFixture($rialsRate);
        
        Http::fake([
            'www.tgju.org/*' => Http::response($htmlFixture, 200),
        ]);
        
        $service = app(TgjuScraperService::class);
        $rate = $service->fetchUsdRate();
        
        $this->assertNotNull($rate);
        $this->assertIsFloat($rate);
        $this->assertGreaterThan(0, $rate);
        $this->assertEquals(5000.0, $rate); // Should be Toman (50000 / 10)
        
        // Verify rate stored in database
        $this->assertDatabaseHas('currency_rates', [
            'currency_from' => 'USD',
            'currency_to' => 'IRR',
            'source' => 'tgju',
            'rate' => (float) $rialsRate, // Stored as Rials
        ]);
        
        // Verify rate is in Toman (Rials / 10)
        $storedRate = CurrencyRate::latest('fetched_at')->first();
        $this->assertEquals($rialsRate, $storedRate->rate); // Stored as Rials
        $this->assertEquals($rate * 10, $storedRate->rate); // Stored as Rials
    }

    /** @test */
    public function it_caches_rate_after_fetching(): void
    {
        $htmlFixture = $this->getTgjuHtmlFixture();
        
        Http::fake([
            'www.tgju.org/*' => Http::response($htmlFixture, 200),
        ]);
        
        Cache::forget('currency_rate:usd_toman');
        
        $service = app(TgjuScraperService::class);
        $rate = $service->fetchUsdRate();
        
        // Verify cached
        $cachedRate = Cache::get('currency_rate:usd_toman');
        $this->assertEquals($rate, $cachedRate);
    }

    /** @test */
    public function it_handles_http_failure_gracefully(): void
    {
        // Allow expected error logs
        $this->allowErrorLogs(['TGJU.org request failed']);
        
        Http::fake([
            'www.tgju.org/*' => Http::response('', 500),
        ]);
        
        $service = app(TgjuScraperService::class);
        $rate = $service->fetchUsdRate();
        
        $this->assertNull($rate);
        
        // Verify no rate stored
        $this->assertDatabaseMissing('currency_rates', [
            'fetched_at' => now()->toDateString(),
        ]);
    }

    /** @test */
    public function it_handles_html_parsing_failure_gracefully(): void
    {
        // Allow expected error logs
        $this->allowErrorLogs(['Failed to extract rate from TGJU.org HTML']);
        
        Http::fake([
            'www.tgju.org/*' => Http::response('<html><body>Invalid HTML</body></html>', 200),
        ]);
        
        $service = app(TgjuScraperService::class);
        $rate = $service->fetchUsdRate();
        
        $this->assertNull($rate);
    }

    /** @test */
    public function it_validates_rate_is_reasonable(): void
    {
        // Test with rate that passes parseRate validation (10000-200000) but fails fetchUsdRate validation (> 1000000)
        // Actually, parseRate validates 10000-200000, so we can't get a rate > 1000000 through parseRate
        // Instead, test with a rate just outside parseRate's range (e.g., 250000)
        // This will be extracted but rejected by parseRate validation
        $invalidRate = 250000; // > 200000, will be rejected by parseRate
        $htmlWithHighRate = $this->getTgjuHtmlFixture($invalidRate);
        
        Http::fake([
            'www.tgju.org/*' => Http::response($htmlWithHighRate, 200),
        ]);
        
        $service = app(TgjuScraperService::class);
        $rate = $service->fetchUsdRate();
        
        // Should reject rate outside parseRate validation range (10000-200000)
        $this->assertNull($rate);
        
        // Allow expected warning logs (parseRate returns null, so extraction fails)
        $this->allowErrorLogs(['Failed to extract rate from TGJU.org HTML']);
    }

    /** @test */
    public function it_converts_rials_to_toman_correctly(): void
    {
        // Use a valid rate within parseRate validation range (10000-200000)
        // 100000 Rials = 10000 Toman
        $rialsRate = 100000;
        $htmlFixture = $this->getTgjuHtmlFixture($rialsRate);
        
        Http::fake([
            'www.tgju.org/*' => Http::response($htmlFixture, 200),
        ]);
        
        $service = app(TgjuScraperService::class);
        $rate = $service->fetchUsdRate();
        
        // Should return 10,000 Toman (100,000 / 10)
        $this->assertEquals(10000.0, $rate);
        
        // Database should store Rials rate
        $storedRate = CurrencyRate::latest('fetched_at')->first();
        $this->assertEquals($rialsRate, $storedRate->rate);
    }

    /**
     * Get TGJU HTML fixture for testing
     */
    private function getTgjuHtmlFixture(int $rialsRate = 50000): string
    {
        // Use Method 1 (data-price attribute) which is the most reliable
        // Format: <div data-price="50000"></div>
        return <<<HTML
<!DOCTYPE html>
<html>
<head><title>TGJU - Dollar Price</title></head>
<body>
    <div data-price="{$rialsRate}"></div>
</body>
</html>
HTML;
    }
}

