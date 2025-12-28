<?php

namespace App\Console\Commands;

use App\Models\GenerationJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupOldGenerationJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:cleanup-old 
                            {--days=90 : Delete jobs older than this many days}
                            {--delete-files : Also delete associated S3 files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old generation jobs (completed, failed, or cancelled)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $deleteFiles = $this->option('delete-files');
        $cutoffDate = now()->subDays($days);

        Log::info('Starting old generation jobs cleanup', [
            'days' => $days,
            'cutoff_date' => $cutoffDate->toISOString(),
            'delete_files' => $deleteFiles,
        ]);

        // Only delete jobs in terminal states (never pending or processing)
        $query = GenerationJob::whereIn('status', ['completed', 'failed', 'cancelled'])
            ->where('created_at', '<', $cutoffDate);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No old jobs to clean up.');
            Log::info('Old generation jobs cleanup completed - no jobs found');
            return Command::SUCCESS;
        }

        // Get jobs before deletion (for file cleanup if needed)
        $jobsToDelete = $deleteFiles 
            ? $query->get(['id', 'result_url', 'result_thumbnail_url'])
            : null;

        // Delete jobs
        $deletedCount = $query->delete();

        // Optionally delete S3 files
        $filesDeleted = 0;
        if ($deleteFiles && $jobsToDelete) {
            foreach ($jobsToDelete as $job) {
                try {
                    if ($job->result_url) {
                        $this->deleteS3File($job->result_url);
                        $filesDeleted++;
                    }
                    if ($job->result_thumbnail_url) {
                        $this->deleteS3File($job->result_thumbnail_url);
                        $filesDeleted++;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to delete S3 file during cleanup', [
                        'job_id' => $job->id,
                        'file_url' => $job->result_url ?? $job->result_thumbnail_url,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Cleaned up {$deletedCount} old generation jobs (older than {$days} days).");
        if ($deleteFiles) {
            $this->info("Deleted {$filesDeleted} associated S3 files.");
        }

        Log::info('Old generation jobs cleanup completed', [
            'deleted_jobs' => $deletedCount,
            'deleted_files' => $filesDeleted,
            'days' => $days,
            'cutoff_date' => $cutoffDate->toISOString(),
        ]);

        return Command::SUCCESS;
    }

    /**
     * Delete file from S3 storage
     */
    protected function deleteS3File(string $url): void
    {
        try {
            // Extract path from URL
            $path = parse_url($url, PHP_URL_PATH);
            // Remove leading slash if present
            $path = ltrim($path, '/');

            if (Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
            }
        } catch (\Exception $e) {
            // Log but don't throw - file deletion is non-critical
            Log::warning('Failed to delete S3 file', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

