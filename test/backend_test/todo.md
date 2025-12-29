# Negarify Backend Test Suite - Live TODO Tracker

This file tracks the implementation progress of the test suite. Items are checked off as tests are implemented and verified.

---

## Infrastructure Setup

- [x] Create folder structure
- [x] Create README.md
- [x] Create checklist.md
- [x] Create todo.md (this file)
- [ ] Create base TestCase with logging
- [ ] Create per-test-file logging helper trait
- [ ] Create test strategy document
- [ ] Create coverage map
- [ ] Create observability plan
- [ ] Update phpunit.xml for backend_test suite

---

## 1. Authentication & OTP Tests

### OTP Request Tests
- [x] Valid phone number sends OTP
- [x] Invalid phone format validation
- [x] Rate limiting (3 per 15 min)
- [x] OTP storage structure
- [x] Request ID generation
- [x] Expiration time (5 minutes)
- [x] Melipayamak service mocking
- [x] Melipayamak failure handling

### OTP Verification Tests
- [x] Valid OTP verification
- [x] Invalid OTP handling
- [x] Max attempts (3) enforcement
- [x] Expired OTP rejection
- [x] Already verified OTP rejection
- [x] User creation on first verification
- [x] User update on subsequent verification
- [x] JWT token issuance
- [x] Phone verified_at timestamp

### OTP Resend Tests
- [x] Valid resend creates new OTP
- [x] Previous OTP invalidated
- [x] Rate limiting for resend
- [x] Invalid request_id handling

### Logout Tests
- [x] Token revocation
- [x] Unauthenticated request handling

### Integration Tests
- [ ] Melipayamak contract test (request payload structure)

---

## 2. User Profile Tests

### Get Profile
- [x] Returns authenticated user data
- [x] Includes tokens_balance
- [x] Unauthenticated request handling

### Update Profile
- [x] Updates allowed fields
- [x] Rejects forbidden fields
- [x] Email validation
- [x] Response structure

### Upload Avatar
- [x] Valid image upload
- [x] File storage (S3 fake)
- [x] Invalid file type validation
- [x] File size validation
- [x] Old avatar deletion

---

## 3. Token Bundles & Currency Tests

### Token Bundles
- [x] Returns active bundles
- [x] Toman price calculation
- [x] Only active bundles returned
- [x] Display order
- [x] Bonus tokens included

### Currency Rate
- [x] Returns current rate
- [x] Cache usage
- [x] Response structure
- [x] Public endpoint (no auth)

### TGJU Scraper Service Tests
- [x] HTML parsing
- [x] Rials to Toman conversion
- [x] Database storage
- [x] Redis caching (5-min TTL)
- [x] Fallback mechanism
- [x] HTML fixture usage

---

## 4. Zarinpal Payment Tests

### Purchase Request
- [x] Creates pending order
- [x] Price calculation (Toman)
- [x] Dollar rate snapshot
- [x] Zarinpal payment request
- [x] Authority and payment_url returned
- [x] Invalid bundle handling
- [x] Inactive bundle rejection

### Payment Callback
- [x] Successful payment verification
- [x] Order status update (paid)
- [x] Token credit
- [x] Transaction creation
- [x] Ref_id storage
- [x] Idempotency (no double credit)
- [x] Failed payment handling
- [x] Invalid authority handling
- [x] Mismatched amount handling

### Integration Tests
- [ ] Zarinpal contract test (request/response structure)

---

## 5. Token Transactions & Balance Tests

### Transaction History
- [x] Returns user history
- [x] Pagination
- [x] Type filtering
- [x] Date range filtering
- [x] Ordering (created_at DESC)

### Token Balance
- [x] Returns current balance
- [x] Balance reconciliation (SUM of transactions)
- [x] Accuracy verification

### Transaction Types
- [x] Purchase transactions
- [x] Consumption transactions
- [x] Refund transactions
- [x] Bonus transactions
- [x] Adjustment transactions

---

## 6. Generation Jobs Tests

### Generation Request (Image/Video/Audio)
- [x] Valid request creates job
- [x] Parameter validation
- [x] Token balance check
- [x] Insufficient tokens handling
- [x] Token reservation
- [x] Job creation (status=pending)
- [x] Job queuing
- [x] Invalid model handling
- [x] Disabled model rejection

### Job Status & Listing
- [x] Job details returned
- [x] Status transitions
- [x] Progress tracking
- [x] Result URL availability
- [x] Error message inclusion
- [x] User access control (own jobs only)
- [x] Job listing with filters
- [x] Pagination

### Job Cancellation & Retry
- [x] Pending job cancellation
- [x] Token refund on cancellation
- [x] Processing job handling
- [x] Failed job retry
- [x] Completed job handling

### Queue Job Processing
- [x] GenerateImageJob processing
- [x] GenerateVideoJob processing
- [x] GenerateAudioJob processing
- [x] Segmind API mocking
- [x] Content download
- [x] S3 storage (fake)
- [x] Thumbnail generation
- [x] Status updates
- [x] Token consumption on success
- [x] Token refund on failure
- [x] Retry logic
- [x] Idempotency

---

## 7. Gallery & Feed Tests

### Gallery Post CRUD
- [x] Post creation from generation job
- [x] Generation job validation
- [x] Title, description, tags storage
- [x] Visibility settings
- [x] Prompt/model visibility flags
- [x] Get post details
- [x] Update post
- [x] Delete post
- [x] User access control
- [x] Privacy settings

### My Posts
- [x] Returns user's posts
- [x] Pagination
- [x] Visibility filtering (visibility field included in response)
- [x] Ordering

### Public Feed
- [x] Only curated posts
- [x] Images/videos only (no audio)
- [x] View limits decrement
- [x] Daily limit reset
- [x] Limit reached handling
- [x] Admin unlimited views
- [x] Cursor pagination
- [x] Redis caching

### Copy Prompt/Model
- [x] Copy prompt (if visible)
- [x] Copy prompt (if hidden)
- [x] Copy model (if visible)
- [x] Copy model (if hidden)
- [x] Only for curated posts

---

## 8. Social Features Tests

### Likes
- [x] Like post increments count
- [x] Unlike post decrements count
- [x] Own post handling
- [x] Duplicate like prevention
- [x] Count accuracy

### Comments
- [x] Create comment
- [x] Nested comments (replies)
- [x] Comments_count accuracy
- [x] Delete comment
- [x] User access control
- [x] Comment validation

---

## 9. Admin Dashboard Tests

### RBAC Enforcement
- [x] Admin endpoints require admin role
- [x] Non-admin 403 response
- [x] Unauthenticated 401 response
- [x] Admin middleware works

### Sales Dashboard
- [x] Revenue by period
- [x] Total revenue calculation
- [x] Top selling bundles
- [x] Refunds tracking
- [x] Date range filtering
- [x] Response structure

### Users Dashboard
- [x] DAU/WAU/MAU calculation
- [x] Top users by generation
- [x] Top users by spending
- [x] Cohort analysis
- [x] User search/filtering

### Models Usage Dashboard
- [x] Usage statistics per model
- [x] Success/failure rates
- [x] Average latency
- [x] Tokens consumed
- [x] Cost and revenue
- [x] Date filtering

### Token Analytics Dashboard
- [x] Consumption by provider
- [x] Consumption by type
- [x] Cost and profit per provider
- [x] Time-series data

### Cost & Profit Dashboard
- [x] Revenue, cost, profit calculation
- [x] Profit margins
- [x] Breakdown by model/provider
- [x] Historical comparison

### System Health Dashboard
- [x] Queue length
- [x] Worker status
- [x] Failed jobs count
- [x] Error rate
- [x] API latency
- [x] Storage usage

### Feed Management
- [ ] Curate post
- [ ] Uncurate post
- [ ] Bulk curation
- [ ] Curated posts listing

---

## 10. Security & Middleware Tests

### Authentication Middleware
- [ ] Protected routes require auth
- [ ] Invalid token handling
- [ ] Expired token handling
- [ ] Missing token handling

### Admin Middleware
- [ ] Admin role required
- [ ] Regular user rejection
- [ ] Moderator access (if exists)

### Rate Limiting
- [ ] OTP request limiting
- [ ] Generation request limiting
- [ ] API rate limiting

### Input Validation
- [ ] SQL injection prevention
- [ ] XSS prevention
- [ ] Invalid input handling

---

## 11. Observability & Smoke Tests

### Route Registration
- [ ] All routes registered
- [ ] Auth errors return 401/403 (not 500)
- [ ] 404 for non-existent endpoints
- [ ] Route parameter validation

### Error Handling
- [ ] Proper status codes
- [ ] No sensitive data exposure
- [ ] Stack traces in logs only
- [ ] Validation error details

### Logging
- [ ] All errors logged
- [ ] No secrets in logs
- [ ] Structured logging
- [ ] Appropriate log levels

### Queue Failures
- [ ] Failed jobs logged
- [ ] Failed jobs table updated
- [ ] No failed jobs after success (unless testing failure)

---

## Test Fixtures

- [ ] TGJU HTML sample
- [ ] Segmind image response sample
- [ ] Segmind video response sample
- [ ] Segmind audio response sample
- [ ] Zarinpal payment response sample

---

## Documentation

- [x] README.md
- [x] checklist.md
- [x] todo.md
- [ ] test_strategy.md
- [ ] coverage_map.md
- [ ] observability.md

---

## Final Verification

- [ ] All tests passing
- [ ] All logs generated
- [ ] Coverage > 80%
- [ ] No hidden errors
- [ ] CI/CD ready

