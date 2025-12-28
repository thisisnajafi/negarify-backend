<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token_bundle_id',
        'amount_tokens',
        'price_toman',
        'price_usd',
        'dollar_rate',
        'zarinpal_authority',
        'zarinpal_ref_id',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount_tokens' => 'integer',
        'price_toman' => 'decimal:2',
        'price_usd' => 'decimal:2',
        'dollar_rate' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tokenBundle()
    {
        return $this->belongsTo(TokenBundle::class);
    }

    // Relationships
    public function tokenTransactions()
    {
        return $this->hasMany(TokenTransaction::class);
    }

    /**
     * Check if order is paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if order is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if order is failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if order is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Scope for paid orders
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope for pending orders
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for failed orders
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
