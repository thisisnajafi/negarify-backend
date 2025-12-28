<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCommentRequest;
use App\Models\Comment;
use App\Models\GalleryPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommentController extends Controller
{
    /**
     * Create a comment (supports nested replies)
     * 
     * Authorization: All authenticated users can create comments
     */
    public function store(StoreCommentRequest $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $post = GalleryPost::findOrFail($id);

        $validated = $request->validated();

        // Validate parent comment if provided (for replies)
        if (isset($validated['parent_id'])) {
            $parentComment = Comment::where('id', $validated['parent_id'])
                ->where('gallery_post_id', $post->id)
                ->first();

            if (!$parentComment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent comment not found or does not belong to this post',
                ], 404);
            }
        }

        try {
            DB::beginTransaction();

            $comment = Comment::create([
                'user_id' => $user->id,
                'gallery_post_id' => $post->id,
                'body' => $validated['body'],
                'parent_id' => $validated['parent_id'] ?? null,
            ]);

            // Increment counter atomically
            $post->increment('comments_count');

            DB::commit();

            Log::info('Comment created', [
                'comment_id' => $comment->id,
                'post_id' => $post->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comment created successfully',
                'data' => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'parent_id' => $comment->parent_id,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'avatar_url' => $user->avatar_url,
                    ],
                    'created_at' => $comment->created_at->toISOString(),
                    'comments_count' => $post->fresh()->comments_count,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create comment', [
                'post_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create comment',
            ], 500);
        }
    }

    /**
     * Delete a comment
     * 
     * Authorization: Only owner or admin can delete comments
     */
    public function destroy(int $id): JsonResponse
    {
        $user = auth()->user();
        $comment = Comment::with('galleryPost')->findOrFail($id);

        // Check ownership or admin
        if ($comment->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this comment',
            ], 403);
        }

        $post = $comment->galleryPost;

        try {
            DB::beginTransaction();

            // Count replies (will be deleted by cascade)
            $repliesCount = Comment::where('parent_id', $comment->id)->count();

            // Delete comment (cascade deletes replies)
            $comment->delete();

            // Decrement counter atomically (including replies)
            $totalDeleted = 1 + $repliesCount;
            $post->comments_count = max(0, $post->comments_count - $totalDeleted);
            $post->save();

            DB::commit();

            Log::info('Comment deleted', [
                'comment_id' => $id,
                'post_id' => $post->id,
                'user_id' => $user->id,
                'replies_deleted' => $repliesCount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully',
                'data' => [
                    'comments_count' => $post->fresh()->comments_count,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete comment', [
                'comment_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment',
            ], 500);
        }
    }
}

