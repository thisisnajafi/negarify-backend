<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GenerationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class AdminSystemHealthController extends Controller
{
    /**
     * Get system health metrics
     * 
     * Authorization: Admin only
     */
    public function index(): JsonResponse
    {
        // Cache key
        $cacheKey = 'admin:system-health';
        
        // Try cache first (1 minute TTL)
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json([
                'success' => true,
                'data' => $cached,
            ]);
        }
        
        // Queue length (pending jobs)
        $queueLength = GenerationJob::where('generation_jobs.status', 'pending')
            ->count();
        
        // Queue length by type
        $queueLengthByType = GenerationJob::where('generation_jobs.status', 'pending')
            ->selectRaw('generation_jobs.job_type, COUNT(*) as count')
            ->groupBy('generation_jobs.job_type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->job_type => (int) $item->count];
            });
        
        // Try to get Redis queue length (if using Redis queue)
        $redisQueueLength = null;
        try {
            if (config('queue.default') === 'redis') {
                $redisQueueLength = Redis::llen('queues:default');
            }
        } catch (\Exception $e) {
            // Redis not available or not configured
            $redisQueueLength = null;
        }
        
        // Failed jobs count (last 24 hours)
        $failedJobsCount = 0;
        try {
            $failedJobsCount = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count();
        } catch (\Exception $e) {
            // failed_jobs table might not exist
            $failedJobsCount = 0;
        }
        
        // Failed jobs today
        $failedJobsToday = 0;
        try {
            $failedJobsToday = DB::table('failed_jobs')
                ->whereDate('failed_at', today())
                ->count();
        } catch (\Exception $e) {
            $failedJobsToday = 0;
        }
        
        // Error rate (last 24 hours)
        $last24Hours = now()->subDay();
        $totalJobs = GenerationJob::where('generation_jobs.created_at', '>=', $last24Hours)->count();
        $failedJobs = GenerationJob::where('generation_jobs.status', 'failed')
            ->where('generation_jobs.created_at', '>=', $last24Hours)
            ->count();
        $errorRate = $totalJobs > 0 
            ? round(($failedJobs / $totalJobs) * 100, 2) 
            : 0;
        
        // Average API latency (last 24 hours, completed jobs only)
        // Use database-agnostic latency calculation
        $latencyCalculation = DB::getDriverName() === 'sqlite'
            ? "(julianday(generation_jobs.completed_at) - julianday(generation_jobs.started_at)) * 86400000"
            : "TIMESTAMPDIFF(MILLISECOND, generation_jobs.started_at, generation_jobs.completed_at)";
        
        $avgLatency = GenerationJob::where('generation_jobs.status', 'completed')
            ->where('generation_jobs.created_at', '>=', $last24Hours)
            ->whereNotNull('generation_jobs.started_at')
            ->whereNotNull('generation_jobs.completed_at')
            ->selectRaw("
                AVG({$latencyCalculation}) as avg_latency_ms
            ")
            ->value('avg_latency_ms');
        
        $avgLatencyMs = $avgLatency ? (int) round($avgLatency) : null;
        
        // P95 and P99 latency (approximate using percentile calculation)
        $latencies = GenerationJob::where('generation_jobs.status', 'completed')
            ->where('generation_jobs.created_at', '>=', $last24Hours)
            ->whereNotNull('generation_jobs.started_at')
            ->whereNotNull('generation_jobs.completed_at')
            ->selectRaw("
                {$latencyCalculation} as latency_ms
            ")
            ->orderBy('latency_ms')
            ->pluck('latency_ms')
            ->toArray();
        
        $p95Latency = null;
        $p99Latency = null;
        if (count($latencies) > 0) {
            $p95Index = (int) floor(count($latencies) * 0.95);
            $p99Index = (int) floor(count($latencies) * 0.99);
            $p95Latency = $latencies[$p95Index] ?? null;
            $p99Latency = $latencies[$p99Index] ?? null;
        }
        
        // Storage usage (estimate from generation jobs with results)
        $filesCount = GenerationJob::whereNotNull('generation_jobs.result_url')
            ->count();
        
        // Estimate storage (rough: assume average file size)
        // This is an approximation - actual storage would require S3 API call
        $estimatedStorageMb = $filesCount * 2; // Assume 2MB per file average
        
        // Worker status (cannot be reliably detected without external monitoring)
        // Return null/unknown as it requires system-level access
        $workerStatus = 'unknown';
        $workerCount = null;
        
        // Build response
        $response = [
            'queue' => [
                'length' => $queueLength,
                'length_by_type' => $queueLengthByType,
                'redis_queue_length' => $redisQueueLength,
            ],
            'workers' => [
                'status' => $workerStatus,
                'count' => $workerCount,
            ],
            'failed_jobs' => [
                'count_last_24h' => $failedJobsCount,
                'count_today' => $failedJobsToday,
            ],
            'error_rate' => [
                'percentage' => $errorRate,
                'failed_jobs' => $failedJobs,
                'total_jobs' => $totalJobs,
                'period' => 'last_24h',
            ],
            'api_latency' => [
                'avg_ms' => $avgLatencyMs,
                'p95_ms' => $p95Latency,
                'p99_ms' => $p99Latency,
                'period' => 'last_24h',
            ],
            'storage' => [
                'files_count' => $filesCount,
                'estimated_usage_mb' => $estimatedStorageMb,
                'note' => 'Estimated - actual storage requires S3 API access',
            ],
            'timestamp' => now()->toISOString(),
        ];
        
        // Cache for 1 minute (system health changes frequently)
        Cache::put($cacheKey, $response, 60);
        
        return response()->json([
            'success' => true,
            'data' => $response,
        ]);
    }
}

