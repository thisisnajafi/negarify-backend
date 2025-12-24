<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TokenTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider_id',
        'amount_tokens',
        'amount_usd',
        'type',
        'reference_id',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount_tokens' => 'decimal:2',
        'amount_usd' => 'decimal:2',
        'metadata' => 'array',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * Scope for purchase transactions
     */
    public function scopePurchases($query)
    {
        return $query->where('type', 'purchase');
    }

    /**
     * Scope for consumption transactions
     */
    public function scopeConsumptions($query)
    {
        return $query->where('type', 'consume');
    }

    /**
     * Scope for refund transactions
     */
    public function scopeRefunds($query)
    {
        return $query->where('type', 'refund');
    }

    /**
     * Check if transaction is a purchase
     */
    public function isPurchase(): bool
    {
        return $this->type === 'purchase';
    }

    /**
     * Check if transaction is a consumption
     */
    public function isConsumption(): bool
    {
        return $this->type === 'consume';
    }

    /**
     * Check if transaction is a refund
     */
    public function isRefund(): bool
    {
        return $this->type === 'refund';
    }
}
