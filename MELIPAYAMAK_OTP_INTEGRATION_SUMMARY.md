# Melipayamak OTP Integration - Extracted Requirements

## STEP 0: Discovery & Extraction Summary

### A) Authentication

**Auth Methods Supported:**
- **Option 1: Username & Password** (Classic)
  - Username: Phone number (e.g., `09136708883`)
  - Password: Account password or API token
  - Used in form-urlencoded POST requests

- **Option 2: API Token** (Recommended)
  - Token can be used as both username and password
  - More secure than password-based auth
  - Same form-urlencoded format

**Headers:**
- Content-Type: `application/x-www-form-urlencoded`
- No special authentication headers required
- Credentials sent in POST body

**Base URLs:**
- REST Base: `https://rest.payamak-panel.com/api`
- SOAP Base: `http://api.payamak-panel.com/post/send.asmx?wsdl` (not used for OTP)

---

### B) OTP Sending Options

#### 1. Pattern/Template OTP (PREFERRED - Required for OTP)

**Endpoint:**
```
POST https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber
```

**Request Format:**
```
Content-Type: application/x-www-form-urlencoded

username=09136708883
password=YourPassword
text=123456
to=09123456789
bodyId=372382
```

**Required Parameters:**
- `username`: Melipayamak username (phone number or token)
- `password`: Melipayamak password or token
- `text`: OTP code (single parameter) OR semicolon-separated for multiple params
- `to`: Recipient phone number (11 digits, starts with 09)
- `bodyId`: Template/Pattern ID (372382 for verification template)

**Template Details:**
- **Template ID**: `372382`
- **Template Message**: `کد ورود شما: {0} این کد 5 دقیقه اعتبار دارد سروکست`
- **Parameter Placeholder**: `{0}` (single parameter for OTP code)
- **How OTP is passed**: As `text` parameter (just the code, e.g., `123456`)

**Response Format:**
```json
{
    "RetStatus": 1,
    "StrRetStatus": "Ok",
    "Value": "12345678"  // Message ID
}
```

#### 2. Plain SMS Fallback (NOT RECOMMENDED for OTP)

**Endpoint:**
```
POST https://rest.payamak-panel.com/api/SendSMS/SendSMS
```

**Request Format:**
```
Content-Type: application/x-www-form-urlencoded

username=09136708883
password=YourPassword
to=09123456789
from=50002710008883
text=Your verification code is: 123456. Valid for 5 minutes.
```

**Required Parameters:**
- `username`: Melipayamak username
- `password`: Melipayamak password
- `to`: Recipient phone number
- `from`: Sender number (approved line, e.g., `50002710008883`)
- `text`: Full message text (can include {CODE} placeholder for substitution)

**Note:** Plain SMS is not recommended for OTP as it may not comply with Iranian SMS regulations. Template SMS is required for OTP delivery.

---

### C) Error & Delivery

#### Success Indicators:
- `RetStatus == 1`: Success
- `StrRetStatus == "Ok"`: Success message
- `Value`: Contains message ID (string)

#### Error Indicators:
- `RetStatus == 0`: Failure
- `StrRetStatus`: Error code string

#### Common Error Codes:

| RetStatus | StrRetStatus | Description |
|-----------|--------------|-------------|
| 0 | UserNameAndPasswordFailed | Invalid credentials |
| 0 | InvalidPhoneNumber | Invalid phone number format |
| 0 | InsufficientCredit | Not enough credit in account |
| 0 | TemplateNotFound | Template ID not found or not approved |
| 0 | ParameterMismatch | Wrong number of parameters for template |

#### Rate Limits & Throttling:
- Documentation mentions rate limiting support but no specific limits defined
- Should implement client-side rate limiting (already done in AuthController)
- Recommended: Max 3 OTP requests per 15 minutes per phone

#### Delivery Status:
- Endpoint: `https://rest.payamak-panel.com/api/SendSMS/GetDeliveries2`
- Requires: `username`, `password`, `recId` (message ID from send response)

---

## Implementation Strategy

### Preferred Approach:
1. **Use Template/Pattern SMS** (`BaseServiceNumber` endpoint)
2. **Support both token and username/password** auth modes
3. **Fallback to plain SMS** only if template fails (with warning)
4. **Implement proper error handling** with specific error codes
5. **Log safely** (no OTP codes, no credentials, masked phone numbers)

### Configuration Requirements:
- Template ID: `372382` (verification template)
- Sender number: `50002710008883` (for plain SMS fallback)
- Base URL: `https://rest.payamak-panel.com/api`
- Support both auth modes via config

---

**Document Version:** 1.0  
**Extracted From:** `MELIPAYAMAK_COMPLETE_DOCUMENTATION.md`  
**Date:** 2024-01-15

