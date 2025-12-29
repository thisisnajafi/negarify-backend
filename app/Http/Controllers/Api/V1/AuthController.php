<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\OtpSender;
use App\Exceptions\SmsProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RequestOtpRequest;
use App\Http\Requests\Api\V1\ResendOtpRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Models\OtpVerification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function __construct(
        private readonly OtpSender $otpSender
    ) {
    }

    /**
     * Request OTP for phone verification
     */
    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        $phone = $this->normalizePhone($request->validated()['phone']);
        $phoneHash = $this->hashPhone($phone);
        $rateLimitKey = "otp_request:{$phoneHash}";

        // Rate limiting: max 3 requests per 15 minutes
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            
            Log::warning('OTP request rate limited', [
                'phone_hash' => $phoneHash,
                'retry_after' => $seconds,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => "Too many OTP requests. Please try again in {$seconds} seconds.",
            ], 429);
        }

        // Check if there's an active OTP that hasn't expired
        $activeOtp = OtpVerification::where('phone', $phone)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->whereColumn('attempts', '<', 'max_attempts')
            ->first();

        if ($activeOtp) {
            return response()->json([
                'success' => false,
                'message' => 'An active OTP already exists. Please wait for it to expire or use the existing one.',
                'data' => [
                    'request_id' => $activeOtp->request_id,
                    'expires_at' => $activeOtp->expires_at->toISOString(),
                ],
            ], 400);
        }

        // Generate new OTP (returns array with 'otp' and 'code')
        $result = OtpVerification::generate($phone);
        $otp = $result['otp'];
        $code = $result['code'];

        // Send SMS via OtpSender interface
        try {
            $this->otpSender->sendOtp($phone, $code);
        } catch (SmsProviderException $e) {
            $otp->delete(); // Clean up if SMS failed
            
            Log::error('OTP SMS send failed', [
                'phone_hash' => $phoneHash,
                'request_id' => $otp->request_id,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again later.',
            ], 500);
        }

        // Record the rate limit attempt
        RateLimiter::hit($rateLimitKey, 900); // 15 minutes

        Log::info('OTP requested', [
            'phone_hash' => $phoneHash,
            'request_id' => $otp->request_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'data' => [
                'request_id' => $otp->request_id,
                'expires_at' => $otp->expires_at->toISOString(),
            ],
        ]);
    }

    /**
     * Verify OTP and authenticate user
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $requestId = $validated['request_id'];
        $code = $validated['code'];

        $otp = OtpVerification::where('request_id', $requestId)->first();

        if (!$otp) {
            Log::warning('OTP verification failed - invalid request_id', [
                'request_id' => $requestId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid request ID',
            ], 404);
        }

        $phoneHash = $this->hashPhone($otp->phone);

        if ($otp->isVerified()) {
            Log::warning('OTP verification failed - already verified', [
                'phone_hash' => $phoneHash,
                'request_id' => $requestId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'OTP has already been verified',
            ], 400);
        }

        if ($otp->isExpired()) {
            Log::warning('OTP verification failed - expired', [
                'phone_hash' => $phoneHash,
                'request_id' => $requestId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired',
            ], 400);
        }

        if ($otp->hasExceededMaxAttempts()) {
            Log::warning('OTP verification failed - max attempts exceeded', [
                'phone_hash' => $phoneHash,
                'request_id' => $requestId,
                'attempts' => $otp->attempts,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Maximum verification attempts exceeded',
            ], 400);
        }

        // Verify the code
        if (!$otp->verify($code)) {
            Log::warning('OTP verification failed - invalid code', [
                'phone_hash' => $phoneHash,
                'request_id' => $requestId,
                'attempts' => $otp->attempts,
                'attempts_remaining' => $otp->max_attempts - $otp->attempts,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP code',
                'data' => [
                    'attempts_remaining' => max(0, $otp->max_attempts - $otp->attempts),
                ],
            ], 400);
        }

        // Find or create user
        $user = User::firstOrCreate(
            ['phone' => $otp->phone],
            [
                'is_verified' => true,
                'phone_verified_at' => now(),
                'tokens_balance' => 0,
                'role' => 'user',
            ]
        );

        // Update user verification status if not already verified
        if (!$user->is_verified) {
            $user->update([
                'is_verified' => true,
                'phone_verified_at' => now(),
            ]);
        }

        // Create API token
        $token = $user->createToken('auth-token')->plainTextToken;

        Log::info('OTP verified successfully', [
            'phone_hash' => $phoneHash,
            'request_id' => $requestId,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'phone' => $user->phone,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'tokens_balance' => (float) $user->tokens_balance,
                    'role' => $user->role,
                    'is_verified' => $user->is_verified,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Resend OTP
     */
    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $requestId = $request->validated()['request_id'];

        $otp = OtpVerification::where('request_id', $requestId)->first();

        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request ID',
            ], 404);
        }

        if ($otp->isVerified()) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has already been verified',
            ], 400);
        }

        $phone = $otp->phone;
        $phoneHash = $this->hashPhone($phone);
        $rateLimitKey = "otp_resend:{$phoneHash}";

        // Rate limiting for resend: max 2 resends per 10 minutes
        if (RateLimiter::tooManyAttempts($rateLimitKey, 2)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            
            Log::warning('OTP resend rate limited', [
                'phone_hash' => $phoneHash,
                'request_id' => $requestId,
                'retry_after' => $seconds,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => "Too many resend attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        // Generate new OTP
        $result = OtpVerification::generate($phone);
        $newOtp = $result['otp'];
        $code = $result['code'];

        // Send SMS via OtpSender interface
        try {
            $this->otpSender->sendOtp($phone, $code);
        } catch (SmsProviderException $e) {
            $newOtp->delete();
            
            Log::error('OTP resend SMS send failed', [
                'phone_hash' => $phoneHash,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend OTP. Please try again later.',
            ], 500);
        }

        // Delete old OTP
        $otp->delete();

        // Record the rate limit attempt
        RateLimiter::hit($rateLimitKey, 600); // 10 minutes

        Log::info('OTP resent', [
            'phone_hash' => $phoneHash,
            'old_request_id' => $requestId,
            'new_request_id' => $newOtp->request_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OTP resent successfully',
            'data' => [
                'request_id' => $newOtp->request_id,
                'expires_at' => $newOtp->expires_at->toISOString(),
            ],
        ]);
    }

    /**
     * Logout user
     */
    public function logout(): JsonResponse
    {
        $user = auth()->user();
        $user->currentAccessToken()->delete();

        Log::info('User logged out', [
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Normalize phone number to consistent format (Iranian: 09...)
     */
    private function normalizePhone(string $phone): string
    {
        // Remove all non-digit characters
        $phone = preg_replace('/[^\d]/', '', $phone);
        
        // Convert to Iranian format (09...)
        if (str_starts_with($phone, '98')) {
            // Remove country code and add 0
            $phone = '0' . substr($phone, 2);
        } elseif (!str_starts_with($phone, '0')) {
            // Add leading 0 if missing
            $phone = '0' . $phone;
        }

        // Ensure it's 11 digits (09 + 9 digits)
        if (strlen($phone) !== 11) {
            throw new \InvalidArgumentException('Invalid phone number format');
        }

        return $phone;
    }

    /**
     * Hash phone number for logging (privacy protection)
     */
    private function hashPhone(string $phone): string
    {
        return hash('sha256', $phone . config('app.key'));
    }
}

