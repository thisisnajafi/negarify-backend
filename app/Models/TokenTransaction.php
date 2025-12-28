<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TokenTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'generation_job_id',
        'amount_tokens',
        'amount_usd',
        'type',
        'reference_id',
        'description',
    ];

    protected $casts = [
        'amount_tokens' => 'integer',
        'amount_usd' => 'decimal:4',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function generationJob()
    {
        return $this->belongsTo(GenerationJob::class);
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
     * Scope for bonus transactions
     */
    public function scopeBonuses($query)
    {
        return $query->where('type', 'bonus');
    }

    /**
     * Scope for adjustment transactions
     */
    public function scopeAdjustments($query)
    {
        return $query->where('type', 'adjustment');
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
