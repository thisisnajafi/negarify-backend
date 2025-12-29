<?php

namespace Test\BackendTest\Laravel\Helpers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Log\Events\MessageLogged;
use Carbon\Carbon;

/**
 * Base TestCase for all backend tests
 * 
 * Provides:
 * - Automatic log capture
 * - Error detection
 * - Per-test-file logging
 * - Response capture
 * - Queue failure detection
 */
abstract class BackendTestCase extends BaseTestCase
{
    use RefreshDatabase;
    use LogsTestExecution;

    /**
     * Creates the application.
     */
    public function createApplication()
    {
        $app = require \Illuminate\Foundation\Application::inferBasePath().'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    /**
     * Allowed error log codes (exceptions to error detection)
     */
    protected array $allowedErrorLogs = [];

    /**
     * Setup the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Set deterministic time
        Carbon::setTestNow(Carbon::now());

        // Setup logging
        $this->setUpLogging();

        // Capture Laravel logs
        $this->setupLogCapture();

        // Fake queues by default
        Queue::fake();

        // Clear allowed error logs
        $this->allowedErrorLogs = [];
    }

    /**
     * Tear down after test
     */
    protected function tearDown(): void
    {
        // Log test end
        if ($this->currentTestMethod) {
            $this->logTestEnd();
        }

        // Check for errors
        $this->checkForErrors();

        // Check for failed jobs
        $this->checkForFailedJobs();

        parent::tearDown();
    }

    /**
     * Setup Laravel log capture
     */
    protected function setupLogCapture(): void
    {
        // Intercept Laravel logs using Event::listen for MessageLogged event
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->captureLog(
                $event->level,
                $event->message,
                is_array($event->context) ? $event->context : []
            );
        });
    }

    /**
     * Make HTTP request and log it
     */
    protected function makeRequest(string $method, string $uri, array $data = [], array $headers = [])
    {
        $this->logRequest($method, $uri, $data);
        
        $response = $this->json($method, $uri, $data, $headers);
        
        $this->logResponse($response);
        
        // Log validation errors if any
        if ($response->status() === 422) {
            $errors = $response->json('errors') ?? [];
            $this->logValidationErrors($errors);
        }
        
        return $response;
    }

    /**
     * Check for errors after test
     */
    protected function checkForErrors(): void
    {
        $errorLogs = $this->getErrorLogs();
        
        if (!empty($errorLogs) && !$this->shouldAllowErrors()) {
            $this->logCapturedLogs();
            $this->fail(
                "Error-level logs detected during test execution:\n" .
                json_encode($errorLogs, JSON_PRETTY_PRINT)
            );
        }
    }

    /**
     * Check if errors should be allowed
     */
    protected function shouldAllowErrors(): bool
    {
        if (empty($this->allowedErrorLogs)) {
            return false;
        }

        $errorLogs = $this->getErrorLogs();
        foreach ($errorLogs as $log) {
            $message = $log['message'];
            $allowed = false;
            
            foreach ($this->allowedErrorLogs as $allowedPattern) {
                if (str_contains($message, $allowedPattern)) {
                    $allowed = true;
                    break;
                }
            }
            
            if (!$allowed) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Allow specific error logs (for expected errors)
     */
    protected function allowErrorLogs(array $patterns): void
    {
        $this->allowedErrorLogs = array_merge($this->allowedErrorLogs, $patterns);
    }

    /**
     * Assert no error logs occurred
     */
    protected function assertNoErrorLogs(): void
    {
        $errorLogs = $this->getErrorLogs();
        $this->assertEmpty(
            $errorLogs,
            "Expected no error logs, but found: " . json_encode($errorLogs, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Check for failed jobs
     */
    protected function checkForFailedJobs(): void
    {
        // Only check if queue is not faked
        if (!Queue::isFake()) {
            $failedJobs = DB::table('failed_jobs')->count();
            if ($failedJobs > 0) {
                $this->logCapturedLogs();
                $this->fail("Failed jobs detected: {$failedJobs} jobs in failed_jobs table");
            }
        }
    }

    /**
     * Assert no failed jobs
     */
    protected function assertNoFailedJobs(): void
    {
        $failedJobs = DB::table('failed_jobs')->count();
        $this->assertEquals(0, $failedJobs, "Expected no failed jobs, but found {$failedJobs}");
    }

    /**
     * Get last response for debugging
     */
    protected function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * Print failure details
     */
    protected function onNotSuccessfulTest(\Throwable $t): never
    {
        // Log exception
        $this->logException($t);
        
        // Log captured logs
        $this->logCapturedLogs();
        
        // Print to console
        if ($this->lastResponse) {
            echo "\n\n=== Last HTTP Response ===\n";
            echo "Status: " . ($this->lastResponse->status() ?? 'N/A') . "\n";
            if (method_exists($this->lastResponse, 'getContent')) {
                $content = $this->lastResponse->getContent();
                // Try to pretty-print JSON
                $decoded = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo "Body: " . json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
                } else {
                    echo "Body: " . substr($content, 0, 2000) . "\n";
                }
            }
        }
        
        if (!empty($this->capturedLogs)) {
            echo "\n\n=== Captured Logs ===\n";
            foreach ($this->capturedLogs as $log) {
                echo "[{$log['timestamp']}] {$log['level']}: {$log['message']}\n";
                if (!empty($log['context'])) {
                    echo "  Context: " . json_encode($log['context'], JSON_PRETTY_PRINT) . "\n";
                }
            }
        }
        
        parent::onNotSuccessfulTest($t);
    }

    /**
     * Run a test method and log it
     */
    public function runTest(): mixed
    {
        $testMethod = $this->getName();
        $this->logTestStart($testMethod);
        
        try {
            $result = parent::runTest();
            return $result;
        } catch (\Throwable $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Assert response has JSON structure (helper method)
     */
    protected function assertResponseJsonStructure(array $structure, $response = null): void
    {
        $response = $response ?? $this->lastResponse;
        if ($response && method_exists($response, 'assertJsonStructure')) {
            $response->assertJsonStructure($structure);
        }
    }

    /**
     * Create authenticated user for testing
     */
    protected function createAuthenticatedUser(array $attributes = [])
    {
        $user = \App\Models\User::factory()->create($attributes);
        $token = $user->createToken('test-token')->plainTextToken;
        
        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Make authenticated request
     */
    protected function makeAuthenticatedRequest(string $method, string $uri, array $data = [], $user = null, array $headers = [])
    {
        if (!$user) {
            $auth = $this->createAuthenticatedUser();
            $user = $auth['user'];
            $token = $auth['token'];
        } else {
            $token = is_array($user) ? $user['token'] : $user->createToken('test-token')->plainTextToken;
        }

        $headers['Authorization'] = 'Bearer ' . $token;
        
        return $this->makeRequest($method, $uri, $data, $headers);
    }
}

