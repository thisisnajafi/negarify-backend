<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GenerationJobListRequest;
use App\Jobs\GenerateAudioJob;
use App\Jobs\GenerateImageJob;
use App\Jobs\GenerateVideoJob;
use App\Models\GenerationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerationJobController extends Controller
{
    /**
     * List user's generation jobs with filters
     */
    public function index(GenerationJobListRequest $request): JsonResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        $query = GenerationJob::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if (isset($validated['type'])) {
            $query->where('job_type', $validated['type']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['start_date'])) {
            $query->whereDate('created_at', '>=', $validated['start_date']);
        }

        if (isset($validated['end_date'])) {
            $query->whereDate('created_at', '<=', $validated['end_date']);
        }

        $jobs = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $jobs->items(),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
            ],
        ]);
    }

    /**
     * Get job status and result
     */
    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $job = GenerationJob::where('user_id', $user->id)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $job->id,
                'job_type' => $job->job_type,
                'status' => $job->status,
                'prompt' => $job->prompt,
                'negative_prompt' => $job->negative_prompt,
                'params' => $job->params_json,
                'result_url' => $job->result_url,
                'result_thumbnail_url' => $job->result_thumbnail_url,
                'error_message' => $job->error_message,
                'tokens_consumed' => $job->tokens_consumed,
                'cost_usd' => (float) $job->cost_usd,
                'created_at' => $job->created_at->toISOString(),
                'started_at' => $job->started_at?->toISOString(),
                'completed_at' => $job->completed_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Cancel a pending or processing job
     */
    public function cancel(int $id): JsonResponse
    {
        $user = auth()->user();

        $job = GenerationJob::where('user_id', $user->id)
            ->lockForUpdate()
            ->findOrFail($id);

        if ($job->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel a completed job',
            ], 400);
        }

        if ($job->isCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'Job is already cancelled',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $job->status = 'cancelled';
            $job->completed_at = now();
            $job->save();

            // Refund reserved tokens
            $user = $job->user;
            $tokensToRefund = $job->model->default_tokens;
            $user->tokens_balance += $tokensToRefund;
            $user->save();

            DB::commit();

            Log::info('Generation job cancelled', [
                'job_id' => $job->id,
                'user_id' => $user->id,
                'tokens_refunded' => $tokensToRefund,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Job cancelled successfully',
                'data' => [
                    'job_id' => $job->id,
                    'status' => 'cancelled',
                    'tokens_refunded' => $tokensToRefund,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel job',
            ], 500);
        }
    }

    /**
     * Retry a failed job
     */
    public function retry(int $id): JsonResponse
    {
        $user = auth()->user();

        $oldJob = GenerationJob::where('user_id', $user->id)->findOrFail($id);

        if (!$oldJob->isFailed()) {
            return response()->json([
                'success' => false,
                'message' => 'Only failed jobs can be retried',
            ], 400);
        }

        $model = $oldJob->model;
        $tokenCost = $model->default_tokens;

        $user->refresh();
        if (!$user->hasTokens($tokenCost)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient token balance',
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Reserve tokens
            $user->tokens_balance -= $tokenCost;
            $user->save();

            // Create new job with same parameters
            $newJob = GenerationJob::create([
                'user_id' => $user->id,
                'provider_id' => $oldJob->provider_id,
                'model_id' => $oldJob->model_id,
                'job_type' => $oldJob->job_type,
                'prompt' => $oldJob->prompt,
                'negative_prompt' => $oldJob->negative_prompt,
                'params_json' => $oldJob->params_json,
                'status' => 'pending',
                'tokens_consumed' => $tokenCost,
            ]);

            DB::commit();

            // Dispatch appropriate job based on type
            match ($newJob->job_type) {
                'image' => GenerateImageJob::dispatch($newJob->id),
                'video' => GenerateVideoJob::dispatch($newJob->id),
                'audio' => GenerateAudioJob::dispatch($newJob->id),
                default => throw new \RuntimeException("Unknown job type: {$newJob->job_type}"),
            };

            Log::info('Generation job retried', [
                'old_job_id' => $oldJob->id,
                'new_job_id' => $newJob->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Job retried successfully',
                'data' => [
                    'old_job_id' => $oldJob->id,
                    'new_job_id' => $newJob->id,
                    'status' => 'pending',
                    'estimated_tokens' => $tokenCost,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry job',
            ], 500);
        }
    }
}

