# Melipayamak SMS Service - Complete Documentation

## Table of Contents
1. [Overview](#overview)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Authentication & Credentials](#authentication--credentials)
5. [Basic Usage](#basic-usage)
6. [Template SMS](#template-sms)
7. [OTP Service](#otp-service)
8. [API Methods](#api-methods)
9. [Facade Usage](#facade-usage)
10. [Error Handling](#error-handling)
11. [Testing](#testing)
12. [Troubleshooting](#troubleshooting)
13. [Best Practices](#best-practices)

---

## Overview

Melipayamak is an Iranian SMS gateway service provider that allows you to send SMS messages to Iranian mobile numbers. The SarvCast backend integrates with Melipayamak for:

- **User Authentication**: Sending OTP codes for login/verification
- **Notifications**: Sending various notification messages
- **Payment Alerts**: Sending payment and commission notifications
- **Template Messages**: Using approved SMS templates for compliance

### Features

- ✅ Simple SMS sending
- ✅ Template-based SMS (approved patterns)
- ✅ OTP code generation and verification
- ✅ Multiple sending methods (SOAP, REST)
- ✅ Comprehensive logging
- ✅ Error handling and fallbacks
- ✅ Rate limiting support
- ✅ Laravel Facade integration

### Package Information

- **Package Name**: `melipayamak/laravel`
- **Version**: `1.0.0`
- **GitHub**: https://github.com/Melipayamak/melipayamak-laravel
- **PHP Package**: https://github.com/Melipayamak/melipayamak-php

---

## Installation

### Step 1: Install via Composer

The package is already included in `composer.json`:

```json
{
    "require": {
        "melipayamak/laravel": "1.0.0"
    }
}
```

If you need to install it manually:

```bash
composer require melipayamak/laravel
```

### Step 2: Register Service Provider

The service provider is already registered in `bootstrap/providers.php`:

```php
<?php

return [
    // ... other providers
    App\Providers\MelipayamakServiceProvider::class,
];
```

### Step 3: Publish Configuration (Optional)

```bash
php artisan vendor:publish --provider="Melipayamak\Laravel\ServiceProvider"
```

---

## Configuration

### Environment Variables

Add the following to your `.env` file:

```env
# Melipayamak Configuration
MELIPAYAMK_TOKEN=your_api_token_here
MELIPAYAMK_SENDER=50002710008883
MELIPAYAMK_VERIFICATION_TEMPLATE=372382

# Alternative Configuration (Legacy)
MELIPAYAMAK_USERNAME=09136708883
MELIPAYAMAK_PASSWORD=your_password_here
```

### Configuration Files

#### 1. `config/services.php`

```php
<?php

return [
    // ... other services
    
    'melipayamk' => [
        'token' => env('MELIPAYAMK_TOKEN', '77c431b7-aec5-4313-b744-d2f16bf760ab'),
        'sender' => env('MELIPAYAMK_SENDER', '50002710008883'),
        'templates' => [
            'verification' => env('MELIPAYAMK_VERIFICATION_TEMPLATE', 372382),
        ],
    ],
];
```

#### 2. `config/melipayamak.php` (Legacy)

```php
<?php

return [
    'username' => env('MELIPAYAMAK_USERNAME', '09136708883'),
    'password' => env('MELIPAYAMAK_PASSWORD', 'Prof48017421@#'),
];
```

#### 3. `config/sms.php`

```php
<?php

return [
    'default_provider' => env('SMS_DEFAULT_PROVIDER', 'melipayamak'),
    
    'providers' => [
        'melipayamak' => [
            'enabled' => env('SMS_MELIPAYAMAK_ENABLED', true),
            'username' => env('SMS_MELIPAYAMAK_USERNAME'),
            'password' => env('SMS_MELIPAYAMAK_PASSWORD'),
            'sender' => env('SMS_MELIPAYAMAK_SENDER', '50002710008883'),
            'base_url' => 'https://rest.payamak-panel.com/api',
        ],
    ],
];
```

---

## Authentication & Credentials

### Getting Your Credentials

1. **Sign up** at [Melipayamak Panel](https://panel.melipayamak.com)
2. **Login** to your account
3. **Navigate** to API settings
4. **Copy** your credentials:
   - **Username**: Your Melipayamak username (usually your phone number)
   - **Password**: Your account password or API token
   - **API Token**: If available, use this instead of password
   - **Sender Number**: Your approved sender number (e.g., `50002710008883`)

### Credential Types

#### Option 1: Username & Password
```env
MELIPAYAMAK_USERNAME=09136708883
MELIPAYAMAK_PASSWORD=YourPassword123
```

#### Option 2: API Token (Recommended)
```env
MELIPAYAMK_TOKEN=77c431b7-aec5-4313-b744-d2f16bf760ab
```

**Note**: The API token can be used as both username and password in some cases.

### Sender Number

The sender number is your approved SMS line number from Melipayamak:

```env
MELIPAYAMK_SENDER=50002710008883
```

**Important**: 
- Must be approved in Melipayamak panel
- Format: Usually starts with `5000` followed by your number
- Required for sending SMS

### Current Configuration (Example)

Based on the codebase, the current configuration uses:

```php
// Username
'username' => '09136708883'

// Password/Token
'password' => 'Prof48017421@#'

// Sender Number
'sender' => '50002710008883'

// API Token (Alternative)
'token' => '77c431b7-aec5-4313-b744-d2f16bf760ab'
```

**⚠️ Security Warning**: Never commit actual credentials to version control. Always use environment variables.

---

## Basic Usage

### Method 1: Using SmsService

```php
<?php

use App\Services\SmsService;

$smsService = new SmsService();

// Send simple SMS
$result = $smsService->sendSms('09123456789', 'Hello from SarvCast!');

if ($result['success']) {
    echo "SMS sent successfully!";
    echo "Message ID: " . $result['message_id'];
} else {
    echo "Failed to send SMS: " . $result['error'];
}
```

### Method 2: Using Melipayamak Facade

```php
<?php

use App\Facades\Melipayamak;

$sms = Melipayamak::sms();
$response = $sms->send('09123456789', '50002710008883', 'Hello from SarvCast!');

$json = json_decode($response);

if ($json && $json->RetStatus == 1) {
    echo "SMS sent successfully!";
    echo "Message ID: " . $json->Value;
} else {
    echo "Failed: " . $json->StrRetStatus;
}
```

### Method 3: Direct API Usage

```php
<?php

use Melipayamak\MelipayamakApi;

$username = config('melipayamak.username');
$password = config('melipayamak.password');

$api = new MelipayamakApi($username, $password);
$sms = $api->sms();

$response = $sms->send('09123456789', '50002710008883', 'Hello from SarvCast!');
$json = json_decode($response);

if ($json && $json->RetStatus == 1) {
    echo "SMS sent successfully!";
}
```

---

## Template SMS

### Overview

Template SMS (Pattern SMS) uses pre-approved message templates from Melipayamak. This is required for certain types of messages like OTP codes.

### Template Configuration

**Template ID**: `372382`  
**Template Message**: `کد ورود شما: {0} این کد 5 دقیقه اعتبار دارد سروکست`

### Using Template SMS

#### Method 1: Using SmsService

```php
<?php

use App\Services\SmsService;

$smsService = new SmsService();

// Send OTP with template
$result = $smsService->sendOtp('09123456789', 'login');

if ($result['success']) {
    echo "OTP sent successfully!";
}
```

#### Method 2: Direct Template Method

```php
<?php

use App\Services\SmsService;

$smsService = new SmsService();

// Send SMS with template
$result = $smsService->sendSmsWithTemplate(
    '09123456789',  // Phone number
    372382,         // Template ID
    ['123456']      // Parameters array (for {0} placeholder)
);
```

#### Method 3: Using REST API

```php
<?php

use Melipayamak\MelipayamakApi;

$username = config('melipayamak.username');
$password = config('melipayamak.password');

$api = new MelipayamakApi($username, $password);
$sms = $api->sms('rest'); // Use REST method for templates

// Parameters should be semicolon-separated for multiple parameters
$text = '123456'; // Single parameter
$response = $sms->sendByBaseNumber($text, '09123456789', 372382);

$json = json_decode($response);

if ($json && $json->RetStatus == 1) {
    echo "Template SMS sent successfully!";
}
```

### Template Parameters

Templates can have multiple parameters:

```php
// Template: "Hello {0}, your code is {1}"
$parameters = ['John', '123456'];
$text = implode(';', $parameters); // "John;123456"

$response = $sms->sendByBaseNumber($text, '09123456789', 12345);
```

---

## OTP Service

### Sending OTP

```php
<?php

use App\Services\SmsService;

$smsService = new SmsService();

// Send OTP for login
$result = $smsService->sendOtp('09123456789', 'login');

// Send OTP for verification
$result = $smsService->sendOtp('09123456789', 'verification');

// Send OTP for admin 2FA
$result = $smsService->sendOtp('09123456789', 'admin_2fa');
```

### Verifying OTP

```php
<?php

use App\Services\SmsService;

$smsService = new SmsService();

// Verify OTP code
$isValid = $smsService->verifyOtp('09123456789', '123456', 'login');

if ($isValid) {
    echo "OTP verified successfully!";
} else {
    echo "Invalid OTP code!";
}
```

### OTP Features

- **6-digit codes**: Automatically generated
- **5-minute expiration**: Codes expire after 5 minutes
- **Rate limiting**: Maximum 5 attempts per hour
- **Cache storage**: Codes stored in Laravel cache
- **Database logging**: All attempts logged in `otp_attempts` table

### Checking Attempts

```php
<?php

use App\Services\SmsService;

$smsService = new SmsService();

// Check if too many attempts
if ($smsService->hasTooManyAttempts('09123456789', 'login')) {
    echo "Too many attempts. Please try again later.";
}

// Get remaining attempts
$remaining = $smsService->getRemainingAttempts('09123456789', 'login');
echo "Remaining attempts: {$remaining}";
```

---

## API Methods

### SmsService Methods

#### 1. `sendSms(string $to, string $message): array`

Send a simple SMS message.

**Parameters:**
- `$to`: Recipient phone number (e.g., `09123456789`)
- `$message`: Message text

**Returns:**
```php
[
    'success' => true,
    'response' => $jsonResponse,
    'message_id' => '12345678'
]
```

**Example:**
```php
$result = $smsService->sendSms('09123456789', 'Hello World!');
```

#### 2. `sendSmsWithTemplate(string $to, int $templateId, array $parameters = []): array`

Send SMS using a template.

**Parameters:**
- `$to`: Recipient phone number
- `$templateId`: Template ID from Melipayamak
- `$parameters`: Array of parameters for template placeholders

**Returns:**
```php
[
    'success' => true,
    'response' => $jsonResponse,
    'message_id' => '12345678'
]
```

**Example:**
```php
$result = $smsService->sendSmsWithTemplate(
    '09123456789',
    372382,
    ['123456']
);
```

#### 3. `sendOtp(string $phoneNumber, string $purpose = 'verification'): array`

Generate and send OTP code.

**Parameters:**
- `$phoneNumber`: Recipient phone number
- `$purpose`: Purpose of OTP (`login`, `verification`, `admin_2fa`)

**Returns:**
```php
[
    'success' => true,
    'response' => $jsonResponse,
    'message_id' => '12345678'
]
```

**Example:**
```php
$result = $smsService->sendOtp('09123456789', 'login');
```

#### 4. `verifyOtp(string $phoneNumber, string $code, string $purpose = 'verification'): bool`

Verify OTP code.

**Parameters:**
- `$phoneNumber`: Phone number
- `$code`: OTP code to verify
- `$purpose`: Purpose of OTP

**Returns:** `true` if valid, `false` otherwise

**Example:**
```php
$isValid = $smsService->verifyOtp('09123456789', '123456', 'login');
```

#### 5. `sendPaymentNotification(string $phoneNumber, float $amount, string $currency = 'IRT'): array`

Send payment notification SMS.

**Parameters:**
- `$phoneNumber`: Recipient phone number
- `$amount`: Payment amount
- `$currency`: Currency code (default: `IRT`)

**Example:**
```php
$result = $smsService->sendPaymentNotification('09123456789', 100000, 'IRT');
```

### MelipayamakApi Methods

#### 1. SOAP Method (Default)

```php
$api = new MelipayamakApi($username, $password);
$sms = $api->sms(); // SOAP method

$response = $sms->send($to, $from, $message);
```

#### 2. REST Method

```php
$api = new MelipayamakApi($username, $password);
$sms = $api->sms('rest'); // REST method

// For simple SMS
$response = $sms->send($to, $from, $message);

// For template SMS
$response = $sms->sendByBaseNumber($text, $to, $bodyId);
```

---

## Facade Usage

### Service Provider

The `MelipayamakServiceProvider` registers the service:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Melipayamak\MelipayamakApi;

class MelipayamakServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('melipayamak', function ($app) {
            $username = config('services.melipayamk.token');
            $password = config('services.melipayamk.token');
            
            return new MelipayamakApi($username, $password);
        });
    }
}
```

### Facade Class

```php
<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Melipayamak extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'melipayamak';
    }
}
```

### Using the Facade

```php
<?php

use App\Facades\Melipayamak;

// Get SMS service
$sms = Melipayamak::sms();

// Send SMS
$response = $sms->send('09123456789', '50002710008883', 'Hello!');

// Decode response
$json = json_decode($response);

if ($json && $json->RetStatus == 1) {
    echo "Success! Message ID: " . $json->Value;
}
```

---

## Error Handling

### Response Format

#### Success Response

```json
{
    "RetStatus": 1,
    "StrRetStatus": "Ok",
    "Value": "12345678"
}
```

#### Error Response

```json
{
    "RetStatus": 0,
    "StrRetStatus": "UserNameAndPasswordFailed"
}
```

### Common Error Codes

| RetStatus | StrRetStatus | Description |
|-----------|--------------|-------------|
| 0 | UserNameAndPasswordFailed | Invalid credentials |
| 0 | InvalidPhoneNumber | Invalid phone number format |
| 0 | InsufficientCredit | Not enough credit in account |
| 0 | TemplateNotFound | Template ID not found |
| 0 | ParameterMismatch | Wrong number of parameters |

### Error Handling Example

```php
<?php

use App\Services\SmsService;

$smsService = new SmsService();
$result = $smsService->sendSms('09123456789', 'Hello!');

if (!$result['success']) {
    $error = $result['error'] ?? 'Unknown error';
    $response = $result['response'] ?? null;
    
    if ($response && isset($response->StrRetStatus)) {
        switch ($response->StrRetStatus) {
            case 'UserNameAndPasswordFailed':
                echo "Invalid credentials. Check your username and password.";
                break;
            case 'InsufficientCredit':
                echo "Not enough credit. Please recharge your account.";
                break;
            default:
                echo "Error: " . $response->StrRetStatus;
        }
    } else {
        echo "Error: " . $error;
    }
}
```

### Logging

All SMS operations are automatically logged:

```php
// Success log
Log::info('SMS sent via Melipayamk', [
    'melipayamak_username' => $this->username,
    'sending_data' => $sendingData,
    'response' => $json,
    'message_id' => $json->Value ?? null
]);

// Error log
Log::error('SMS sending failed via Melipayamak library', [
    'melipayamak_username' => $this->username,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString()
]);
```

---

## Testing

### Test Command

Use the artisan command to test SMS sending:

```bash
# Send test SMS
php artisan sms:test-melipayamak 09123456789

# Send test SMS with custom message
php artisan sms:test-melipayamak 09123456789 --message="Test message"

# Send OTP
php artisan sms:test-melipayamak 09123456789 --otp

# Send payment notification
php artisan sms:test-melipayamak 09123456789 --payment
```

### Test Script

Run the test script directly:

```bash
php test-melipayamak-facade.php
```

This script tests:
1. Melipayamak Facade
2. Updated SmsService
3. Direct MelipayamakApi usage

### Unit Testing

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SmsServiceTest extends TestCase
{
    public function test_send_sms()
    {
        $smsService = new SmsService();
        $result = $smsService->sendSms('09123456789', 'Test message');
        
        $this->assertArrayHasKey('success', $result);
    }
    
    public function test_send_otp()
    {
        $smsService = new SmsService();
        $result = $smsService->sendOtp('09123456789', 'login');
        
        $this->assertArrayHasKey('success', $result);
    }
    
    public function test_verify_otp()
    {
        $smsService = new SmsService();
        
        // Send OTP
        $sendResult = $smsService->sendOtp('09123456789', 'login');
        
        if ($sendResult['success']) {
            // Note: In real test, you'd need to get the actual OTP code
            // This is just an example
            $isValid = $smsService->verifyOtp('09123456789', '123456', 'login');
            $this->assertIsBool($isValid);
        }
    }
}
```

---

## Troubleshooting

### Common Issues

#### 1. "UserNameAndPasswordFailed" Error

**Problem**: Invalid credentials

**Solutions:**
- Verify username and password in `.env` file
- Check if credentials are correct in Melipayamak panel
- Ensure no extra spaces in credentials
- Try using API token instead of password

```env
# Check these values
MELIPAYAMAK_USERNAME=09136708883
MELIPAYAMAK_PASSWORD=YourPassword123
# OR
MELIPAYAMK_TOKEN=your_api_token_here
```

#### 2. "InsufficientCredit" Error

**Problem**: Not enough credit in Melipayamak account

**Solutions:**
- Login to Melipayamak panel
- Check account balance
- Recharge your account
- Verify payment status

#### 3. SMS Not Received

**Problem**: SMS sent but not received by user

**Solutions:**
- Check phone number format (should be 11 digits starting with 09)
- Verify sender number is approved
- Check Melipayamak panel for delivery status
- Verify network connectivity
- Check if number is blocked

#### 4. Template Not Found

**Problem**: Template ID error

**Solutions:**
- Verify template ID is correct
- Check if template is approved in Melipayamak panel
- Ensure template is active
- Verify parameter count matches template

```php
// Check template ID
$templateId = config('services.melipayamk.templates.verification');
// Should be: 372382
```

#### 5. Timeout Errors

**Problem**: Request timeout

**Solutions:**
- Check network connectivity
- Increase timeout settings
- Verify Melipayamak API status
- Check firewall settings

```php
// Increase timeout
ini_set('default_socket_timeout', 60);
```

#### 6. cURL Errors

**Problem**: cURL connection errors

**Solutions:**
- Verify SSL certificates
- Check firewall rules
- Verify API endpoint URL
- Check proxy settings

```php
// Disable SSL verification (development only)
curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
```

### Debug Mode

Enable detailed logging:

```php
// In SmsService
Log::info('SMS Debug', [
    'username' => $this->username,
    'password' => '***', // Don't log actual password
    'to' => $to,
    'from' => $from,
    'message' => $message,
    'response' => $response
]);
```

### Checking Configuration

```php
// Check current configuration
dd([
    'username' => config('melipayamak.username'),
    'password' => config('melipayamak.password') ? '***' : 'NOT SET',
    'token' => config('services.melipayamk.token') ? '***' : 'NOT SET',
    'sender' => config('services.melipayamk.sender'),
    'template' => config('services.melipayamk.templates.verification'),
]);
```

---

## Best Practices

### 1. Security

- ✅ Never commit credentials to version control
- ✅ Use environment variables for all sensitive data
- ✅ Rotate passwords/tokens regularly
- ✅ Use API tokens instead of passwords when possible
- ✅ Log operations but mask sensitive data

### 2. Error Handling

- ✅ Always check `success` flag in responses
- ✅ Implement retry logic for transient errors
- ✅ Log all errors for debugging
- ✅ Provide user-friendly error messages
- ✅ Handle rate limiting gracefully

### 3. Performance

- ✅ Use caching for OTP codes
- ✅ Implement rate limiting
- ✅ Use queue jobs for bulk SMS
- ✅ Monitor API response times
- ✅ Set appropriate timeouts

### 4. Code Organization

```php
// Good: Use service class
$smsService = new SmsService();
$result = $smsService->sendSms($to, $message);

// Bad: Direct API calls everywhere
$api = new MelipayamakApi($username, $password);
// ... scattered code
```

### 5. Testing

- ✅ Test with real phone numbers in development
- ✅ Mock SMS service in unit tests
- ✅ Test error scenarios
- ✅ Verify OTP expiration
- ✅ Test rate limiting

### 6. Monitoring

- ✅ Log all SMS operations
- ✅ Track success/failure rates
- ✅ Monitor account balance
- ✅ Alert on high error rates
- ✅ Track delivery times

---

## Configuration Summary

### Required Environment Variables

```env
# Primary Configuration (Recommended)
MELIPAYAMK_TOKEN=your_api_token_here
MELIPAYAMK_SENDER=50002710008883
MELIPAYAMK_VERIFICATION_TEMPLATE=372382

# Alternative Configuration (Legacy)
MELIPAYAMAK_USERNAME=09136708883
MELIPAYAMAK_PASSWORD=your_password_here

# SMS Provider Configuration
SMS_MELIPAYAMAK_ENABLED=true
SMS_MELIPAYAMAK_USERNAME=09136708883
SMS_MELIPAYAMAK_PASSWORD=your_password_here
SMS_MELIPAYAMAK_SENDER=50002710008883
```

### Current Production Values (Example)

```php
// Username
'username' => '09136708883'

// Password/Token
'password' => 'Prof48017421@#'

// API Token
'token' => '77c431b7-aec5-4313-b744-d2f16bf760ab'

// Sender Number
'sender' => '50002710008883'

// Verification Template
'verification_template' => 372382
```

**⚠️ Important**: These are example values. Replace with your actual credentials.

---

## API Endpoints

### Melipayamak API URLs

#### SOAP Endpoint
```
http://api.payamak-panel.com/post/send.asmx?wsdl
```

#### REST Endpoints
```
# Simple SMS
https://rest.payamak-panel.com/api/SendSMS/SendSMS

# Template SMS
https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber
```

### Request Format (REST)

```http
POST https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber
Content-Type: application/x-www-form-urlencoded

username=09136708883&password=YourPassword&text=123456&to=09123456789&bodyId=372382
```

---

## Support & Resources

### Official Resources

- **Website**: https://melipayamak.com
- **Panel**: https://panel.melipayamak.com
- **Documentation**: https://github.com/Melipayamak/melipayamak-php
- **Support**: support@melipayamak.com

### Internal Resources

- **Service Class**: `app/Services/SmsService.php`
- **Facade**: `app/Facades/Melipayamak.php`
- **Service Provider**: `app/Providers/MelipayamakServiceProvider.php`
- **Test Script**: `test-melipayamak-facade.php`
- **Test Command**: `app/Console/Commands/TestMelipayamakSms.php`

### Related Documentation

- `docs/SMS_TEMPLATE_INTEGRATION.md` - Template integration guide
- `docs/ENVIRONMENT_CONFIGURATION_GUIDE.md` - Environment setup

---

## Quick Reference

### Send Simple SMS
```php
$smsService = new SmsService();
$result = $smsService->sendSms('09123456789', 'Hello!');
```

### Send OTP
```php
$smsService = new SmsService();
$result = $smsService->sendOtp('09123456789', 'login');
```

### Verify OTP
```php
$smsService = new SmsService();
$isValid = $smsService->verifyOtp('09123456789', '123456', 'login');
```

### Send Template SMS
```php
$smsService = new SmsService();
$result = $smsService->sendSmsWithTemplate('09123456789', 372382, ['123456']);
```

### Using Facade
```php
use App\Facades\Melipayamak;

$sms = Melipayamak::sms();
$response = $sms->send('09123456789', '50002710008883', 'Hello!');
```

---

**Document Version:** 1.0  
**Last Updated:** 2024-01-15  
**Maintained By:** SarvCast Development Team

---

## Appendix: Complete Code Examples

### Example 1: Complete SMS Sending Flow

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SmsService;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    protected SmsService $smsService;
    
    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }
    
    public function sendSms(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|regex:/^09\d{9}$/',
            'message' => 'required|string|max:1000',
        ]);
        
        $result = $this->smsService->sendSms(
            $request->phone,
            $request->message
        );
        
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'SMS sent successfully',
                'message_id' => $result['message_id']
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to send SMS',
            'error' => $result['error'] ?? 'Unknown error'
        ], 500);
    }
}
```

### Example 2: OTP Authentication Flow

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SmsService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected SmsService $smsService;
    
    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }
    
    public function sendVerificationCode(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|regex:/^09\d{9}$/',
        ]);
        
        // Check rate limiting
        if ($this->smsService->hasTooManyAttempts($request->phone, 'login')) {
            return response()->json([
                'success' => false,
                'message' => 'Too many attempts. Please try again later.',
                'remaining_attempts' => 0
            ], 429);
        }
        
        // Send OTP
        $result = $this->smsService->sendOtp($request->phone, 'login');
        
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'کد تایید به شماره شما ارسال شد',
                'expires_in' => 300, // 5 minutes
                'remaining_attempts' => $this->smsService->getRemainingAttempts($request->phone, 'login')
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'خطا در ارسال کد تایید. لطفاً مجدداً تلاش کنید.',
            'error' => $result['error'] ?? 'Unknown error'
        ], 500);
    }
    
    public function verifyCode(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|regex:/^09\d{9}$/',
            'code' => 'required|string|size:6',
        ]);
        
        $isValid = $this->smsService->verifyOtp(
            $request->phone,
            $request->code,
            'login'
        );
        
        if ($isValid) {
            // Proceed with authentication
            return response()->json([
                'success' => true,
                'message' => 'کد تایید صحیح است'
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'کد تایید نامعتبر است',
            'remaining_attempts' => $this->smsService->getRemainingAttempts($request->phone, 'login')
        ], 400);
    }
}
```

---

**End of Documentation**

