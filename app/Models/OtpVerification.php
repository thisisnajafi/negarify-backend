<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class OtpVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'code_hash',
        'request_id',
        'attempts',
        'max_attempts',
        'expires_at',
        'verified_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * Generate OTP code and create verification record
     * Uses HMAC-SHA256 for hashing (fast, secure for short-lived codes)
     * 
     * @param string $phone Phone number
     * @return array ['otp' => OtpVerification, 'code' => string] Returns OTP record and plaintext code
     */
    public static function generate(string $phone): array
    {
        // Generate 6-digit cryptographically secure random code
        $code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $requestId = Str::uuid()->toString();
        
        // Hash OTP using HMAC-SHA256 (not bcrypt - OTPs are short-lived)
        $codeHash = hash_hmac('sha256', $code, config('app.key'));
        
        $otp = self::create([
            'phone' => $phone,
            'code_hash' => $codeHash,
            'request_id' => $requestId,
            'max_attempts' => 3,
            'expires_at' => now()->addMinutes(5),
        ]);
        
        // Return both OTP record and plaintext code (code only used for SMS, never stored)
        return ['otp' => $otp, 'code' => $code];
    }

    /**
     * Verify OTP code using constant-time comparison (prevents timing attacks)
     */
    public function verify(string $code): bool
    {
        if ($this->isVerified() || $this->isExpired()) {
            return false;
        }

        if ($this->hasExceededMaxAttempts()) {
            return false;
        }

        // Increment attempts atomically (before verification to prevent race conditions)
        $this->increment('attempts');

        // Verify using HMAC with constant-time comparison
        $codeHash = hash_hmac('sha256', $code, config('app.key'));
        
        // Use hash_equals for constant-time comparison (prevents timing attacks)
        if (hash_equals($this->code_hash, $codeHash)) {
            $this->update([
                'verified_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Check if OTP is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if OTP is verified
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Check if OTP has exceeded max attempts
     */
    public function hasExceededMaxAttempts(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    /**
     * Get the request ID for this OTP
     */
    public function getRequestId(): string
    {
        return $this->request_id;
    }
}
