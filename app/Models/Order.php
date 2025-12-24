<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token_bundle_id',
        'order_number',
        'tokens',
        'amount_usd',
        'status',
        'payment_method',
        'payment_id',
        'payment_details',
        'completed_at',
    ];

    protected $casts = [
        'tokens' => 'integer',
        'amount_usd' => 'decimal:2',
        'payment_details' => 'array',
        'completed_at' => 'datetime',
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

    /**
     * Generate unique order number
     */
    public static function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-' . strtoupper(Str::random(8));
        } while (self::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    /**
     * Create new order
     */
    public static function createOrder(User $user, TokenBundle $bundle, string $paymentMethod = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'order_number' => self::generateOrderNumber(),
            'tokens' => $bundle->tokens,
            'amount_usd' => $bundle->effective_price,
            'status' => 'pending',
            'payment_method' => $paymentMethod,
        ]);
    }

    /**
     * Mark order as completed
     */
    public function markAsCompleted(string $paymentId = null, array $paymentDetails = []): void
    {
        $this->update([
            'status' => 'completed',
            'payment_id' => $paymentId,
            'payment_details' => $paymentDetails,
            'completed_at' => now(),
        ]);

        // Add tokens to user balance
        $this->user->addTokens($this->tokens);

        // Create token transaction record
        TokenTransaction::create([
            'user_id' => $this->user_id,
            'amount_tokens' => $this->tokens,
            'amount_usd' => $this->amount_usd,
            'type' => 'purchase',
            'reference_id' => $this->order_number,
            'description' => "Token purchase - {$this->tokenBundle->name}",
            'metadata' => [
                'bundle_id' => $this->token_bundle_id,
                'payment_method' => $this->payment_method,
                'payment_id' => $paymentId,
            ],
        ]);
    }

    /**
     * Mark order as failed
     */
    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'payment_details' => array_merge($this->payment_details ?? [], [
                'failure_reason' => $reason,
                'failed_at' => now()->toISOString(),
            ]),
        ]);
    }

    /**
     * Mark order as refunded
     */
    public function markAsRefunded(string $refundId = null, string $reason = null): void
    {
        $this->update([
            'status' => 'refunded',
            'payment_details' => array_merge($this->payment_details ?? [], [
                'refund_id' => $refundId,
                'refund_reason' => $reason,
                'refunded_at' => now()->toISOString(),
            ]),
        ]);

        // Deduct tokens from user balance
        $this->user->deductTokens($this->tokens);

        // Create refund transaction record
        TokenTransaction::create([
            'user_id' => $this->user_id,
            'amount_tokens' => -$this->tokens,
            'amount_usd' => -$this->amount_usd,
            'type' => 'refund',
            'reference_id' => $this->order_number,
            'description' => "Token refund - {$this->tokenBundle->name}",
            'metadata' => [
                'bundle_id' => $this->token_bundle_id,
                'refund_id' => $refundId,
                'refund_reason' => $reason,
            ],
        ]);
    }

    /**
     * Check if order is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
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
     * Check if order is refunded
     */
    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    /**
     * Scope for completed orders
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
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
