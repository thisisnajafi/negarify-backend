<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CurrencyRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'currency_from',
        'currency_to',
        'rate',
        'source',
        'fetched_at',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'fetched_at' => 'datetime',
    ];

    // Methods
    /**
     * Get rate in Toman (divide Rials by 10)
     */
    public function getRateInTomanAttribute(): float
    {
        return $this->rate / 10;
    }

    // Scopes
    public function scopeLatest($query)
    {
        return $query->orderBy('fetched_at', 'desc');
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Get the latest USD to Toman rate
     */
    public static function getLatestUsdToTomanRate(): ?float
    {
        $latest = self::where('currency_from', 'USD')
            ->where('currency_to', 'IRR')
            ->latest('fetched_at')
            ->first();

        return $latest ? $latest->rate_in_toman : null;
    }
}

