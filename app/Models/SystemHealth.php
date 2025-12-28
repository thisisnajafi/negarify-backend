<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SystemHealth extends Model
{
    use HasFactory;

    protected $fillable = [
        'metric_name',
        'metric_value',
        'metric_unit',
        'provider_id',
        'recorded_at',
    ];

    protected $casts = [
        'metric_value' => 'decimal:4',
        'recorded_at' => 'datetime',
    ];

    // Relationships
    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    // Scopes
    public function scopeByMetric($query, string $metricName)
    {
        return $query->where('metric_name', $metricName);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('recorded_at', 'desc');
    }

    public function scopeForProvider($query, ?int $providerId)
    {
        if ($providerId) {
            return $query->where('provider_id', $providerId);
        }
        return $query->whereNull('provider_id');
    }

    /**
     * Get latest metric value
     */
    public static function getLatestMetric(string $metricName, ?int $providerId = null): ?float
    {
        $query = self::where('metric_name', $metricName)
            ->latest('recorded_at');

        if ($providerId) {
            $query->where('provider_id', $providerId);
        } else {
            $query->whereNull('provider_id');
        }

        $latest = $query->first();
        return $latest ? (float) $latest->metric_value : null;
    }
}

