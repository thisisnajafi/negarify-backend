<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TokenAnalyticsRequest;
use App\Models\GenerationJob;
use App\Models\Provider;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminTokensController extends Controller
{
    /**
     * Get token analytics summary
     * 
     * Authorization: Admin only
     */
    public function summary(TokenAnalyticsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Build date range
        $dateRange = $this->buildDateRange($validated);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];
        
        // Cache key
        $range = $validated['range'] ?? 'custom';
        $cacheKey = "admin:tokens:analytics:{$range}:{$startDate->format('Y-m-d')}:{$endDate->format('Y-m-d')}";
        
        // Try cache first
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json([
                'success' => true,
                'data' => $cached,
            ]);
        }
        
        // Tokens consumed per provider
        $tokensByProvider = GenerationJob::where('generation_jobs.status', 'completed')
            ->whereBetween('generation_jobs.created_at', [$startDate, $endDate])
            ->join('providers', 'generation_jobs.provider_id', '=', 'providers.id')
            ->selectRaw('
                providers.id as provider_id,
                providers.name as provider_name,
                SUM(generation_jobs.tokens_consumed) as tokens_consumed,
                SUM(generation_jobs.cost_usd) as cost_usd
            ')
            ->groupBy('providers.id', 'providers.name')
            ->get()
            ->map(function ($item) {
                return [
                    'provider_id' => (int) $item->provider_id,
                    'provider_name' => $item->provider_name,
                    'tokens_consumed' => (int) $item->tokens_consumed,
                    'cost_usd' => (float) $item->cost_usd,
                ];
            });
        
        // Tokens by type (image/video/audio)
        $tokensByType = GenerationJob::where('generation_jobs.status', 'completed')
            ->whereBetween('generation_jobs.created_at', [$startDate, $endDate])
            ->selectRaw('
                generation_jobs.job_type,
                SUM(generation_jobs.tokens_consumed) as tokens_consumed,
                SUM(generation_jobs.cost_usd) as cost_usd,
                COUNT(*) as jobs_count
            ')
            ->groupBy('generation_jobs.job_type')
            ->get()
            ->map(function ($item) {
                return [
                    'job_type' => $item->job_type,
                    'tokens_consumed' => (int) $item->tokens_consumed,
                    'cost_usd' => (float) $item->cost_usd,
                    'jobs_count' => (int) $item->jobs_count,
                ];
            });
        
        // Time-series data (by day)
        // Use database-agnostic date extraction
        $dateFormat = DB::getDriverName() === 'sqlite' 
            ? "strftime('%Y-%m-%d', generation_jobs.created_at)" 
            : "DATE(generation_jobs.created_at)";
        
        $timeSeries = GenerationJob::where('generation_jobs.status', 'completed')
            ->whereBetween('generation_jobs.created_at', [$startDate, $endDate])
            ->selectRaw("
                {$dateFormat} as date,
                SUM(generation_jobs.tokens_consumed) as tokens_consumed,
                SUM(generation_jobs.cost_usd) as cost_usd,
                COUNT(*) as jobs_count
            ")
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'tokens_consumed' => (int) $item->tokens_consumed,
                    'cost_usd' => (float) $item->cost_usd,
                    'jobs_count' => (int) $item->jobs_count,
                ];
            });
        
        // Build response
        $response = [
            'tokens_by_provider' => $tokensByProvider,
            'tokens_by_type' => $tokensByType,
            'time_series' => $timeSeries,
            'summary' => [
                'total_tokens_consumed' => $tokensByProvider->sum('tokens_consumed'),
                'total_cost_usd' => (float) $tokensByProvider->sum('cost_usd'),
                'total_jobs' => $tokensByType->sum('jobs_count'),
            ],
            'date_range' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
        ];
        
        // Cache for 10 minutes
        Cache::put($cacheKey, $response, 600);
        
        return response()->json([
            'success' => true,
            'data' => $response,
        ]);
    }
    
    /**
     * Build date range from request parameters
     */
    protected function buildDateRange(array $validated): array
    {
        // If explicit dates provided, use them
        if (isset($validated['start_date']) && isset($validated['end_date'])) {
            return [
                'start' => Carbon::parse($validated['start_date'])->startOfDay(),
                'end' => Carbon::parse($validated['end_date'])->endOfDay(),
            ];
        }
        
        // Otherwise use range parameter
        $range = $validated['range'] ?? 'month';
        $end = now();
        
        $start = match($range) {
            'day' => $end->copy()->subDay(),
            'week' => $end->copy()->subWeek(),
            'month' => $end->copy()->subMonth(),
            'year' => $end->copy()->subYear(),
            default => $end->copy()->subMonth(),
        };
        
        return [
            'start' => $start->startOfDay(),
            'end' => $end->endOfDay(),
        ];
    }
}



