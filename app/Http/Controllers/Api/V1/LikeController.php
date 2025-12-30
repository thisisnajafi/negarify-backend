<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GalleryPost;
use App\Models\Like;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LikeController extends Controller
{
    /**
     * Like a post (idempotent)
     * 
     * Authorization: All authenticated users can like posts
     */
    public function store(int $id): JsonResponse
    {
        $user = auth()->user();
        $post = GalleryPost::findOrFail($id);

        try {
            // SQLite doesn't support nested transactions, so check if we're already in one
            $executeLike = function () use ($user, $post) {
                // Check if like already exists (idempotent)
                $like = Like::where('user_id', $user->id)
                    ->where('gallery_post_id', $post->id)
                    ->first();

                if ($like) {
                    // Already liked - return success (idempotent)
                    return ['exists' => true, 'like' => $like];
                }

                // Create like
                $like = Like::create([
                    'user_id' => $user->id,
                    'gallery_post_id' => $post->id,
                ]);

                // Increment counter atomically
                $post->increment('likes_count');
                
                return ['exists' => false, 'like' => $like];
            };

            if (DB::transactionLevel() > 0 || DB::connection()->getPdo()->inTransaction()) {
                // Already in a transaction, execute directly
                $result = $executeLike();
            } else {
                // Not in a transaction, use DB::transaction()
                $result = DB::transaction($executeLike);
            }

            if ($result['exists']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Post already liked',
                    'data' => [
                        'liked' => true,
                        'likes_count' => $post->fresh()->likes_count,
                    ],
                ]);
            }

            $like = $result['like'];

            Log::info('Post liked', [
                'post_id' => $post->id,
                'user_id' => $user->id,
            ]);

            // Notify post owner (if not self-like)
            if ($post->user_id !== $user->id) {
                app(NotificationService::class)->notifyPostLiked(
                    $post->user_id,
                    $post->id,
                    $user->id,
                    $user->name
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Post liked successfully',
                'data' => [
                    'liked' => true,
                    'likes_count' => $post->fresh()->likes_count,
                ],
            ]);
        } catch (\Exception $e) {
            // Check if it's a duplicate key error (race condition)
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                // Another request created the like - return success (idempotent)
                return response()->json([
                    'success' => true,
                    'message' => 'Post liked successfully',
                    'data' => [
                        'liked' => true,
                        'likes_count' => $post->fresh()->likes_count,
                    ],
                ]);
            }

            Log::error('Failed to like post', [
                'post_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to like post',
            ], 500);
        }
    }

    /**
     * Unlike a post (safe - no error if not liked)
     * 
     * Authorization: Users can only unlike their own likes
     */
    public function destroy(int $id): JsonResponse
    {
        $user = auth()->user();
        $post = GalleryPost::findOrFail($id);

        try {
            // SQLite doesn't support nested transactions, so check if we're already in one
            $executeUnlike = function () use ($user, $post) {
                $like = Like::where('user_id', $user->id)
                    ->where('gallery_post_id', $post->id)
                    ->first();

                if (!$like) {
                    // Not liked - return success (safe operation)
                    return ['exists' => false];
                }

                // Delete like
                $like->delete();

                // Decrement counter atomically (ensure it doesn't go below 0)
                $post->decrement('likes_count');
                if ($post->likes_count < 0) {
                    $post->likes_count = 0;
                    $post->save();
                }
                
                return ['exists' => true];
            };

            if (DB::transactionLevel() > 0 || DB::connection()->getPdo()->inTransaction()) {
                // Already in a transaction, execute directly
                $result = $executeUnlike();
            } else {
                // Not in a transaction, use DB::transaction()
                $result = DB::transaction($executeUnlike);
            }

            if (!$result['exists']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Post not liked',
                    'data' => [
                        'liked' => false,
                        'likes_count' => $post->likes_count,
                    ],
                ]);
            }

            Log::info('Post unliked', [
                'post_id' => $post->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Post unliked successfully',
                'data' => [
                    'liked' => false,
                    'likes_count' => $post->fresh()->likes_count,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to unlike post', [
                'post_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unlike post',
            ], 500);
        }
    }
}

