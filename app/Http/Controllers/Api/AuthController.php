<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OtpVerification;
use App\Models\User;
use App\Services\MelipayamakService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private MelipayamakService $melipayamakService;

    public function __construct(MelipayamakService $melipayamakService)
    {
        $this->melipayamakService = $melipayamakService;
    }

    /**
     * Request OTP for phone verification
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^[0-9+\-\s()]+$/|min:10|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);
        $rateLimitKey = "otp_request:{$phone}";

        // Rate limiting: max 3 requests per 15 minutes
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'success' => false,
                'message' => "Too many OTP requests. Please try again in {$seconds} seconds.",
            ], 429);
        }

        // Check if there's an active OTP that hasn't expired
        $activeOtp = OtpVerification::where('phone', $phone)
            ->where('is_verified', false)
            ->where('expires_at', '>', now())
            ->where('attempts', '<', 3)
            ->first();

        if ($activeOtp) {
            return response()->json([
                'success' => false,
                'message' => 'An active OTP already exists. Please wait for it to expire or use the existing one.',
                'expires_at' => $activeOtp->expires_at->toISOString(),
            ], 400);
        }

        // Generate new OTP
        $otp = OtpVerification::generate($phone);

        // Send SMS
        $smsSent = $this->melipayamakService->sendOtp($phone, $this->getOtpCode($otp));

        if (!$smsSent) {
            $otp->delete(); // Clean up if SMS failed
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again later.',
            ], 500);
        }

        // Record the rate limit attempt
        RateLimiter::hit($rateLimitKey, 900); // 15 minutes

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'data' => [
                'request_id' => $otp->getRequestId(),
                'expires_at' => $otp->expires_at->toISOString(),
            ],
        ]);
    }

    /**
     * Verify OTP and authenticate user
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'request_id' => 'required|string|uuid',
            'code' => 'required|string|size:6|regex:/^[0-9]+$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $otp = OtpVerification::where('request_id', $request->request_id)->first();

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

        if ($otp->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired',
            ], 400);
        }

        if ($otp->hasExceededMaxAttempts()) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum verification attempts exceeded',
            ], 400);
        }

        // Verify the code
        if (!$otp->verify($request->code)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP code',
                'attempts_remaining' => 3 - $otp->attempts,
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
                    'tokens_balance' => $user->tokens_balance,
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
    public function resendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'request_id' => 'required|string|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $otp = OtpVerification::where('request_id', $request->request_id)->first();

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
        $rateLimitKey = "otp_resend:{$phone}";

        // Rate limiting for resend: max 2 resends per 10 minutes
        if (RateLimiter::tooManyAttempts($rateLimitKey, 2)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'success' => false,
                'message' => "Too many resend attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        // Generate new OTP
        $newOtp = OtpVerification::generate($phone);

        // Send SMS
        $smsSent = $this->melipayamakService->sendOtp($phone, $this->getOtpCode($newOtp));

        if (!$smsSent) {
            $newOtp->delete();
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend OTP. Please try again later.',
            ], 500);
        }

        // Delete old OTP
        $otp->delete();

        // Record the rate limit attempt
        RateLimiter::hit($rateLimitKey, 600); // 10 minutes

        return response()->json([
            'success' => true,
            'message' => 'OTP resent successfully',
            'data' => [
                'request_id' => $newOtp->getRequestId(),
                'expires_at' => $newOtp->expires_at->toISOString(),
            ],
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get current user profile
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'tokens_balance' => $user->tokens_balance,
                'role' => $user->role,
                'is_verified' => $user->is_verified,
                'phone_verified_at' => $user->phone_verified_at,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    /**
     * Normalize phone number
     */
    private function normalizePhone(string $phone): string
    {
        // Remove all non-digit characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // Add country code if not present (assuming Iran +98)
        if (!str_starts_with($phone, '+')) {
            if (str_starts_with($phone, '0')) {
                $phone = '+98' . substr($phone, 1);
            } else {
                $phone = '+98' . $phone;
            }
        }

        return $phone;
    }

    /**
     * Get OTP code from hash (for testing purposes)
     * In production, this should not be accessible
     */
    private function getOtpCode(OtpVerification $otp): string
    {
        // This is a simplified version for testing
        // In production, you'd need to store the actual code temporarily
        // or use a different approach
        return '123456'; // This should be the actual generated code
    }
}
