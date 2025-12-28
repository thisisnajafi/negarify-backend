<?php

namespace App\Services\Segmind;

use App\Models\Model as AiModel;
use App\Models\Provider;

class SegmindImageService extends BaseSegmindService
{
    protected AiModel $model;

    public function __construct(Provider $provider, AiModel $model)
    {
        parent::__construct($provider);
        $this->model = $model;
    }

    /**
     * Generate image using Segmind API
     * 
     * @param array $params {
     *   @var string $prompt Required. Image generation prompt
     *   @var string|null $negative_prompt Optional. Negative prompt
     *   @var string|null $size Optional. Image size (e.g., "512x512", "1024x1024")
     *   @var string|null $style Optional. Style preset
     *   @var int|null $seed Optional. Random seed for reproducibility
     *   @var int|null $steps Optional. Number of inference steps
     *   @var float|null $guidance_scale Optional. Guidance scale
     * }
     * @return array {
     *   @var string $image_url URL to generated image
     *   @var string|null $job_id Job ID if async (null if synchronous)
     * }
     * @throws \Exception On API failure
     */
    public function generateImage(array $params): array
    {
        // Validate required parameters
        if (empty($params['prompt'])) {
            throw new \InvalidArgumentException('Prompt is required for image generation');
        }

        // Validate and map parameters
        $segmindParams = $this->mapParameters($params);

        // Construct API endpoint
        $endpoint = $this->model->api_endpoint ?? "/v1/{$this->model->model_name}";

        // Make API request
        $response = $this->makeRequest($endpoint, $segmindParams, 'POST', 60);

        // Parse response
        // Segmind typically returns: { "image": "base64..." } or { "image_url": "https://..." }
        // Or async: { "job_id": "...", "status": "pending" }
        
        if (isset($response['image_url'])) {
            return [
                'image_url' => $response['image_url'],
                'job_id' => null, // Synchronous response
            ];
        }

        if (isset($response['image'])) {
            // Base64 image - would need to decode and store
            // For now, assume URL is returned
            throw new \RuntimeException('Base64 image response not yet supported');
        }

        if (isset($response['job_id'])) {
            // Async response - return job ID for polling
            return [
                'image_url' => null,
                'job_id' => $response['job_id'],
            ];
        }

        throw new \RuntimeException('Unexpected response format from Segmind API');
    }

    /**
     * Get job status (for async generation)
     * 
     * @param string $jobId Segmind job ID
     * @return array {
     *   @var string $status Job status (pending, processing, completed, failed)
     *   @var string|null $image_url URL to generated image (if completed)
     *   @var string|null $error Error message (if failed)
     * }
     */
    public function getJobStatus(string $jobId): array
    {
        $endpoint = "/v1/jobs/{$jobId}";
        
        $response = $this->makeRequest($endpoint, [], 'GET', 10);

        return [
            'status' => $response['status'] ?? 'unknown',
            'image_url' => $response['image_url'] ?? null,
            'error' => $response['error'] ?? null,
        ];
    }

    /**
     * Map internal parameters to Segmind API format
     */
    protected function mapParameters(array $params): array
    {
        $segmindParams = [
            'prompt' => $params['prompt'],
        ];

        // Negative prompt
        if (!empty($params['negative_prompt'])) {
            $segmindParams['negative_prompt'] = $params['negative_prompt'];
        }

        // Size (validate against model capabilities)
        if (!empty($params['size'])) {
            if ($this->model->supports_size) {
                // Validate size against model.max_resolution
                $this->validateSize($params['size']);
                $segmindParams['width'] = $this->extractWidth($params['size']);
                $segmindParams['height'] = $this->extractHeight($params['size']);
            }
        } else {
            // Use default size from model or API default
            $defaultSize = $this->getDefaultSize();
            $segmindParams['width'] = $this->extractWidth($defaultSize);
            $segmindParams['height'] = $this->extractHeight($defaultSize);
        }

        // Style (validate against model capabilities)
        if (!empty($params['style']) && $this->model->supports_style) {
            $this->validateStyle($params['style']);
            $segmindParams['style'] = $params['style'];
        }

        // Seed
        if (isset($params['seed'])) {
            $segmindParams['seed'] = (int) $params['seed'];
        }

        // Steps
        if (isset($params['steps'])) {
            $segmindParams['num_inference_steps'] = (int) $params['steps'];
        }

        // Guidance scale
        if (isset($params['guidance_scale'])) {
            $segmindParams['guidance_scale'] = (float) $params['guidance_scale'];
        }

        return $segmindParams;
    }

    /**
     * Validate image size against model capabilities
     */
    protected function validateSize(string $size): void
    {
        $maxResolution = $this->model->max_resolution;
        if (!$maxResolution) {
            return; // No limit specified
        }

        [$maxWidth, $maxHeight] = explode('x', $maxResolution);
        [$width, $height] = explode('x', $size);

        if ($width > $maxWidth || $height > $maxHeight) {
            throw new \InvalidArgumentException(
                "Image size {$size} exceeds model maximum: {$maxResolution}"
            );
        }
    }

    /**
     * Validate style against model capabilities
     */
    protected function validateStyle(string $style): void
    {
        // If model has supported_styles, validate against it
        // For now, accept any style (validation can be added later)
    }

    /**
     * Extract width from size string (e.g., "512x512" -> 512)
     */
    protected function extractWidth(string $size): int
    {
        [$width] = explode('x', $size);
        return (int) $width;
    }

    /**
     * Extract height from size string (e.g., "512x512" -> 512)
     */
    protected function extractHeight(string $size): int
    {
        [, $height] = explode('x', $size);
        return (int) $height;
    }

    /**
     * Get default size for model
     */
    protected function getDefaultSize(): string
    {
        // Default to 512x512 if not specified
        return '512x512';
    }

    /**
     * Get model name
     */
    protected function getModelName(): string
    {
        return $this->model->model_name;
    }
}

