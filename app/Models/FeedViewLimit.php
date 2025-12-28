<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FeedViewLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'content_type',
        'views_remaining',
        'daily_limit',
        'reset_at',
    ];

    protected $casts = [
        'views_remaining' => 'integer',
        'daily_limit' => 'integer',
        'reset_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Methods
    public function hasViewsRemaining(): bool
    {
        return $this->views_remaining > 0;
    }

    public function decrementViews(): void
    {
        if ($this->views_remaining > 0) {
            $this->decrement('views_remaining');
        }
    }

    public function reset(): void
    {
        $this->update([
            'views_remaining' => $this->daily_limit,
            'reset_at' => now(),
        ]);
    }

    // Scopes
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('content_type', $type);
    }

    public function scopeImages($query)
    {
        return $query->where('content_type', 'image');
    }

    public function scopeVideos($query)
    {
        return $query->where('content_type', 'video');
    }
}

