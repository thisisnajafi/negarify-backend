<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GenerateImageRequest;
use App\Jobs\GenerateImageJob;
use App\Models\GenerationJob;
use App\Models\Model as AiModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerationController extends Controller
{
    /**
     * Generate image (async)
     * 
     * Flow:
     * 1. Validate request
     * 2. Check model availability
     * 3. Calculate token cost
     * 4. Check user balance
     * 5. Reserve tokens (atomic: balance update + job creation)
     * 6. Dispatch queue job
     * 7. Return job ID
     */
    public function generateImage(GenerateImageRequest $request): JsonResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        // Get model with lock
        $model = AiModel::lockForUpdate()->findOrFail($validated['model_id']);

        // Validate model is available
        if (!$model->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Selected model is not available',
            ], 400);
        }

        // Validate model type
        if ($model->model_type !== 'image') {
            return response()->json([
                'success' => false,
                'message' => 'Selected model is not an image generation model',
            ], 400);
        }

        // Calculate token cost
        $tokenCost = $model->default_tokens;

        // Check user balance (with lock to prevent race conditions)
        $user->refresh();
        if (!$user->hasTokens($tokenCost)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient token balance',
                'data' => [
                    'required' => $tokenCost,
                    'available' => (int) $user->tokens_balance,
                ],
            ], 400);
        }

        // Reserve tokens and create job (atomic)
        try {
            DB::beginTransaction();

            // Reserve tokens (deduct from balance)
            $user->tokens_balance -= $tokenCost;
            $user->save();

            // Create generation job
            $job = GenerationJob::create([
                'user_id' => $user->id,
                'provider_id' => $model->provider_id,
                'model_id' => $model->id,
                'job_type' => 'image',
                'prompt' => $validated['prompt'],
                'negative_prompt' => $validated['negative_prompt'] ?? null,
                'params_json' => [
                    'size' => $validated['size'] ?? null,
                    'style' => $validated['style'] ?? null,
                    'seed' => $validated['seed'] ?? null,
                    'steps' => $validated['steps'] ?? null,
                    'guidance_scale' => $validated['guidance_scale'] ?? null,
                ],
                'status' => 'pending',
                'tokens_consumed' => $tokenCost, // Reserved amount
            ]);

            DB::commit();

            // Dispatch queue job
            GenerateImageJob::dispatch($job->id);

            Log::info('Image generation job created', [
                'job_id' => $job->id,
                'user_id' => $user->id,
                'model_id' => $model->id,
                'tokens_reserved' => $tokenCost,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image generation job created',
                'data' => [
                    'job_id' => $job->id,
                    'status' => 'pending',
                    'estimated_tokens' => $tokenCost,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create image generation job', [
                'user_id' => $user->id,
                'model_id' => $validated['model_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create generation job. Please try again later.',
            ], 500);
        }
    }

    /**
     * Generate video (async)
     */
    public function generateVideo(\App\Http\Requests\Api\V1\GenerateVideoRequest $request): JsonResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        $model = \App\Models\Model::lockForUpdate()->findOrFail($validated['model_id']);

        if (!$model->isAvailable() || $model->model_type !== 'video') {
            return response()->json(['success' => false, 'message' => 'Model not available'], 400);
        }

        $tokenCost = $model->default_tokens;
        $user->refresh();

        if (!$user->hasTokens($tokenCost)) {
            return response()->json(['success' => false, 'message' => 'Insufficient tokens'], 400);
        }

        try {
            DB::beginTransaction();
            $user->tokens_balance -= $tokenCost;
            $user->save();

            $job = GenerationJob::create([
                'user_id' => $user->id,
                'provider_id' => $model->provider_id,
                'model_id' => $model->id,
                'job_type' => 'video',
                'prompt' => $validated['prompt'],
                'negative_prompt' => $validated['negative_prompt'] ?? null,
                'params_json' => [
                    'duration' => $validated['duration'] ?? null,
                    'resolution' => $validated['resolution'] ?? null,
                    'fps' => $validated['fps'] ?? null,
                    'seed' => $validated['seed'] ?? null,
                ],
                'status' => 'pending',
                'tokens_consumed' => $tokenCost,
            ]);

            DB::commit();
            \App\Jobs\GenerateVideoJob::dispatch($job->id);

            return response()->json([
                'success' => true,
                'message' => 'Video generation job created',
                'data' => ['job_id' => $job->id, 'status' => 'pending', 'estimated_tokens' => $tokenCost],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to create job'], 500);
        }
    }

    /**
     * Generate audio (async)
     */
    public function generateAudio(\App\Http\Requests\Api\V1\GenerateAudioRequest $request): JsonResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        $model = \App\Models\Model::lockForUpdate()->findOrFail($validated['model_id']);

        if (!$model->isAvailable() || $model->model_type !== 'audio') {
            return response()->json(['success' => false, 'message' => 'Model not available'], 400);
        }

        $tokenCost = $model->default_tokens;
        $user->refresh();

        if (!$user->hasTokens($tokenCost)) {
            return response()->json(['success' => false, 'message' => 'Insufficient tokens'], 400);
        }

        try {
            DB::beginTransaction();
            $user->tokens_balance -= $tokenCost;
            $user->save();

            $job = GenerationJob::create([
                'user_id' => $user->id,
                'provider_id' => $model->provider_id,
                'model_id' => $model->id,
                'job_type' => 'audio',
                'prompt' => $validated['prompt'],
                'params_json' => [
                    'duration' => $validated['duration'] ?? null,
                    'format' => $validated['format'] ?? null,
                    'sample_rate' => $validated['sample_rate'] ?? null,
                    'seed' => $validated['seed'] ?? null,
                ],
                'status' => 'pending',
                'tokens_consumed' => $tokenCost,
            ]);

            DB::commit();
            \App\Jobs\GenerateAudioJob::dispatch($job->id);

            return response()->json([
                'success' => true,
                'message' => 'Audio generation job created',
                'data' => ['job_id' => $job->id, 'status' => 'pending', 'estimated_tokens' => $tokenCost],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to create job'], 500);
        }
    }
}

