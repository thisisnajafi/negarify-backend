<?php

namespace App\Services;

use App\Models\CurrencyRate;
use App\Services\CurrencyRateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TgjuScraperService
{
    private const TGJU_URL = 'https://www.tgju.org/profile/price_dollar_rl';
    private const CACHE_KEY = 'currency_rate:usd_toman';
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly CurrencyRateService $currencyRateService
    ) {
    }

    /**
     * Fetch USD to Rials rate from TGJU.org and store in database
     * 
     * @return float|null Rate in Toman (Rials / 10), or null on failure
     */
    public function fetchUsdRate(): ?float
    {
        try {
            Log::info('Fetching USD rate from TGJU.org');

            // Fetch HTML from TGJU.org
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ])
                ->get(self::TGJU_URL);

            if (!$response->successful()) {
                Log::error('TGJU.org request failed', [
                    'status' => $response->status(),
                    'url' => self::TGJU_URL,
                ]);
                return null;
            }

            $html = $response->body();

            // Extract USD to Rials rate from HTML
            $rialsRate = $this->extractRateFromHtml($html);

            if ($rialsRate === null) {
                Log::error('Failed to extract rate from TGJU.org HTML');
                return null;
            }

            // Validate rate (must be positive and reasonable)
            if ($rialsRate <= 0 || $rialsRate > 1000000) {
                Log::error('Invalid rate extracted from TGJU.org', [
                    'rate' => $rialsRate,
                ]);
                return null;
            }

            // Convert Rials to Toman (divide by 10)
            $tomanRate = $rialsRate / 10;

            // Store rate in database (append-only, never updates)
            $currencyRate = CurrencyRate::create([
                'currency_from' => 'USD',
                'currency_to' => 'IRR',
                'rate' => $rialsRate, // Store Rials rate
                'source' => 'tgju',
                'fetched_at' => now(),
            ]);

            // Cache Toman rate
            $this->currencyRateService->cacheRate($tomanRate);

            Log::info('USD rate fetched and stored successfully', [
                'rials_rate' => $rialsRate,
                'toman_rate' => $tomanRate,
                'currency_rate_id' => $currencyRate->id,
            ]);

            return $tomanRate;
        } catch (\Exception $e) {
            Log::error('Exception while fetching USD rate from TGJU.org', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Extract USD to Rials rate from TGJU.org HTML
     * 
     * TGJU.org structure may vary, so we try multiple extraction methods
     * 
     * @param string $html HTML content from TGJU.org
     * @return float|null Rate in Rials, or null if extraction fails
     */
    private function extractRateFromHtml(string $html): ?float
    {
        // Method 1: Look for data-price attribute (common in TGJU.org)
        if (preg_match('/data-price=["\']?([0-9,]+)["\']?/i', $html, $matches)) {
            $rate = $this->parseRate($matches[1]);
            if ($rate !== null) {
                return $rate;
            }
        }

        // Method 2: Look for price in specific HTML structure
        // TGJU.org often uses: <span class="value">...</span> or similar
        if (preg_match('/<span[^>]*class=["\']?[^"\']*value[^"\']*["\']?[^>]*>([0-9,]+)<\/span>/i', $html, $matches)) {
            $rate = $this->parseRate($matches[1]);
            if ($rate !== null) {
                return $rate;
            }
        }

        // Method 3: Look for JSON-LD structured data
        if (preg_match('/"price":\s*"?([0-9,]+)"?/i', $html, $matches)) {
            $rate = $this->parseRate($matches[1]);
            if ($rate !== null) {
                return $rate;
            }
        }

        // Method 4: Look for common price patterns
        // Try to find numbers that look like exchange rates (typically 5-6 digits)
        if (preg_match('/\b([4-9][0-9]{4,5})\b/', $html, $matches)) {
            $rate = (float) str_replace(',', '', $matches[1]);
            // Validate it's a reasonable rate (USD to Rials is typically 40k-60k)
            if ($rate >= 30000 && $rate <= 100000) {
                return $rate;
            }
        }

        Log::warning('Could not extract rate from TGJU.org HTML using any method');
        return null;
    }

    /**
     * Parse rate string (remove commas, convert to float)
     * 
     * @param string $rateString Rate string (may contain commas)
     * @return float|null Parsed rate or null if invalid
     */
    private function parseRate(string $rateString): ?float
    {
        // Remove commas and whitespace
        $cleaned = str_replace([',', ' '], '', trim($rateString));
        
        if (!is_numeric($cleaned)) {
            return null;
        }

        $rate = (float) $cleaned;

        // Validate rate is reasonable (USD to Rials is typically 30k-100k)
        if ($rate < 10000 || $rate > 200000) {
            return null;
        }

        return $rate;
    }

    /**
     * Get current USD to Toman rate
     * 
     * Priority: Cache → Database → Fallback
     * 
     * @return float Rate in Toman (never null - uses fallback if needed)
     */
    public function getCurrentRate(): float
    {
        return $this->currencyRateService->getCurrentRateWithFallback();
    }
}

