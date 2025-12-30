# Full Suite Stabilization Task List

**Date Created:** 2025-12-29  
**Status:** Phase 2 In Progress (C1-C3 Complete)  
**Objective:** Fix 139 transaction isolation failures in full test suite execution

**Phase 1 Summary (Completed 2025-12-29):**
- ✅ Task A1: Fixed global transaction nesting issue by switching BackendTestCase to LazilyRefreshDatabase
- ✅ Task A2: Validated all manual transaction usage (18 DB::beginTransaction() calls in controllers, 9 DB::transaction() closures in jobs)
- ✅ Task A3: Verified test isolation - all tests properly isolated, no state leakage detected
- ✅ Task A4: Validated queue job execution context - queue jobs work correctly with LazilyRefreshDatabase
- ✅ Task A5: Phase 1 verification - All Payment and Generation tests pass with zero transaction nesting errors

**Phase 2 Summary (In Progress):**
- ✅ Task B1: Validated selected infrastructure strategy (Strategy 1 + Strategy 4) - fully implemented and working
- ✅ Task B2: Verified test suite execution boundaries and isolation - all 11 suites validated independently
- ✅ Task C1: Finalized base test case and shared test infrastructure - BackendTestCase validated, all 318 tests passing
- ✅ Task C2: Normalized shared test helpers and configuration - all helpers consistent, all 318 tests passing
- ✅ Task C3: Finalized PHPUnit logical suites and validated suite boundaries - all 11 suites + full suite validated, all 318 tests passing
- ✅ Task C4: Verified and finalized test environment variables - all 20 env vars validated, all 318 tests passing

**Key Changes:**
- BackendTestCase now uses LazilyRefreshDatabase instead of RefreshDatabase
- Eliminated transaction nesting conflicts in SQLite
- All manual transactions in controllers/jobs work correctly
- Test isolation maintained, no state leakage

**Commits:**
- eeb8a2e: Phase 1 Task A1: Fix global transaction nesting issue
- 2c4d5fb: Phase 1 Task A2: Validate manual transaction usage
- 45e18da: Phase 1 Task A3: Verify test isolation
- be067c8: Phase 1 Task A4: Validate queue job execution context

---

## A) Root Cause Analysis Tasks

### A1. Transaction Trait Mapping
- [x] **A1.1** Audit all test files to identify which trait each uses:
  - [x] Count tests using `RefreshDatabase` (default from `BackendTestCase`) - **0** (replaced by LazilyRefreshDatabase)
  - [x] Count tests using `DatabaseMigrations` (overrides) - **0** (all removed in Phase 2)
  - [x] Count tests using `DatabaseTransactions` (if any) - **0** (none found)
  - [x] Count tests using `LazilyRefreshDatabase` (if any) - **47** (all tests inherit from BackendTestCase)
  - [x] Document findings in `test/backend_test/TRANSACTION_TRAIT_AUDIT.md`

- [x] **A1.2** Map trait usage by test category:
  - [x] Auth/OTP tests (4 files) - All use `LazilyRefreshDatabase`
  - [x] Payment tests (2 files: `CallbackTest`, `PurchaseTest`) - All use `LazilyRefreshDatabase`
  - [x] Generation Job tests (4 files) - All use `LazilyRefreshDatabase`
  - [x] Gallery & Feed tests (7 files) - All use `LazilyRefreshDatabase`
  - [x] Social tests (4 files) - All use `LazilyRefreshDatabase`
  - [x] Admin tests (11 files) - All use `LazilyRefreshDatabase`
  - [x] Security tests (3 files) - All use `LazilyRefreshDatabase`
  - [x] Observability tests (3 files) - All use `LazilyRefreshDatabase`
  - [x] User/Profile tests (2 files) - All use `LazilyRefreshDatabase`
  - [x] Transaction tests (2 files) - All use `LazilyRefreshDatabase`
  - [x] Other tests (5 files) - All use `LazilyRefreshDatabase`

### A2. Manual Transaction Identification
- [x] **A2.1** Document all `DB::beginTransaction()` calls in controllers:
  - [x] `app/Http/Controllers/Api/V1/OrderController.php` (2 calls: lines 60, 266) - Actually uses conditional `DB::transaction()`
  - [x] `app/Http/Controllers/Api/V1/GalleryPostController.php` (2 calls: lines 59, 154) - Uses `DB::beginTransaction()` unconditionally
  - [x] `app/Http/Controllers/Api/V1/CommentController.php` (2 calls: lines 43, 145) - Actually uses conditional `DB::transaction()`
  - [x] `app/Http/Controllers/Api/V1/LikeController.php` (2 calls: lines 26, 121) - Actually uses conditional `DB::transaction()`
  - [x] `app/Http/Controllers/Api/V1/GenerationController.php` (3 calls: lines 70, 154, 211) - Uses `DB::beginTransaction()` unconditionally
  - [x] `app/Http/Controllers/Api/V1/GenerationJobController.php` (2 calls: lines 113, 179) - Uses `DB::beginTransaction()` unconditionally
  - [x] `app/Http/Controllers/Api/V1/AdminGalleryController.php` (4 calls: lines 46, 112, 269, 331) - Uses `DB::beginTransaction()` unconditionally
  - [x] `app/Http/Controllers/Api/V1/ReportController.php` (1 call: line 61) - Actually uses conditional `DB::transaction()`
  - [x] Total: 18 manual transaction operations in controllers (10 `DB::beginTransaction()`, 8 conditional `DB::transaction()`)

- [x] **A2.2** Document all `DB::transaction()` closures in services/jobs:
  - [x] `app/Services/ModerationService.php` (3 closures: lines 90, 116, 167) - Uses conditional `DB::transaction()` (checks `DB::transactionLevel() > 0`)
  - [x] `app/Jobs/GenerateImageJob.php` (2 closures: lines 264, 289) - Uses `DB::transaction()` unconditionally
  - [x] `app/Jobs/GenerateVideoJob.php` (2 closures: lines 143, 157) - Uses `DB::transaction()` unconditionally
  - [x] `app/Jobs/GenerateAudioJob.php` (2 closures: lines 138, 152) - Uses `DB::transaction()` unconditionally
  - [x] Total: 9 transaction closures in services/jobs (3 conditional, 6 unconditional)

- [x] **A2.3** Create mapping document: `test/backend_test/MANUAL_TRANSACTIONS_MAP.md`
  - [x] List each controller/job/service with transaction usage
  - [x] Note which tests exercise each transaction
  - [x] Identify potential nested transaction scenarios

### A3. Queue Job Transaction Analysis
- [x] **A3.1** Identify which tests execute jobs synchronously vs asynchronously:
  - [x] Check `phpunit.xml` for `QUEUE_CONNECTION` setting (currently `sync`) - **Line 91: `QUEUE_CONNECTION=sync`**
  - [x] Document which tests use `Queue::fake()` vs real queue execution
    - **Pattern 1:** `ImageGenerationTest.php`, `VideoAudioGenerationTest.php` - Use `Queue::fake()` and `Queue::assertPushed()`, jobs NOT executed
    - **Pattern 2:** `QueueJobProcessingTest.php` - Uses `Queue::fake()` (inherited) but bypasses with direct `->handle()` calls, jobs ARE executed
  - [x] Identify tests that call `Queue::assertPushed()` or execute jobs
    - **Queue::assertPushed():** `ImageGenerationTest.php` (line 105), `VideoAudioGenerationTest.php` (lines 105, 181)
    - **Direct execution:** `QueueJobProcessingTest.php` - 8 tests calling `->handle()` directly

- [x] **A3.2** Analyze job transaction lifecycle:
  - [x] Determine if job transactions can overlap with test transactions - **✅ SAFE:** `LazilyRefreshDatabase` doesn't wrap tests in transactions, so job transactions don't nest
  - [x] Check if `RefreshDatabase` wraps job execution in transactions - **✅ FIXED:** Now using `LazilyRefreshDatabase`, no test-level transaction wrapping
  - [x] Document any `lockForUpdate()` usage that conflicts with transactions - **✅ SAFE:** All `lockForUpdate()` calls (10 locations) are in safe contexts, either within transactions or SQLite-safe

### A4. PHP 8.4+ SQLite Limitations
- [x] **A4.1** Research PHP 8.4 SQLite transaction behavior changes:
  - [x] Document `transaction_mode` configuration option - **DEFERRED (default, optimal)**
  - [x] Identify if `DEFERRED` vs `IMMEDIATE` vs `EXCLUSIVE` modes affect behavior - **DEFERRED is optimal, no changes needed**
  - [x] Check Laravel framework version compatibility with PHP 8.4 - **✅ Laravel 12.0 fully compatible with PHP 8.4.14**

- [x] **A4.2** Test SQLite transaction nesting limits:
  - [x] Create isolated test to reproduce nested transaction error - **Created `SqliteTransactionNestingTest.php` with 7 comprehensive tests**
  - [x] Document maximum nesting depth (if any) - **No hard limit; Laravel uses savepoints (unlimited), application uses max 1-2 levels**
  - [x] Test transaction rollback behavior in nested scenarios - **✅ All rollback scenarios tested and working correctly**

### A5. Test Execution Order Analysis
- [x] **A5.1** Identify test execution order dependencies:
  - [x] Run PHPUnit with `--testdox` to see execution order
  - [x] Document if certain test sequences trigger failures
  - [x] Check if alphabetical ordering affects transaction state

- [x] **A5.2** Identify shared state between tests:
  - [x] Check for static variables or singletons
  - [x] Check for cached data that persists between tests
  - [x] Verify `LazilyRefreshDatabase` properly resets state
  - [x] Fix cache state pollution by adding `Cache::flush()` in `BackendTestCase::setUp()`
  - [x] Fix risky test `AvatarTest::it_handles_storage_failure_gracefully` with proper assertions

---

## B) Infrastructure Fix Strategy (Choose & Justify)

**Status:** ✅ B1 Complete - Strategy validated and documented

### B1. Strategy Evaluation
- [x] **B1.1** Review and validate selected infrastructure strategy
  - [x] Verified Strategy 1 (LazilyRefreshDatabase) is fully implemented
  - [x] Verified Strategy 4 (test suites) is fully implemented
  - [x] All prerequisites met
  - [x] Strategy working correctly (318 tests passing)
  - [x] Analysis document created: `TASK_B1_STRATEGY_VALIDATION_ANALYSIS.md`

#### Strategy 1: Global Switch to `LazilyRefreshDatabase`
**Description:** Replace `RefreshDatabase` with `LazilyRefreshDatabase` in `BackendTestCase`

**Pros:**
- [ ] Minimal code changes (single file change)
- [ ] Better performance (lazy migration execution)
- [ ] Reduces transaction conflicts (migrations run outside transaction)
- [ ] Maintains test isolation
- [ ] Compatible with manual transactions

**Cons:**
- [ ] May not fully resolve nested transaction issues
- [ ] Still uses transactions for test cleanup
- [ ] Potential for test pollution if not properly isolated
- [ ] Requires verification of all tests

**Impact on Runtime:**
- Faster test execution (estimated 10-20% improvement)
- Lower memory usage

**Risk Level:** Medium

**Compatibility:** High (Laravel 10+ supports this trait)

---

#### Strategy 2: Full Switch to `DatabaseMigrations`
**Description:** Replace `RefreshDatabase` with `DatabaseMigrations` in `BackendTestCase`, remove all overrides

**Pros:**
- [ ] No transaction wrapping (migrations run before test)
- [ ] Eliminates transaction nesting conflicts
- [ ] Consistent behavior across all tests
- [ ] Faster than `RefreshDatabase` (no rollback)

**Cons:**
- [ ] Slower than `RefreshDatabase` (full migration run per test)
- [ ] Requires all migrations to be reversible
- [ ] May expose migration issues
- [ ] Higher memory usage (full schema per test)

**Impact on Runtime:**
- Slower test execution (estimated 30-50% slower)
- Higher memory usage

**Risk Level:** Medium-High

**Compatibility:** High (standard Laravel trait)

---

#### Strategy 3: Remove `DatabaseTransactions` Entirely
**Description:** Not applicable - we're not using `DatabaseTransactions`, we're using `RefreshDatabase`

**Status:** N/A (not our current approach)

---

#### Strategy 4: Split PHPUnit Suites (Isolated DB Lifecycle)
**Description:** Create separate test suites in `phpunit.xml`, each with isolated database lifecycle

**Pros:**
- [ ] Complete isolation between suites
- [ ] Can use different DB strategies per suite
- [ ] Parallel execution possible
- [ ] Easier debugging (smaller test groups)

**Cons:**
- [ ] More complex configuration
- [ ] Requires maintaining multiple suite definitions
- [ ] May duplicate setup/teardown logic
- [ ] CI/CD configuration becomes more complex

**Impact on Runtime:**
- Similar or slightly faster (if parallelized)
- More complex execution model

**Risk Level:** Low

**Compatibility:** High (PHPUnit native feature)

**Implementation:**
```xml
<testsuites>
    <testsuite name="BackendTest-Auth">
        <directory>test/backend_test/laravel/Feature/Auth</directory>
    </testsuite>
    <testsuite name="BackendTest-Payments">
        <directory>test/backend_test/laravel/Feature/Payments</directory>
    </testsuite>
    <!-- etc -->
</testsuites>
```

---

#### Strategy 5: Switch to MySQL/PostgreSQL (Docker/Sail)
**Description:** Use real database instead of SQLite for full suite execution

**Pros:**
- [ ] Better transaction support (no nesting limitations)
- [ ] Production-like environment
- [ ] More realistic testing
- [ ] Better performance for complex queries

**Cons:**
- [ ] Requires Docker/Sail setup
- [ ] Slower test execution (network overhead)
- [ ] More complex CI/CD setup
- [ ] Requires database cleanup between runs
- [ ] May expose SQLite-specific bugs that were hidden

**Impact on Runtime:**
- Slower test execution (estimated 50-100% slower)
- Requires infrastructure setup

**Risk Level:** High (major infrastructure change)

**Compatibility:** Medium (requires Docker/Sail)

---

### B2. Strategy Selection

**Status:** ✅ B2 Complete - Test suite boundaries and isolation validated

**SELECTED STRATEGY:** Strategy 1 + Strategy 4 (Hybrid Approach)

**Rationale:**
1. **Primary:** Switch to `LazilyRefreshDatabase` globally
   - Addresses root cause (transaction wrapping)
   - Minimal code changes
   - Better performance
   - Maintains test isolation

2. **Secondary:** Split into logical test suites
   - Provides additional isolation layer
   - Enables parallel execution in future
   - Easier debugging and maintenance
   - Low risk, high benefit

3. **Why not others:**
   - Strategy 2 (`DatabaseMigrations`): Too slow, doesn't solve root cause
   - Strategy 5 (MySQL/PostgreSQL): Too complex, overkill for test suite
   - Strategy 3: Not applicable

**Implementation Plan:**
- Phase 1: Switch `BackendTestCase` to `LazilyRefreshDatabase`
- Phase 2: Remove all `DatabaseMigrations` overrides (revert to base trait)
- Phase 3: Create logical test suites in `phpunit.xml`
- Phase 4: Verify all tests pass in full suite

---

## C) Required Code & Config Changes

### C1. Base Test Case Changes
**Status:** ✅ C1 Complete - Base test case finalized and validated

- [x] **C1.1** Modify `test/backend_test/laravel/Helpers/BackendTestCase.php`:
  - [x] Change line 5: `use Illuminate\Foundation\Testing\RefreshDatabase;` → `use Illuminate\Foundation\Testing\LazilyRefreshDatabase;`
  - [x] Change line 31: `use RefreshDatabase;` → `use LazilyRefreshDatabase;`
  - [x] Add comment explaining the change and rationale
  - [x] Verify all 48 test files extend BackendTestCase correctly
  - [x] Validate BackendTestCase implementation (transaction handling, cache isolation, logging)
  - [x] Run full test suite - all 318 tests passing with 1765 assertions
  - [x] Create validation document: `TASK_C1_BASE_TEST_CASE_VALIDATION.md`

### C2. Remove Trait Overrides
**Status:** ✅ C2 Complete - Shared test helpers and configuration normalized

- [x] **C2.1** Remove `DatabaseMigrations` override from:
  - [x] `test/backend_test/laravel/Feature/Payments/CallbackTest.php` (line 10, 17)
  - [x] `test/backend_test/laravel/Feature/Payments/PurchaseTest.php` (line 12, 21)
  - [x] `test/backend_test/laravel/Feature/Generation/JobStatusTest.php` (line 9, 14)
  - [x] `test/backend_test/laravel/Feature/Generation/ImageGenerationTest.php` (line 10, 14)
  - [x] `test/backend_test/laravel/Feature/Generation/QueueJobProcessingTest.php` (line 11, 20)
  - [x] `test/backend_test/laravel/Feature/Generation/VideoAudioGenerationTest.php` (line 10, 14)
  - [x] `test/backend_test/laravel/Feature/Gallery/GalleryPostTest.php` (line 8, 14)
  - [x] `test/backend_test/laravel/Feature/Gallery/GalleryPostManagementTest.php` (line 8, 14)
  - [x] `test/backend_test/laravel/Unit/Services/TgjuScraperServiceTest.php` (line 10, 15)

- [x] **C2.2** Remove comments explaining `DatabaseMigrations` usage (no longer needed)

- [x] **C2.3** Verify no other test files have trait overrides:
  - [x] Search for `use.*DatabaseMigrations` in all test files
  - [x] Search for `use.*DatabaseTransactions` in all test files
  - [x] Document any remaining overrides

### C3. PHPUnit Configuration Changes
**Status:** ✅ C3 Complete - PHPUnit logical suites finalized and validated

- [x] **C3.1** Modify `phpunit.xml` to add logical test suites:
  - [x] Add `<testsuite name="BackendTest-Auth">` for Auth tests
  - [x] Add `<testsuite name="BackendTest-Payments">` for Payment tests
  - [x] Add `<testsuite name="BackendTest-Generation">` for Generation tests
  - [x] Add `<testsuite name="BackendTest-Gallery">` for Gallery/Feed tests
  - [x] Add `<testsuite name="BackendTest-Social">` for Social tests
  - [x] Add `<testsuite name="BackendTest-Admin">` for Admin tests
  - [x] Add `<testsuite name="BackendTest-Security">` for Security tests
  - [x] Add `<testsuite name="BackendTest-Observability">` for Observability tests
  - [x] Add `<testsuite name="BackendTest-User">` for User/Profile tests
  - [x] Add `<testsuite name="BackendTest-Transactions">` for Transaction tests
  - [x] Add `<testsuite name="BackendTest-Other">` for remaining tests
  - [x] Keep existing `<testsuite name="BackendTest">` for full suite execution

- [x] **C3.2** Verify suite definitions are correct:
  - [x] Each directory maps to correct test files
  - [x] No test files are missing from suites
  - [x] No test files are duplicated across suites
  - [x] All 11 logical suites execute independently with zero failures
  - [x] Full BackendTest suite includes all 318 tests (311 in logical suites + 7 Database tests)
  - [x] Suite boundaries match directories exactly

### C4. Environment Variable Changes
**Status:** ✅ C4 Complete - All environment variables validated and finalized

- [x] **C4.1** Verify `phpunit.xml` environment settings:
  - [x] `DB_CONNECTION=sqlite` (keep as-is) ✅
  - [x] `DB_DATABASE=:memory:` (keep as-is) ✅
  - [x] `QUEUE_CONNECTION=sync` (keep as-is) ✅
  - [x] All 20 env vars validated and correct ✅
  - [x] No missing critical env vars ✅
  - [x] No unused env vars ✅
  - [x] All configuration files verified ✅
  - [x] Full BackendTest suite passes with all env vars ✅

### C5. Queue/Job Execution Adjustments
- [ ] **C5.1** Verify queue behavior:
  - [ ] `Queue::fake()` is used in `BackendTestCase::setUp()` (line 61)
  - [ ] No real queue jobs execute during tests
  - [ ] No changes needed

### C6. Database Connection Lifecycle Changes
- [ ] **C6.1** Verify `LazilyRefreshDatabase` behavior:
  - [ ] Migrations run once per test class (not per test method)
  - [ ] Database is reset between test classes
  - [ ] No manual connection management needed

---

## D) Test Impact Matrix

| Test Suite Name | Current DB Strategy | Needs Refactor? | Reason | New Strategy |
|----------------|---------------------|-----------------|--------|--------------|
| **Auth/OTP** | `RefreshDatabase` (inherited) | No | No manual transactions | `LazilyRefreshDatabase` (inherited) |
| `OtpRequestTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `OtpVerifyTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `OtpResendTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `LogoutTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| **Payments** | Mixed | Yes | Manual transactions in controllers | `LazilyRefreshDatabase` (inherited) |
| `CallbackTest` | `DatabaseMigrations` (override) | Yes | Remove override | `LazilyRefreshDatabase` |
| `PurchaseTest` | `DatabaseMigrations` (override) | Yes | Remove override | `LazilyRefreshDatabase` |
| **Generation Jobs** | Mixed | Yes | Manual transactions in jobs/controllers | `LazilyRefreshDatabase` (inherited) |
| `ImageGenerationTest` | `DatabaseMigrations` (override) | Yes | Remove override | `LazilyRefreshDatabase` |
| `VideoAudioGenerationTest` | `DatabaseMigrations` (override) | Yes | Remove override | `LazilyRefreshDatabase` |
| `JobStatusTest` | `DatabaseMigrations` (override) | Yes | Remove override | `LazilyRefreshDatabase` |
| `QueueJobProcessingTest` | `DatabaseMigrations` (override) | Yes | Remove override | `LazilyRefreshDatabase` |
| **Gallery & Feed** | Mixed | Yes | Manual transactions in controllers | `LazilyRefreshDatabase` (inherited) |
| `GalleryPostTest` | `DatabaseMigrations` (override) | Yes | Remove override | `LazilyRefreshDatabase` |
| `GalleryPostManagementTest` | `DatabaseMigrations` (override) | Yes | Remove override | `LazilyRefreshDatabase` |
| `FeedListTest` | `RefreshDatabase` (inherited) | No | - | `LazilyRefreshDatabase` |
| `FeedVisibilityTest` | `RefreshDatabase` (inherited) | No | - | `LazilyRefreshDatabase` |
| `FeedCopyTest` | `RefreshDatabase` (inherited) | No | - | `LazilyRefreshDatabase` |
| `FeedCacheTest` | `RefreshDatabase` (inherited) | No | - | `LazilyRefreshDatabase` |
| **Social** | `RefreshDatabase` (inherited) | Yes | Manual transactions in controllers | `LazilyRefreshDatabase` (inherited) |
| `LikeTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `CommentTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `CommentDeleteTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `CommentAccuracyTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| **Admin** | `RefreshDatabase` (inherited) | Yes | Manual transactions in controllers | `LazilyRefreshDatabase` (inherited) |
| `AdminRbacTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `AdminSalesTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `AdminUsersTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `AdminModelsTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `AdminTokensTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `AdminCostProfitTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `SystemHealthTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `GalleryAdminTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `DashboardSummaryTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `ModerationAdminTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `TokenBundleAdminTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| **Security** | `RefreshDatabase` (inherited) | No | No manual transactions | `LazilyRefreshDatabase` (inherited) |
| `MiddlewareTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `RateLimitingTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `InputValidationTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| **Observability** | `RefreshDatabase` (inherited) | No | No manual transactions | `LazilyRefreshDatabase` (inherited) |
| `SmokeTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `ErrorHandlingTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `RouteRegistrationTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| **User/Profile** | `RefreshDatabase` (inherited) | No | No manual transactions | `LazilyRefreshDatabase` (inherited) |
| `ProfileTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `AvatarTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| **Transactions** | `RefreshDatabase` (inherited) | No | No manual transactions | `LazilyRefreshDatabase` (inherited) |
| `BalanceTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `HistoryTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `TransactionTypesTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| **Other** | Mixed | Yes | One override exists | `LazilyRefreshDatabase` (inherited) |
| `TgjuScraperServiceTest` | `DatabaseMigrations` (override) | Yes | Remove override | `LazilyRefreshDatabase` |
| `TokenBundlesTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `RateTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `ReportTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |
| `NotificationTest` | `RefreshDatabase` | No | - | `LazilyRefreshDatabase` |

**Summary:**
- **Total Test Files:** 47
- **Files Needing Refactor:** 9 (all are removing `DatabaseMigrations` override)
- **Files No Change Needed:** 38 (will inherit `LazilyRefreshDatabase` automatically)

---

## E) Full Test Execution Plan

### E1. Smoke Subset (Infrastructure Sanity)
- [ ] **E1.1** Run minimal test subset to verify infrastructure:
  - [ ] Command: `php artisan test --testsuite=BackendTest-Observability`
  - [ ] Expected runtime: < 10 seconds
  - [ ] Expected result: All tests pass, zero failures
  - [ ] Purpose: Verify `LazilyRefreshDatabase` works

- [ ] **E1.2** Run single test file with manual transactions:
  - [ ] Command: `php artisan test test/backend_test/laravel/Feature/Payments/CallbackTest.php`
  - [ ] Expected runtime: < 5 seconds
  - [ ] Expected result: All tests pass, zero failures
  - [ ] Purpose: Verify transaction conflicts are resolved

### E2. Medium Subset (DB-Heavy Tests)
- [ ] **E2.1** Run Payment tests:
  - [ ] Command: `php artisan test --testsuite=BackendTest-Payments`
  - [ ] Expected runtime: < 30 seconds
  - [ ] Expected result: All tests pass, zero failures
  - [ ] Purpose: Verify payment transaction handling

- [ ] **E2.2** Run Generation Job tests:
  - [ ] Command: `php artisan test --testsuite=BackendTest-Generation`
  - [ ] Expected runtime: < 60 seconds
  - [ ] Expected result: All tests pass, zero failures
  - [ ] Purpose: Verify job transaction handling

- [ ] **E2.3** Run Gallery & Feed tests:
  - [ ] Command: `php artisan test --testsuite=BackendTest-Gallery`
  - [ ] Expected runtime: < 45 seconds
  - [ ] Expected result: All tests pass, zero failures
  - [ ] Purpose: Verify gallery transaction handling

- [ ] **E2.4** Run Social tests:
  - [ ] Command: `php artisan test --testsuite=BackendTest-Social`
  - [ ] Expected runtime: < 30 seconds
  - [ ] Expected result: All tests pass, zero failures
  - [ ] Purpose: Verify social transaction handling

- [ ] **E2.5** Run Admin tests:
  - [ ] Command: `php artisan test --testsuite=BackendTest-Admin`
  - [ ] Expected runtime: < 90 seconds
  - [ ] Expected result: All tests pass, zero failures
  - [ ] Purpose: Verify admin transaction handling

### E3. Full Test Suite Execution
- [ ] **E3.1** Run complete test suite:
  - [ ] Command: `php artisan test --testsuite=BackendTest`
  - [ ] Expected runtime: < 240 seconds (4 minutes)
  - [ ] Expected result: **ZERO failures, ZERO errors**
  - [ ] Expected output: `Tests: 311 passed (1092 assertions)`
  - [ ] Log file: `test/backend_test/logs/full_suite_run_stable.log`

- [ ] **E3.2** Verify test execution order:
  - [ ] Run with `--testdox` flag
  - [ ] Document execution order
  - [ ] Verify no test pollution between suites

- [ ] **E3.3** Run full suite multiple times to verify stability:
  - [ ] Run 1: Record results
  - [ ] Run 2: Record results
  - [ ] Run 3: Record results
  - [ ] All runs must have identical results (zero failures)

### E4. Individual Suite Verification
- [ ] **E4.1** Run each logical suite individually:
  - [ ] `BackendTest-Auth`
  - [ ] `BackendTest-Payments`
  - [ ] `BackendTest-Generation`
  - [ ] `BackendTest-Gallery`
  - [ ] `BackendTest-Social`
  - [ ] `BackendTest-Admin`
  - [ ] `BackendTest-Security`
  - [ ] `BackendTest-Observability`
  - [ ] `BackendTest-User`
  - [ ] `BackendTest-Transactions`
  - [ ] `BackendTest-Other`
  - [ ] All must pass with zero failures

---

## F) Coverage Enablement Tasks

### F1. Coverage Driver Selection
- [ ] **F1.1** Evaluate PCOV vs Xdebug:
  - [ ] **PCOV Pros:**
    - [ ] Faster execution (native C extension)
    - [ ] Lower memory usage
    - [ ] Better performance
    - [ ] Actively maintained
  - [ ] **PCOV Cons:**
    - [ ] Requires PHP 7.1+
    - [ ] May not be available on all systems
  - [ ] **Xdebug Pros:**
    - [ ] More widely available
    - [ ] Better IDE integration
    - [ ] More features (debugging)
  - [ ] **Xdebug Cons:**
    - [ ] Slower execution
    - [ ] Higher memory usage
    - [ ] May impact test performance significantly

- [ ] **F1.2** **SELECTED:** PCOV (for performance)
  - [ ] Rationale: Test suite performance is critical, PCOV is faster
  - [ ] Fallback: Xdebug if PCOV unavailable

### F2. Installation Steps
- [ ] **F2.1** Install PCOV extension:
  - [ ] Windows: `pecl install pcov` or use pre-built DLL
  - [ ] Linux: `pecl install pcov` or `apt-get install php-pcov`
  - [ ] macOS: `pecl install pcov` or `brew install php-pcov`
  - [ ] Verify installation: `php -m | grep pcov`

- [ ] **F2.2** Configure PCOV in `php.ini`:
  - [ ] Add: `extension=pcov.so` (or `extension=pcov.dll` on Windows)
  - [ ] Add: `pcov.enabled=1`
  - [ ] Add: `pcov.directory=app` (limit to app directory)
  - [ ] Verify: `php -i | grep pcov`

- [ ] **F2.3** Alternative: Install Xdebug if PCOV fails:
  - [ ] Windows: Download DLL from xdebug.org
  - [ ] Linux: `apt-get install php-xdebug` or `yum install php-xdebug`
  - [ ] macOS: `brew install php-xdebug`
  - [ ] Configure: `xdebug.mode=coverage`

### F3. PHPUnit Configuration Changes
- [ ] **F3.1** Verify `phpunit.xml` coverage configuration:
  - [ ] Coverage is already configured (lines 31-37)
  - [ ] HTML output: `test/backend_test/reports/coverage/`
  - [ ] Text output: `test/backend_test/reports/coverage.txt`
  - [ ] XML output: `test/backend_test/reports/coverage.xml`
  - [ ] No changes needed

- [ ] **F3.2** Add coverage exclusions (if needed):
  - [ ] Exclude test files (already excluded via source configuration)
  - [ ] Exclude vendor files (already excluded)
  - [ ] Verify exclusions are correct

### F4. Coverage Threshold
- [ ] **F4.1** Set coverage threshold:
  - [ ] Target: > 80% overall coverage
  - [ ] Minimum: 70% (acceptable for initial run)
  - [ ] Document in `phpunit.xml` or separate config

- [ ] **F4.2** Generate initial coverage report:
  - [ ] Command: `php artisan test --testsuite=BackendTest --coverage --min=70`
  - [ ] Expected output: Coverage percentage
  - [ ] Expected files: HTML, text, XML reports generated
  - [ ] Document actual coverage percentage

---

## G) Verification & Acceptance Criteria

### G1. Full Test Suite Pass Criteria
- [ ] **G1.1** Full suite execution:
  - [ ] Command: `php artisan test --testsuite=BackendTest`
  - [ ] **MUST:** Zero failures
  - [ ] **MUST:** Zero errors
  - [ ] **MUST:** All 311 tests pass
  - [ ] **MUST:** All 1,092 assertions pass
  - [ ] **MUST:** Execution time < 5 minutes

- [ ] **G1.2** Test output verification:
  - [ ] Output contains: `Tests: 311 passed`
  - [ ] Output contains: `1092 assertions`
  - [ ] Output does NOT contain: `FAILED`
  - [ ] Output does NOT contain: `ERROR`

### G2. Coverage Report Criteria
- [ ] **G2.1** Coverage report generation:
  - [ ] HTML report exists: `test/backend_test/reports/coverage/index.html`
  - [ ] Text report exists: `test/backend_test/reports/coverage.txt`
  - [ ] XML report exists: `test/backend_test/reports/coverage.xml`
  - [ ] Reports contain actual coverage percentage (not "N/A")

- [ ] **G2.2** Coverage threshold:
  - [ ] Overall coverage >= 70% (minimum acceptable)
  - [ ] Overall coverage >= 80% (target)
  - [ ] Document actual percentage in final report

### G3. Git Status Criteria
- [ ] **G3.1** Repository state:
  - [ ] `git status` shows clean working directory (or only report files)
  - [ ] All changes committed
  - [ ] Branch: `BackEnd`
  - [ ] Latest commit includes stabilization changes

- [ ] **G3.2** Remote synchronization:
  - [ ] All changes pushed to `origin/BackEnd`
  - [ ] Remote and local are in sync
  - [ ] No uncommitted changes

### G4. Report Updates
- [ ] **G4.1** Update `test/backend_test/reports/final_verification_report.md`:
  - [ ] Document full suite execution results
  - [ ] Document coverage percentage
  - [ ] Remove "Known Issues" section (or mark as resolved)
  - [ ] Update status to "PRODUCTION READY"

- [ ] **G4.2** Update `test/backend_test/todo.md`:
  - [ ] Mark "Full Suite Stabilization" as complete
  - [ ] Document any remaining items (if any)

### G5. No Known Issues
- [ ] **G5.1** Verify no remaining issues:
  - [ ] No transaction errors in logs
  - [ ] No test isolation problems
  - [ ] No coverage generation failures
  - [ ] All documentation updated

- [ ] **G5.2** Final verification:
  - [ ] Run full suite 3 times consecutively
  - [ ] All 3 runs must pass with zero failures
  - [ ] Results must be identical across runs
  - [ ] Document stability confirmation

---

## H) Risk & Rollback Plan

### H1. What Could Break?
- [ ] **H1.1** Test failures due to `LazilyRefreshDatabase` behavior:
  - [ ] Risk: Some tests may depend on `RefreshDatabase` transaction rollback
  - [ ] Impact: Tests may fail or show data pollution
  - [ ] Mitigation: Run tests incrementally, fix issues as they arise

- [ ] **H1.2** Migration issues:
  - [ ] Risk: `LazilyRefreshDatabase` may expose migration problems
  - [ ] Impact: Tests fail during migration
  - [ ] Mitigation: Verify all migrations are reversible

- [ ] **H1.3** Test suite configuration errors:
  - [ ] Risk: Incorrect suite definitions in `phpunit.xml`
  - [ ] Impact: Tests not executed or executed incorrectly
  - [ ] Mitigation: Verify suite definitions match directory structure

- [ ] **H1.4** Performance degradation:
  - [ ] Risk: `LazilyRefreshDatabase` may be slower in some scenarios
  - [ ] Impact: Test execution time increases
  - [ ] Mitigation: Monitor execution time, optimize if needed

### H2. Rollback Procedure
- [ ] **H2.1** Git-based rollback:
  - [ ] Identify commit hash before changes: `git log --oneline -10`
  - [ ] Create backup branch: `git branch backup-before-stabilization`
  - [ ] Rollback to previous commit: `git reset --hard <previous-commit-hash>`
  - [ ] Verify rollback: `git log -1`
  - [ ] Force push if needed: `git push origin BackEnd --force` (with caution)

- [ ] **H2.2** Manual rollback steps:
  - [ ] Revert `BackendTestCase.php`: Change `LazilyRefreshDatabase` back to `RefreshDatabase`
  - [ ] Restore `DatabaseMigrations` overrides in 9 test files
  - [ ] Revert `phpunit.xml` suite definitions (remove new suites, keep original)
  - [ ] Verify: Run full suite, should return to previous state (139 failures)

- [ ] **H2.3** Rollback verification:
  - [ ] Run full test suite after rollback
  - [ ] Verify test behavior matches pre-stabilization state
  - [ ] Document rollback success/failure

### H3. Validation of Rollback Success
- [ ] **H3.1** Test execution:
  - [ ] Run full suite: `php artisan test --testsuite=BackendTest`
  - [ ] Expected: 139 failures (original state)
  - [ ] Verify: Transaction errors return

- [ ] **H3.2** Code verification:
  - [ ] `BackendTestCase.php` uses `RefreshDatabase`
  - [ ] 9 test files have `DatabaseMigrations` override
  - [ ] `phpunit.xml` has original suite definition only

- [ ] **H3.3** Documentation:
  - [ ] Document rollback reason
  - [ ] Document issues encountered
  - [ ] Plan alternative approach if needed

---

## Implementation Order

1. **Phase 1: Analysis** (Sections A1-A5)
   - Complete root cause analysis
   - Document all findings
   - Create audit documents

2. **Phase 2: Strategy Implementation** (Sections C1-C6)
   - Modify `BackendTestCase.php`
   - Remove trait overrides
   - Update `phpunit.xml`

3. **Phase 3: Testing** (Sections E1-E4)
   - Run smoke tests
   - Run medium subsets
   - Run full suite

4. **Phase 4: Coverage** (Section F)
   - Install coverage driver
   - Generate coverage reports
   - Document coverage percentage

5. **Phase 5: Verification** (Section G)
   - Verify all acceptance criteria
   - Update documentation
   - Final confirmation

6. **Phase 6: Rollback Preparation** (Section H)
   - Create backup branch
   - Document rollback procedure
   - Test rollback (if needed)

---

## Notes

- All tasks must be completed in order
- Do not skip verification steps
- Document all findings and decisions
- Commit changes incrementally (after each phase)
- Test thoroughly before declaring DONE

---

**END OF TASK LIST**

