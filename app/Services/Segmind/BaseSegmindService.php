<?php

namespace App\Services\Segmind;

use App\Models\Provider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseSegmindService
{
    protected Provider $provider;
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct(Provider $provider)
    {
        $this->provider = $provider;
        $this->apiKey = $provider->getApiKey();
        $this->baseUrl = $provider->api_base_url;

        if (!$this->apiKey) {
            throw new \RuntimeException("API key not available for provider: {$provider->name}");
        }

        if (!$this->baseUrl) {
            throw new \RuntimeException("Base URL not configured for provider: {$provider->name}");
        }
    }

    /**
     * Make HTTP request to Segmind API with retry logic
     * 
     * @param string $endpoint API endpoint (relative to base URL)
     * @param array $data Request payload
     * @param string $method HTTP method (GET, POST, etc.)
     * @param int $timeout Timeout in seconds
     * @return array Response data
     * @throws \Exception On failure after all retries
     */
    protected function makeRequest(
        string $endpoint,
        array $data = [],
        string $method = 'POST',
        int $timeout = 30
    ): array {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        $maxRetries = 3;
        $retryDelays = [1, 2, 4]; // Exponential backoff in seconds

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                // Log request (without API key)
                $this->logRequest($endpoint, $method, $data, $attempt + 1);

                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'Authorization' => "Bearer {$this->apiKey}",
                        'Content-Type' => 'application/json',
                    ])
                    ->{strtolower($method)}($url, $data);

                // Log response (without sensitive data)
                $this->logResponse($endpoint, $response->status(), $response->json(), $attempt + 1);

                if ($response->successful()) {
                    return $response->json() ?? [];
                }

                // Handle rate limiting (429)
                if ($response->status() === 429) {
                    $retryAfter = $response->header('Retry-After', $retryDelays[$attempt] ?? 4);
                    $delay = (int) $retryAfter;

                    Log::warning('Segmind API rate limited', [
                        'provider' => $this->provider->name,
                        'endpoint' => $endpoint,
                        'attempt' => $attempt + 1,
                        'retry_after' => $delay,
                    ]);

                    if ($attempt < $maxRetries - 1) {
                        sleep($delay);
                        continue;
                    }
                }

                // Handle server errors (5xx) - retry
                if ($response->status() >= 500 && $response->status() < 600) {
                    Log::warning('Segmind API server error', [
                        'provider' => $this->provider->name,
                        'endpoint' => $endpoint,
                        'status' => $response->status(),
                        'attempt' => $attempt + 1,
                    ]);

                    if ($attempt < $maxRetries - 1) {
                        sleep($retryDelays[$attempt]);
                        continue;
                    }
                }

                // Handle client errors (4xx) - don't retry
                if ($response->status() >= 400 && $response->status() < 500) {
                    $errorMessage = $this->extractErrorMessage($response->json());
                    
                    Log::error('Segmind API client error', [
                        'provider' => $this->provider->name,
                        'endpoint' => $endpoint,
                        'status' => $response->status(),
                        'error' => $errorMessage,
                    ]);

                    throw new \RuntimeException(
                        "Segmind API error: {$errorMessage}",
                        $response->status()
                    );
                }

                // Other errors - retry
                if ($attempt < $maxRetries - 1) {
                    sleep($retryDelays[$attempt]);
                    continue;
                }

                // All retries exhausted
                throw new \RuntimeException(
                    "Segmind API request failed after {$maxRetries} attempts. Status: {$response->status()}",
                    $response->status()
                );
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Network errors - retry
                Log::warning('Segmind API network error', [
                    'provider' => $this->provider->name,
                    'endpoint' => $endpoint,
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $maxRetries - 1) {
                    sleep($retryDelays[$attempt]);
                    continue;
                }

                throw new \RuntimeException(
                    "Segmind API network error after {$maxRetries} attempts: {$e->getMessage()}",
                    0,
                    $e
                );
            } catch (\Exception $e) {
                // Other exceptions - don't retry
                Log::error('Segmind API exception', [
                    'provider' => $this->provider->name,
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        // Should never reach here, but just in case
        throw new \RuntimeException("Segmind API request failed after {$maxRetries} attempts");
    }

    /**
     * Log API request (without API key)
     */
    protected function logRequest(string $endpoint, string $method, array $data, int $attempt): void
    {
        Log::info('Segmind API request', [
            'provider' => $this->provider->name,
            'endpoint' => $endpoint,
            'method' => $method,
            'data' => $this->sanitizeLogData($data),
            'attempt' => $attempt,
        ]);
    }

    /**
     * Log API response (without sensitive data)
     */
    protected function logResponse(string $endpoint, int $status, ?array $response, int $attempt): void
    {
        Log::info('Segmind API response', [
            'provider' => $this->provider->name,
            'endpoint' => $endpoint,
            'status' => $status,
            'response' => $this->sanitizeLogData($response ?? []),
            'attempt' => $attempt,
        ]);
    }

    /**
     * Sanitize log data (remove sensitive information)
     */
    protected function sanitizeLogData(array $data): array
    {
        $sensitiveKeys = ['api_key', 'apiKey', 'authorization', 'token', 'password', 'secret'];
        
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeLogData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Extract error message from API response
     */
    protected function extractErrorMessage(?array $response): string
    {
        if (!$response) {
            return 'Unknown error';
        }

        // Try common error message fields
        $errorFields = ['error', 'message', 'error_message', 'detail', 'details'];
        
        foreach ($errorFields as $field) {
            if (isset($response[$field])) {
                return is_string($response[$field]) 
                    ? $response[$field] 
                    : json_encode($response[$field]);
            }
        }

        return 'Unknown error';
    }

    /**
     * Get provider name
     */
    protected function getProviderName(): string
    {
        return $this->provider->name;
    }

    /**
     * Get model name (to be implemented by child classes)
     */
    abstract protected function getModelName(): string;
}

