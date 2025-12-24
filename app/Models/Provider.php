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
        'cost_per_image_usd_override',
        'enabled',
        'config',
        'priority',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'cost_per_image_usd_override' => 'decimal:4',
        'config' => 'array',
        'priority' => 'integer',
    ];

    // Relationships
    public function models()
    {
        return $this->hasMany(Model::class);
    }

    public function imageJobs()
    {
        return $this->hasMany(ImageJob::class);
    }

    public function tokenTransactions()
    {
        return $this->hasMany(TokenTransaction::class);
    }

    /**
     * Get decrypted API key
     */
    public function getApiKey(): string
    {
        return decrypt($this->api_key_encrypted);
    }

    /**
     * Set encrypted API key
     */
    public function setApiKey(string $key): void
    {
        $this->api_key_encrypted = encrypt($key);
    }

    /**
     * Check if provider is available
     */
    public function isAvailable(): bool
    {
        return $this->enabled;
    }
}
