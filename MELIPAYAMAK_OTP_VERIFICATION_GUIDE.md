# Melipayamak OTP Integration - Verification Guide

## Overview

This guide provides steps to verify the Melipayamak OTP integration is working correctly.

## Prerequisites

1. Melipayamak account credentials (username/password or API token)
2. Approved SMS template ID (372382 for verification template)
3. Approved sender number (e.g., 50002710008883)

## Configuration Checklist

Before testing, ensure the following environment variables are set in your `.env` file:

### Required Configuration

```env
# Enable/disable Melipayamak service
MELIPAYAMAK_ENABLED=true

# Authentication mode: 'token' or 'classic'
MELIPAYAMAK_AUTH_MODE=classic

# Base URL (default: https://rest.payamak-panel.com/api)
MELIPAYAMAK_BASE_URL=https://rest.payamak-panel.com/api

# For token-based auth (if MELIPAYAMAK_AUTH_MODE=token)
MELIPAYAMAK_AUTH_TOKEN=<PUT_TOKEN_HERE>

# For classic auth (if MELIPAYAMAK_AUTH_MODE=classic)
MELIPAYAMAK_USERNAME=<PUT_USERNAME_HERE>
MELIPAYAMAK_PASSWORD=<PUT_PASSWORD_HERE>

# OTP Configuration
MELIPAYAMAK_OTP_MODE=pattern
MELIPAYAMAK_OTP_TEMPLATE_ID=372382

# Fallback configuration (for plain SMS)
MELIPAYAMAK_FROM_NUMBER=50002710008883
MELIPAYAMAK_OTP_MESSAGE_TEXT=کد ورود شما: {CODE} این کد 5 دقیقه اعتبار دارد سروکست
```

### Optional Configuration

```env
# Timeout in seconds (default: 10)
MELIPAYAMAK_TIMEOUT=10

# Retry configuration
MELIPAYAMAK_RETRY_MAX_ATTEMPTS=3
MELIPAYAMAK_RETRY_DELAY_SECONDS=2
```

## Verification Steps

### Step 1: Clear Configuration Cache

```bash
php artisan config:clear
php artisan cache:clear
```

### Step 2: Run Tests

Run the test suite to verify integration:

```bash
php artisan test
```

Or run specific test suites:

```bash
# Unit tests for OTP sender
php artisan test tests/Unit/Services/Otp/MelipayamakOtpSenderTest.php

# Integration tests for client
php artisan test tests/Unit/Integrations/Melipayamak/TokenClientTest.php

# Feature tests for auth endpoints
php artisan test tests/Feature/Api/V1/AuthControllerOtpTest.php
```

### Step 3: Manual Testing (Optional)

#### Test OTP Request Endpoint

```bash
curl -X POST http://localhost:8000/api/v1/auth/request-otp \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "09123456789"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "OTP sent successfully",
  "data": {
    "request_id": "uuid-here",
    "expires_at": "2024-01-15T12:00:00.000000Z"
  }
}
```

#### Test OTP Verification Endpoint

```bash
curl -X POST http://localhost:8000/api/v1/auth/verify-otp \
  -H "Content-Type: application/json" \
  -d '{
    "request_id": "uuid-from-previous-response",
    "code": "123456"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "OTP verified successfully",
  "data": {
    "user": {
      "id": 1,
      "phone": "09123456789",
      "tokens_balance": 0,
      ...
    },
    "token": "sanctum-token-here",
    "token_type": "Bearer"
  }
}
```

## Troubleshooting

### Issue: "SMS provider authentication failed"

**Solution:**
- Verify `MELIPAYAMAK_USERNAME` and `MELIPAYAMAK_PASSWORD` are correct
- Or verify `MELIPAYAMAK_AUTH_TOKEN` is correct if using token mode
- Check credentials in Melipayamak panel

### Issue: "SMS template not found or not approved"

**Solution:**
- Verify `MELIPAYAMAK_OTP_TEMPLATE_ID` matches your approved template ID
- Check template is active in Melipayamak panel
- Ensure template has correct parameter count (single parameter for OTP)

### Issue: "SMS provider account has insufficient credit"

**Solution:**
- Login to Melipayamak panel
- Check account balance
- Recharge account if needed

### Issue: "Invalid phone number format"

**Solution:**
- Ensure phone number is in Iranian format: `09XXXXXXXXX` (11 digits)
- Phone must start with `09`

### Issue: Tests failing

**Solution:**
- Ensure all environment variables are set (use `.env.testing` for tests)
- Clear config cache: `php artisan config:clear`
- Check test database is properly configured

## Architecture Overview

### Components

1. **Interface**: `App\Contracts\OtpSender`
   - Defines contract for OTP sending
   - Single method: `sendOtp(string $phone, string $code): void`

2. **Service**: `App\Services\Otp\MelipayamakOtpSender`
   - Implements `OtpSender` interface
   - Handles template/pattern SMS (preferred) with plain SMS fallback
   - Validates phone and code formats

3. **Clients**:
   - `App\Integrations\Melipayamak\TokenClient` - Token-based auth
   - `App\Integrations\Melipayamak\ClassicClient` - Username/password auth

4. **Exception**: `App\Exceptions\SmsProviderException`
   - Sanitized error messages (no secrets)
   - Specific exception types for different errors

### Flow

```
AuthController
    ↓
OtpSender (interface)
    ↓
MelipayamakOtpSender (implementation)
    ↓
TokenClient/ClassicClient
    ↓
Melipayamak REST API
```

## Security Notes

- ✅ No OTP codes logged
- ✅ No credentials/tokens logged
- ✅ Phone numbers hashed in logs
- ✅ Exception messages sanitized
- ✅ All secrets in environment variables

## Next Steps

1. Fill in actual credentials in `.env` file
2. Test with real phone number
3. Monitor logs for any errors
4. Verify SMS delivery in Melipayamak panel

---

**Document Version:** 1.0  
**Last Updated:** 2024-01-15  
**Integration Status:** ✅ Complete

