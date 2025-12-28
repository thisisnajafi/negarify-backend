<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnalyticsModelsUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_id',
        'job_type',
        'requests_count',
        'successful_count',
        'failed_count',
        'tokens_consumed',
        'cost_usd',
        'revenue_usd',
        'avg_latency_ms',
        'period_type',
        'period_start',
        'period_end',
    ];

    protected $casts = [
        'requests_count' => 'integer',
        'successful_count' => 'integer',
        'failed_count' => 'integer',
        'tokens_consumed' => 'integer',
        'cost_usd' => 'decimal:4',
        'revenue_usd' => 'decimal:4',
        'avg_latency_ms' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    // Relationships
    public function model()
    {
        return $this->belongsTo(Model::class);
    }

    // Methods
    public function getSuccessRateAttribute(): float
    {
        if ($this->requests_count === 0) {
            return 0;
        }
        return $this->successful_count / $this->requests_count;
    }

    public function getFailureRateAttribute(): float
    {
        if ($this->requests_count === 0) {
            return 0;
        }
        return $this->failed_count / $this->requests_count;
    }

    // Scopes
    public function scopeByModel($query, int $modelId)
    {
        return $query->where('model_id', $modelId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('job_type', $type);
    }

    public function scopeByPeriodType($query, string $periodType)
    {
        return $query->where('period_type', $periodType);
    }

    public function scopeDaily($query)
    {
        return $query->where('period_type', 'daily');
    }

    public function scopeWeekly($query)
    {
        return $query->where('period_type', 'weekly');
    }

    public function scopeMonthly($query)
    {
        return $query->where('period_type', 'monthly');
    }
}
