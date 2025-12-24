<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Model extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'model_name',
        'quality_profile',
        'base_cost_usd',
        'default_tokens',
        'supported_sizes',
        'supported_styles',
        'enabled',
    ];

    protected $casts = [
        'base_cost_usd' => 'decimal:4',
        'default_tokens' => 'integer',
        'supported_sizes' => 'array',
        'supported_styles' => 'array',
        'enabled' => 'boolean',
    ];

    // Relationships
    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function imageJobs()
    {
        return $this->hasMany(ImageJob::class);
    }

    public function analyticsUsage()
    {
        return $this->hasMany(AnalyticsModelsUsage::class);
    }

    /**
     * Check if model is available
     */
    public function isAvailable(): bool
    {
        return $this->enabled && $this->provider->isAvailable();
    }

    /**
     * Get token cost for this model
     */
    public function getTokenCost(): int
    {
        return $this->default_tokens;
    }

    /**
     * Get supported sizes
     */
    public function getSupportedSizes(): array
    {
        return $this->supported_sizes ?? [];
    }

    /**
     * Get supported styles
     */
    public function getSupportedStyles(): array
    {
        return $this->supported_styles ?? [];
    }

    /**
     * Check if size is supported
     */
    public function supportsSize(string $size): bool
    {
        return in_array($size, $this->getSupportedSizes());
    }

    /**
     * Check if style is supported
     */
    public function supportsStyle(string $style): bool
    {
        return in_array($style, $this->getSupportedStyles());
    }

    /**
     * Scope for available models
     */
    public function scopeAvailable($query)
    {
        return $query->where('enabled', true)
            ->whereHas('provider', function ($q) {
                $q->where('enabled', true);
            });
    }

    /**
     * Scope for models by provider
     */
    public function scopeByProvider($query, $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    /**
     * Scope for models by quality
     */
    public function scopeByQuality($query, $quality)
    {
        return $query->where('quality_profile', $quality);
    }
}
