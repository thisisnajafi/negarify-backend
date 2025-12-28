# Phase 2: Authentication & User Management - Verification Checklist

## ✅ Implementation Complete

All Phase 2 tasks have been implemented, tested, and committed to the `BackEnd` branch.

**Commits:**
1. `feat(auth): implement melipayamak otp service with HMAC hashing (Task 2.1)`
2. `feat(auth): add otp auth endpoints with rate limiting (Task 2.2)`
3. `feat(user): add user profile and avatar endpoints (Task 2.3)`
4. `test(auth): add feature tests for OTP auth and user profile endpoints`
5. `docs: mark Phase 2 tasks as completed in BACKEND_TASKS.md`

**Branch:** `BackEnd` (pushed to remote)

---

## Verification Commands

### Prerequisites
```bash
# 1. Install dependencies (if not already done)
composer install

# 2. Set up environment
cp .env.example .env
php artisan key:generate

# 3. Configure database and Redis in .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_DATABASE=negarify
# REDIS_HOST=127.0.0.1

# 4. Run migrations
php artisan migrate

# 5. Configure Melipayamak credentials in .env
# MELIPAYAMAK_USERNAME=your_username
# MELIPAYAMAK_PASSWORD=your_password
# MELIPAYAMAK_FROM=your_sender_number
```

### Testing

#### Run Feature Tests
```bash
# Run all Phase 2 tests
php artisan test --filter=AuthTest
php artisan test --filter=UserTest

# Run all tests
php artisan test
```

#### Manual API Testing (using HTTPie or cURL)

**1. Request OTP**
```bash
# Using HTTPie
http POST http://localhost:8000/api/v1/auth/request-otp phone="+989123456789"

# Using cURL
curl -X POST http://localhost:8000/api/v1/auth/request-otp \
  -H "Content-Type: application/json" \
  -d '{"phone":"+989123456789"}'

# Expected Response (200):
# {
#   "success": true,
#   "message": "OTP sent successfully",
#   "data": {
#     "request_id": "uuid-here",
#     "expires_at": "2024-01-01T12:05:00.000000Z"
#   }
# }
```

**2. Verify OTP**
```bash
# Note: You'll need the actual OTP code from SMS and request_id from step 1
# For testing, check the database or use a test OTP service mock

http POST http://localhost:8000/api/v1/auth/verify-otp \
  request_id="uuid-from-step-1" \
  code="123456"

# Expected Response (200):
# {
#   "success": true,
#   "message": "OTP verified successfully",
#   "data": {
#     "user": {...},
#     "token": "sanctum-token-here",
#     "token_type": "Bearer"
#   }
# }
```

**3. Get User Profile (requires auth)**
```bash
# Replace TOKEN with token from step 2
http GET http://localhost:8000/api/v1/user \
  Authorization:"Bearer TOKEN"

# Expected Response (200):
# {
#   "success": true,
#   "data": {
#     "id": 1,
#     "phone": "+989123456789",
#     "tokens_balance": 0,
#     ...
#   }
# }
```

**4. Update User Profile**
```bash
http PUT http://localhost:8000/api/v1/user \
  Authorization:"Bearer TOKEN" \
  name="New Name" \
  email="new@example.com"
```

**5. Upload Avatar**
```bash
http POST http://localhost:8000/api/v1/user/avatar \
  Authorization:"Bearer TOKEN" \
  avatar@/path/to/image.jpg
```

**6. Logout**
```bash
http POST http://localhost:8000/api/v1/auth/logout \
  Authorization:"Bearer TOKEN"
```

### Testing Rate Limiting

**Test OTP Request Rate Limit:**
```bash
# Make 4 requests quickly (4th should be rate limited)
for i in {1..4}; do
  http POST http://localhost:8000/api/v1/auth/request-otp phone="+989123456789"
done

# 4th request should return 429 with retry-after message
```

**Test OTP Verification Attempt Limit:**
```bash
# Request OTP first, then try wrong codes 4 times
# 4th attempt should be rejected (max 3 attempts)
```

### Testing Error Scenarios

**1. Invalid Phone Format**
```bash
http POST http://localhost:8000/api/v1/auth/request-otp phone="invalid"
# Expected: 422 with validation errors
```

**2. Invalid Request ID**
```bash
http POST http://localhost:8000/api/v1/auth/verify-otp \
  request_id="invalid-uuid" \
  code="123456"
# Expected: 404
```

**3. Expired OTP**
```bash
# Manually expire an OTP in database, then try to verify
# Expected: 400 with "OTP has expired"
```

**4. Unauthorized Profile Access**
```bash
http GET http://localhost:8000/api/v1/user
# Expected: 401
```

**5. Invalid Avatar File**
```bash
# Upload non-image file
http POST http://localhost:8000/api/v1/user/avatar \
  Authorization:"Bearer TOKEN" \
  avatar@/path/to/document.pdf
# Expected: 422 with validation errors
```

### Database Verification

```bash
# Check OTP records
php artisan tinker
>>> App\Models\OtpVerification::count()
>>> App\Models\OtpVerification::whereNull('verified_at')->count()

# Check users
>>> App\Models\User::count()
>>> App\Models\User::where('is_verified', true)->count()

# Check tokens
>>> DB::table('personal_access_tokens')->count()
```

### Scheduled Job Verification

```bash
# Test cleanup command manually
php artisan otp:cleanup --hours=1

# Check scheduled tasks
php artisan schedule:list

# Run scheduler manually (for testing)
php artisan schedule:run
```

### Log Verification

```bash
# Check logs (should NOT contain OTP codes or full phone numbers)
tail -f storage/logs/laravel.log | grep -i otp

# Verify phone numbers are hashed in logs
# Verify OTP codes are never logged
```

---

## Security Checklist

- [x] OTP codes never logged (verified in code)
- [x] OTP codes hashed with HMAC-SHA256 (not bcrypt)
- [x] Phone numbers hashed in logs
- [x] Rate limiting enforced per phone number
- [x] OTP expiration (5 minutes) enforced
- [x] Max attempts (3) enforced
- [x] Constant-time verification (prevents timing attacks)
- [x] Request ID lookup (prevents phone enumeration)
- [x] Generic error messages (no information leakage)
- [x] Sanctum tokens never logged
- [x] File upload validation (type, size, mime)
- [x] Safe filename generation (prevents path traversal)
- [x] Authentication required for profile endpoints

---

## Known Limitations

1. **SMS Provider**: MelipayamakService requires real credentials for production. For testing, use mocks (as in tests).

2. **S3 Storage**: Avatar upload requires S3 credentials configured. For local testing, you can use the `local` disk instead.

3. **OTP Code Retrieval**: In production, OTP codes are sent via SMS. For testing, you can:
   - Use a test SMS service
   - Mock MelipayamakService (as in tests)
   - Check database directly (for development only)

---

## Next Steps

Phase 2 is complete. Ready to proceed with:
- **Phase 3**: Token System & Payment
- **Phase 4**: Segmind API Integration
- **Phase 5**: Gallery & Feed System

All Phase 2 code is production-ready and follows security best practices.

