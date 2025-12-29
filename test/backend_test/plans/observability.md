# Observability Plan

## Overview

The test suite implements comprehensive observability to ensure no errors are hidden. All logs, exceptions, and warnings are captured and surfaced.

## Logging Strategy

### Per-Test-File Logging

Every test class automatically writes to a corresponding log file:

**Pattern:**
- Test file: `laravel/Feature/Auth/OtpRequestTest.php`
- Log file: `logs/Feature/Auth/OtpRequestTest.log`

**Log Contents:**
- Test method name
- Start/end timestamps
- Request payloads (for HTTP tests)
- Response status and body
- Validation errors
- Captured Laravel logs
- Full exception stack traces

### Log Format

```
=== Test: test_valid_otp_request ===
Started: 2024-01-01 12:00:00
Request: POST /api/v1/auth/request-otp
Payload: {"phone": "+989123456789"}
Response: 200 OK
Response Body: {"request_id": "...", "expires_in": 300}
Laravel Logs:
  [2024-01-01 12:00:00] INFO: OTP requested for phone +989123456789
  [2024-01-01 12:00:00] INFO: OTP sent via Melipayamak
Ended: 2024-01-01 12:00:01
Duration: 1.234s
=== End Test ===
```

### Log Writing

- **Automatic**: No manual writing in test bodies
- **Append Mode**: Logs appended, not overwritten
- **Separators**: Clear separators between test methods
- **Never Lost**: Logs written even on test failure

## Error Capture

### Laravel Log Capture

All Laravel logs during test execution are captured:

```php
// Base TestCase automatically captures:
- Log::info(), Log::warning(), Log::error() calls
- Exception logs
- Query logs (if enabled)
- All log channels
```

### Error Detection

Tests fail if error-level logs occur (unless explicitly allowed):

```php
// Test fails if any error log occurs
$this->assertNoErrorLogs();

// Allow specific errors
$this->allowErrorLogs(['expected.error.code']);
```

### Exception Capture

All exceptions are captured with full stack traces:

- Exception message
- Stack trace
- File and line numbers
- Context (request, user, etc.)

## Test Failure Output

On test failure, the following is printed:

1. **Test Name**: Which test failed
2. **Collected Logs**: All Laravel logs during test
3. **Last HTTP Response**: Full response body
4. **Validation Errors**: Form validation errors
5. **Exception Stack Trace**: Full exception details
6. **Database State**: Relevant database records (if applicable)

## Queue Failure Detection

### Failed Jobs Table

After each test, verify no failed jobs:

```php
// In tearDown or test
$this->assertNoFailedJobs();
```

### Queue Assertions

When queue is faked, ensure job handler exceptions are not suppressed:

```php
// Jobs should not fail silently
Queue::fake();
// ... test code ...
// If job would fail, test should fail
```

## Log Levels

### Error Level
- **ERROR**: Test fails (unless allowed)
- **CRITICAL**: Test fails (unless allowed)
- **ALERT**: Test fails (unless allowed)
- **EMERGENCY**: Test fails (unless allowed)

### Warning Level
- **WARNING**: Can be configured to fail tests
- **NOTICE**: Tracked but doesn't fail tests

### Info Level
- **INFO**: Tracked in logs
- **DEBUG**: Tracked in logs (if enabled)

## Configuration

### PHPUnit Configuration

```xml
<php>
    <env name="APP_DEBUG" value="true"/>
    <env name="LOG_CHANNEL" value="stderr"/>
    <env name="LOG_LEVEL" value="debug"/>
</php>
```

### Laravel Configuration

```php
// config/logging.php (testing environment)
'channels' => [
    'stderr' => [
        'driver' => 'monolog',
        'handler' => StreamHandler::class,
        'with' => [
            'stream' => 'php://stderr',
        ],
        'level' => 'debug',
    ],
],
```

## CI/CD Integration

### Log Artifacts

Test logs are collected as CI artifacts:

```yaml
# GitHub Actions example
- name: Upload test logs
  uses: actions/upload-artifact@v3
  if: always()
  with:
    name: test-logs
    path: test/backend_test/logs/
```

### Log Retention

- Logs retained for 30 days (configurable)
- Logs compressed for storage efficiency
- Logs searchable by test name

## Debugging

### Viewing Logs

```bash
# View specific test log
cat test/backend_test/logs/Feature/Auth/OtpRequestTest.log

# Search logs for errors
grep -r "ERROR" test/backend_test/logs/

# View latest test logs
ls -lt test/backend_test/logs/ | head -10
```

### Common Patterns

1. **Error in Logs**: Check log file for full context
2. **Missing Logs**: Verify log directory permissions
3. **Large Logs**: Consider log rotation for long-running tests

## Best Practices

1. **Always Check Logs**: Review logs after test failures
2. **Allow Expected Errors**: Use `allowErrorLogs()` for expected errors
3. **Clear Logs**: Clear logs between test runs (optional)
4. **Log Structure**: Maintain consistent log format
5. **No Secrets**: Never log API keys, tokens, passwords

## Future Enhancements

- Log aggregation and search
- Log visualization
- Performance metrics in logs
- Test execution timeline
- Error pattern detection

