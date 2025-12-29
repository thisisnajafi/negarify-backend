<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UsersListRequest;
use App\Http\Requests\Api\V1\UsersSummaryRequest;
use App\Models\GenerationJob;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminUsersController extends Controller
{
    /**
     * Get users summary with DAU/WAU/MAU, top users, and cohort analysis
     * 
     * Authorization: Admin only
     */
    public function summary(UsersSummaryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $range = $validated['range'] ?? 'month';
        
        // Cache key
        $cacheKey = "admin:users:summary:{$range}";
        
        // Try cache first
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json([
                'success' => true,
                'data' => $cached,
            ]);
        }
        
        // Calculate DAU/WAU/MAU
        $now = now();
        $dauStart = $now->copy()->subDay();
        $wauStart = $now->copy()->subWeek();
        $mauStart = $now->copy()->subMonth();
        
        // DAU: Users who created generation jobs or logged in (if last_login_at exists) in last 24 hours
        $dau = GenerationJob::where('created_at', '>=', $dauStart)
            ->distinct('user_id')
            ->count('user_id');
        
        // WAU: Users active in last 7 days
        $wau = GenerationJob::where('created_at', '>=', $wauStart)
            ->distinct('user_id')
            ->count('user_id');
        
        // MAU: Users active in last 30 days
        $mau = GenerationJob::where('created_at', '>=', $mauStart)
            ->distinct('user_id')
            ->count('user_id');
        
        // Top users by generation count
        $topUsersByGeneration = User::select('users.id', 'users.name', 'users.phone', 'users.email')
            ->join('generation_jobs', 'users.id', '=', 'generation_jobs.user_id')
            ->where('generation_jobs.created_at', '>=', $mauStart) // Last 30 days
            ->groupBy('users.id', 'users.name', 'users.phone', 'users.email')
            ->selectRaw('COUNT(generation_jobs.id) as generation_count')
            ->orderBy('generation_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'user_id' => $user->id,
                    'name' => $user->name ?? 'N/A',
                    'phone' => $user->phone ?? 'N/A',
                    'generation_count' => (int) $user->generation_count,
                ];
            });
        
        // Top users by spending
        $topUsersBySpending = User::select('users.id', 'users.name', 'users.phone', 'users.email')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->where('orders.status', 'paid')
            ->where('orders.created_at', '>=', $mauStart) // Last 30 days
            ->groupBy('users.id', 'users.name', 'users.phone', 'users.email')
            ->selectRaw('SUM(orders.price_usd) as total_spending')
            ->orderBy('total_spending', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'user_id' => $user->id,
                    'name' => $user->name ?? 'N/A',
                    'phone' => $user->phone ?? 'N/A',
                    'total_spending_usd' => (float) $user->total_spending,
                ];
            });
        
        // Cohort analysis (users by signup month)
        // Use database-agnostic date formatting
        $cohorts = User::selectRaw('
                strftime("%Y-%m", created_at) as signup_month,
                COUNT(*) as signups_count
            ')
            ->where('created_at', '>=', $mauStart->copy()->subYear()) // Last year
            ->groupBy('signup_month')
            ->orderBy('signup_month', 'desc')
            ->get()
            ->map(function ($cohort) {
                return [
                    'month' => $cohort->signup_month,
                    'signups_count' => (int) $cohort->signups_count,
                ];
            });
        
        // Churn and reactivation (simplified: users inactive for 30+ days)
        $churnThreshold = $now->copy()->subDays(30);
        $reactivationThreshold = $now->copy()->subDays(60);
        
        $churnedUsers = User::whereDoesntHave('generationJobs', function ($query) use ($churnThreshold) {
                $query->where('created_at', '>=', $churnThreshold);
            })
            ->whereHas('generationJobs', function ($query) use ($churnThreshold) {
                $query->where('created_at', '<', $churnThreshold);
            })
            ->count();
        
        $reactivatedUsers = User::whereHas('generationJobs', function ($query) use ($churnThreshold, $reactivationThreshold) {
                $query->where('created_at', '>=', $churnThreshold)
                      ->where('created_at', '<', $reactivationThreshold);
            })
            ->whereHas('generationJobs', function ($query) use ($reactivationThreshold) {
                $query->where('created_at', '<', $reactivationThreshold);
            })
            ->count();
        
        // Build response
        $response = [
            'active_users' => [
                'dau' => $dau,
                'wau' => $wau,
                'mau' => $mau,
            ],
            'top_users_by_generation' => $topUsersByGeneration,
            'top_users_by_spending' => $topUsersBySpending,
            'cohort_analysis' => $cohorts,
            'churn_metrics' => [
                'churned_users' => $churnedUsers,
                'reactivated_users' => $reactivatedUsers,
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
     * List users with search and filtering
     * 
     * Authorization: Admin only
     */
    public function list(UsersListRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $query = User::query();
        
        // Search by name, phone, or email
        if (isset($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        // Filter by role
        if (isset($validated['role'])) {
            $query->where('role', $validated['role']);
        }
        
        // Filter by date range
        if (isset($validated['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($validated['date_from'])->startOfDay());
        }
        if (isset($validated['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($validated['date_to'])->endOfDay());
        }
        
        // Paginate
        $perPage = $validated['per_page'] ?? 15;
        $users = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => collect($users->items())->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'role' => $user->role,
                    'tokens_balance' => (float) $user->tokens_balance,
                    'is_verified' => $user->is_verified,
                    'created_at' => $user->created_at->toISOString(),
                ];
            }),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }
}

