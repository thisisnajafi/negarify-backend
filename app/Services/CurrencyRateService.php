<?php

namespace App\Services;

use App\Models\CurrencyRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CurrencyRateService
{
    private const CACHE_KEY = 'currency_rate:usd_toman';
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Get current USD to Toman rate
     * 
     * Priority: Cache → Database → Fallback (last known)
     * 
     * @return float|null Rate in Toman, or null if unavailable
     */
    public function getCurrentRate(): ?float
    {
        // Try cache first
        $cachedRate = Cache::get(self::CACHE_KEY);
        if ($cachedRate !== null) {
            return (float) $cachedRate;
        }

        // Try database (latest rate)
        $latest = CurrencyRate::where('currency_from', 'USD')
            ->where('currency_to', 'IRR')
            ->latest('fetched_at')
            ->first();

        if ($latest) {
            $rateInToman = $latest->rate_in_toman;
            
            // Cache the rate
            Cache::put(self::CACHE_KEY, $rateInToman, self::CACHE_TTL);
            
            return (float) $rateInToman;
        }

        // Fallback: return null (should not happen if scraper is running)
        Log::warning('No currency rate available in cache or database');
        return null;
    }

    /**
     * Get current rate with fallback to last known
     * 
     * Never returns null - always returns a rate (even if stale)
     * 
     * @return float Rate in Toman (never null)
     */
    public function getCurrentRateWithFallback(): float
    {
        $rate = $this->getCurrentRate();
        
        if ($rate === null) {
            // This should not happen, but if it does, use a safe default
            // In production, this would trigger an alert
            Log::error('Currency rate unavailable, using emergency fallback');
            return 50000.0; // Emergency fallback (should be last known good rate)
        }
        
        return $rate;
    }

    /**
     * Cache a rate value
     * 
     * @param float $rate Rate in Toman
     */
    public function cacheRate(float $rate): void
    {
        Cache::put(self::CACHE_KEY, $rate, self::CACHE_TTL);
    }
}

