<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ImageJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider_id',
        'model_id',
        'prompt',
        'params_json',
        'status',
        'tokens_consumed',
        'cost_usd',
        'result_url',
        'error_message',
        'job_id',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'params_json' => 'array',
        'tokens_consumed' => 'integer',
        'cost_usd' => 'decimal:4',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function model()
    {
        return $this->belongsTo(Model::class);
    }

    public function galleryPost()
    {
        return $this->hasOne(GalleryPost::class);
    }

    /**
     * Check if job is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if job failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if job is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if job is processing
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Mark job as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark job as completed
     */
    public function markAsCompleted(string $resultUrl): void
    {
        $this->update([
            'status' => 'completed',
            'result_url' => $resultUrl,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark job as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }
}
