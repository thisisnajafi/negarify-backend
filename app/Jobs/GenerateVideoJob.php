<?php

namespace App\Jobs;

use App\Models\GenerationJob;
use App\Models\TokenTransaction;
use App\Services\NotificationService;
use App\Services\Segmind\SegmindVideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes
    public int $tries = 3;

    public function __construct(private readonly int $generationJobId) {}

    public function handle(): void
    {
        $job = GenerationJob::lockForUpdate()->find($this->generationJobId);

        if (!$job || $job->isCompleted() || $job->isFailed() || $job->isCancelled()) {
            return;
        }

        $job->status = 'processing';
        $job->started_at = now();
        $job->save();

        try {
            $service = new SegmindVideoService($job->provider, $job->model);

            $params = [
                'prompt' => $job->prompt,
                'negative_prompt' => $job->negative_prompt,
                'duration' => $job->params_json['duration'] ?? null,
                'resolution' => $job->params_json['resolution'] ?? null,
                'fps' => $job->params_json['fps'] ?? null,
                'seed' => $job->params_json['seed'] ?? null,
            ];

            $result = $service->generateVideo($params);

            if (isset($result['job_id'])) {
                $this->pollForCompletion($job, $service, $result['job_id']);
                return;
            }

            if (isset($result['video_url'])) {
                $this->processVideoResult($job, $result['video_url']);
                return;
            }

            throw new \RuntimeException('Unexpected response format');
        } catch (\Exception $e) {
            $this->handleFailure($job, $e);
        }
    }

    protected function pollForCompletion(GenerationJob $job, SegmindVideoService $service, string $segmindJobId): void
    {
        $job->segmind_job_id = $segmindJobId;
        $job->save();

        for ($i = 0; $i < 120; $i++) { // Max 10 minutes (5s intervals)
            sleep(5);
            $status = $service->getJobStatus($segmindJobId);

            if ($status['status'] === 'completed' && $status['video_url']) {
                $this->processVideoResult($job, $status['video_url']);
                return;
            }

            if ($status['status'] === 'failed') {
                throw new \RuntimeException($status['error'] ?? 'Job failed');
            }
        }

        throw new \RuntimeException('Video generation timed out');
    }

    protected function processVideoResult(GenerationJob $job, string $videoUrl): void
    {
        try {
            $videoContent = $this->downloadVideo($videoUrl);
            $resultUrl = $this->storeVideo($job, $videoContent);
            $thumbnailUrl = $this->generateThumbnail($job, $videoContent);

            $job->result_url = $resultUrl;
            $job->result_thumbnail_url = $thumbnailUrl;
            $job->status = 'completed';
            $job->completed_at = now();
            $job->tokens_consumed = $job->model->default_tokens;
            $job->cost_usd = $job->model->base_cost_usd;
            $job->save();

            $this->consumeTokens($job);
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to process video: {$e->getMessage()}", 0, $e);
        }
    }

    protected function downloadVideo(string $url): string
    {
        $response = \Illuminate\Support\Facades\Http::timeout(120)->get($url);
        if (!$response->successful()) {
            throw new \RuntimeException("Failed to download video: HTTP {$response->status()}");
        }
        return $response->body();
    }

    protected function storeVideo(GenerationJob $job, string $videoContent): string
    {
        $path = "generations/video/{$job->user_id}/{$job->id}.mp4";
        Storage::disk('s3')->put($path, $videoContent, 'public');
        return Storage::disk('s3')->url($path);
    }

    protected function generateThumbnail(GenerationJob $job, string $videoContent): ?string
    {
        try {
            // For video thumbnails, we'd typically use ffmpeg
            // For now, return null (can be implemented later)
            Log::info('Video thumbnail generation not yet implemented', ['job_id' => $job->id]);
            return null;
        } catch (\Exception $e) {
            Log::warning('Video thumbnail generation failed', ['job_id' => $job->id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    protected function consumeTokens(GenerationJob $job): void
    {
        DB::transaction(function () use ($job) {
            TokenTransaction::create([
                'user_id' => $job->user_id,
                'generation_job_id' => $job->id,
                'amount_tokens' => -$job->tokens_consumed,
                'amount_usd' => $job->cost_usd,
                'type' => 'consume',
                'description' => "Video generation - {$job->model->model_name}",
            ]);
        });
    }

    protected function handleFailure(GenerationJob $job, \Exception $e): void
    {
        DB::transaction(function () use ($job, $e) {
            $job->status = 'failed';
            $job->error_message = preg_replace('/Bearer\s+[\w-]+/i', '[REDACTED]', $e->getMessage());
            $job->completed_at = now();
            $job->save();

            $user = $job->user;
            $user->tokens_balance += $job->model->default_tokens;
            $user->save();
        });
    }

    public function failed(\Throwable $exception): void
    {
        $job = GenerationJob::find($this->generationJobId);
        if ($job) {
            $this->handleFailure($job, $exception);
        }
    }
}

