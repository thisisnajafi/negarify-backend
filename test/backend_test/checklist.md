# Negarify Backend Test Suite - Master Checklist

## Overview

This checklist defines all test areas, endpoints, edge cases, and acceptance criteria for the Negarify backend test suite.

---

## 1. Authentication & OTP (Melipayamak)

### Endpoints
- `POST /api/v1/auth/request-otp`
- `POST /api/v1/auth/verify-otp`
- `POST /api/v1/auth/resend-otp`
- `POST /api/v1/auth/logout`

### Test Cases

#### OTP Request (`requestOtp`)
- ✅ Valid phone number sends OTP via Melipayamak
- ✅ Invalid phone number format returns 422
- ✅ OTP stored in database with correct structure (code_hash, expires_at, request_id)
- ✅ Rate limiting: Max 3 requests per phone per 15 minutes
- ✅ Rate limit exceeded returns 429
- ✅ Only one active OTP per phone (previous invalidated)
- ✅ Request ID (UUID) generated and returned
- ✅ OTP expiration set to 5 minutes from creation
- ✅ Attempts initialized to 0
- ✅ Melipayamak service called with correct payload
- ✅ Melipayamak failure handled gracefully (logs error, returns generic message)

#### OTP Verification (`verifyOtp`)
- ✅ Valid OTP code verifies successfully
- ✅ Invalid OTP code increments attempts
- ✅ Max 3 attempts reached locks OTP (requires new request)
- ✅ Expired OTP returns error
- ✅ Already verified OTP cannot be reused
- ✅ Successful verification creates/updates user
- ✅ JWT token issued on successful verification
- ✅ Token stored in Sanctum tokens table
- ✅ Phone verified_at timestamp set
- ✅ User auto-created on first verification
- ✅ Existing user updated on subsequent verification

#### OTP Resend (`resendOtp`)
- ✅ Valid request_id creates new OTP
- ✅ Previous OTP invalidated
- ✅ New request_id generated
- ✅ Rate limiting applies (max 2 resends per 10 minutes)
- ✅ Invalid request_id returns 404

#### Logout (`logout`)
- ✅ Authenticated user can logout
- ✅ Token revoked from database
- ✅ Unauthenticated request returns 401

### Mocks/Fakes
- Melipayamak service mocked via `Http::fake()` or service binding
- Integration contract test validates request payload structure (no real network)

### Success Criteria
- All OTP flows work correctly
- Rate limiting prevents abuse
- Security measures enforced (expiration, attempt limits)
- User creation/authentication works
- No real SMS sent during tests

---

## 2. User Profile Management

### Endpoints
- `GET /api/v1/user`
- `PUT /api/v1/user`
- `POST /api/v1/user/avatar`

### Test Cases

#### Get Profile (`GET /user`)
- ✅ Returns current authenticated user data
- ✅ Includes tokens_balance field
- ✅ Unauthenticated request returns 401
- ✅ Response structure matches expected format

#### Update Profile (`PUT /user`)
- ✅ Updates allowed fields (name, email)
- ✅ Rejects forbidden fields (role, tokens_balance)
- ✅ Validates email format if provided
- ✅ Returns updated user data
- ✅ Unauthenticated request returns 401

#### Upload Avatar (`POST /user/avatar`)
- ✅ Valid image file uploads successfully
- ✅ File stored in Storage (S3 fake)
- ✅ Avatar URL returned in response
- ✅ Invalid file type returns 422
- ✅ File size validation (max size enforced)
- ✅ Old avatar deleted on new upload
- ✅ Unauthenticated request returns 401

### Mocks/Fakes
- `Storage::fake()` for avatar uploads

### Success Criteria
- Profile CRUD operations work
- File upload validation works
- Authorization enforced

---

## 3. Token Bundles & Currency Rate

### Endpoints
- `GET /api/v1/tokens/bundles`
- `GET /api/v1/currency/rate`

### Test Cases

#### Token Bundles (`GET /tokens/bundles`)
- ✅ Returns all active bundles
- ✅ Includes computed Toman price using cached rate
- ✅ Price calculation: `price_toman = price_usd * dollar_rate`
- ✅ Only active bundles returned (is_active = true)
- ✅ Bundles ordered by display_order
- ✅ Bonus tokens included in response

#### Currency Rate (`GET /currency/rate`)
- ✅ Returns current USD to Toman rate
- ✅ Rate fetched from cache (Redis) if available
- ✅ Rate structure includes: usd_to_toman, usd_to_rials, fetched_at, source
- ✅ Public endpoint (no auth required)

#### TGJU Scraper Service Tests
- ✅ Scrapes USD rate from TGJU.org HTML
- ✅ Converts Rials to Toman (divide by 10)
- ✅ Stores rate in database
- ✅ Caches rate in Redis with 5-minute TTL
- ✅ Fallback to last known rate if scraping fails
- ✅ HTML fixture parsing works correctly
- ✅ Handles HTML structure changes gracefully

### Mocks/Fakes
- HTML fixture for TGJU.org response
- Redis cache faked or mocked
- HTTP client faked for scraper

### Success Criteria
- Bundles returned with correct pricing
- Currency rate fetching and caching works
- Fallback mechanism handles failures

---

## 4. Zarinpal Payment Integration

### Endpoints
- `POST /api/v1/tokens/purchase`
- `GET /api/v1/tokens/purchase/callback`

### Test Cases

#### Purchase Request (`POST /tokens/purchase`)
- ✅ Creates pending order with correct data
- ✅ Calculates price in Toman using current rate
- ✅ Stores dollar_rate snapshot in order
- ✅ Requests payment from Zarinpal
- ✅ Returns authority and payment_url
- ✅ Unauthenticated request returns 401
- ✅ Invalid bundle_id returns 404
- ✅ Inactive bundle cannot be purchased

#### Payment Callback (`GET /tokens/purchase/callback`)
- ✅ Successful payment verifies with Zarinpal
- ✅ Order status updated to 'paid'
- ✅ Tokens credited to user balance
- ✅ Token transaction created (type='purchase')
- ✅ Zarinpal ref_id stored in order
- ✅ paid_at timestamp set
- ✅ Idempotency: Same authority/ref_id cannot credit twice
- ✅ Failed payment updates order status to 'failed'
- ✅ Invalid authority returns error
- ✅ Mismatched amount returns error
- ✅ Missing callback parameters handled

### Mocks/Fakes
- Zarinpal API mocked via `Http::fake()`
- Integration contract test validates request/response structure

### Success Criteria
- Payment flow works end-to-end
- Idempotency prevents double-crediting
- Token credit accuracy verified
- Error cases handled correctly

---

## 5. Token Transactions & Balance

### Endpoints
- `GET /api/v1/tokens/history`
- `GET /api/v1/tokens/balance`

### Test Cases

#### Transaction History (`GET /tokens/history`)
- ✅ Returns user's transaction history
- ✅ Pagination works (default 15 per page)
- ✅ Filters by type (purchase, consume, refund, bonus, adjustment)
- ✅ Filters by date range
- ✅ Ordered by created_at DESC
- ✅ Unauthenticated request returns 401

#### Token Balance (`GET /tokens/balance`)
- ✅ Returns current token balance
- ✅ Balance matches sum of transactions
- ✅ Reconciliation check: balance = SUM(transactions.amount_tokens)
- ✅ Unauthenticated request returns 401

#### Transaction Types
- ✅ Purchase transactions (positive amount)
- ✅ Consumption transactions (negative amount)
- ✅ Refund transactions (negative amount, links to order)
- ✅ Bonus transactions (positive amount, no order_id)
- ✅ Adjustment transactions (admin adjustments)

### Success Criteria
- History pagination and filtering work
- Balance calculation accurate
- Transaction ledger consistency verified

---

## 6. Unified Generation Jobs (Image/Video/Audio)

### Endpoints
- `POST /api/v1/generate/image`
- `POST /api/v1/generate/video`
- `POST /api/v1/generate/audio`
- `GET /api/v1/generate/jobs`
- `GET /api/v1/generate/jobs/{id}`
- `POST /api/v1/generate/jobs/{id}/cancel`
- `POST /api/v1/generate/jobs/{id}/retry`

### Test Cases

#### Generation Request (Image/Video/Audio)
- ✅ Valid request creates generation job
- ✅ Parameters validated (prompt, model_id, size, etc.)
- ✅ Token balance checked before job creation
- ✅ Insufficient tokens returns 400
- ✅ Tokens reserved (deducted from balance) on job creation
- ✅ Job created with status='pending'
- ✅ Job queued in Redis queue
- ✅ Unauthenticated request returns 401
- ✅ Invalid model_id returns 404
- ✅ Disabled model cannot be used

#### Job Status (`GET /generate/jobs/{id}`)
- ✅ Returns job details with current status
- ✅ Status transitions: pending → processing → completed/failed
- ✅ Progress tracking for long jobs
- ✅ Result URL available when completed
- ✅ Error message included when failed
- ✅ User can only access own jobs

#### Job Listing (`GET /generate/jobs`)
- ✅ Returns user's jobs with pagination
- ✅ Filters by type (image/video/audio)
- ✅ Filters by status (pending/processing/completed/failed/cancelled)
- ✅ Filters by date range
- ✅ Ordered by created_at DESC

#### Job Cancellation (`POST /generate/jobs/{id}/cancel`)
- ✅ Pending job can be cancelled
- ✅ Cancelled job status updated
- ✅ Reserved tokens refunded
- ✅ Processing job cannot be cancelled (or handled gracefully)
- ✅ Already completed job cannot be cancelled

#### Job Retry (`POST /generate/jobs/{id}/retry`)
- ✅ Failed job can be retried
- ✅ New job created with same parameters
- ✅ Tokens reserved again
- ✅ Completed job cannot be retried

#### Queue Job Processing
- ✅ GenerateImageJob processes image generation
- ✅ GenerateVideoJob processes video generation
- ✅ GenerateAudioJob processes audio generation
- ✅ Segmind API called with correct parameters
- ✅ Generated content downloaded
- ✅ Content stored in S3 (fake)
- ✅ Thumbnail generated for images/videos
- ✅ Job status updated to 'completed' on success
- ✅ Tokens consumed (transaction created) on success
- ✅ Job status updated to 'failed' on error
- ✅ Reserved tokens refunded on failure
- ✅ Retry logic works (max 3 attempts)
- ✅ Idempotency: Same job cannot process twice

### Mocks/Fakes
- Segmind API mocked via `Http::fake()`
- Storage faked for S3 operations
- Queue faked (jobs asserted, not executed)
- Thumbnail generation tested with image fixtures

### Success Criteria
- Generation flow works end-to-end
- Token reservation → consumption → refund lifecycle correct
- Job status tracking accurate
- Queue jobs process correctly
- Error handling robust

---

## 7. Gallery & Feed System

### Endpoints
- `POST /api/v1/gallery/post`
- `GET /api/v1/gallery/posts/{id}`
- `PUT /api/v1/gallery/posts/{id}`
- `DELETE /api/v1/gallery/posts/{id}`
- `GET /api/v1/gallery/my-posts`
- `GET /api/v1/gallery/feed`
- `POST /api/v1/gallery/feed/copy-prompt`
- `POST /api/v1/gallery/feed/copy-model`

### Test Cases

#### Gallery Post Creation (`POST /gallery/post`)
- ✅ Creates post from generation job
- ✅ Validates generation_job_id exists and belongs to user
- ✅ Title, description, tags stored correctly
- ✅ Visibility settings (public/private) work
- ✅ Prompt/model visibility flags stored
- ✅ Tags stored as JSON array
- ✅ Unauthenticated request returns 401
- ✅ Invalid generation_job_id returns 404
- ✅ Cannot create duplicate post from same job

#### Gallery Post CRUD
- ✅ Get post details returns correct data
- ✅ Update post modifies allowed fields
- ✅ Delete post removes from database
- ✅ User can only access own posts (unless public)
- ✅ Prompt/model visibility respected in responses

#### My Posts (`GET /gallery/my-posts`)
- ✅ Returns user's own posts
- ✅ Pagination works
- ✅ Filters by visibility
- ✅ Ordered by created_at DESC

#### Public Feed (`GET /gallery/feed`)
- ✅ Returns only curated posts (is_curated = true)
- ✅ Only images and videos (no audio)
- ✅ View limits decremented on feed access
- ✅ Daily limit reset works (scheduled task)
- ✅ View limit reached returns appropriate message
- ✅ Admins have unlimited views
- ✅ Cursor-based pagination works
- ✅ Feed cached in Redis

#### Copy Prompt/Model
- ✅ Copy prompt returns prompt if prompt_visible = true
- ✅ Copy prompt returns 403 if prompt_visible = false
- ✅ Copy model returns model info if model_visible = true
- ✅ Copy model returns 403 if model_visible = false
- ✅ Only works for curated posts

### Success Criteria
- Gallery CRUD operations work
- Feed shows only curated content
- View limits enforced
- Privacy settings respected

---

## 8. Social Features (Likes & Comments)

### Endpoints
- `POST /api/v1/gallery/{id}/like`
- `DELETE /api/v1/gallery/{id}/like`
- `POST /api/v1/gallery/{id}/comment`
- `DELETE /api/v1/gallery/comments/{id}`

### Test Cases

#### Likes
- ✅ Like post increments likes_count
- ✅ Unlike post decrements likes_count
- ✅ Cannot like own post (or allowed, based on requirements)
- ✅ Duplicate like prevented (idempotent)
- ✅ Likes_count accurate

#### Comments
- ✅ Create comment on post
- ✅ Nested comments (replies) work
- ✅ Comments_count accurate
- ✅ Delete comment decrements count
- ✅ User can only delete own comments (or admin)
- ✅ Comment body validated (not empty, max length)

### Success Criteria
- Likes and comments work correctly
- Counts updated accurately
- Nested comments supported

---

## 9. Admin Dashboards

### Endpoints
- `GET /api/v1/admin/sales/summary`
- `GET /api/v1/admin/users/summary`
- `GET /api/v1/admin/users/list`
- `GET /api/v1/admin/models/usage`
- `GET /api/v1/admin/tokens/summary`
- `GET /api/v1/admin/cost-profit/summary`
- `GET /api/v1/admin/system-health`
- `POST /api/v1/admin/gallery/{id}/curate`
- `POST /api/v1/admin/gallery/{id}/uncurate`
- `GET /api/v1/admin/gallery/curated`

### Test Cases

#### RBAC Enforcement
- ✅ All admin endpoints require admin role
- ✅ Non-admin users get 403 Forbidden
- ✅ Unauthenticated users get 401
- ✅ Admin middleware works correctly

#### Sales Dashboard
- ✅ Returns revenue by day/week/month
- ✅ Total revenue calculated correctly (only paid orders)
- ✅ Top selling bundles returned
- ✅ Refunds tracked separately
- ✅ Date range filtering works
- ✅ Response structure matches expected format

#### Users Dashboard
- ✅ DAU/WAU/MAU calculated correctly
- ✅ Top users by generation count
- ✅ Top users by spending
- ✅ Cohort analysis data returned
- ✅ User search and filtering works

#### Models Usage Dashboard
- ✅ Usage statistics per model
- ✅ Success/failure rates calculated
- ✅ Average latency per model
- ✅ Tokens consumed per model
- ✅ Cost and revenue per model
- ✅ Date range filtering works

#### Token Analytics Dashboard
- ✅ Token consumption by provider
- ✅ Token consumption by type (image/video/audio)
- ✅ Cost and profit per provider
- ✅ Time-series data returned

#### Cost & Profit Dashboard
- ✅ Total revenue, cost, and profit calculated
- ✅ Profit margins calculated correctly
- ✅ Breakdown by model and provider
- ✅ Historical comparison data

#### System Health Dashboard
- ✅ Queue length returned
- ✅ Worker status returned
- ✅ Failed jobs count
- ✅ Error rate calculated
- ✅ API latency metrics
- ✅ Storage usage (if available)

#### Feed Management
- ✅ Curate post sets is_curated = true
- ✅ Uncurate post sets is_curated = false
- ✅ Bulk curation actions work
- ✅ Curated posts listing works

### Success Criteria
- All admin endpoints protected
- Analytics data accurate
- Date filtering works
- No N+1 queries (query count assertions)

---

## 10. Security & Middleware

### Test Cases

#### Authentication Middleware
- ✅ Protected routes require authentication
- ✅ Invalid token returns 401
- ✅ Expired token returns 401
- ✅ Missing token returns 401

#### Admin Middleware
- ✅ Admin routes require admin role
- ✅ Regular users cannot access admin endpoints
- ✅ Moderator role (if exists) has appropriate access

#### Rate Limiting
- ✅ OTP request rate limiting works
- ✅ Generation request rate limiting (if implemented)
- ✅ API rate limiting (if implemented)

#### Input Validation
- ✅ SQL injection attempts fail
- ✅ XSS attempts sanitized
- ✅ Invalid input returns 422 with validation errors

### Success Criteria
- All security measures enforced
- Middleware works correctly
- Input validation robust

---

## 11. Observability & Smoke Tests

### Test Cases

#### Route Registration
- ✅ All routes registered correctly
- ✅ Route returns 401/403 instead of 500 for auth errors
- ✅ Route returns 404 for non-existent endpoints
- ✅ Route parameters validated

#### Error Handling
- ✅ Server errors return proper status codes
- ✅ Error messages don't expose sensitive data
- ✅ Stack traces captured in logs (not in responses)
- ✅ Validation errors return 422 with details

#### Logging
- ✅ All errors logged
- ✅ No secrets in logs (API keys, tokens, passwords)
- ✅ Structured logging format
- ✅ Log levels appropriate (INFO, WARNING, ERROR)

#### Queue Failures
- ✅ Failed jobs logged
- ✅ Failed jobs table updated
- ✅ No failed jobs after successful test (unless testing failure)

### Success Criteria
- All routes accessible
- Errors handled gracefully
- Logging comprehensive
- No hidden errors

---

## Test Coverage Goals

- **Line Coverage**: > 80%
- **Branch Coverage**: > 75%
- **Critical Paths**: 100% (auth, payments, token operations)
- **Admin Endpoints**: 100%
- **Generation Jobs**: 100%

---

## Notes

- All tests use `RefreshDatabase` for clean state
- All tests use deterministic time (`Carbon::setTestNow()`)
- All external services mocked/faked
- All tests produce per-test-file logs
- All tests capture and surface errors

