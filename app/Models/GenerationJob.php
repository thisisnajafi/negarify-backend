<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GenerationJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider_id',
        'model_id',
        'job_type',
        'prompt',
        'negative_prompt',
        'params_json',
        'status',
        'tokens_consumed',
        'cost_usd',
        'segmind_job_id',
        'result_url',
        'result_thumbnail_url',
        'error_message',
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

    public function tokenTransaction()
    {
        return $this->hasOne(TokenTransaction::class);
    }

    // Methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('job_type', $type);
    }

    public function scopeImages($query)
    {
        return $query->where('job_type', 'image');
    }

    public function scopeVideos($query)
    {
        return $query->where('job_type', 'video');
    }

    public function scopeAudio($query)
    {
        return $query->where('job_type', 'audio');
    }
}

