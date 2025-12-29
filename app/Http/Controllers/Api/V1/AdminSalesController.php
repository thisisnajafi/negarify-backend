<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SalesSummaryRequest;
use App\Models\Order;
use App\Models\TokenBundle;
use App\Models\TokenTransaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminSalesController extends Controller
{
    /**
     * Get sales summary with revenue, orders, top bundles, refunds, and LTV
     * 
     * Authorization: Admin only
     */
    public function summary(SalesSummaryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Build date range
        $dateRange = $this->buildDateRange($validated);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];
        
        // Cache key
        $cacheKey = "admin:sales:summary:{$validated['range'] ?? 'custom'}:{$startDate->format('Y-m-d')}:{$endDate->format('Y-m-d')}";
        
        // Try cache first
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json([
                'success' => true,
                'data' => $cached,
            ]);
        }
        
        // Get total revenue (only paid orders)
        $revenueData = Order::where('status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                SUM(price_toman) as total_revenue_toman,
                SUM(price_usd) as total_revenue_usd,
                COUNT(*) as total_orders
            ')
            ->first();
        
        // Get revenue by day
        $revenueByDay = Order::where('status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                DATE(created_at) as date,
                SUM(price_toman) as revenue_toman,
                SUM(price_usd) as revenue_usd,
                COUNT(*) as orders_count
            ')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();
        
        // Get top selling bundles
        $topBundles = Order::where('status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->join('token_bundles', 'orders.token_bundle_id', '=', 'token_bundles.id')
            ->selectRaw('
                token_bundles.id,
                token_bundles.name,
                token_bundles.amount_tokens,
                COUNT(orders.id) as orders_count,
                SUM(orders.price_toman) as total_revenue_toman,
                SUM(orders.price_usd) as total_revenue_usd
            ')
            ->groupBy('token_bundles.id', 'token_bundles.name', 'token_bundles.amount_tokens')
            ->orderBy('orders_count', 'desc')
            ->limit(10)
            ->get();
        
        // Get refunds
        $refunds = TokenTransaction::where('type', 'refund')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as refunds_count,
                SUM(ABS(amount_tokens)) as refunded_tokens,
                SUM(ABS(amount_usd)) as refunded_usd
            ')
            ->first();
        
        // Calculate customer LTV (average revenue per customer)
        $ltvData = Order::where('status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(DISTINCT user_id) as unique_customers,
                SUM(price_usd) as total_revenue_usd
            ')
            ->first();
        
        $avgLtv = $ltvData->unique_customers > 0 
            ? round($ltvData->total_revenue_usd / $ltvData->unique_customers, 2)
            : 0;
        
        // Build response
        $response = [
            'total_revenue_toman' => (float) ($revenueData->total_revenue_toman ?? 0),
            'total_revenue_usd' => (float) ($revenueData->total_revenue_usd ?? 0),
            'total_orders' => (int) ($revenueData->total_orders ?? 0),
            'revenue_by_day' => $revenueByDay->map(function ($item) {
                return [
                    'date' => $item->date,
                    'revenue_toman' => (float) $item->revenue_toman,
                    'revenue_usd' => (float) $item->revenue_usd,
                    'orders_count' => (int) $item->orders_count,
                ];
            }),
            'top_bundles' => $topBundles->map(function ($bundle) {
                return [
                    'id' => $bundle->id,
                    'name' => $bundle->name,
                    'amount_tokens' => (int) $bundle->amount_tokens,
                    'orders_count' => (int) $bundle->orders_count,
                    'total_revenue_toman' => (float) $bundle->total_revenue_toman,
                    'total_revenue_usd' => (float) $bundle->total_revenue_usd,
                ];
            }),
            'refunds' => [
                'count' => (int) ($refunds->refunds_count ?? 0),
                'refunded_tokens' => (int) ($refunds->refunded_tokens ?? 0),
                'refunded_usd' => (float) ($refunds->refunded_usd ?? 0),
            ],
            'customer_ltv' => [
                'unique_customers' => (int) ($ltvData->unique_customers ?? 0),
                'average_ltv_usd' => $avgLtv,
                'total_revenue_usd' => (float) ($ltvData->total_revenue_usd ?? 0),
            ],
            'date_range' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
        ];
        
        // Cache for 5 minutes
        Cache::put($cacheKey, $response, 300);
        
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



