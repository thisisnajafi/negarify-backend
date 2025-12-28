<?php

namespace App\Console\Commands;

use App\Models\AnalyticsModelsUsage;
use App\Models\GenerationJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AggregateModelsUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:aggregate-models-usage 
                            {--period=daily : Period type (daily, weekly, monthly)}
                            {--date= : Specific date to aggregate (YYYY-MM-DD), defaults to yesterday}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregate generation job data into analytics_models_usage table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $periodType = $this->option('period');
        $date = $this->option('date') 
            ? \Carbon\Carbon::parse($this->option('date'))
            : now()->subDay();

        // Calculate period boundaries based on period type
        $periodStart = match($periodType) {
            'daily' => $date->copy()->startOfDay(),
            'weekly' => $date->copy()->startOfWeek(),
            'monthly' => $date->copy()->startOfMonth(),
            default => $date->copy()->startOfDay(),
        };

        $periodEnd = match($periodType) {
            'daily' => $date->copy()->endOfDay(),
            'weekly' => $date->copy()->endOfWeek(),
            'monthly' => $date->copy()->endOfMonth(),
            default => $date->copy()->endOfDay(),
        };

        Log::info('Starting models usage aggregation', [
            'period_type' => $periodType,
            'period_start' => $periodStart->toISOString(),
            'period_end' => $periodEnd->toISOString(),
        ]);

        // Aggregate by model_id and job_type
        $aggregatedData = GenerationJob::whereBetween('created_at', [$periodStart, $periodEnd])
            ->whereIn('status', ['completed', 'failed']) // Include both for accurate counts
            ->selectRaw('
                model_id,
                job_type,
                COUNT(*) as requests_count,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful_count,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = "completed" THEN tokens_consumed ELSE 0 END) as tokens_consumed,
                SUM(CASE WHEN status = "completed" THEN cost_usd ELSE 0 END) as cost_usd,
                AVG(CASE WHEN status = "completed" AND started_at IS NOT NULL AND completed_at IS NOT NULL
                    THEN TIMESTAMPDIFF(MILLISECOND, started_at, completed_at)
                    ELSE NULL END) as avg_latency_ms
            ')
            ->groupBy('model_id', 'job_type')
            ->get();

        $recordsCreated = 0;
        $recordsUpdated = 0;

        foreach ($aggregatedData as $data) {
            // Calculate revenue (approximation - would need token_transactions join for accuracy)
            $revenueUsd = 0; // Complex to calculate accurately without token_transactions join

            // UPSERT: Update if exists, insert if not
            $result = AnalyticsModelsUsage::updateOrCreate(
                [
                    'model_id' => $data->model_id,
                    'job_type' => $data->job_type,
                    'period_type' => $periodType,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                ],
                [
                    'requests_count' => (int) $data->requests_count,
                    'successful_count' => (int) $data->successful_count,
                    'failed_count' => (int) $data->failed_count,
                    'tokens_consumed' => (int) $data->tokens_consumed,
                    'cost_usd' => (float) $data->cost_usd,
                    'revenue_usd' => $revenueUsd,
                    'avg_latency_ms' => $data->avg_latency_ms ? (int) round($data->avg_latency_ms) : null,
                ]
            );

            if ($result->wasRecentlyCreated) {
                $recordsCreated++;
            } else {
                $recordsUpdated++;
            }
        }

        $this->info("Aggregated models usage: {$recordsCreated} created, {$recordsUpdated} updated.");
        Log::info('Models usage aggregation completed', [
            'period_type' => $periodType,
            'period_start' => $periodStart->toISOString(),
            'period_end' => $periodEnd->toISOString(),
            'records_created' => $recordsCreated,
            'records_updated' => $recordsUpdated,
            'models_aggregated' => $aggregatedData->count(),
        ]);

        return Command::SUCCESS;
    }
}

