<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreGalleryPostRequest;
use App\Http\Requests\Api\V1\UpdateGalleryPostRequest;
use App\Models\GalleryPost;
use App\Models\GenerationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GalleryPostController extends Controller
{
    /**
     * Create a new gallery post
     * 
     * Authorization:
     * - Generation job must belong to current user
     * - Generation job must be completed
     * - One post per generation job (unique constraint)
     */
    public function store(StoreGalleryPostRequest $request): JsonResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        // Get generation job with lock to prevent race conditions
        $generationJob = GenerationJob::lockForUpdate()
            ->where('id', $validated['generation_job_id'])
            ->where('user_id', $user->id)
            ->first();

        if (!$generationJob) {
            return response()->json([
                'success' => false,
                'message' => 'Generation job not found or does not belong to you',
            ], 404);
        }

        // Validate generation job is completed
        if (!$generationJob->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Generation job must be completed before creating a gallery post',
            ], 400);
        }

        // Check if post already exists for this generation job
        if (GalleryPost::where('generation_job_id', $generationJob->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'A gallery post already exists for this generation job',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $post = GalleryPost::create([
                'user_id' => $user->id,
                'generation_job_id' => $generationJob->id,
                'title' => $validated['title'] ?? null,
                'description' => $validated['description'] ?? null,
                'tags_json' => $validated['tags'] ?? null,
                'visibility' => $validated['visibility'] ?? 'public',
                'prompt_visible' => array_key_exists('prompt_visible', $validated) ? (bool)$validated['prompt_visible'] : true,
                'model_visible' => array_key_exists('model_visible', $validated) ? (bool)$validated['model_visible'] : true,
            ]);

            DB::commit();

            Log::info('Gallery post created', [
                'post_id' => $post->id,
                'user_id' => $user->id,
                'generation_job_id' => $generationJob->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gallery post created successfully',
                'data' => $this->formatPostResponse($post, true), // Creator is always the owner
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create gallery post', [
                'user_id' => $user->id,
                'generation_job_id' => $validated['generation_job_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create gallery post',
            ], 500);
        }
    }

    /**
     * Get gallery post details
     * 
     * Authorization:
     * - Owner can view their own posts (public or private)
     * - Others can only view public posts
     */
    public function show(string $id): JsonResponse
    {
        $user = auth()->user();
        $post = GalleryPost::with(['user', 'generationJob.model'])
            ->findOrFail((int) $id);

        // Check visibility: owner can see private posts, others cannot
        if ($post->isPrivate() && $post->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found',
            ], 404); // Don't reveal existence of private posts
        }

        $isOwner = $user->id === $post->user_id;
        return response()->json([
            'success' => true,
            'data' => $this->formatPostResponse($post, $isOwner),
        ]);
    }

    /**
     * Update gallery post
     * 
     * Authorization:
     * - Only owner can update their posts
     */
    public function update(UpdateGalleryPostRequest $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $post = GalleryPost::findOrFail($id);

        // Check ownership
        if ($post->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this post',
            ], 403);
        }

        // Prevent changing immutable fields
        $validated = $request->validated();
        unset($validated['generation_job_id']); // Immutable
        unset($validated['is_curated']); // Admin-only

        try {
            DB::beginTransaction();

            // Update only provided fields
            if (isset($validated['title'])) {
                $post->title = $validated['title'];
            }
            if (isset($validated['description'])) {
                $post->description = $validated['description'];
            }
            if (isset($validated['tags'])) {
                $post->tags_json = $validated['tags'];
            }
            if (isset($validated['visibility'])) {
                $post->visibility = $validated['visibility'];
            }
            if (isset($validated['prompt_visible'])) {
                $post->prompt_visible = $validated['prompt_visible'];
            }
            if (isset($validated['model_visible'])) {
                $post->model_visible = $validated['model_visible'];
            }

            $post->save();

            DB::commit();

            Log::info('Gallery post updated', [
                'post_id' => $post->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gallery post updated successfully',
                'data' => $this->formatPostResponse($post, true),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update gallery post', [
                'post_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update gallery post',
            ], 500);
        }
    }

    /**
     * Delete gallery post
     * 
     * Authorization:
     * - Only owner can delete their posts
     */
    public function destroy(int $id): JsonResponse
    {
        $user = auth()->user();
        $post = GalleryPost::findOrFail($id);

        // Check ownership
        if ($post->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this post',
            ], 403);
        }

        try {
            $post->delete();

            Log::info('Gallery post deleted', [
                'post_id' => $id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gallery post deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete gallery post', [
                'post_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete gallery post',
            ], 500);
        }
    }

    /**
     * List user's own posts
     */
    public function myPosts(): JsonResponse
    {
        $user = auth()->user();

        $posts = GalleryPost::where('user_id', $user->id)
            ->with(['generationJob.model'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => collect($posts->items())->map(function ($post) {
                return $this->formatPostResponse($post, true); // Owner can see all
            })->values()->all(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * Format post response with visibility checks
     */
    protected function formatPostResponse(GalleryPost $post, bool $isOwner = false): array
    {
        $response = [
            'id' => $post->id,
            'title' => $post->title,
            'description' => $post->description,
            'tags' => $post->tags_json ?? [],
            'visibility' => $post->visibility,
            'likes_count' => $post->likes_count,
            'comments_count' => $post->comments_count,
            'views_count' => $post->views_count,
            'is_curated' => $post->is_curated,
            'is_featured' => $post->is_featured,
            'created_at' => $post->created_at->toISOString(),
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

            // Add prompt only if visible (or if owner - owners always see prompts)
            // Note: Owners can always see their own prompts, even if prompt_visible is false
            // Use strict boolean check: prompt_visible must be explicitly true AND user must not be owner
            if ($isOwner || ($post->prompt_visible === true)) {
                $response['generation_job']['prompt'] = $post->generationJob->prompt;
                $response['generation_job']['negative_prompt'] = $post->generationJob->negative_prompt;
            }

            // Add model only if visible (or if owner - owners always see models)
            // Note: Owners can always see their own models, even if model_visible is false
            // Use strict boolean check: model_visible must be explicitly true AND user must not be owner
            if ($isOwner || ($post->model_visible === true)) {
                if ($post->generationJob->model) {
                    $response['generation_job']['model'] = [
                        'id' => $post->generationJob->model->id,
                        'name' => $post->generationJob->model->model_name,
                    ];
                }
            }
        }

        return $response;
    }
}

