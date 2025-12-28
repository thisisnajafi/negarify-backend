<?php

namespace App\Jobs;

use App\Models\GenerationJob;
use App\Models\TokenTransaction;
use App\Services\NotificationService;
use App\Services\Segmind\SegmindImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120; // 2 minutes
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly int $generationJobId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Get job with lock to prevent concurrent processing
        $job = GenerationJob::lockForUpdate()->find($this->generationJobId);

        if (!$job) {
            Log::error('Generation job not found', [
                'job_id' => $this->generationJobId,
            ]);
            return;
        }

        // Idempotency check: Skip if already processed
        if ($job->isCompleted() || $job->isFailed() || $job->isCancelled()) {
            Log::info('Generation job already processed (idempotent)', [
                'job_id' => $job->id,
                'status' => $job->status,
            ]);
            return;
        }

        // Update status to processing
        $job->status = 'processing';
        $job->started_at = now();
        $job->save();

        try {
            // Get provider and model
            $provider = $job->provider;
            $model = $job->model;

            if (!$provider || !$model) {
                throw new \RuntimeException('Provider or model not found');
            }

            // Create service
            $service = new SegmindImageService($provider, $model);

            // Prepare parameters
            $params = [
                'prompt' => $job->prompt,
                'negative_prompt' => $job->negative_prompt,
                'size' => $job->params_json['size'] ?? null,
                'style' => $job->params_json['style'] ?? null,
                'seed' => $job->params_json['seed'] ?? null,
                'steps' => $job->params_json['steps'] ?? null,
                'guidance_scale' => $job->params_json['guidance_scale'] ?? null,
            ];

            // Generate image
            $result = $service->generateImage($params);

            // Handle async response (polling)
            if (isset($result['job_id'])) {
                $this->pollForCompletion($job, $service, $result['job_id']);
                return;
            }

            // Handle synchronous response
            if (isset($result['image_url'])) {
                $this->processImageResult($job, $result['image_url']);
                return;
            }

            throw new \RuntimeException('Unexpected response format from Segmind API');
        } catch (\Exception $e) {
            $this->handleFailure($job, $e);
        }
    }

    /**
     * Poll for async job completion
     */
    protected function pollForCompletion(GenerationJob $job, SegmindImageService $service, string $segmindJobId): void
    {
        $maxPolls = 60; // Max 5 minutes (5 second intervals)
        $pollInterval = 5; // 5 seconds

        $job->segmind_job_id = $segmindJobId;
        $job->save();

        for ($i = 0; $i < $maxPolls; $i++) {
            sleep($pollInterval);

            $status = $service->getJobStatus($segmindJobId);

            if ($status['status'] === 'completed' && $status['image_url']) {
                $this->processImageResult($job, $status['image_url']);
                return;
            }

            if ($status['status'] === 'failed') {
                throw new \RuntimeException($status['error'] ?? 'Job failed');
            }

            // Continue polling for pending/processing
        }

        // Timeout
        throw new \RuntimeException('Image generation timed out');
    }

    /**
     * Process image result (download, store, generate thumbnail)
     */
    protected function processImageResult(GenerationJob $job, string $imageUrl): void
    {
        try {
            // Download image
            $imageContent = $this->downloadImage($imageUrl);

            // Store in S3
            $resultUrl = $this->storeImage($job, $imageContent);

            // Generate thumbnail
            $thumbnailUrl = $this->generateThumbnail($job, $imageContent);

            // Update job with results
            $job->result_url = $resultUrl;
            $job->result_thumbnail_url = $thumbnailUrl;
            $job->status = 'completed';
            $job->completed_at = now();
            $job->tokens_consumed = $job->model->default_tokens;
            $job->cost_usd = $job->model->base_cost_usd;
            $job->save();

            // Consume tokens (atomic: balance update + transaction log)
            $this->consumeTokens($job);

            Log::info('Image generation completed', [
                'job_id' => $job->id,
                'user_id' => $job->user_id,
                'result_url' => $resultUrl,
            ]);

            // Notify user of completion
            app(NotificationService::class)->notifyGenerationCompleted(
                $job->user_id,
                $job->id,
                'image'
            );
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to process image result: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Download image from URL
     */
    protected function downloadImage(string $url): string
    {
        $response = \Illuminate\Support\Facades\Http::timeout(30)->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException("Failed to download image: HTTP {$response->status()}");
        }

        return $response->body();
    }

    /**
     * Store image in S3
     */
    protected function storeImage(GenerationJob $job, string $imageContent): string
    {
        $extension = 'png'; // Default, could detect from content-type
        $path = "generations/image/{$job->user_id}/{$job->id}.{$extension}";

        Storage::disk('s3')->put($path, $imageContent, 'public');

        return Storage::disk('s3')->url($path);
    }

    /**
     * Generate thumbnail from image
     */
    protected function generateThumbnail(GenerationJob $job, string $imageContent): ?string
    {
        try {
            // Create image resource from content
            $image = imagecreatefromstring($imageContent);
            if (!$image) {
                Log::warning('Failed to create image resource for thumbnail', [
                    'job_id' => $job->id,
                ]);
                return null;
            }

            // Get original dimensions
            $width = imagesx($image);
            $height = imagesy($image);

            // Calculate thumbnail dimensions (max 512px)
            $maxSize = 512;
            $ratio = min($maxSize / $width, $maxSize / $height);
            $thumbWidth = (int) ($width * $ratio);
            $thumbHeight = (int) ($height * $ratio);

            // Create thumbnail
            $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
            imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

            // Save thumbnail to temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'thumb_');
            imagepng($thumbnail, $tempFile);
            imagedestroy($image);
            imagedestroy($thumbnail);

            // Store thumbnail in S3
            $thumbPath = "generations/image/{$job->user_id}/{$job->id}_thumb.png";
            Storage::disk('s3')->put($thumbPath, file_get_contents($tempFile), 'public');
            unlink($tempFile);

            return Storage::disk('s3')->url($thumbPath);
        } catch (\Exception $e) {
            Log::warning('Thumbnail generation failed (non-critical)', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Consume tokens (atomic operation)
     */
    protected function consumeTokens(GenerationJob $job): void
    {
        DB::transaction(function () use ($job) {
            $user = $job->user;
            $tokensToConsume = $job->tokens_consumed;

            // Create transaction log
            TokenTransaction::create([
                'user_id' => $user->id,
                'generation_job_id' => $job->id,
                'amount_tokens' => -$tokensToConsume, // Negative for consumption
                'amount_usd' => $job->cost_usd,
                'type' => 'consume',
                'description' => "Image generation - {$job->model->model_name}",
            ]);

            // Balance already reserved, so no need to deduct again
            // The reservation was done when job was created
            // We just finalize it with the transaction log
        });
    }

    /**
     * Handle job failure
     */
    protected function handleFailure(GenerationJob $job, \Exception $e): void
    {
        DB::transaction(function () use ($job, $e) {
            // Update job status
            $job->status = 'failed';
            $job->error_message = $this->sanitizeErrorMessage($e->getMessage());
            $job->completed_at = now();
            $job->save();

            // Refund reserved tokens
            $user = $job->user;
            $tokensToRefund = $job->model->default_tokens;
            $user->tokens_balance += $tokensToRefund;
            $user->save();

            Log::error('Image generation failed', [
                'job_id' => $job->id,
                'user_id' => $job->user_id,
                'error' => $e->getMessage(),
                'tokens_refunded' => $tokensToRefund,
            ]);
        });
    }

    /**
     * Sanitize error message (remove sensitive data)
     */
    protected function sanitizeErrorMessage(string $message): string
    {
        // Remove API keys, tokens, etc.
        $message = preg_replace('/Bearer\s+[\w-]+/i', '[REDACTED]', $message);
        $message = preg_replace('/api[_-]?key[\s:=]+[\w-]+/i', '[REDACTED]', $message);
        return $message;
    }

    /**
     * Handle job failure (Laravel queue failure handler)
     */
    public function failed(\Throwable $exception): void
    {
        $job = GenerationJob::find($this->generationJobId);
        if ($job) {
            $this->handleFailure($job, $exception);
        }
    }
}

