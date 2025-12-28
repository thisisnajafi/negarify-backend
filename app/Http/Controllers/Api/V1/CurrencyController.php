<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CurrencyRate;
use App\Services\CurrencyRateService;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    public function __construct(
        private readonly CurrencyRateService $currencyRateService
    ) {
    }

    /**
     * Get current USD to Toman exchange rate
     * 
     * Public endpoint - no authentication required
     * Rate is public information
     */
    public function getRate(): JsonResponse
    {
        // Get latest rate from database
        $latest = CurrencyRate::where('currency_from', 'USD')
            ->where('currency_to', 'IRR')
            ->latest('fetched_at')
            ->first();

        if (!$latest) {
            return response()->json([
                'success' => false,
                'message' => 'Currency rate not available',
            ], 503);
        }

        // Get current rate (from cache or database)
        $currentRate = $this->currencyRateService->getCurrentRate();

        return response()->json([
            'success' => true,
            'data' => [
                'rate' => (float) $latest->rate, // Rials rate
                'rate_in_toman' => $currentRate, // Toman rate
                'currency_from' => $latest->currency_from,
                'currency_to' => $latest->currency_to,
                'source' => $latest->source,
                'fetched_at' => $latest->fetched_at->toISOString(),
            ],
        ]);
    }
}

