<?php

namespace App\Services\Segmind;

use App\Models\Model as AiModel;
use App\Models\Provider;

class SegmindVideoService extends BaseSegmindService
{
    protected AiModel $model;

    public function __construct(Provider $provider, AiModel $model)
    {
        parent::__construct($provider);
        $this->model = $model;
    }

    /**
     * Generate video using Segmind API
     */
    public function generateVideo(array $params): array
    {
        if (empty($params['prompt'])) {
            throw new \InvalidArgumentException('Prompt is required for video generation');
        }

        $segmindParams = $this->mapParameters($params);
        $endpoint = $this->model->api_endpoint ?? "/v1/{$this->model->model_name}";

        $response = $this->makeRequest($endpoint, $segmindParams, 'POST', 300); // 5 min timeout

        if (isset($response['video_url'])) {
            return [
                'video_url' => $response['video_url'],
                'job_id' => null,
            ];
        }

        if (isset($response['job_id'])) {
            return [
                'video_url' => null,
                'job_id' => $response['job_id'],
            ];
        }

        throw new \RuntimeException('Unexpected response format from Segmind API');
    }

    /**
     * Get job status (for async generation)
     */
    public function getJobStatus(string $jobId): array
    {
        $endpoint = "/v1/jobs/{$jobId}";
        $response = $this->makeRequest($endpoint, [], 'GET', 10);

        return [
            'status' => $response['status'] ?? 'unknown',
            'video_url' => $response['video_url'] ?? null,
            'error' => $response['error'] ?? null,
        ];
    }

    /**
     * Map parameters to Segmind API format
     */
    protected function mapParameters(array $params): array
    {
        $segmindParams = ['prompt' => $params['prompt']];

        if (!empty($params['negative_prompt'])) {
            $segmindParams['negative_prompt'] = $params['negative_prompt'];
        }

        if (!empty($params['duration'])) {
            $this->validateDuration($params['duration']);
            $segmindParams['duration'] = (int) $params['duration'];
        } else {
            $segmindParams['duration'] = 5; // Default 5 seconds
        }

        if (!empty($params['resolution'])) {
            $this->validateResolution($params['resolution']);
            [$width, $height] = explode('x', $params['resolution']);
            $segmindParams['width'] = (int) $width;
            $segmindParams['height'] = (int) $height;
        } else {
            $defaultRes = $this->getDefaultResolution();
            [$width, $height] = explode('x', $defaultRes);
            $segmindParams['width'] = (int) $width;
            $segmindParams['height'] = (int) $height;
        }

        if (isset($params['fps'])) {
            $segmindParams['fps'] = (int) $params['fps'];
        }

        if (isset($params['seed'])) {
            $segmindParams['seed'] = (int) $params['seed'];
        }

        return $segmindParams;
    }

    protected function validateDuration(int $duration): void
    {
        if ($duration < 1 || $duration > 60) {
            throw new \InvalidArgumentException('Duration must be between 1 and 60 seconds');
        }
    }

    protected function validateResolution(string $resolution): void
    {
        $maxResolution = $this->model->max_resolution;
        if (!$maxResolution) {
            return;
        }

        [$maxWidth, $maxHeight] = explode('x', $maxResolution);
        [$width, $height] = explode('x', $resolution);

        if ($width > $maxWidth || $height > $maxHeight) {
            throw new \InvalidArgumentException("Resolution {$resolution} exceeds model maximum: {$maxResolution}");
        }
    }

    protected function getDefaultResolution(): string
    {
        return '512x512';
    }

    protected function getModelName(): string
    {
        return $this->model->model_name;
    }
}

