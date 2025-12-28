<?php

namespace App\Services\Segmind;

use App\Models\Model as AiModel;
use App\Models\Provider;

class SegmindAudioService extends BaseSegmindService
{
    protected AiModel $model;

    public function __construct(Provider $provider, AiModel $model)
    {
        parent::__construct($provider);
        $this->model = $model;
    }

    public function generateAudio(array $params): array
    {
        if (empty($params['prompt'])) {
            throw new \InvalidArgumentException('Prompt is required for audio generation');
        }

        $segmindParams = $this->mapParameters($params);
        $endpoint = $this->model->api_endpoint ?? "/v1/{$this->model->model_name}";

        $response = $this->makeRequest($endpoint, $segmindParams, 'POST', 120); // 2 min timeout

        if (isset($response['audio_url'])) {
            return ['audio_url' => $response['audio_url'], 'job_id' => null];
        }

        if (isset($response['job_id'])) {
            return ['audio_url' => null, 'job_id' => $response['job_id']];
        }

        throw new \RuntimeException('Unexpected response format');
    }

    public function getJobStatus(string $jobId): array
    {
        $endpoint = "/v1/jobs/{$jobId}";
        $response = $this->makeRequest($endpoint, [], 'GET', 10);

        return [
            'status' => $response['status'] ?? 'unknown',
            'audio_url' => $response['audio_url'] ?? null,
            'error' => $response['error'] ?? null,
        ];
    }

    protected function mapParameters(array $params): array
    {
        $segmindParams = ['prompt' => $params['prompt']];

        if (!empty($params['duration'])) {
            $this->validateDuration($params['duration']);
            $segmindParams['duration'] = (int) $params['duration'];
        } else {
            $segmindParams['duration'] = 30; // Default 30 seconds
        }

        if (!empty($params['format'])) {
            $this->validateFormat($params['format']);
            $segmindParams['format'] = $params['format'];
        } else {
            $segmindParams['format'] = 'mp3';
        }

        if (isset($params['sample_rate'])) {
            $segmindParams['sample_rate'] = (int) $params['sample_rate'];
        }

        if (isset($params['seed'])) {
            $segmindParams['seed'] = (int) $params['seed'];
        }

        return $segmindParams;
    }

    protected function validateDuration(int $duration): void
    {
        if ($duration < 1 || $duration > 300) {
            throw new \InvalidArgumentException('Duration must be between 1 and 300 seconds');
        }
    }

    protected function validateFormat(string $format): void
    {
        $allowed = ['mp3', 'wav', 'flac'];
        if (!in_array(strtolower($format), $allowed)) {
            throw new \InvalidArgumentException("Format must be one of: " . implode(', ', $allowed));
        }
    }

    protected function getModelName(): string
    {
        return $this->model->model_name;
    }
}

