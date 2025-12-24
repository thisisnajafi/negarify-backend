<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OtpVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'code_hash',
        'request_id',
        'attempts',
        'is_verified',
        'expires_at',
        'verified_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * Generate OTP code and create verification record
     */
    public static function generate(string $phone): self
    {
        $code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $requestId = Str::uuid()->toString();
        
        return self::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'request_id' => $requestId,
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    /**
     * Verify OTP code
     */
    public function verify(string $code): bool
    {
        if ($this->is_verified || $this->isExpired()) {
            return false;
        }

        if ($this->attempts >= 3) {
            return false;
        }

        $this->increment('attempts');

        if (Hash::check($code, $this->code_hash)) {
            $this->update([
                'is_verified' => true,
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
        return $this->is_verified;
    }

    /**
     * Check if OTP has exceeded max attempts
     */
    public function hasExceededMaxAttempts(): bool
    {
        return $this->attempts >= 3;
    }

    /**
     * Get the request ID for this OTP
     */
    public function getRequestId(): string
    {
        return $this->request_id;
    }
}
