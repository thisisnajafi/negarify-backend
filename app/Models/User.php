<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'phone',
        'email',
        'name',
        'avatar_url',
        'password',
        'is_verified',
        'tokens_balance',
        'role',
        'phone_verified_at',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'tokens_balance' => 'decimal:2',
            'is_verified' => 'boolean',
        ];
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is moderator
     */
    public function isModerator(): bool
    {
        return in_array($this->role, ['admin', 'moderator']);
    }

    /**
     * Check if user has sufficient tokens
     */
    public function hasTokens(int $amount): bool
    {
        return $this->tokens_balance >= $amount;
    }

    /**
     * Deduct tokens from user balance
     */
    public function deductTokens(int $amount): bool
    {
        if (!$this->hasTokens($amount)) {
            return false;
        }
        
        $this->tokens_balance -= $amount;
        return $this->save();
    }

    /**
     * Add tokens to user balance
     */
    public function addTokens(int $amount): bool
    {
        $this->tokens_balance += $amount;
        return $this->save();
    }

    // Relationships
    public function imageJobs()
    {
        return $this->hasMany(ImageJob::class);
    }

    public function galleryPosts()
    {
        return $this->hasMany(GalleryPost::class);
    }

    public function tokenTransactions()
    {
        return $this->hasMany(TokenTransaction::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'seller_id');
    }

    public function purchases()
    {
        return $this->hasMany(Sale::class, 'buyer_id');
    }

    public function otpVerifications()
    {
        return $this->hasMany(OtpVerification::class, 'phone', 'phone');
    }
}
