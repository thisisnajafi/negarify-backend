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
- [ ] Valid phone number sends OTP
- [ ] Invalid phone format validation
- [ ] Rate limiting (3 per 15 min)
- [ ] OTP storage structure
- [ ] Request ID generation
- [ ] Expiration time (5 minutes)
- [ ] Melipayamak service mocking
- [ ] Melipayamak failure handling

### OTP Verification Tests
- [ ] Valid OTP verification
- [ ] Invalid OTP handling
- [ ] Max attempts (3) enforcement
- [ ] Expired OTP rejection
- [ ] Already verified OTP rejection
- [ ] User creation on first verification
- [ ] User update on subsequent verification
- [ ] JWT token issuance
- [ ] Phone verified_at timestamp

### OTP Resend Tests
- [ ] Valid resend creates new OTP
- [ ] Previous OTP invalidated
- [ ] Rate limiting for resend
- [ ] Invalid request_id handling

### Logout Tests
- [ ] Token revocation
- [ ] Unauthenticated request handling

### Integration Tests
- [ ] Melipayamak contract test (request payload structure)

---

## 2. User Profile Tests

### Get Profile
- [ ] Returns authenticated user data
- [ ] Includes tokens_balance
- [ ] Unauthenticated request handling

### Update Profile
- [ ] Updates allowed fields
- [ ] Rejects forbidden fields
- [ ] Email validation
- [ ] Response structure

### Upload Avatar
- [ ] Valid image upload
- [ ] File storage (S3 fake)
- [ ] Invalid file type validation
- [ ] File size validation
- [ ] Old avatar deletion

---

## 3. Token Bundles & Currency Tests

### Token Bundles
- [ ] Returns active bundles
- [ ] Toman price calculation
- [ ] Only active bundles returned
- [ ] Display order
- [ ] Bonus tokens included

### Currency Rate
- [ ] Returns current rate
- [ ] Cache usage
- [ ] Response structure
- [ ] Public endpoint (no auth)

### TGJU Scraper Service Tests
- [ ] HTML parsing
- [ ] Rials to Toman conversion
- [ ] Database storage
- [ ] Redis caching (5-min TTL)
- [ ] Fallback mechanism
- [ ] HTML fixture usage

---

## 4. Zarinpal Payment Tests

### Purchase Request
- [ ] Creates pending order
- [ ] Price calculation (Toman)
- [ ] Dollar rate snapshot
- [ ] Zarinpal payment request
- [ ] Authority and payment_url returned
- [ ] Invalid bundle handling
- [ ] Inactive bundle rejection

### Payment Callback
- [ ] Successful payment verification
- [ ] Order status update (paid)
- [ ] Token credit
- [ ] Transaction creation
- [ ] Ref_id storage
- [ ] Idempotency (no double credit)
- [ ] Failed payment handling
- [ ] Invalid authority handling
- [ ] Mismatched amount handling

### Integration Tests
- [ ] Zarinpal contract test (request/response structure)

---

## 5. Token Transactions & Balance Tests

### Transaction History
- [ ] Returns user history
- [ ] Pagination
- [ ] Type filtering
- [ ] Date range filtering
- [ ] Ordering (created_at DESC)

### Token Balance
- [ ] Returns current balance
- [ ] Balance reconciliation (SUM of transactions)
- [ ] Accuracy verification

### Transaction Types
- [ ] Purchase transactions
- [ ] Consumption transactions
- [ ] Refund transactions
- [ ] Bonus transactions
- [ ] Adjustment transactions

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

