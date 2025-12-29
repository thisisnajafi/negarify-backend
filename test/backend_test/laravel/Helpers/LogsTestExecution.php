<?php

namespace Test\BackendTest\Laravel\Helpers;

use Illuminate\Support\Facades\Log;

/**
 * Trait for automatic per-test-file logging
 * 
 * This trait automatically logs test execution details to a corresponding log file.
 * Log file path is derived from the test class file path.
 */
trait LogsTestExecution
{
    /**
     * Log file path for this test class
     */
    protected ?string $testLogPath = null;

    /**
     * Current test method name
     */
    protected ?string $currentTestMethod = null;

    /**
     * Test start time
     */
    protected ?float $testStartTime = null;

    /**
     * Captured Laravel logs during test execution
     */
    protected array $capturedLogs = [];

    /**
     * Last HTTP response (for HTTP tests)
     */
    protected $lastResponse = null;

    /**
     * Setup logging before test runs
     */
    protected function setUpLogging(): void
    {
        // Determine log file path based on test class file path
        $testClassFile = (new \ReflectionClass($this))->getFileName();
        $testClassFile = str_replace('\\', '/', $testClassFile);
        
        // Convert: test/backend_test/laravel/Feature/Auth/OtpRequestTest.php
        // To: test/backend_test/logs/Feature/Auth/OtpRequestTest.log
        $logPath = str_replace(
            ['test/backend_test/laravel/', '.php'],
            ['test/backend_test/logs/', '.log'],
            $testClassFile
        );

        // Ensure log directory exists (use native PHP to avoid facade dependency during setup)
        $logDir = dirname($logPath);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $this->testLogPath = $logPath;

        // Clear captured logs
        $this->capturedLogs = [];
    }

    /**
     * Log test method start
     */
    protected function logTestStart(string $testMethod): void
    {
        $this->currentTestMethod = $testMethod;
        $this->testStartTime = microtime(true);
        
        $this->writeToLog("=== Test: {$testMethod} ===");
        $this->writeToLog("Started: " . date('Y-m-d H:i:s'));
    }

    /**
     * Log test method end
     */
    protected function logTestEnd(): void
    {
        if ($this->testStartTime) {
            $duration = microtime(true) - $this->testStartTime;
            $this->writeToLog("Duration: " . number_format($duration, 3) . "s");
        }
        
        $this->writeToLog("Ended: " . date('Y-m-d H:i:s'));
        $this->writeToLog("=== End Test ===");
        $this->writeToLog(""); // Empty line separator
    }

    /**
     * Log HTTP request
     */
    protected function logRequest(string $method, string $uri, array $data = []): void
    {
        $this->writeToLog("Request: {$method} {$uri}");
        if (!empty($data)) {
            $this->writeToLog("Payload: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Log HTTP response
     */
    protected function logResponse($response): void
    {
        $this->lastResponse = $response;
        
        if ($response) {
            $status = method_exists($response, 'status') ? $response->status() : 'N/A';
            $this->writeToLog("Response: {$status} " . ($status >= 400 ? 'ERROR' : 'OK'));
            
            if (method_exists($response, 'getContent')) {
                $content = $response->getContent();
                if ($content) {
                    // Try to pretty-print JSON
                    $decoded = json_decode($content, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $this->writeToLog("Response Body: " . json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    } else {
                        $this->writeToLog("Response Body: " . substr($content, 0, 1000)); // Limit length
                    }
                }
            }
        }
    }

    /**
     * Log validation errors
     */
    protected function logValidationErrors(array $errors): void
    {
        if (!empty($errors)) {
            $this->writeToLog("Validation Errors: " . json_encode($errors, JSON_PRETTY_PRINT));
        }
    }

    /**
     * Log captured Laravel logs
     */
    protected function logCapturedLogs(): void
    {
        if (!empty($this->capturedLogs)) {
            $this->writeToLog("Laravel Logs:");
            foreach ($this->capturedLogs as $log) {
                $this->writeToLog("  [{$log['timestamp']}] {$log['level']}: {$log['message']}");
                if (!empty($log['context'])) {
                    $this->writeToLog("    Context: " . json_encode($log['context'], JSON_PRETTY_PRINT));
                }
            }
        }
    }

    /**
     * Log exception
     */
    protected function logException(\Throwable $exception): void
    {
        $this->writeToLog("Exception: " . get_class($exception));
        $this->writeToLog("Message: " . $exception->getMessage());
        $this->writeToLog("File: " . $exception->getFile() . ":" . $exception->getLine());
        $this->writeToLog("Stack Trace:");
        $this->writeToLog($exception->getTraceAsString());
    }

    /**
     * Write to log file
     */
    protected function writeToLog(string $message): void
    {
        if ($this->testLogPath) {
            // Ensure directory exists
            $logDir = dirname($this->testLogPath);
            if (!file_exists($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            file_put_contents($this->testLogPath, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Log database query (for debugging)
     */
    protected function logDatabaseQuery(string $query, array $bindings = []): void
    {
        $this->writeToLog("Database Query: {$query}");
        if (!empty($bindings)) {
            $this->writeToLog("  Bindings: " . json_encode($bindings, JSON_PRETTY_PRINT));
        }
    }

    /**
     * Log test assertion (for debugging complex tests)
     */
    protected function logAssertion(string $assertion, bool $passed, string $message = ''): void
    {
        $status = $passed ? 'PASS' : 'FAIL';
        $this->writeToLog("Assertion [{$status}]: {$assertion}");
        if ($message) {
            $this->writeToLog("  Message: {$message}");
        }
    }

    /**
     * Capture a Laravel log entry
     */
    public function captureLog(string $level, string $message, array $context = []): void
    {
        $this->capturedLogs[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
        ];
    }

    /**
     * Get captured logs
     */
    protected function getCapturedLogs(): array
    {
        return $this->capturedLogs;
    }

    /**
     * Clear captured logs
     */
    protected function clearCapturedLogs(): void
    {
        $this->capturedLogs = [];
    }

    /**
     * Get error-level logs
     */
    protected function getErrorLogs(): array
    {
        $errorLevels = ['error', 'critical', 'alert', 'emergency'];
        return array_filter($this->capturedLogs, function ($log) use ($errorLevels) {
            return in_array(strtolower($log['level']), $errorLevels);
        });
    }
}

