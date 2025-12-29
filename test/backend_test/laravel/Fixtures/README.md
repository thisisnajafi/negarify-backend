# Test Fixtures

This directory contains sample data files used in tests for external services and APIs.

## Files

### `tgju_sample.html`
Sample HTML page from TGJU (Tehran Gold and Jewelry Union) used for testing currency rate scraping. Contains sample USD to IRR exchange rate data.

### `segmind_image_response.json`
Sample JSON response from Segmind API for image generation requests. Used to mock successful image generation responses.

### `segmind_video_response.json`
Sample JSON response from Segmind API for video generation requests. Used to mock successful video generation responses.

### `segmind_audio_response.json`
Sample JSON response from Segmind API for audio generation requests. Used to mock successful audio generation responses.

### `zarinpal_payment_response.json`
Sample JSON response from Zarinpal payment gateway for payment request. Contains authority code for redirecting user to payment page.

### `zarinpal_verification_response.json`
Sample JSON response from Zarinpal payment gateway for payment verification. Contains ref_id after successful payment.

## Usage

These fixtures are loaded in tests using:

```php
$html = file_get_contents(base_path('test/backend_test/laravel/Fixtures/tgju_sample.html'));
$json = json_decode(file_get_contents(base_path('test/backend_test/laravel/Fixtures/segmind_image_response.json')), true);
```

## Updating Fixtures

When external APIs change their response format, update the corresponding fixture files to match the new structure. This ensures tests continue to work with the updated API contracts.

