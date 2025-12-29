# Negarify Backend Test Suite

## Overview

This directory contains the comprehensive backend testing suite for the Negarify platform. The test suite is designed with "No Hidden Errors" philosophy - all errors, warnings, and exceptions are captured and surfaced.

## Structure

```
test/backend_test/
├── README.md              # This file
├── checklist.md           # Master test checklist with all sections
├── todo.md                # Live TODO tracker (updated as tests are implemented)
├── logs/                  # Per-test-file log outputs
├── plans/
│   ├── test_strategy.md   # Overall testing strategy
│   ├── coverage_map.md    # Endpoint -> test file mapping
│   └── observability.md   # Logging and error capture approach
└── laravel/
    ├── Feature/           # Feature tests (HTTP endpoints)
    ├── Unit/              # Unit tests (isolated components)
    ├── Integration/       # Integration tests (external services)
    ├── Helpers/           # Test helpers and traits
    └── Fixtures/          # Test fixtures (HTML samples, API responses)
```

## Running Tests

### Prerequisites

- PHP 8.2+
- Composer dependencies installed
- Test database configured (SQLite in-memory by default)

### Environment Setup

The test suite uses the following environment configuration (from `phpunit.xml`):

- `APP_ENV=testing`
- `APP_DEBUG=true` (for maximum verbosity)
- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`
- `QUEUE_CONNECTION=sync` (for deterministic testing)
- `CACHE_STORE=array`
- `LOG_CHANNEL=stderr` (for immediate visibility)

### Running All Tests

```bash
# Run all tests
php artisan test --testsuite=backend_test

# Run specific test file
php artisan test test/backend_test/laravel/Feature/Auth/OtpRequestTest.php

# Run with coverage
php artisan test --coverage

# Run with verbose output
php artisan test --verbose
```

### Running Specific Test Suites

```bash
# Feature tests only
php artisan test test/backend_test/laravel/Feature

# Unit tests only
php artisan test test/backend_test/laravel/Unit

# Integration tests only
php artisan test test/backend_test/laravel/Integration
```

## Test Configuration

### PHPUnit Configuration

The test suite extends Laravel's base TestCase and includes:

- **RefreshDatabase**: All tests use database transactions that rollback
- **Deterministic Time**: `Carbon::setTestNow()` for time-dependent tests
- **Fixed Seeds**: Consistent test data via seeders
- **Error Capture**: All Laravel logs captured during test execution
- **Queue Faking**: Queues are faked to prevent actual job execution

### Base TestCase Features

All tests extend `BackendTestCase` which provides:

1. **Automatic Log Capture**: All Laravel logs during test execution
2. **Error Detection**: Tests fail if error-level logs occur (unless explicitly allowed)
3. **Response Capture**: Last HTTP response body stored for debugging
4. **Validation Error Capture**: Form validation errors captured
5. **Exception Stack Traces**: Full stack traces on failures
6. **Per-Test Logging**: Each test class writes to its own log file

### Per-Test-File Logging

Every test class automatically writes to a corresponding log file:

- Test file: `laravel/Feature/Auth/OtpRequestTest.php`
- Log file: `logs/Feature/Auth/OtpRequestTest.log`

Logs include:
- Test method name and timestamps
- Request payloads (for HTTP tests)
- Response status and body
- Validation errors
- Captured Laravel logs
- Full exception stack traces

## Test Organization

### Feature Tests

Feature tests verify HTTP endpoints and user flows:

- `Feature/Auth/` - Authentication and OTP flows
- `Feature/User/` - User profile management
- `Feature/Tokens/` - Token bundles and currency
- `Feature/Payments/` - Zarinpal payment integration
- `Feature/Transactions/` - Token transaction history
- `Feature/Generation/` - Content generation jobs
- `Feature/Gallery/` - Gallery posts and feed
- `Feature/Social/` - Likes, comments, interactions
- `Feature/Admin/` - Admin dashboard endpoints

### Unit Tests

Unit tests verify isolated components:

- Service classes (MelipayamakService, ZarinpalService, etc.)
- Model methods and relationships
- Helper functions
- Validation rules

### Integration Tests

Integration tests verify external service contracts:

- `Integration/Payments/ZarinpalContractTest.php` - Payment gateway request/response structure
- `Integration/SMS/MelipayamakContractTest.php` - SMS service contract
- `Integration/Currency/TgjuScraperContractTest.php` - Currency scraper contract

## Mocks and Fakes

### External Services

All external services are mocked/faked:

- **Melipayamak**: `Http::fake()` for SMS requests
- **Zarinpal**: `Http::fake()` for payment requests
- **Segmind API**: `Http::fake()` for AI generation requests
- **TGJU Scraper**: HTML fixtures for currency scraping
- **Storage**: `Storage::fake()` for file uploads
- **Queue**: `Queue::fake()` for job processing

### Fixtures

Test fixtures are stored in `laravel/Fixtures/`:

- `tgju_sample.html` - Sample TGJU.org HTML for currency scraping
- `segmind_image_response.json` - Sample Segmind image generation response
- `segmind_video_response.json` - Sample Segmind video generation response
- `zarinpal_payment_response.json` - Sample Zarinpal payment response

## CI/CD Integration

### GitHub Actions / CI Configuration

The test suite is designed for CI environments:

1. **Log Artifacts**: Test logs are collected as CI artifacts
2. **Parallel Execution**: Tests can run in parallel (use `--parallel`)
3. **Coverage Reports**: Coverage reports generated for PRs
4. **No External Dependencies**: All external services mocked

### CI Environment Variables

```yaml
APP_ENV: testing
APP_DEBUG: true
DB_CONNECTION: sqlite
DB_DATABASE: :memory:
QUEUE_CONNECTION: sync
LOG_CHANNEL: stderr
```

## Debugging Failed Tests

### Viewing Test Logs

Each test class produces a log file in `logs/`:

```bash
# View log for specific test
cat test/backend_test/logs/Feature/Auth/OtpRequestTest.log

# View all logs
ls -la test/backend_test/logs/
```

### Common Issues

1. **Test Fails with "Error log detected"**:
   - Check the test log file for the error
   - The error occurred during test execution
   - Fix the underlying issue or mark as expected with `$this->allowErrorLogs()`

2. **Queue Jobs Not Executing**:
   - Tests use `Queue::fake()` by default
   - Use `Queue::assertPushed()` to verify jobs were queued
   - Use `Queue::assertNothingPushed()` to verify no jobs

3. **Database State Issues**:
   - All tests use `RefreshDatabase` trait
   - Each test runs in a transaction that rolls back
   - Ensure seeders are deterministic

## Code Changes for Testability

Minimal code changes may be required for improved testability:

1. **Service Binding**: Services bound to container for easy mocking
2. **Dependency Injection**: Controllers use DI for services
3. **Event Broadcasting**: Events can be faked for testing

Document any required code changes in this README.

## Test Coverage Goals

- **Line Coverage**: > 80%
- **Branch Coverage**: > 75%
- **Critical Paths**: 100% (auth, payments, token operations)

## Maintenance

- Update `checklist.md` when adding new endpoints
- Update `todo.md` as tests are implemented
- Update `coverage_map.md` when adding new test files
- Keep fixtures up-to-date with actual API responses

## Contributing

When adding new tests:

1. Add test file to appropriate directory
2. Update `checklist.md` and `todo.md`
3. Ensure per-test logging works
4. Add fixtures if needed
5. Update this README if adding new patterns
