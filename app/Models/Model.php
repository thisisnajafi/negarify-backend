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
        'model_type',
        'api_endpoint',
        'quality_profile',
        'base_cost_usd',
        'default_tokens',
        'supports_size',
        'supports_style',
        'max_resolution',
        'enabled',
    ];

    protected $casts = [
        'base_cost_usd' => 'decimal:4',
        'default_tokens' => 'integer',
        'supports_size' => 'boolean',
        'supports_style' => 'boolean',
        'enabled' => 'boolean',
    ];

    // Relationships
    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function generationJobs()
    {
        return $this->hasMany(GenerationJob::class);
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

    /**
     * Scope for models by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('model_type', $type);
    }

    /**
     * Scope for image models
     */
    public function scopeImages($query)
    {
        return $query->where('model_type', 'image');
    }

    /**
     * Scope for video models
     */
    public function scopeVideos($query)
    {
        return $query->where('model_type', 'video');
    }

    /**
     * Scope for audio models
     */
    public function scopeAudio($query)
    {
        return $query->where('model_type', 'audio');
    }
}
