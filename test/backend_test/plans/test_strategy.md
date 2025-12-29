# Test Strategy

## Philosophy: "No Hidden Errors"

The test suite is designed with the principle that **no error should be hidden**. All warnings, notices, deprecations, and exceptions are captured and surfaced.

## Testing Approach

### 1. Test Types

#### Feature Tests
- Test HTTP endpoints and user flows
- Use `RefreshDatabase` for clean state
- Mock external services
- Verify response structure and status codes
- Test authentication and authorization

#### Unit Tests
- Test isolated components (services, models, helpers)
- No database or HTTP layer
- Fast execution
- Test business logic and edge cases

#### Integration Tests
- Test external service contracts
- Verify request/response structure
- No real network calls
- Ensure compatibility with external APIs

### 2. Test Organization

Tests are organized by feature area:
- `Feature/Auth/` - Authentication flows
- `Feature/User/` - User profile management
- `Feature/Tokens/` - Token operations
- `Feature/Payments/` - Payment integration
- `Feature/Generation/` - Content generation
- `Feature/Gallery/` - Gallery and feed
- `Feature/Admin/` - Admin dashboards
- `Unit/Services/` - Service unit tests
- `Integration/` - External service contracts

### 3. Deterministic Testing

All tests are deterministic:
- **Fixed Time**: `Carbon::setTestNow()` for time-dependent tests
- **Fixed Seeds**: Consistent test data via seeders
- **Isolated State**: Each test runs in a transaction that rolls back
- **No Randomness**: All random values are seeded or mocked

### 4. Error Capture

#### Automatic Log Capture
- All Laravel logs during test execution are captured
- Logs written to per-test-file log files
- Logs included in test failure output

#### Error Detection
- Tests fail if error-level logs occur (unless explicitly allowed)
- Warnings and notices can be configured to fail tests
- Deprecations tracked and reported

#### Exception Handling
- All exceptions captured with full stack traces
- Exceptions included in test failure output
- No exceptions swallowed silently

### 5. Mocking Strategy

#### External Services
- **Melipayamak**: `Http::fake()` for SMS requests
- **Zarinpal**: `Http::fake()` for payment requests
- **Segmind**: `Http::fake()` for AI generation requests
- **TGJU**: HTML fixtures for currency scraping

#### Storage
- `Storage::fake()` for file operations
- No real S3 calls during tests

#### Queue
- `Queue::fake()` for job processing
- Jobs asserted, not executed
- Can use `Queue::assertPushed()` to verify jobs

#### Cache
- `Cache::fake()` or in-memory cache
- Redis faked for testing

### 6. Test Data Management

#### Factories
- Use Laravel factories for test data
- Factories create consistent, valid data
- Factories support relationships

#### Seeders
- Seeders for initial data (admin user, token bundles, models)
- Seeders run before tests
- Seeders are deterministic

#### Fixtures
- HTML samples for scraping tests
- JSON samples for API response tests
- Stored in `laravel/Fixtures/`

### 7. Assertions

#### Response Assertions
- Status code assertions
- JSON structure assertions
- Field value assertions
- Validation error assertions

#### Database Assertions
- Record existence assertions
- Field value assertions
- Relationship assertions
- Count assertions

#### Queue Assertions
- Job pushed assertions
- Job not pushed assertions
- Job payload assertions

#### Log Assertions
- Error log absence assertions
- Log message assertions
- Log level assertions

### 8. Performance Considerations

#### Query Optimization
- Avoid N+1 queries (use eager loading)
- Assert query counts where appropriate
- Use database transactions for isolation

#### Test Speed
- Use in-memory SQLite for speed
- Mock external services (no network calls)
- Use `RefreshDatabase` efficiently

### 9. Coverage Goals

- **Line Coverage**: > 80%
- **Branch Coverage**: > 75%
- **Critical Paths**: 100%
  - Authentication flows
  - Payment flows
  - Token operations
  - Generation jobs

### 10. CI/CD Integration

#### Test Execution
- Tests run in parallel where possible
- Tests produce artifacts (logs, coverage)
- Tests fail fast on errors

#### Reporting
- Coverage reports generated
- Test results published
- Logs collected as artifacts

### 11. Maintenance

#### Keeping Tests Updated
- Update tests when endpoints change
- Update fixtures when APIs change
- Review and update coverage regularly

#### Test Quality
- Tests should be readable and maintainable
- Tests should be fast
- Tests should be isolated
- Tests should be deterministic

