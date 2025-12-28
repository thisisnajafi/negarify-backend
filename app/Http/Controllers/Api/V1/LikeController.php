<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GalleryPost;
use App\Models\Like;
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
            DB::beginTransaction();

            // Check if like already exists (idempotent)
            $like = Like::where('user_id', $user->id)
                ->where('gallery_post_id', $post->id)
                ->first();

            if ($like) {
                // Already liked - return success (idempotent)
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Post already liked',
                    'data' => [
                        'liked' => true,
                        'likes_count' => $post->fresh()->likes_count,
                    ],
                ]);
            }

            // Create like
            $like = Like::create([
                'user_id' => $user->id,
                'gallery_post_id' => $post->id,
            ]);

            // Increment counter atomically
            $post->increment('likes_count');

            DB::commit();

            Log::info('Post liked', [
                'post_id' => $post->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Post liked successfully',
                'data' => [
                    'liked' => true,
                    'likes_count' => $post->fresh()->likes_count,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

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
            DB::beginTransaction();

            $like = Like::where('user_id', $user->id)
                ->where('gallery_post_id', $post->id)
                ->first();

            if (!$like) {
                // Not liked - return success (safe operation)
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Post not liked',
                    'data' => [
                        'liked' => false,
                        'likes_count' => $post->likes_count,
                    ],
                ]);
            }

            // Delete like
            $like->delete();

            // Decrement counter atomically (ensure it doesn't go below 0)
            $post->decrement('likes_count');
            if ($post->likes_count < 0) {
                $post->likes_count = 0;
                $post->save();
            }

            DB::commit();

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
            DB::rollBack();

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

