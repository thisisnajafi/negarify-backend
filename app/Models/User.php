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
    public function generationJobs()
    {
        return $this->hasMany(GenerationJob::class);
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

    public function feedViewLimits()
    {
        return $this->hasMany(FeedViewLimit::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id');
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id');
    }

    // Scopes
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeModerators($query)
    {
        return $query->whereIn('role', ['admin', 'moderator']);
    }
}
