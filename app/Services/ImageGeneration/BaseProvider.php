<?php

namespace App\Services\ImageGeneration;

use App\Models\ImageJob;
use App\Models\Model;
use App\Models\Provider;
use Illuminate\Support\Facades\Log;

abstract class BaseProvider
{
    protected Provider $provider;
    protected Model $model;

    public function __construct(Provider $provider, Model $model)
    {
        $this->provider = $provider;
        $this->model = $model;
    }

    /**
     * Generate image
     */
    abstract public function generate(ImageJob $job): array;

    /**
     * Get job status
     */
    abstract public function getStatus(string $jobId): array;

    /**
     * Get job result
     */
    abstract public function getResult(string $jobId): array;

    /**
     * Cancel job
     */
    abstract public function cancel(string $jobId): bool;

    /**
     * Get provider name
     */
    public function getName(): string
    {
        return $this->provider->name;
    }

    /**
     * Get model name
     */
    public function getModelName(): string
    {
        return $this->model->model_name;
    }

    /**
     * Get API key
     */
    protected function getApiKey(): string
    {
        return $this->provider->getApiKey();
    }

    /**
     * Get base URL
     */
    protected function getBaseUrl(): string
    {
        return $this->provider->api_base_url;
    }

    /**
     * Log API call
     */
    protected function logApiCall(string $endpoint, array $data = [], array $response = []): void
    {
        Log::info('Image Generation API Call', [
            'provider' => $this->getName(),
            'model' => $this->getModelName(),
            'endpoint' => $endpoint,
            'request_data' => $data,
            'response' => $response,
        ]);
    }

    /**
     * Log error
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::error('Image Generation Error', array_merge([
            'provider' => $this->getName(),
            'model' => $this->getModelName(),
            'message' => $message,
        ], $context));
    }

    /**
     * Validate parameters
     */
    protected function validateParameters(array $params): array
    {
        $errors = [];

        // Validate size
        if (isset($params['size']) && !$this->model->supportsSize($params['size'])) {
            $errors[] = "Size '{$params['size']}' is not supported by this model";
        }

        // Validate style
        if (isset($params['style']) && !$this->model->supportsStyle($params['style'])) {
            $errors[] = "Style '{$params['style']}' is not supported by this model";
        }

        return $errors;
    }

    /**
     * Get default parameters
     */
    protected function getDefaultParameters(): array
    {
        return [
            'size' => '1024x1024',
            'quality' => 'standard',
            'style' => 'vivid',
            'n' => 1,
        ];
    }

    /**
     * Merge parameters with defaults
     */
    protected function mergeParameters(array $params): array
    {
        return array_merge($this->getDefaultParameters(), $params);
    }
}
