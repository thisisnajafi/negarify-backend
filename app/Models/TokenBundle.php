<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TokenBundle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'token_amount',
        'price_usd',
        'bonus_tokens',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'token_amount' => 'integer',
        'price_usd' => 'decimal:2',
        'bonus_tokens' => 'integer',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    // Relationships
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get total tokens including bonus
     */
    public function getTotalTokensAttribute(): int
    {
        return $this->token_amount + $this->bonus_tokens;
    }

    /**
     * Check if bundle is available for purchase
     */
    public function isAvailable(): bool
    {
        return $this->is_active;
    }

    /**
     * Scope for active bundles
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordering by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('token_amount');
    }
}
