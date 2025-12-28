<?php

namespace App\Jobs;

use App\Models\GenerationJob;
use App\Models\TokenTransaction;
use App\Services\Segmind\SegmindAudioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 240; // 4 minutes
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
            $service = new SegmindAudioService($job->provider, $job->model);
            $params = [
                'prompt' => $job->prompt,
                'duration' => $job->params_json['duration'] ?? null,
                'format' => $job->params_json['format'] ?? null,
                'sample_rate' => $job->params_json['sample_rate'] ?? null,
                'seed' => $job->params_json['seed'] ?? null,
            ];

            $result = $service->generateAudio($params);

            if (isset($result['job_id'])) {
                $this->pollForCompletion($job, $service, $result['job_id']);
                return;
            }

            if (isset($result['audio_url'])) {
                $this->processAudioResult($job, $result['audio_url']);
                return;
            }

            throw new \RuntimeException('Unexpected response format');
        } catch (\Exception $e) {
            $this->handleFailure($job, $e);
        }
    }

    protected function pollForCompletion(GenerationJob $job, SegmindAudioService $service, string $segmindJobId): void
    {
        $job->segmind_job_id = $segmindJobId;
        $job->save();

        for ($i = 0; $i < 48; $i++) { // Max 4 minutes (5s intervals)
            sleep(5);
            $status = $service->getJobStatus($segmindJobId);

            if ($status['status'] === 'completed' && $status['audio_url']) {
                $this->processAudioResult($job, $status['audio_url']);
                return;
            }

            if ($status['status'] === 'failed') {
                throw new \RuntimeException($status['error'] ?? 'Job failed');
            }
        }

        throw new \RuntimeException('Audio generation timed out');
    }

    protected function processAudioResult(GenerationJob $job, string $audioUrl): void
    {
        try {
            $audioContent = $this->downloadAudio($audioUrl);
            $resultUrl = $this->storeAudio($job, $audioContent, $job->params_json['format'] ?? 'mp3');

            $job->result_url = $resultUrl;
            $job->status = 'completed';
            $job->completed_at = now();
            $job->tokens_consumed = $job->model->default_tokens;
            $job->cost_usd = $job->model->base_cost_usd;
            $job->save();

            $this->consumeTokens($job);
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to process audio: {$e->getMessage()}", 0, $e);
        }
    }

    protected function downloadAudio(string $url): string
    {
        $response = \Illuminate\Support\Facades\Http::timeout(60)->get($url);
        if (!$response->successful()) {
            throw new \RuntimeException("Failed to download audio: HTTP {$response->status()}");
        }
        return $response->body();
    }

    protected function storeAudio(GenerationJob $job, string $audioContent, string $format): string
    {
        $path = "generations/audio/{$job->user_id}/{$job->id}.{$format}";
        Storage::disk('s3')->put($path, $audioContent, 'public');
        return Storage::disk('s3')->url($path);
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
                'description' => "Audio generation - {$job->model->model_name}",
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

