<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CostProfitSummaryRequest;
use App\Models\GenerationJob;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminCostProfitController extends Controller
{
    /**
     * Get cost and profit summary
     * 
     * Authorization: Admin only
     */
    public function summary(CostProfitSummaryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Build date range
        $dateRange = $this->buildDateRange($validated);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];
        
        // Cache key
        $range = $validated['range'] ?? 'custom';
        $cacheKey = "admin:cost-profit:summary:{$range}:{$startDate->format('Y-m-d')}:{$endDate->format('Y-m-d')}";
        
        // Try cache first
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json([
                'success' => true,
                'data' => $cached,
            ]);
        }
        
        // Revenue: Only paid orders
        $revenueData = Order::where('status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                SUM(price_usd) as total_revenue_usd,
                SUM(price_toman) as total_revenue_toman
            ')
            ->first();
        
        // Cost: Only completed generation jobs
        $costData = GenerationJob::where('generation_jobs.status', 'completed')
            ->whereBetween('generation_jobs.created_at', [$startDate, $endDate])
            ->selectRaw('
                SUM(generation_jobs.cost_usd) as total_cost_usd
            ')
            ->first();
        
        $totalRevenueUsd = (float) ($revenueData->total_revenue_usd ?? 0);
        $totalCostUsd = (float) ($costData->total_cost_usd ?? 0);
        $totalProfitUsd = $totalRevenueUsd - $totalCostUsd;
        $profitMargin = $totalRevenueUsd > 0 
            ? round(($totalProfitUsd / $totalRevenueUsd) * 100, 2) 
            : 0;
        
        // Breakdown by model
        $breakdownByModel = GenerationJob::where('generation_jobs.status', 'completed')
            ->whereBetween('generation_jobs.created_at', [$startDate, $endDate])
            ->join('models', 'generation_jobs.model_id', '=', 'models.id')
            ->selectRaw('
                models.id as model_id,
                models.model_name,
                SUM(generation_jobs.cost_usd) as cost_usd,
                SUM(generation_jobs.tokens_consumed) as tokens_consumed,
                COUNT(*) as jobs_count
            ')
            ->groupBy('models.id', 'models.model_name')
            ->get()
            ->map(function ($item) {
                // Revenue per model is approximated from token consumption
                // (actual revenue would require mapping tokens to orders, which is complex)
                return [
                    'model_id' => (int) $item->model_id,
                    'model_name' => $item->model_name,
                    'cost_usd' => (float) $item->cost_usd,
                    'tokens_consumed' => (int) $item->tokens_consumed,
                    'jobs_count' => (int) $item->jobs_count,
                    'revenue_usd' => 0, // Complex to calculate accurately
                    'profit_usd' => 0, // Complex to calculate accurately
                ];
            });
        
        // Breakdown by provider
        $breakdownByProvider = GenerationJob::where('generation_jobs.status', 'completed')
            ->whereBetween('generation_jobs.created_at', [$startDate, $endDate])
            ->join('providers', 'generation_jobs.provider_id', '=', 'providers.id')
            ->selectRaw('
                providers.id as provider_id,
                providers.name as provider_name,
                SUM(generation_jobs.cost_usd) as cost_usd,
                SUM(generation_jobs.tokens_consumed) as tokens_consumed,
                COUNT(*) as jobs_count
            ')
            ->groupBy('providers.id', 'providers.name')
            ->get()
            ->map(function ($item) {
                return [
                    'provider_id' => (int) $item->provider_id,
                    'provider_name' => $item->provider_name,
                    'cost_usd' => (float) $item->cost_usd,
                    'tokens_consumed' => (int) $item->tokens_consumed,
                    'jobs_count' => (int) $item->jobs_count,
                    'revenue_usd' => 0, // Complex to calculate accurately
                    'profit_usd' => 0, // Complex to calculate accurately
                ];
            });
        
        // Profit margins over time (daily)
        // Use database-agnostic date extraction
        $dateFormat = DB::getDriverName() === 'sqlite' 
            ? "strftime('%Y-%m-%d', orders.created_at)" 
            : "DATE(orders.created_at)";
        
        $profitMarginsOverTime = DB::table('orders')
            ->where('orders.status', 'paid')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->selectRaw("
                {$dateFormat} as date,
                SUM(orders.price_usd) as revenue_usd
            ")
            ->groupBy('date')
            ->get()
            ->map(function ($orderItem) use ($startDate, $endDate) {
                $date = Carbon::parse($orderItem->date);
                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->endOfDay();
                
                // Get cost for this day
                $dayCost = GenerationJob::where('generation_jobs.status', 'completed')
                    ->whereBetween('generation_jobs.created_at', [$dayStart, $dayEnd])
                    ->sum('generation_jobs.cost_usd');
                
                $revenue = (float) $orderItem->revenue_usd;
                $cost = (float) $dayCost;
                $profit = $revenue - $cost;
                $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0;
                
                return [
                    'date' => $orderItem->date,
                    'revenue_usd' => $revenue,
                    'cost_usd' => $cost,
                    'profit_usd' => $profit,
                    'profit_margin' => $margin,
                ];
            })
            ->sortBy('date')
            ->values();
        
        // Historical comparison (previous period)
        $previousStart = $startDate->copy()->sub($endDate->diffInDays($startDate) + 1, 'day');
        $previousEnd = $startDate->copy()->subDay();
        
        $previousRevenue = Order::where('status', 'paid')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->sum('price_usd');
        
        $previousCost = GenerationJob::where('generation_jobs.status', 'completed')
            ->whereBetween('generation_jobs.created_at', [$previousStart, $previousEnd])
            ->sum('generation_jobs.cost_usd');
        
        $previousProfit = $previousRevenue - $previousCost;
        $previousMargin = $previousRevenue > 0 
            ? round(($previousProfit / $previousRevenue) * 100, 2) 
            : 0;
        
        $revenueGrowth = $previousRevenue > 0 
            ? round((($totalRevenueUsd - $previousRevenue) / $previousRevenue) * 100, 2) 
            : 0;
        $profitGrowth = $previousProfit != 0 
            ? round((($totalProfitUsd - $previousProfit) / abs($previousProfit)) * 100, 2) 
            : 0;
        
        // Build response
        $response = [
            'summary' => [
                'total_revenue_usd' => $totalRevenueUsd,
                'total_revenue_toman' => (float) ($revenueData->total_revenue_toman ?? 0),
                'total_cost_usd' => $totalCostUsd,
                'total_profit_usd' => $totalProfitUsd,
                'profit_margin' => $profitMargin,
            ],
            'breakdown_by_model' => $breakdownByModel,
            'breakdown_by_provider' => $breakdownByProvider,
            'profit_margins_over_time' => $profitMarginsOverTime,
            'historical_comparison' => [
                'previous_period' => [
                    'start_date' => $previousStart->format('Y-m-d'),
                    'end_date' => $previousEnd->format('Y-m-d'),
                    'revenue_usd' => (float) $previousRevenue,
                    'cost_usd' => (float) $previousCost,
                    'profit_usd' => (float) $previousProfit,
                    'profit_margin' => $previousMargin,
                ],
                'current_period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'revenue_usd' => $totalRevenueUsd,
                    'cost_usd' => $totalCostUsd,
                    'profit_usd' => $totalProfitUsd,
                    'profit_margin' => $profitMargin,
                ],
                'growth' => [
                    'revenue_growth_percent' => $revenueGrowth,
                    'profit_growth_percent' => $profitGrowth,
                ],
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

