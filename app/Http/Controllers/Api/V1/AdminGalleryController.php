<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkCurationRequest;
use App\Models\GalleryPost;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminGalleryController extends Controller
{
    /**
     * Curate a post (mark as curated for feed)
     * 
     * Authorization: Admin only
     */
    public function curate(int $id): JsonResponse
    {
        $admin = auth()->user();
        $post = GalleryPost::findOrFail($id);

        // Post must be public to be curated (or document if private posts can be curated)
        if ($post->isPrivate()) {
            return response()->json([
                'success' => false,
                'message' => 'Private posts cannot be curated',
            ], 400);
        }

        if ($post->is_curated) {
            return response()->json([
                'success' => true,
                'message' => 'Post is already curated',
                'data' => [
                    'post_id' => $post->id,
                    'is_curated' => true,
                ],
            ]);
        }

        try {
            DB::beginTransaction();

            $post->is_curated = true;
            $post->curated_at = now();
            $post->save();

            DB::commit();

            // Invalidate feed cache
            Cache::tags(['feed'])->flush(); // Or use pattern: Cache::forget('feed:*');

            Log::info('Post curated by admin', [
                'post_id' => $post->id,
                'admin_id' => $admin->id,
            ]);

            // Notify post owner
            app(NotificationService::class)->notifyPostCurated($post->user_id, $post->id);

            return response()->json([
                'success' => true,
                'message' => 'Post curated successfully',
                'data' => [
                    'post_id' => $post->id,
                    'is_curated' => true,
                    'curated_at' => $post->curated_at->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to curate post', [
                'post_id' => $id,
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to curate post',
            ], 500);
        }
    }

    /**
     * Uncurate a post (remove from feed)
     * 
     * Authorization: Admin only
     */
    public function uncurate(int $id): JsonResponse
    {
        $admin = auth()->user();
        $post = GalleryPost::findOrFail($id);

        if (!$post->is_curated) {
            return response()->json([
                'success' => true,
                'message' => 'Post is not curated',
                'data' => [
                    'post_id' => $post->id,
                    'is_curated' => false,
                ],
            ]);
        }

        try {
            DB::beginTransaction();

            $post->is_curated = false;
            $post->curated_at = null; // Clear timestamp
            $post->save();

            DB::commit();

            // Invalidate feed cache
            Cache::tags(['feed'])->flush();

            Log::info('Post uncurated by admin', [
                'post_id' => $post->id,
                'admin_id' => $admin->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Post uncurated successfully',
                'data' => [
                    'post_id' => $post->id,
                    'is_curated' => false,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to uncurate post', [
                'post_id' => $id,
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to uncurate post',
            ], 500);
        }
    }

    /**
     * Feature a post (prioritize in feed)
     * 
     * Authorization: Admin only
     */
    public function feature(int $id): JsonResponse
    {
        $admin = auth()->user();
        $post = GalleryPost::findOrFail($id);

        if ($post->is_featured) {
            return response()->json([
                'success' => true,
                'message' => 'Post is already featured',
                'data' => [
                    'post_id' => $post->id,
                    'is_featured' => true,
                ],
            ]);
        }

        try {
            $post->is_featured = true;
            $post->save();

            // Invalidate feed cache
            Cache::tags(['feed'])->flush();

            Log::info('Post featured by admin', [
                'post_id' => $post->id,
                'admin_id' => $admin->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Post featured successfully',
                'data' => [
                    'post_id' => $post->id,
                    'is_featured' => true,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to feature post', [
                'post_id' => $id,
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to feature post',
            ], 500);
        }
    }

    /**
     * Unfeature a post
     * 
     * Authorization: Admin only
     */
    public function unfeature(int $id): JsonResponse
    {
        $admin = auth()->user();
        $post = GalleryPost::findOrFail($id);

        if (!$post->is_featured) {
            return response()->json([
                'success' => true,
                'message' => 'Post is not featured',
                'data' => [
                    'post_id' => $post->id,
                    'is_featured' => false,
                ],
            ]);
        }

        try {
            $post->is_featured = false;
            $post->save();

            // Invalidate feed cache
            Cache::tags(['feed'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Post unfeatured successfully',
                'data' => [
                    'post_id' => $post->id,
                    'is_featured' => false,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to unfeature post', [
                'post_id' => $id,
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unfeature post',
            ], 500);
        }
    }

    /**
     * Bulk curate posts
     * 
     * Authorization: Admin only
     */
    public function bulkCurate(BulkCurationRequest $request): JsonResponse
    {
        $admin = auth()->user();
        $validated = $request->validated();
        $postIds = $validated['post_ids'];

        try {
            DB::beginTransaction();

            $posts = GalleryPost::whereIn('id', $postIds)
                ->where('visibility', 'public') // Only public posts can be curated
                ->where('is_curated', false)
                ->get();

            $curatedCount = 0;
            foreach ($posts as $post) {
                $post->is_curated = true;
                $post->curated_at = now();
                $post->save();
                $curatedCount++;
            }

            DB::commit();

            // Invalidate feed cache
            Cache::tags(['feed'])->flush();

            Log::info('Bulk curation by admin', [
                'admin_id' => $admin->id,
                'requested_count' => count($postIds),
                'curated_count' => $curatedCount,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully curated {$curatedCount} posts",
                'data' => [
                    'requested' => count($postIds),
                    'curated' => $curatedCount,
                    'skipped' => count($postIds) - $curatedCount,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to bulk curate posts', [
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk curate posts',
            ], 500);
        }
    }

    /**
     * Bulk uncurate posts
     * 
     * Authorization: Admin only
     */
    public function bulkUncurate(BulkCurationRequest $request): JsonResponse
    {
        $admin = auth()->user();
        $validated = $request->validated();
        $postIds = $validated['post_ids'];

        try {
            DB::beginTransaction();

            $posts = GalleryPost::whereIn('id', $postIds)
                ->where('is_curated', true)
                ->get();

            $uncuratedCount = 0;
            foreach ($posts as $post) {
                $post->is_curated = false;
                $post->curated_at = null;
                $post->save();
                $uncuratedCount++;
            }

            DB::commit();

            // Invalidate feed cache
            Cache::tags(['feed'])->flush();

            Log::info('Bulk uncuration by admin', [
                'admin_id' => $admin->id,
                'requested_count' => count($postIds),
                'uncurated_count' => $uncuratedCount,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully uncurated {$uncuratedCount} posts",
                'data' => [
                    'requested' => count($postIds),
                    'uncurated' => $uncuratedCount,
                    'skipped' => count($postIds) - $uncuratedCount,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to bulk uncurate posts', [
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk uncurate posts',
            ], 500);
        }
    }

    /**
     * List curated posts (admin view)
     * 
     * Authorization: Admin only
     */
    public function curated(): JsonResponse
    {
        $posts = GalleryPost::where('is_curated', true)
            ->with(['user', 'generationJob.model'])
            ->orderBy('curated_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $posts->items(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }
}

