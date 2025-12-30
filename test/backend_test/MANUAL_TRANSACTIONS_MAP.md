# Manual Transactions Mapping Document

**Date:** 2025-12-30  
**Task:** A2 - Manual Transaction Identification  
**Status:** ✅ Complete

---

## Summary

This document maps all manual database transaction usage in controllers, services, and jobs, identifying where transactions are started and which tests exercise them.

**Total Transactions Identified:**
- **Controllers:** 18 transaction starts (10 `DB::beginTransaction()`, 8 `DB::transaction()` closures)
- **Services:** 3 `DB::transaction()` closures
- **Jobs:** 9 `DB::transaction()` closures
- **Grand Total:** 30 manual transaction operations

---

## Controllers

### 1. OrderController (`app/Http/Controllers/Api/V1/OrderController.php`)

#### Transaction 1: `purchase()` - Order Creation (Line ~74)
- **Type:** `DB::transaction()` closure (conditional)
- **Conditional Logic:** Checks `DB::transactionLevel() > 0 || DB::connection()->getPdo()->inTransaction()` before starting transaction
- **Purpose:** Create order atomically (Order model creation)
- **Lines:** 60-85
- **Methods:** `purchase()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Payments/PurchaseTest.php`
    - `it_creates_order_and_requests_payment()`
    - `it_rejects_inactive_bundle_purchase()`
    - `it_calculates_price_using_current_rate()`
    - `it_includes_bonus_tokens_in_order()`
    - `it_handles_zarinpal_failure_gracefully()`
- **Potential Nested Transaction Scenarios:** Yes - runs inside `RefreshDatabase` transaction in tests (now fixed with `LazilyRefreshDatabase`)

#### Transaction 2: `callback()` - Payment Processing (Line ~312)
- **Type:** `DB::transaction()` closure (conditional)
- **Conditional Logic:** Checks `DB::transactionLevel() > 0 || DB::connection()->getPdo()->inTransaction()` before starting transaction
- **Purpose:** Process payment atomically (update order status, credit tokens, create transaction record)
- **Lines:** 307-313
- **Methods:** `callback()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Payments/CallbackTest.php`
    - `it_processes_successful_payment_callback()`
    - `it_is_idempotent_prevents_double_credit()`
    - `it_handles_failed_payment_status()`
    - `it_handles_invalid_authority()`
    - `it_handles_zarinpal_verification_failure()`
    - `it_prevents_processing_non_pending_orders()`
    - `it_handles_mismatched_amount()`
- **Potential Nested Transaction Scenarios:** Yes - runs inside `RefreshDatabase` transaction in tests (now fixed with `LazilyRefreshDatabase`)

---

### 2. GalleryPostController (`app/Http/Controllers/Api/V1/GalleryPostController.php`)

#### Transaction 1: `store()` - Post Creation (Line 59)
- **Type:** `DB::beginTransaction()` (unconditional)
- **Purpose:** Create gallery post atomically
- **Lines:** 59-72
- **Methods:** `store()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Gallery/GalleryPostTest.php`
    - `it_creates_gallery_post_from_completed_job()`
    - `it_rejects_post_from_incomplete_job()`
    - `it_rejects_post_from_other_user_job()`
    - `it_prevents_duplicate_posts_for_same_job()`
    - `it_respects_prompt_and_model_visibility_flags()`
- **Potential Nested Transaction Scenarios:** ⚠️ **YES** - Uses `DB::beginTransaction()` unconditionally, will fail in SQLite if already in a transaction

#### Transaction 2: `update()` - Post Update (Line 154)
- **Type:** `DB::beginTransaction()` (unconditional)
- **Purpose:** Update gallery post atomically
- **Lines:** 154-185
- **Methods:** `update()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Gallery/GalleryPostManagementTest.php`
    - `it_updates_gallery_post()`
    - `it_rejects_updating_other_user_post()`
- **Potential Nested Transaction Scenarios:** ⚠️ **YES** - Uses `DB::beginTransaction()` unconditionally, will fail in SQLite if already in a transaction

---

### 3. CommentController (`app/Http/Controllers/Api/V1/CommentController.php`)

#### Transaction 1: `store()` - Comment Creation (Line ~63)
- **Type:** `DB::transaction()` closure (conditional)
- **Conditional Logic:** Checks `DB::transactionLevel() > 0 || DB::connection()->getPdo()->inTransaction()` before starting transaction
- **Purpose:** Create comment atomically and increment post comments_count
- **Lines:** 58-64
- **Methods:** `store()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Social/CommentTest.php`
    - `it_creates_comment_on_post()`
    - `it_creates_nested_reply_comment()`
    - `it_rejects_reply_to_invalid_parent_comment()`
    - `it_validates_comment_body()`
  - `test/backend_test/laravel/Feature/Social/CommentAccuracyTest.php`
    - `it_maintains_accurate_comments_count()`
- **Potential Nested Transaction Scenarios:** No - uses conditional logic to prevent nesting

#### Transaction 2: `destroy()` - Comment Deletion (Line ~173)
- **Type:** `DB::transaction()` closure (conditional)
- **Conditional Logic:** Checks `DB::transactionLevel() > 0 || DB::connection()->getPdo()->inTransaction()` before starting transaction
- **Purpose:** Delete comment atomically and decrement post comments_count (including replies)
- **Lines:** 170-174
- **Methods:** `destroy()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Social/CommentDeleteTest.php`
    - `it_deletes_own_comment()`
    - `it_deletes_comment_with_replies()`
    - `it_rejects_deleting_other_user_comment()`
    - `it_allows_admin_to_delete_any_comment()`
  - `test/backend_test/laravel/Feature/Social/CommentAccuracyTest.php`
    - `it_decrements_comments_count_when_deleting_comment()`
    - `it_decrements_comments_count_when_deleting_comment_with_replies()`
- **Potential Nested Transaction Scenarios:** No - uses conditional logic to prevent nesting

---

### 4. LikeController (`app/Http/Controllers/Api/V1/LikeController.php`)

#### Transaction 1: `store()` - Like Creation (Line ~55)
- **Type:** `DB::transaction()` closure (conditional)
- **Conditional Logic:** Checks `DB::transactionLevel() > 0 || DB::connection()->getPdo()->inTransaction()` before starting transaction
- **Purpose:** Create like atomically and increment post likes_count (idempotent)
- **Lines:** 52-56
- **Methods:** `store()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Social/LikeTest.php`
    - `it_likes_a_post()`
    - `it_is_idempotent_prevents_duplicate_likes()`
    - `it_allows_liking_own_post()`
    - `it_maintains_accurate_likes_count()`
- **Potential Nested Transaction Scenarios:** No - uses conditional logic to prevent nesting

#### Transaction 2: `destroy()` - Unlike (Line ~161)
- **Type:** `DB::transaction()` closure (conditional)
- **Conditional Logic:** Checks `DB::transactionLevel() > 0 || DB::connection()->getPdo()->inTransaction()` before starting transaction
- **Purpose:** Delete like atomically and decrement post likes_count
- **Lines:** 158-162
- **Methods:** `destroy()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Social/LikeTest.php`
    - `it_unlikes_a_post()`
    - `it_maintains_accurate_likes_count()`
- **Potential Nested Transaction Scenarios:** No - uses conditional logic to prevent nesting

---

### 5. GenerationController (`app/Http/Controllers/Api/V1/GenerationController.php`)

#### Transaction 1: `generateImage()` - Image Job Creation (Line 70)
- **Type:** `DB::beginTransaction()` (unconditional)
- **Purpose:** Reserve tokens and create generation job atomically
- **Lines:** 70-100
- **Methods:** `generateImage()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Generation/ImageGenerationTest.php`
    - `it_creates_image_generation_job()`
    - `it_rejects_generation_with_insufficient_tokens()`
    - `it_rejects_unavailable_model()`
    - `it_rejects_wrong_model_type()`
    - `it_validates_required_prompt()`
    - `it_stores_generation_parameters()`
- **Potential Nested Transaction Scenarios:** ⚠️ **YES** - Uses `DB::beginTransaction()` unconditionally, will fail in SQLite if already in a transaction

#### Transaction 2: `generateVideo()` - Video Job Creation (Line 154)
- **Type:** `DB::beginTransaction()` (unconditional)
- **Purpose:** Reserve tokens and create generation job atomically
- **Lines:** 154-185
- **Methods:** `generateVideo()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Generation/VideoAudioGenerationTest.php`
    - `it_creates_video_generation_job()`
    - `it_rejects_video_generation_with_wrong_model_type()`
    - `it_stores_video_generation_parameters()`
- **Potential Nested Transaction Scenarios:** ⚠️ **YES** - Uses `DB::beginTransaction()` unconditionally, will fail in SQLite if already in a transaction

#### Transaction 3: `generateAudio()` - Audio Job Creation (Line 211)
- **Type:** `DB::beginTransaction()` (unconditional)
- **Purpose:** Reserve tokens and create generation job atomically
- **Lines:** 211-242
- **Methods:** `generateAudio()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Generation/VideoAudioGenerationTest.php`
    - `it_creates_audio_generation_job()`
    - `it_rejects_audio_generation_with_wrong_model_type()`
    - `it_stores_audio_generation_parameters()`
- **Potential Nested Transaction Scenarios:** ⚠️ **YES** - Uses `DB::beginTransaction()` unconditionally, will fail in SQLite if already in a transaction

---

### 6. GenerationJobController (`app/Http/Controllers/Api/V1/GenerationJobController.php`)

#### Transaction 1: `cancel()` - Job Cancellation (Line 113)
- **Type:** `DB::beginTransaction()` (unconditional)
- **Purpose:** Cancel job and refund tokens atomically
- **Lines:** 113-130
- **Methods:** `cancel()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Generation/JobStatusTest.php`
    - `it_cancels_pending_job_and_refunds_tokens()`
    - `it_rejects_cancelling_completed_job()`
    - `it_handles_cancelling_processing_job()`
- **Potential Nested Transaction Scenarios:** ⚠️ **YES** - Uses `DB::beginTransaction()` unconditionally, will fail in SQLite if already in a transaction

#### Transaction 2: `retry()` - Job Retry (Line 179)
- **Type:** `DB::beginTransaction()` (unconditional)
- **Purpose:** Retry failed job and reserve tokens atomically
- **Lines:** 179-200
- **Methods:** `retry()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Generation/JobStatusTest.php`
    - `it_retries_failed_job()`
    - `it_rejects_retrying_non_failed_job()`
- **Potential Nested Transaction Scenarios:** ⚠️ **YES** - Uses `DB::beginTransaction()` unconditionally, will fail in SQLite if already in a transaction

---

### 7. AdminGalleryController (`app/Http/Controllers/Api/V1/AdminGalleryController.php`)

#### Transaction 1: `curate()` - Single Post Curation (Line 46)
- **Type:** `DB::beginTransaction()` (unconditional)
- **Purpose:** Mark single post as curated atomically
- **Lines:** 46-60
- **Methods:** `curate()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Admin/GalleryAdminTest.php`
    - Various curation tests
- **Potential Nested Transaction Scenarios:** ⚠️ **YES** - Uses `DB::beginTransaction()` unconditionally, will fail in SQLite if already in a transaction

#### Transaction 2: `uncurate()` - Single Post Uncuration (Line 112)
- **Type:** `DB::beginTransaction()` (unconditional)
- **Purpose:** Remove curation from single post atomically
- **Lines:** 112-126
- **Methods:** `uncurate()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Admin/GalleryAdminTest.php`
    - Various uncuration tests
- **Potential Nested Transaction Scenarios:** ⚠️ **YES** - Uses `DB::beginTransaction()` unconditionally, will fail in SQLite if already in a transaction

#### Transaction 3: `curateBatch()` - Batch Curation (Line 269)
- **Type:** `DB::beginTransaction()` (unconditional)
- **Purpose:** Mark multiple posts as curated atomically
- **Lines:** 269-300
- **Methods:** `curateBatch()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Admin/GalleryAdminTest.php`
    - Batch curation tests
- **Potential Nested Transaction Scenarios:** ⚠️ **YES** - Uses `DB::beginTransaction()` unconditionally, will fail in SQLite if already in a transaction

#### Transaction 4: `uncurateBatch()` - Batch Uncuration (Line 331)
- **Type:** `DB::beginTransaction()` (unconditional)
- **Purpose:** Remove curation from multiple posts atomically
- **Lines:** 331-360
- **Methods:** `uncurateBatch()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Admin/GalleryAdminTest.php`
    - Batch uncuration tests
- **Potential Nested Transaction Scenarios:** ⚠️ **YES** - Uses `DB::beginTransaction()` unconditionally, will fail in SQLite if already in a transaction

---

### 8. ReportController (`app/Http/Controllers/Api/V1/ReportController.php`)

#### Transaction 1: `store()` - Report Creation (Line ~91)
- **Type:** `DB::transaction()` closure (conditional)
- **Conditional Logic:** Checks `DB::transactionLevel() > 0 || DB::connection()->getPdo()->inTransaction()` before starting transaction
- **Purpose:** Create report atomically and check moderation threshold
- **Lines:** 88-92
- **Methods:** `store()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Reports/ReportTest.php`
    - `it_creates_report_for_post()`
    - `it_rejects_reporting_own_content()`
    - `it_prevents_duplicate_reports_within_24_hours()`
    - `it_enforces_daily_report_limit()`
    - `it_adds_post_to_moderation_queue_on_threshold()`
    - `it_validates_report_reason()`
- **Potential Nested Transaction Scenarios:** No - uses conditional logic to prevent nesting

---

## Services

### 1. ModerationService (`app/Services/ModerationService.php`)

#### Transaction 1: `approve()` - Approve Content (Line ~109)
- **Type:** `DB::transaction()` closure (conditional)
- **Conditional Logic:** Checks `DB::transactionLevel() > 0 || DB::connection()->getPdo()->inTransaction()` before starting transaction
- **Purpose:** Approve moderation queue item and update post atomically
- **Lines:** 107-110
- **Methods:** `approve()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Admin/ModerationAdminTest.php`
    - Various moderation approval tests
- **Potential Nested Transaction Scenarios:** No - uses conditional logic to prevent nesting

#### Transaction 2: `reject()` - Reject Content (Line ~174)
- **Type:** `DB::transaction()` closure (conditional)
- **Conditional Logic:** Checks `DB::transactionLevel() > 0 || DB::connection()->getPdo()->inTransaction()` before starting transaction
- **Purpose:** Reject moderation queue item and hide post atomically
- **Lines:** 172-175
- **Methods:** `reject()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Admin/ModerationAdminTest.php`
    - Various moderation rejection tests
- **Potential Nested Transaction Scenarios:** No - uses conditional logic to prevent nesting

#### Transaction 3: `remove()` - Remove Content (Line ~214)
- **Type:** `DB::transaction()` closure (conditional)
- **Conditional Logic:** Checks `DB::transactionLevel() > 0 || DB::connection()->getPdo()->inTransaction()` before starting transaction
- **Purpose:** Remove moderation queue item and delete post atomically
- **Lines:** 212-215
- **Methods:** `remove()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Admin/ModerationAdminTest.php`
    - Various moderation removal tests
- **Potential Nested Transaction Scenarios:** No - uses conditional logic to prevent nesting

---

## Jobs

### 1. GenerateImageJob (`app/Jobs/GenerateImageJob.php`)

#### Transaction 1: `consumeTokens()` - Token Consumption (Line 264)
- **Type:** `DB::transaction()` closure (unconditional)
- **Purpose:** Create token transaction record atomically
- **Lines:** 264-280
- **Methods:** `consumeTokens()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Generation/QueueJobProcessingTest.php`
    - `it_processes_image_generation_job_successfully()`
    - `it_handles_image_generation_failure_and_refunds_tokens()`
- **Potential Nested Transaction Scenarios:** ⚠️ **YES** - Uses `DB::transaction()` unconditionally. Jobs run synchronously in tests (`QUEUE_CONNECTION=sync`), so if called from within a test transaction, will nest

#### Transaction 2: `handleFailure()` - Failure Handling (Line 289)
- **Type:** `DB::transaction()` closure (unconditional)
- **Purpose:** Update job status to failed atomically
- **Lines:** 289-305
- **Methods:** `handleFailure()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Generation/QueueJobProcessingTest.php`
    - `it_handles_image_generation_failure_and_refunds_tokens()`
- **Potential Nested Transaction Scenarios:** ⚠️ **YES** - Uses `DB::transaction()` unconditionally

---

### 2. GenerateVideoJob (`app/Jobs/GenerateVideoJob.php`)

#### Transaction 1: `consumeTokens()` - Token Consumption (Line 143)
- **Type:** `DB::transaction()` closure (unconditional)
- **Purpose:** Create token transaction record atomically
- **Lines:** 143-150
- **Methods:** `consumeTokens()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Generation/QueueJobProcessingTest.php`
    - `it_processes_video_generation_job_successfully()`
- **Potential Nested Transaction Scenarios:** ⚠️ **YES** - Uses `DB::transaction()` unconditionally

#### Transaction 2: `handleFailure()` - Failure Handling (Line 157)
- **Type:** `DB::transaction()` closure (unconditional)
- **Purpose:** Update job status to failed atomically
- **Lines:** 157-165
- **Methods:** `handleFailure()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Generation/QueueJobProcessingTest.php`
    - (Failure scenarios in video generation)
- **Potential Nested Transaction Scenarios:** ⚠️ **YES** - Uses `DB::transaction()` unconditionally

---

### 3. GenerateAudioJob (`app/Jobs/GenerateAudioJob.php`)

#### Transaction 1: `consumeTokens()` - Token Consumption (Line 138)
- **Type:** `DB::transaction()` closure (unconditional)
- **Purpose:** Create token transaction record atomically
- **Lines:** 138-145
- **Methods:** `consumeTokens()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Generation/QueueJobProcessingTest.php`
    - `it_processes_audio_generation_job_successfully()`
- **Potential Nested Transaction Scenarios:** ⚠️ **YES** - Uses `DB::transaction()` unconditionally

#### Transaction 2: `handleFailure()` - Failure Handling (Line 152)
- **Type:** `DB::transaction()` closure (unconditional)
- **Purpose:** Update job status to failed atomically
- **Lines:** 152-160
- **Methods:** `handleFailure()`
- **Tests Exercising:**
  - `test/backend_test/laravel/Feature/Generation/QueueJobProcessingTest.php`
    - (Failure scenarios in audio generation)
- **Potential Nested Transaction Scenarios:** ⚠️ **YES** - Uses `DB::transaction()` unconditionally

---

## Summary Statistics

### Transaction Type Distribution

| Type | Count | Risk Level |
|------|-------|------------|
| `DB::beginTransaction()` (unconditional) | 10 | ⚠️ **HIGH** - Will fail in SQLite if already in transaction |
| `DB::transaction()` (conditional) | 11 | ✅ **LOW** - Checks for existing transaction first |
| `DB::transaction()` (unconditional) | 9 | ⚠️ **MEDIUM** - Jobs (may nest if called from test transaction) |

### Risk Analysis

#### High Risk (10 transactions)
These use `DB::beginTransaction()` unconditionally and will fail in SQLite if already in a transaction:
- `GalleryPostController::store()` (Line 59)
- `GalleryPostController::update()` (Line 154)
- `GenerationController::generateImage()` (Line 70)
- `GenerationController::generateVideo()` (Line 154)
- `GenerationController::generateAudio()` (Line 211)
- `GenerationJobController::cancel()` (Line 113)
- `GenerationJobController::retry()` (Line 179)
- `AdminGalleryController::curate()` (Line 46)
- `AdminGalleryController::uncurate()` (Line 112)
- `AdminGalleryController::curateBatch()` (Line 269)
- `AdminGalleryController::uncurateBatch()` (Line 331)

**Status:** ✅ **FIXED** - These are now safe because `LazilyRefreshDatabase` is used instead of `RefreshDatabase`, so tests don't wrap methods in transactions.

#### Medium Risk (9 transactions)
These use `DB::transaction()` unconditionally in jobs, which may nest if jobs are executed synchronously within test transactions:
- All job `consumeTokens()` and `handleFailure()` methods

**Status:** ✅ **FIXED** - Jobs run synchronously in tests, but `LazilyRefreshDatabase` doesn't wrap tests in transactions, so no nesting occurs.

#### Low Risk (11 transactions)
These check for existing transactions before starting new ones:
- `OrderController::purchase()` (Line 74)
- `OrderController::callback()` (Line 312)
- `CommentController::store()` (Line 63)
- `CommentController::destroy()` (Line 173)
- `LikeController::store()` (Line 55)
- `LikeController::destroy()` (Line 161)
- `ReportController::store()` (Line 91)
- `ModerationService::approve()` (Line 109)
- `ModerationService::reject()` (Line 174)
- `ModerationService::remove()` (Line 214)

**Status:** ✅ **SAFE** - These already have proper conditional logic to prevent nesting.

---

## Test Coverage

All identified transactions are exercised by tests:

- ✅ OrderController transactions: Covered by `PurchaseTest.php` and `CallbackTest.php`
- ✅ GalleryPostController transactions: Covered by `GalleryPostTest.php` and `GalleryPostManagementTest.php`
- ✅ CommentController transactions: Covered by `CommentTest.php`, `CommentDeleteTest.php`, and `CommentAccuracyTest.php`
- ✅ LikeController transactions: Covered by `LikeTest.php`
- ✅ GenerationController transactions: Covered by `ImageGenerationTest.php` and `VideoAudioGenerationTest.php`
- ✅ GenerationJobController transactions: Covered by `JobStatusTest.php`
- ✅ AdminGalleryController transactions: Covered by `GalleryAdminTest.php`
- ✅ ReportController transactions: Covered by `ReportTest.php`
- ✅ ModerationService transactions: Covered by `ModerationAdminTest.php`
- ✅ Job transactions: Covered by `QueueJobProcessingTest.php`

---

## Recommendations

1. ✅ **COMPLETED:** All high-risk transactions (`DB::beginTransaction()`) are now safe because `LazilyRefreshDatabase` replaced `RefreshDatabase`, eliminating per-method transaction wrapping in tests.

2. ✅ **COMPLETED:** Job transactions are safe because `LazilyRefreshDatabase` doesn't wrap tests in transactions, so jobs executed synchronously don't nest.

3. ✅ **NO ACTION NEEDED:** Low-risk transactions already have proper conditional logic and continue to work correctly.

---

**Document Completed:** 2025-12-30  
**Next Task:** A3 - Queue Job Transaction Analysis

