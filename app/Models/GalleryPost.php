<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GalleryPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'generation_job_id',
        'title',
        'description',
        'tags_json',
        'visibility',
        'is_curated',
        'is_featured',
        'likes_count',
        'comments_count',
        'views_count',
        'prompt_visible',
        'model_visible',
        'curated_at',
    ];

    protected $casts = [
        'tags_json' => 'array',
        'is_curated' => 'boolean',
        'is_featured' => 'boolean',
        'likes_count' => 'integer',
        'comments_count' => 'integer',
        'views_count' => 'integer',
        'prompt_visible' => 'boolean',
        'model_visible' => 'boolean',
        'curated_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function generationJob()
    {
        return $this->belongsTo(GenerationJob::class);
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function moderationQueue()
    {
        return $this->hasOne(ModerationQueue::class);
    }

    // Methods
    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    public function isPrivate(): bool
    {
        return $this->visibility === 'private';
    }

    public function isCurated(): bool
    {
        return $this->is_curated;
    }

    public function isFeatured(): bool
    {
        return $this->is_featured;
    }

    // Scopes
    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    public function scopePrivate($query)
    {
        return $query->where('visibility', 'private');
    }

    public function scopeCurated($query)
    {
        return $query->where('is_curated', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeForFeed($query)
    {
        return $query->where('is_curated', true)
            ->where('visibility', 'public');
    }
}
