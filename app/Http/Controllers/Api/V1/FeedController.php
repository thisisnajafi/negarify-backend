<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CopyModelRequest;
use App\Http\Requests\Api\V1\CopyPromptRequest;
use App\Http\Requests\Api\V1\FeedListRequest;
use App\Models\FeedViewLimit;
use App\Models\GalleryPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FeedController extends Controller
{
    /**
     * Get curated public feed with view limits
     * 
     * Returns only curated, public posts (images/videos only, no audio)
     * Decrements view limits for each item viewed
     * Admins bypass view limits
     */
    public function index(FeedListRequest $request): JsonResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        // Admins bypass view limits
        $isAdmin = $user->isAdmin();

        // Get view limits for images and videos
        $imageLimit = $this->getViewLimit($user->id, 'image', $isAdmin);
        $videoLimit = $this->getViewLimit($user->id, 'video', $isAdmin);

        // If no views remaining and not admin, return empty feed
        if (!$isAdmin && $imageLimit->views_remaining <= 0 && $videoLimit->views_remaining <= 0) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'views_remaining' => [
                        'image' => 0,
                        'video' => 0,
                    ],
                    'message' => 'Daily view limit reached',
                ],
            ]);
        }

        // Build cache key (cursor-specific)
        $cacheKey = 'feed:posts:' . ($validated['cursor'] ?? 'first');
        
        // Try to get posts from cache (5 minute TTL)
        $posts = Cache::tags(['feed'])->remember($cacheKey, 300, function () use ($validated) {
            $query = GalleryPost::where('is_curated', true)
                ->where('visibility', 'public')
                ->whereHas('generationJob', function ($q) {
                    $q->whereIn('job_type', ['image', 'video']); // Exclude audio
                })
                ->with(['user', 'generationJob.model'])
                ->orderBy('is_featured', 'desc') // Featured first
                ->orderBy('curated_at', 'desc'); // Then by curation date

            // Cursor-based pagination
            $cursor = $validated['cursor'] ?? null;
            if ($cursor) {
                $query->where('id', '<', $cursor);
            }

            return $query->limit(50)->get(); // Cache more than needed, apply limit per user
        });

        // Apply user-specific limit (respect view limits)
        $limit = min(15, $imageLimit->views_remaining + $videoLimit->views_remaining);
        if ($isAdmin) {
            $limit = 15; // Admins get full page
        }

        $posts = $posts->take($limit);

        // Decrement view limits for each post viewed
        $decremented = $this->decrementViewLimits($user->id, $posts, $isAdmin);

        // Format response
        $formattedPosts = $posts->map(function ($post) use ($user) {
            return $this->formatFeedPost($post, $user->id !== $post->user_id);
        });

        // Get next cursor
        $nextCursor = $posts->last() ? $posts->last()->id : null;

        return response()->json([
            'success' => true,
            'data' => $formattedPosts,
            'meta' => [
                'cursor' => $nextCursor,
                'has_more' => $nextCursor !== null && $posts->count() === $limit,
                'views_remaining' => [
                    'image' => $imageLimit->views_remaining,
                    'video' => $videoLimit->views_remaining,
                ],
            ],
        ]);
    }

    /**
     * Copy prompt from feed post
     * 
     * Authorization:
     * - Post must be in feed (curated and public)
     * - Post must have prompt_visible = true
     */
    public function copyPrompt(CopyPromptRequest $request): JsonResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        $post = GalleryPost::with('generationJob')
            ->where('id', $validated['post_id'])
            ->where('is_curated', true)
            ->where('visibility', 'public')
            ->firstOrFail();

        // Check prompt visibility
        if (!$post->prompt_visible) {
            return response()->json([
                'success' => false,
                'message' => 'Prompt is not visible for this post',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'prompt' => $post->generationJob->prompt,
                'negative_prompt' => $post->generationJob->negative_prompt,
            ],
        ]);
    }

    /**
     * Copy model from feed post
     * 
     * Authorization:
     * - Post must be in feed (curated and public)
     * - Post must have model_visible = true
     */
    public function copyModel(CopyModelRequest $request): JsonResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        $post = GalleryPost::with('generationJob.model')
            ->where('id', $validated['post_id'])
            ->where('is_curated', true)
            ->where('visibility', 'public')
            ->firstOrFail();

        // Check model visibility
        if (!$post->model_visible) {
            return response()->json([
                'success' => false,
                'message' => 'Model information is not visible for this post',
            ], 403);
        }

        if (!$post->generationJob->model) {
            return response()->json([
                'success' => false,
                'message' => 'Model information not available',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'model_id' => $post->generationJob->model->id,
                'model_name' => $post->generationJob->model->model_name,
            ],
        ]);
    }

    /**
     * Get or create view limit for user and content type
     */
    protected function getViewLimit(int $userId, string $contentType, bool $isAdmin): FeedViewLimit
    {
        if ($isAdmin) {
            // Admins have unlimited views (return dummy limit with high value)
            return new FeedViewLimit([
                'user_id' => $userId,
                'content_type' => $contentType,
                'views_remaining' => 999999,
                'daily_limit' => 999999,
            ]);
        }

        $limit = FeedViewLimit::where('user_id', $userId)
            ->where('content_type', $contentType)
            ->first();

        if (!$limit) {
            // Create default limit if not exists
            $limit = FeedViewLimit::create([
                'user_id' => $userId,
                'content_type' => $contentType,
                'views_remaining' => 10, // Default daily limit
                'daily_limit' => 10,
                'reset_at' => now(),
            ]);
        }

        // Check if reset is needed (daily reset)
        if ($limit->reset_at && $limit->reset_at->lt(now()->startOfDay())) {
            $limit->reset();
        }

        return $limit;
    }

    /**
     * Decrement view limits for posts viewed
     */
    protected function decrementViewLimits(int $userId, $posts, bool $isAdmin): int
    {
        if ($isAdmin) {
            return 0; // Admins don't consume views
        }

        $decremented = 0;

        foreach ($posts as $post) {
            $contentType = $post->generationJob->job_type; // 'image' or 'video'

            $limit = FeedViewLimit::where('user_id', $userId)
                ->where('content_type', $contentType)
                ->lockForUpdate()
                ->first();

            if ($limit && $limit->views_remaining > 0) {
                $limit->decrement('views_remaining');
                $decremented++;
            }
        }

        return $decremented;
    }

    /**
     * Format feed post response (respects visibility flags)
     */
    protected function formatFeedPost(GalleryPost $post, bool $isNotOwner): array
    {
        $response = [
            'id' => $post->id,
            'title' => $post->title,
            'description' => $post->description,
            'tags' => $post->tags_json ?? [],
            'likes_count' => $post->likes_count,
            'comments_count' => $post->comments_count,
            'views_count' => $post->views_count,
            'is_featured' => $post->is_featured,
            'created_at' => $post->created_at->toISOString(),
            'curated_at' => $post->curated_at?->toISOString(),
            'user' => [
                'id' => $post->user->id,
                'name' => $post->user->name,
                'avatar_url' => $post->user->avatar_url,
            ],
        ];

        // Add generation job details
        if ($post->generationJob) {
            $response['generation_job'] = [
                'id' => $post->generationJob->id,
                'job_type' => $post->generationJob->job_type,
                'result_url' => $post->generationJob->result_url,
                'result_thumbnail_url' => $post->generationJob->result_thumbnail_url,
            ];

            // Add prompt only if visible
            if ($post->prompt_visible) {
                $response['generation_job']['prompt'] = $post->generationJob->prompt;
                $response['generation_job']['negative_prompt'] = $post->generationJob->negative_prompt;
            }

            // Add model only if visible
            if ($post->model_visible && $post->generationJob->model) {
                $response['generation_job']['model'] = [
                    'id' => $post->generationJob->model->id,
                    'name' => $post->generationJob->model->model_name,
                ];
            }
        }

        return $response;
    }
}

