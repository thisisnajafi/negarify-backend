<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ModelsUsageRequest;
use App\Models\AnalyticsModelsUsage;
use App\Models\GenerationJob;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminModelsController extends Controller
{
    /**
     * Get models usage statistics
     * 
     * Authorization: Admin only
     */
    public function usage(ModelsUsageRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Build date range
        $dateRange = $this->buildDateRange($validated);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];
        
        // Cache key
        $cacheKey = "admin:models:usage:{$validated['range'] ?? 'custom'}:{$startDate->format('Y-m-d')}:{$endDate->format('Y-m-d')}";
        
        // Try cache first
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json([
                'success' => true,
                'data' => $cached,
            ]);
        }
        
        // Try to use aggregated data first (if available)
        $useAggregated = AnalyticsModelsUsage::where('period_start', '>=', $startDate)
            ->where('period_end', '<=', $endDate)
            ->exists();
        
        if ($useAggregated) {
            // Use aggregated data
            $usageData = AnalyticsModelsUsage::where('period_start', '>=', $startDate)
                ->where('period_end', '<=', $endDate)
                ->with('model:id,model_name,provider_id')
                ->get()
                ->groupBy('model_id')
                ->map(function ($records, $modelId) {
                    $model = $records->first()->model;
                    return [
                        'model_id' => (int) $modelId,
                        'model_name' => $model->model_name ?? 'N/A',
                        'requests_count' => $records->sum('requests_count'),
                        'successful_count' => $records->sum('successful_count'),
                        'failed_count' => $records->sum('failed_count'),
                        'tokens_consumed' => $records->sum('tokens_consumed'),
                        'cost_usd' => (float) $records->sum('cost_usd'),
                        'revenue_usd' => (float) $records->sum('revenue_usd'),
                        'avg_latency_ms' => $records->avg('avg_latency_ms') ? (int) $records->avg('avg_latency_ms') : null,
                        'success_rate' => $records->sum('requests_count') > 0 
                            ? round($records->sum('successful_count') / $records->sum('requests_count') * 100, 2)
                            : 0,
                        'failure_rate' => $records->sum('requests_count') > 0
                            ? round($records->sum('failed_count') / $records->sum('requests_count') * 100, 2)
                            : 0,
                    ];
                })
                ->values();
        } else {
            // Fallback to raw data from generation_jobs
            $usageData = GenerationJob::where('status', '!=', 'pending')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->with('model:id,model_name,provider_id')
                ->selectRaw('
                    model_id,
                    job_type,
                    COUNT(*) as requests_count,
                    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful_count,
                    SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count,
                    SUM(tokens_consumed) as tokens_consumed,
                    SUM(cost_usd) as cost_usd,
                    AVG(CASE WHEN started_at IS NOT NULL AND completed_at IS NOT NULL 
                        THEN TIMESTAMPDIFF(MILLISECOND, started_at, completed_at) 
                        ELSE NULL END) as avg_latency_ms
                ')
                ->groupBy('model_id', 'job_type')
                ->get()
                ->groupBy('model_id')
                ->map(function ($records, $modelId) {
                    $model = $records->first()->model;
                    return [
                        'model_id' => (int) $modelId,
                        'model_name' => $model->model_name ?? 'N/A',
                        'requests_count' => (int) $records->sum('requests_count'),
                        'successful_count' => (int) $records->sum('successful_count'),
                        'failed_count' => (int) $records->sum('failed_count'),
                        'tokens_consumed' => (int) $records->sum('tokens_consumed'),
                        'cost_usd' => (float) $records->sum('cost_usd'),
                        'revenue_usd' => 0, // Not available from generation_jobs
                        'avg_latency_ms' => $records->avg('avg_latency_ms') ? (int) $records->avg('avg_latency_ms') : null,
                        'success_rate' => $records->sum('requests_count') > 0
                            ? round($records->sum('successful_count') / $records->sum('requests_count') * 100, 2)
                            : 0,
                        'failure_rate' => $records->sum('requests_count') > 0
                            ? round($records->sum('failed_count') / $records->sum('requests_count') * 100, 2)
                            : 0,
                        'breakdown_by_type' => $records->map(function ($record) {
                            return [
                                'job_type' => $record->job_type,
                                'requests_count' => (int) $record->requests_count,
                                'tokens_consumed' => (int) $record->tokens_consumed,
                                'cost_usd' => (float) $record->cost_usd,
                            ];
                        })->values(),
                    ];
                })
                ->values();
        }
        
        // Build response
        $response = [
            'models' => $usageData,
            'summary' => [
                'total_requests' => $usageData->sum('requests_count'),
                'total_successful' => $usageData->sum('successful_count'),
                'total_failed' => $usageData->sum('failed_count'),
                'total_tokens_consumed' => $usageData->sum('tokens_consumed'),
                'total_cost_usd' => (float) $usageData->sum('cost_usd'),
                'total_revenue_usd' => (float) $usageData->sum('revenue_usd'),
                'overall_success_rate' => $usageData->sum('requests_count') > 0
                    ? round($usageData->sum('successful_count') / $usageData->sum('requests_count') * 100, 2)
                    : 0,
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



