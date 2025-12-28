# Phase 2: Authentication & User Management - Security Summary

## OTP Authentication Flow & Rationale

1. **OTP-Based Authentication**: Phone number + OTP replaces traditional password-based auth
   - Eliminates password storage and associated risks (breaches, reuse, weak passwords)
   - Leverages phone ownership as identity proof (SMS delivery = phone possession)
   - Reduces attack surface (no password reset flows, no credential stuffing)

2. **Flow**: User requests OTP → SMS sent → User submits code → System verifies → Token issued
   - Stateless token (Sanctum) issued on successful verification
   - User auto-created on first successful verification
   - No persistent session state required

3. **Why OTP for Negarify**: 
   - Iranian market preference for phone-based auth
   - Lower friction than email verification
   - SMS delivery via Melipayamak (local provider)
   - No password management overhead

## Abuse Prevention Measures (CRITICAL)

4. **Rate Limiting Per Phone Number**:
   - OTP requests: Max 3 per 15 minutes per phone
   - OTP resends: Max 2 per 10 minutes per phone
   - Verification attempts: Max 3 per OTP code
   - Prevents SMS spam, brute-force, and enumeration attacks

5. **OTP Expiration**: 5-minute TTL
   - Reduces window for replay attacks
   - Forces timely verification
   - Balances UX (not too short) and security (not too long)

6. **Attempt Limiting**: Max 3 verification attempts per OTP
   - Prevents brute-force guessing of 6-digit codes
   - Locks OTP after max attempts (requires new request)
   - Tracks attempts atomically to prevent race conditions

7. **Active OTP Prevention**: Only one active (non-expired, non-verified) OTP per phone
   - Prevents OTP flooding
   - Reduces SMS costs
   - Simplifies verification logic

8. **Request ID (UUID)**: Opaque identifier for OTP verification
   - Prevents phone number enumeration via timing attacks
   - No direct phone lookup in verification endpoint
   - UUIDs are unguessable and non-sequential

## Logging Requirements

9. **MUST LOG** (Audit Trail):
   - OTP request events (phone hash, timestamp, request_id)
   - OTP verification attempts (success/failure, request_id, timestamp)
   - Rate limit violations (phone hash, endpoint, timestamp)
   - SMS delivery failures (phone hash, error, timestamp)
   - User creation events (user_id, phone hash, timestamp)
   - Token issuance (user_id, timestamp, token_id)

10. **MUST NEVER LOG** (Security):
    - OTP codes (plaintext or hashed in logs)
    - OTP code hashes (even in error logs)
    - Full phone numbers (use hash or last 4 digits only)
    - Sanctum tokens (only token_id, never plaintext)
    - User passwords (N/A for OTP, but principle applies)

11. **Logging Format**:
    - Use structured logging (JSON)
    - Include request_id for traceability
    - Hash phone numbers: `hash('sha256', phone . config('app.key'))`
    - Include IP address for abuse tracking
    - Include user_agent for pattern detection

## Production-Grade OTP Auth Definition

12. **Cryptographic Security**:
    - OTP codes: 6-digit random (100000-999999), cryptographically secure RNG
    - OTP storage: HMAC-SHA256 hash (not bcrypt/argon2 - OTPs are short-lived)
    - Request IDs: UUID v4 (cryptographically random)
    - Token storage: Sanctum handles token hashing

13. **State Management**:
    - OTP records: Single source of truth in database
    - Atomic operations: Use database transactions for attempt increments
    - Cleanup: Scheduled job removes expired OTPs (older than 1 hour)
    - Idempotency: Resend creates new OTP, deletes old (no duplicates)

14. **Error Handling**:
    - Generic error messages (no information leakage)
    - Consistent HTTP status codes (422 validation, 429 rate limit, 400 bad request)
    - No timing attacks (constant-time verification)
    - Graceful degradation (SMS failure doesn't crash system)

15. **Service Layer Abstraction**:
    - MelipayamakService: Abstracted SMS provider (mockable for tests)
    - Retry logic: 3 attempts with exponential backoff
    - Circuit breaker: Fail fast if provider is down
    - Fallback: Log failures, allow manual retry

16. **Validation & Input Sanitization**:
    - Phone normalization: Consistent format (+98XXXXXXXXXX)
    - Phone validation: Regex + length checks
    - OTP code validation: Exactly 6 digits, numeric only
    - Request ID validation: UUID format validation

17. **Token Management**:
    - Sanctum tokens: Named tokens ('auth-token'), revocable
    - Token expiration: Configurable (default: no expiration for API tokens)
    - Logout: Explicit token revocation (currentAccessToken()->delete())
    - Token scoping: Future-proof for API key management

18. **Monitoring & Alerting**:
    - Track OTP request rates (detect spikes)
    - Track verification failure rates (detect attacks)
    - Track SMS delivery success rates (detect provider issues)
    - Alert on rate limit violations (potential abuse)

19. **Testing Requirements**:
    - Mock MelipayamakService in tests (no real SMS)
    - Test rate limiting (verify limits enforced)
    - Test expiration (verify expired OTPs rejected)
    - Test attempt limiting (verify max attempts enforced)
    - Test phone normalization (verify consistent format)
    - Test error scenarios (SMS failure, provider down)

20. **Compliance & Privacy**:
    - GDPR-ready: User can request data deletion
    - Phone number privacy: Hashed in logs, encrypted at rest
    - Audit trail: Immutable logs for compliance
    - Data retention: OTP records deleted after 1 hour (cleanup job)
