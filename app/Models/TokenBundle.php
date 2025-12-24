<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TokenBundle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'tokens',
        'price_usd',
        'discount_percentage',
        'description',
        'is_active',
        'is_popular',
        'sort_order',
    ];

    protected $casts = [
        'tokens' => 'integer',
        'price_usd' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the effective price after discount
     */
    public function getEffectivePriceAttribute(): float
    {
        if ($this->discount_percentage > 0) {
            return $this->price_usd * (1 - $this->discount_percentage / 100);
        }
        
        return $this->price_usd;
    }

    /**
     * Get the savings amount
     */
    public function getSavingsAttribute(): float
    {
        return $this->price_usd - $this->effective_price;
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
     * Scope for popular bundles
     */
    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    /**
     * Scope for ordering by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('tokens');
    }
}
