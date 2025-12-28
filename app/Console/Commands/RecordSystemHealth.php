<?php

namespace App\Console\Commands;

use App\Models\GenerationJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RecordSystemHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:record-health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Record system health metrics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $metrics = $this->collectMetrics();

            // Try to store in system_health table if it exists
            if ($this->tableExists('system_health')) {
                $this->storeInDatabase($metrics);
            } else {
                // Fallback to logging
                Log::info('System health metrics recorded', $metrics);
            }

            $this->info('System health metrics recorded successfully.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Failed to record system health', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error('Failed to record system health: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Collect system health metrics
     */
    protected function collectMetrics(): array
    {
        $last24Hours = now()->subDay();

        // Queue length (pending jobs)
        $queueLength = GenerationJob::where('status', 'pending')->count();

        // Failed jobs count (last 24 hours)
        $failedJobsCount = 0;
        try {
            $failedJobsCount = DB::table('failed_jobs')
                ->where('failed_at', '>=', $last24Hours)
                ->count();
        } catch (\Exception $e) {
            // Table might not exist
        }

        // Error rate (last 24 hours)
        $totalJobs = GenerationJob::where('created_at', '>=', $last24Hours)->count();
        $failedJobs = GenerationJob::where('status', 'failed')
            ->where('created_at', '>=', $last24Hours)
            ->count();
        $errorRate = $totalJobs > 0 ? round(($failedJobs / $totalJobs) * 100, 2) : 0;

        // Average API latency (last 24 hours, completed jobs only)
        $avgLatency = GenerationJob::where('status', 'completed')
            ->where('created_at', '>=', $last24Hours)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->selectRaw('
                AVG(TIMESTAMPDIFF(MILLISECOND, started_at, completed_at)) as avg_latency_ms
            ')
            ->value('avg_latency_ms');

        // Storage usage (estimate)
        $filesCount = GenerationJob::whereNotNull('result_url')->count();
        $estimatedStorageMb = $filesCount * 2; // Assume 2MB per file average

        // Redis queue length (if using Redis)
        $redisQueueLength = null;
        try {
            if (config('queue.default') === 'redis') {
                $redisQueueLength = Redis::llen('queues:default');
            }
        } catch (\Exception $e) {
            // Redis not available
        }

        return [
            'queue_length' => $queueLength,
            'redis_queue_length' => $redisQueueLength,
            'failed_jobs_count_24h' => $failedJobsCount,
            'error_rate_percent' => $errorRate,
            'avg_latency_ms' => $avgLatency ? (int) round($avgLatency) : null,
            'files_count' => $filesCount,
            'estimated_storage_mb' => $estimatedStorageMb,
            'recorded_at' => now()->toISOString(),
        ];
    }

    /**
     * Store metrics in database
     */
    protected function storeInDatabase(array $metrics): void
    {
        foreach ($metrics as $metricName => $value) {
            if ($metricName === 'recorded_at') {
                continue; // Skip timestamp, use created_at
            }

            DB::table('system_health')->insert([
                'metric_name' => $metricName,
                'value' => is_numeric($value) ? $value : json_encode($value),
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Check if table exists
     */
    protected function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Exception $e) {
            return false;
        }
    }
}

