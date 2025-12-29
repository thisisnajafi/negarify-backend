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
- [ ] Valid request creates job
- [ ] Parameter validation
- [ ] Token balance check
- [ ] Insufficient tokens handling
- [ ] Token reservation
- [ ] Job creation (status=pending)
- [ ] Job queuing
- [ ] Invalid model handling
- [ ] Disabled model rejection

### Job Status & Listing
- [ ] Job details returned
- [ ] Status transitions
- [ ] Progress tracking
- [ ] Result URL availability
- [ ] Error message inclusion
- [ ] User access control (own jobs only)
- [ ] Job listing with filters
- [ ] Pagination

### Job Cancellation & Retry
- [ ] Pending job cancellation
- [ ] Token refund on cancellation
- [ ] Processing job handling
- [ ] Failed job retry
- [ ] Completed job handling

### Queue Job Processing
- [ ] GenerateImageJob processing
- [ ] GenerateVideoJob processing
- [ ] GenerateAudioJob processing
- [ ] Segmind API mocking
- [ ] Content download
- [ ] S3 storage (fake)
- [ ] Thumbnail generation
- [ ] Status updates
- [ ] Token consumption on success
- [ ] Token refund on failure
- [ ] Retry logic
- [ ] Idempotency

---

## 7. Gallery & Feed Tests

### Gallery Post CRUD
- [ ] Post creation from generation job
- [ ] Generation job validation
- [ ] Title, description, tags storage
- [ ] Visibility settings
- [ ] Prompt/model visibility flags
- [ ] Get post details
- [ ] Update post
- [ ] Delete post
- [ ] User access control
- [ ] Privacy settings

### My Posts
- [ ] Returns user's posts
- [ ] Pagination
- [ ] Visibility filtering
- [ ] Ordering

### Public Feed
- [ ] Only curated posts
- [ ] Images/videos only (no audio)
- [ ] View limits decrement
- [ ] Daily limit reset
- [ ] Limit reached handling
- [ ] Admin unlimited views
- [ ] Cursor pagination
- [ ] Redis caching

### Copy Prompt/Model
- [ ] Copy prompt (if visible)
- [ ] Copy prompt (if hidden)
- [ ] Copy model (if visible)
- [ ] Copy model (if hidden)
- [ ] Only for curated posts

---

## 8. Social Features Tests

### Likes
- [ ] Like post increments count
- [ ] Unlike post decrements count
- [ ] Own post handling
- [ ] Duplicate like prevention
- [ ] Count accuracy

### Comments
- [ ] Create comment
- [ ] Nested comments (replies)
- [ ] Comments_count accuracy
- [ ] Delete comment
- [ ] User access control
- [ ] Comment validation

---

## 9. Admin Dashboard Tests

### RBAC Enforcement
- [ ] Admin endpoints require admin role
- [ ] Non-admin 403 response
- [ ] Unauthenticated 401 response
- [ ] Admin middleware works

### Sales Dashboard
- [ ] Revenue by period
- [ ] Total revenue calculation
- [ ] Top selling bundles
- [ ] Refunds tracking
- [ ] Date range filtering
- [ ] Response structure

### Users Dashboard
- [ ] DAU/WAU/MAU calculation
- [ ] Top users by generation
- [ ] Top users by spending
- [ ] Cohort analysis
- [ ] User search/filtering

### Models Usage Dashboard
- [ ] Usage statistics per model
- [ ] Success/failure rates
- [ ] Average latency
- [ ] Tokens consumed
- [ ] Cost and revenue
- [ ] Date filtering

### Token Analytics Dashboard
- [ ] Consumption by provider
- [ ] Consumption by type
- [ ] Cost and profit per provider
- [ ] Time-series data

### Cost & Profit Dashboard
- [ ] Revenue, cost, profit calculation
- [ ] Profit margins
- [ ] Breakdown by model/provider
- [ ] Historical comparison

### System Health Dashboard
- [ ] Queue length
- [ ] Worker status
- [ ] Failed jobs count
- [ ] Error rate
- [ ] API latency
- [ ] Storage usage

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

