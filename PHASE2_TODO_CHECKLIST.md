# Phase 2: Authentication & User Management - TODO Checklist

## Task 2.1: OTP Service (Melipayamak)

### Subtask 2.1.1: Create MelipayamakService Class
**Files to Create/Modify:**
- `app/Services/MelipayamakService.php` (update existing)
- `config/services.php` (add melipayamak config if missing)

**Dependencies:**
- Environment variables: `MELIPAYAMAK_USERNAME`, `MELIPAYAMAK_PASSWORD`, `MELIPAYAMAK_FROM`, `MELIPAYAMAK_BASE_URL`
- Package: `guzzlehttp/guzzle` (via Laravel HTTP facade)

**Acceptance Criteria:**
- Service class exists with proper namespace
- Configuration loaded from `config/services.php`
- Constructor injects config values
- Service is mockable for testing

**Security Risks & Mitigations:**
- Risk: Credentials exposed in code
- Mitigation: Store in `.env`, load via config, never log credentials

---

### Subtask 2.1.2: Implement SMS Sending Method
**Files to Create/Modify:**
- `app/Services/MelipayamakService.php` (add `sendOtp()` and `sendSms()` methods)

**Dependencies:**
- Melipayamak API endpoint (from config)
- HTTP client (Laravel HTTP facade)

**Acceptance Criteria:**
- `sendOtp(string $phone, string $code): bool` method implemented
- `sendSms(string $phone, string $message): bool` method implemented
- Proper error handling (try-catch)
- Returns boolean success status
- Logs SMS delivery attempts (without OTP code)

**Security Risks & Mitigations:**
- Risk: OTP code logged in error messages
- Mitigation: Never log `$code` parameter, only log phone hash and success/failure

---

### Subtask 2.1.3: Add Error Handling and Retry Logic
**Files to Create/Modify:**
- `app/Services/MelipayamakService.php` (add retry logic)

**Dependencies:**
- None (implement retry in service)

**Acceptance Criteria:**
- Retry up to 3 times on failure
- Exponential backoff (1s, 2s, 4s delays)
- Logs retry attempts
- Returns false after all retries exhausted

**Security Risks & Mitigations:**
- Risk: Infinite retry loops
- Mitigation: Hard limit of 3 retries, exponential backoff prevents hammering

---

### Subtask 2.1.4: Create OTP Generation Utility
**Files to Create/Modify:**
- `app/Services/OtpService.php` (new file - service for OTP generation logic)
- `app/Models/OtpVerification.php` (update `generate()` method)

**Dependencies:**
- None (use PHP's `random_int()`)

**Acceptance Criteria:**
- OTP code: 6-digit random (100000-999999)
- Cryptographically secure RNG (`random_int()`)
- OTP generation is testable (injectable RNG for tests)

**Security Risks & Mitigations:**
- Risk: Predictable OTP codes
- Mitigation: Use `random_int()` (cryptographically secure), never use `rand()` or `mt_rand()`

---

### Subtask 2.1.5: Implement OTP Hashing (HMAC)
**Files to Create/Modify:**
- `app/Models/OtpVerification.php` (update `generate()` to use HMAC)

**Dependencies:**
- Laravel's `Hash` facade (but use HMAC, not bcrypt)

**Acceptance Criteria:**
- OTP code hashed using HMAC-SHA256
- Hash stored in `code_hash` column
- Hash key: `config('app.key')` (Laravel app key)
- Format: `hash_hmac('sha256', $code, config('app.key'))`

**Security Risks & Mitigations:**
- Risk: OTP codes stored in plaintext
- Mitigation: Always hash before storage, never store plaintext
- Risk: Weak hashing (bcrypt too slow for OTPs)
- Mitigation: Use HMAC-SHA256 (fast, secure for short-lived codes)

---

### Subtask 2.1.6: Create OTP Verification Logic
**Files to Create/Modify:**
- `app/Models/OtpVerification.php` (update `verify()` method)

**Dependencies:**
- HMAC verification function

**Acceptance Criteria:**
- `verify(string $code): bool` method implemented
- Constant-time comparison (prevents timing attacks)
- Checks expiration before verification
- Checks attempt limit before verification
- Increments attempts atomically
- Sets `verified_at` on success

**Security Risks & Mitigations:**
- Risk: Timing attacks on code comparison
- Mitigation: Use `hash_equals()` for constant-time comparison
- Risk: Race conditions on attempt increment
- Mitigation: Use database atomic increment (`increment()`)

---

### Subtask 2.1.7: Add Rate Limiting Per Phone Number
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AuthController.php` (add rate limiting)

**Dependencies:**
- Laravel `RateLimiter` facade
- Redis (for distributed rate limiting)

**Acceptance Criteria:**
- OTP requests: Max 3 per 15 minutes per phone
- OTP resends: Max 2 per 10 minutes per phone
- Rate limit keys: `otp_request:{phone_hash}` and `otp_resend:{phone_hash}`
- Returns 429 status with retry-after header

**Security Risks & Mitigations:**
- Risk: Rate limit bypass via phone number variations
- Mitigation: Normalize phone numbers before rate limiting
- Risk: Distributed rate limiting not working
- Mitigation: Use Redis for rate limit storage (shared across servers)

---

### Subtask 2.1.8: Implement OTP Expiration (5 minutes)
**Files to Create/Modify:**
- `app/Models/OtpVerification.php` (already has `expires_at`, verify logic)

**Dependencies:**
- None (already in model)

**Acceptance Criteria:**
- OTP expires 5 minutes after creation
- `isExpired()` method checks `expires_at < now()`
- Expired OTPs rejected in verification

**Security Risks & Mitigations:**
- Risk: Clock skew causing premature expiration
- Mitigation: Use server time consistently, add small buffer if needed

---

### Subtask 2.1.9: Add Attempt Limiting (max 3 attempts)
**Files to Create/Modify:**
- `app/Models/OtpVerification.php` (already has `max_attempts`, verify logic)

**Dependencies:**
- None (already in model)

**Acceptance Criteria:**
- Max 3 verification attempts per OTP
- `hasExceededMaxAttempts()` method checks `attempts >= max_attempts`
- Attempts incremented atomically on each verification attempt
- OTP locked after max attempts (requires new request)

**Security Risks & Mitigations:**
- Risk: Race condition on attempt increment
- Mitigation: Use database atomic increment
- Risk: Attempts reset on code change
- Mitigation: Attempts tied to OTP record, not code

---

### Subtask 2.1.10: Create Cleanup Job for Expired OTPs
**Files to Create/Modify:**
- `app/Console/Commands/CleanupExpiredOtps.php` (new file)
- `app/Console/Kernel.php` (schedule cleanup job)

**Dependencies:**
- Laravel scheduler
- Cron job setup

**Acceptance Criteria:**
- Command deletes OTPs older than 1 hour
- Scheduled to run hourly
- Logs cleanup statistics (count deleted)
- Safe to run multiple times (idempotent)

**Security Risks & Mitigations:**
- Risk: OTPs never cleaned up (database bloat)
- Mitigation: Scheduled job ensures regular cleanup
- Risk: Deleting OTPs too early
- Mitigation: 1-hour retention (OTP expires in 5 min, cleanup after 1 hour)

**Test Coverage Expectations:**
- Unit test: OTP generation produces 6-digit code
- Unit test: OTP hashing uses HMAC-SHA256
- Unit test: OTP verification with correct code returns true
- Unit test: OTP verification with wrong code returns false
- Unit test: Expired OTP rejected
- Unit test: Max attempts exceeded rejected
- Feature test: Rate limiting enforced
- Feature test: Cleanup job deletes old OTPs

---

## Task 2.2: OTP Authentication Endpoints

### Subtask 2.2.1: Create AuthController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AuthController.php` (move/refactor existing)
- `app/Http/Requests/Api/V1/RequestOtpRequest.php` (new - Form Request)
- `app/Http/Requests/Api/V1/VerifyOtpRequest.php` (new - Form Request)
- `app/Http/Requests/Api/V1/ResendOtpRequest.php` (new - Form Request)

**Dependencies:**
- MelipayamakService (injected)
- OtpVerification model
- User model
- Sanctum (for token generation)

**Acceptance Criteria:**
- Controller in `App\Http\Controllers\Api\V1` namespace
- Thin controller (business logic in services)
- Uses Form Requests for validation
- Proper dependency injection

**Security Risks & Mitigations:**
- Risk: Business logic in controller (hard to test)
- Mitigation: Extract to service classes
- Risk: Validation bypass
- Mitigation: Use Form Requests (Laravel validation)

---

### Subtask 2.2.2: Implement requestOtp() Method
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AuthController.php` (implement method)
- `app/Http/Requests/Api/V1/RequestOtpRequest.php` (validation rules)

**Dependencies:**
- RequestOtpRequest (Form Request)
- MelipayamakService
- OtpVerification model
- RateLimiter facade

**Acceptance Criteria:**
- Validates phone number (required, format, length)
- Normalizes phone number (+98XXXXXXXXXX format)
- Checks rate limit (3 per 15 minutes)
- Checks for active OTP (prevents duplicates)
- Generates OTP via OtpVerification::generate()
- Sends SMS via MelipayamakService
- Returns request_id and expires_at
- HTTP 200 on success, 429 on rate limit, 422 on validation error

**Security Risks & Mitigations:**
- Risk: Phone number enumeration
- Mitigation: Generic error messages, consistent timing
- Risk: SMS spam
- Mitigation: Rate limiting per phone number
- Risk: OTP code leaked in response
- Mitigation: Never return OTP code, only request_id

---

### Subtask 2.2.3: Implement verifyOtp() Method
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AuthController.php` (implement method)
- `app/Http/Requests/Api/V1/VerifyOtpRequest.php` (validation rules)

**Dependencies:**
- VerifyOtpRequest (Form Request)
- OtpVerification model
- User model
- Sanctum (token creation)

**Acceptance Criteria:**
- Validates request_id (UUID format) and code (6 digits)
- Finds OTP by request_id (not phone - prevents enumeration)
- Checks OTP not already verified
- Checks OTP not expired
- Checks attempts not exceeded
- Verifies code via OtpVerification::verify()
- Creates/updates user (firstOrCreate)
- Generates Sanctum token
- Returns user data and token
- HTTP 200 on success, 400 on failure, 404 on invalid request_id

**Security Risks & Mitigations:**
- Risk: Timing attacks on OTP lookup
- Mitigation: Constant-time operations, generic error messages
- Risk: Token leakage in logs
- Mitigation: Never log full token, only token_id
- Risk: User enumeration
- Mitigation: Use request_id lookup, not phone lookup

---

### Subtask 2.2.4: Implement resendOtp() Method
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AuthController.php` (implement method)
- `app/Http/Requests/Api/V1/ResendOtpRequest.php` (validation rules)

**Dependencies:**
- ResendOtpRequest (Form Request)
- MelipayamakService
- OtpVerification model
- RateLimiter facade

**Acceptance Criteria:**
- Validates request_id (UUID format)
- Finds OTP by request_id
- Checks OTP not already verified
- Checks rate limit for resend (2 per 10 minutes)
- Generates new OTP
- Deletes old OTP
- Sends new SMS
- Returns new request_id and expires_at
- HTTP 200 on success, 429 on rate limit, 404 on invalid request_id

**Security Risks & Mitigations:**
- Risk: Resend spam
- Mitigation: Separate rate limit for resend (stricter than request)
- Risk: Multiple active OTPs
- Mitigation: Delete old OTP before creating new one

---

### Subtask 2.2.5: Add Rate Limiting Middleware
**Files to Create/Modify:**
- `app/Http/Middleware/ThrottleOtpRequests.php` (new - optional, or use Laravel's built-in)

**Dependencies:**
- Laravel RateLimiter (or built-in throttle middleware)

**Acceptance Criteria:**
- Rate limiting applied to OTP endpoints
- Different limits for request vs resend
- Returns 429 with Retry-After header
- Rate limit keys use phone hash (not plain phone)

**Security Risks & Mitigations:**
- Risk: Rate limit bypass
- Mitigation: Apply at middleware level (can't be skipped)

---

### Subtask 2.2.6: Generate JWT Tokens on Verification
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AuthController.php` (use Sanctum in verifyOtp)

**Dependencies:**
- Laravel Sanctum

**Acceptance Criteria:**
- Token created via `$user->createToken('auth-token')`
- Token name: 'auth-token' (for audit trail)
- Returns plain text token in response
- Token type: 'Bearer' (for Authorization header)

**Security Risks & Mitigations:**
- Risk: Token stored in plaintext
- Mitigation: Sanctum hashes tokens in database
- Risk: Token never expires
- Mitigation: Configurable expiration (future enhancement)

---

### Subtask 2.2.7: Add Validation Rules
**Files to Create/Modify:**
- `app/Http/Requests/Api/V1/RequestOtpRequest.php` (phone validation)
- `app/Http/Requests/Api/V1/VerifyOtpRequest.php` (request_id, code validation)
- `app/Http/Requests/Api/V1/ResendOtpRequest.php` (request_id validation)

**Dependencies:**
- Laravel validation

**Acceptance Criteria:**
- Phone: required, string, regex pattern, min 10, max 20
- Request ID: required, string, UUID format
- Code: required, string, size 6, numeric only
- Custom validation messages (user-friendly)
- Validation errors returned as JSON (422 status)

**Security Risks & Mitigations:**
- Risk: Invalid input causing errors
- Mitigation: Strict validation rules, sanitize input
- Risk: SQL injection via phone number
- Mitigation: Eloquent ORM prevents SQL injection

---

### Subtask 2.2.8: Add Request/Response Logging
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AuthController.php` (add logging)
- `app/Http/Middleware/LogApiRequests.php` (optional - global logging)

**Dependencies:**
- Laravel Log facade

**Acceptance Criteria:**
- Log OTP requests (phone hash, request_id, timestamp)
- Log OTP verifications (request_id, success/failure, timestamp)
- Log rate limit violations (phone hash, endpoint, timestamp)
- Never log OTP codes or tokens
- Structured logging (JSON format)
- Include IP address and user agent

**Security Risks & Mitigations:**
- Risk: Sensitive data in logs
- Mitigation: Never log OTP codes, tokens, or full phone numbers
- Risk: Log injection attacks
- Mitigation: Sanitize log data, use structured logging

**Test Coverage Expectations:**
- Feature test: Request OTP with valid phone (200, returns request_id)
- Feature test: Request OTP with invalid phone (422, validation error)
- Feature test: Request OTP rate limited (429, after 3 requests)
- Feature test: Verify OTP with correct code (200, returns token)
- Feature test: Verify OTP with wrong code (400, increments attempts)
- Feature test: Verify OTP expired (400, expired message)
- Feature test: Verify OTP max attempts (400, attempts exceeded)
- Feature test: Resend OTP (200, new request_id)
- Feature test: Resend OTP rate limited (429, after 2 resends)
- Feature test: Logout invalidates token (200, token deleted)

---

## Task 2.3: User Profile Endpoints

### Subtask 2.3.1: Create UserController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/UserController.php` (new file)
- `app/Http/Requests/Api/V1/UpdateUserRequest.php` (new - Form Request)
- `app/Http/Requests/Api/V1/UploadAvatarRequest.php` (new - Form Request)

**Dependencies:**
- Sanctum authentication middleware
- User model
- S3 storage (for avatar)

**Acceptance Criteria:**
- Controller in `App\Http\Controllers\Api\V1` namespace
- All methods require authentication (`auth:sanctum` middleware)
- Uses Form Requests for validation
- Thin controller (business logic in services if needed)

**Security Risks & Mitigations:**
- Risk: Unauthorized access
- Mitigation: Sanctum middleware on all routes
- Risk: User can modify other users' data
- Mitigation: Only allow modification of authenticated user's data

---

### Subtask 2.3.2: Implement show() - Get User Profile
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/UserController.php` (implement show method)

**Dependencies:**
- Authenticated user (via Sanctum)

**Acceptance Criteria:**
- Returns current authenticated user profile
- Includes: id, phone, name, email, avatar_url, tokens_balance, role, is_verified, timestamps
- HTTP 200 on success
- Never returns password or sensitive fields

**Security Risks & Mitigations:**
- Risk: Sensitive data exposure
- Mitigation: Only return allowed fields, never password
- Risk: User enumeration
- Mitigation: Only return authenticated user's data

---

### Subtask 2.3.3: Implement update() - Update Profile
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/UserController.php` (implement update method)
- `app/Http/Requests/Api/V1/UpdateUserRequest.php` (validation rules)

**Dependencies:**
- UpdateUserRequest (Form Request)
- User model

**Acceptance Criteria:**
- Validates: name (optional, string, max 255), email (optional, email, unique)
- Updates only authenticated user's profile
- Returns updated user data
- HTTP 200 on success, 422 on validation error

**Security Risks & Mitigations:**
- Risk: Email collision (user tries to use another user's email)
- Mitigation: Unique validation rule (excludes current user)
- Risk: Mass assignment vulnerability
- Mitigation: Use `$fillable` in model, validate in Form Request

---

### Subtask 2.3.4: Implement uploadAvatar() - Avatar Upload
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/UserController.php` (implement uploadAvatar method)
- `app/Http/Requests/Api/V1/UploadAvatarRequest.php` (file validation)

**Dependencies:**
- UploadAvatarRequest (Form Request)
- S3 storage disk
- Image processing (optional - for resizing)

**Acceptance Criteria:**
- Validates file: required, image, max 5MB, mimes: jpeg,jpg,png,webp
- Stores file in S3 (disk: s3)
- File path: `avatars/{user_id}/{timestamp}_{filename}`
- Updates user's `avatar_url` field
- Returns updated user data with new avatar_url
- Deletes old avatar from S3 (if exists)
- HTTP 200 on success, 422 on validation error

**Security Risks & Mitigations:**
- Risk: File upload attacks (malicious files)
- Mitigation: Strict mime type validation, store in S3 (not public directory)
- Risk: Large file uploads (DoS)
- Mitigation: Max 5MB limit, validate file size
- Risk: Path traversal in filename
- Mitigation: Sanitize filename, use user_id in path
- Risk: Old avatars not deleted (storage bloat)
- Mitigation: Delete old avatar before saving new one

---

### Subtask 2.3.5: Add File Validation for Avatar
**Files to Create/Modify:**
- `app/Http/Requests/Api/V1/UploadAvatarRequest.php` (validation rules)

**Dependencies:**
- Laravel validation

**Acceptance Criteria:**
- File: required, image, max:5120 (5MB in KB), mimes:jpeg,jpg,png,webp
- Custom error messages
- Returns 422 with validation errors on failure

**Security Risks & Mitigations:**
- Risk: Invalid file types uploaded
- Mitigation: Strict mime type validation
- Risk: File size too large
- Mitigation: Max size validation

---

### Subtask 2.3.6: Store Avatar in S3
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/UserController.php` (use Storage facade)

**Dependencies:**
- Laravel Storage facade
- S3 disk configured (from Phase 1)

**Acceptance Criteria:**
- File stored via `Storage::disk('s3')->put()`
- Path: `avatars/{user_id}/{timestamp}_{sanitized_filename}`
- Returns public URL via `Storage::disk('s3')->url()`
- URL stored in user's `avatar_url` field

**Security Risks & Mitigations:**
- Risk: S3 credentials exposed
- Mitigation: Store in `.env`, never commit
- Risk: Public access to avatars
- Mitigation: Use signed URLs or public bucket with proper CORS

---

### Subtask 2.3.7: Add Sanctum Authentication Middleware
**Files to Create/Modify:**
- `routes/api.php` (add middleware to user routes)

**Dependencies:**
- Laravel Sanctum

**Acceptance Criteria:**
- All user profile routes protected with `auth:sanctum` middleware
- Unauthenticated requests return 401
- Authenticated user available via `$request->user()`

**Security Risks & Mitigations:**
- Risk: Unauthorized access
- Mitigation: Middleware enforces authentication

---

### Subtask 2.3.8: Return Token Balance in Profile
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/UserController.php` (include tokens_balance in response)

**Dependencies:**
- User model (tokens_balance field)

**Acceptance Criteria:**
- `tokens_balance` included in profile response
- Balance is decimal with 2 decimal places
- Balance is accurate (from database, not calculated)

**Security Risks & Mitigations:**
- Risk: Balance manipulation
- Mitigation: Balance stored in database, not calculated client-side

**Test Coverage Expectations:**
- Feature test: Get profile with auth (200, returns user data)
- Feature test: Get profile without auth (401, unauthorized)
- Feature test: Update profile with valid data (200, returns updated user)
- Feature test: Update profile with invalid email (422, validation error)
- Feature test: Upload avatar with valid image (200, returns avatar_url)
- Feature test: Upload avatar with invalid file (422, validation error)
- Feature test: Upload avatar too large (422, size validation error)
- Feature test: Avatar stored in S3 (verify file exists)
- Feature test: Old avatar deleted on new upload (verify old file deleted)

---

## Summary

**Total Files to Create:** 12
- 3 Form Requests (RequestOtp, VerifyOtp, ResendOtp, UpdateUser, UploadAvatar)
- 1 Service (OtpService - optional, or use model methods)
- 1 Command (CleanupExpiredOtps)
- 1 Controller (UserController)
- 1 Middleware (optional - ThrottleOtpRequests, or use built-in)

**Total Files to Modify:** 6
- MelipayamakService (update)
- AuthController (refactor to V1 namespace, use Form Requests)
- OtpVerification model (update hashing to HMAC)
- routes/api.php (add user routes)
- Kernel.php (schedule cleanup job)
- config/services.php (verify melipayamak config)

**Dependencies:**
- Environment variables: MELIPAYAMAK_USERNAME, MELIPAYAMAK_PASSWORD, MELIPAYAMAK_FROM, MELIPAYAMAK_BASE_URL
- Packages: Laravel Sanctum (already installed), Guzzle (via Laravel HTTP)
- Infrastructure: Redis (for rate limiting), S3 (for avatar storage)

**Security Checklist:**
- [ ] OTP codes never logged
- [ ] OTP codes hashed with HMAC-SHA256
- [ ] Rate limiting enforced
- [ ] Phone numbers hashed in logs
- [ ] Tokens never logged
- [ ] File upload validation strict
- [ ] Authentication required for profile endpoints
- [ ] No information leakage in error messages

**Test Coverage Minimum:**
- 15+ feature tests for OTP endpoints
- 8+ feature tests for user profile endpoints
- Unit tests for OTP generation/hashing/verification
- Mock MelipayamakService in all tests

