<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Provider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'api_base_url',
        'api_key_encrypted',
        'cost_per_image_usd',
        'cost_per_video_usd',
        'cost_per_audio_usd',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'cost_per_image_usd' => 'decimal:4',
        'cost_per_video_usd' => 'decimal:4',
        'cost_per_audio_usd' => 'decimal:4',
    ];

    // Relationships
    public function models()
    {
        return $this->hasMany(Model::class);
    }

    public function generationJobs()
    {
        return $this->hasMany(GenerationJob::class);
    }

    public function systemHealth()
    {
        return $this->hasMany(SystemHealth::class);
    }

    /**
     * Get decrypted API key (accessor)
     */
    public function getApiKeyAttribute(): ?string
    {
        if (!$this->api_key_encrypted) {
            return null;
        }
        try {
            return decrypt($this->attributes['api_key_encrypted']);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set encrypted API key (mutator)
     */
    public function setApiKeyEncryptedAttribute($value): void
    {
        if ($value && !empty($value)) {
            $this->attributes['api_key_encrypted'] = encrypt($value);
        }
    }

    /**
     * Get decrypted API key (method)
     */
    public function getApiKey(): ?string
    {
        return $this->api_key;
    }

    /**
     * Set encrypted API key (method)
     */
    public function setApiKey(string $key): void
    {
        $this->api_key_encrypted = $key;
    }

    /**
     * Check if provider is available
     */
    public function isAvailable(): bool
    {
        return $this->enabled;
    }

    // Scopes
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
