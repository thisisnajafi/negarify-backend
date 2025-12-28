<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ModerationActionRequest;
use App\Models\ModerationQueue;
use App\Models\Report;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AdminModerationController extends Controller
{
    /**
     * List moderation queue (pending items)
     * 
     * Authorization: Admin only
     */
    public function queue(): JsonResponse
    {
        $queue = ModerationQueue::with(['galleryPost.user', 'reviewer'])
            ->pending()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $queue->items()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'post_id' => $item->gallery_post_id,
                    'reason' => $item->reason,
                    'status' => $item->status,
                    'created_at' => $item->created_at->toISOString(),
                    'post' => [
                        'id' => $item->galleryPost->id,
                        'title' => $item->galleryPost->title,
                        'visibility' => $item->galleryPost->visibility,
                        'is_curated' => $item->galleryPost->is_curated,
                        'user' => [
                            'id' => $item->galleryPost->user->id,
                            'name' => $item->galleryPost->user->name,
                        ],
                    ],
                ];
            }),
            'meta' => [
                'current_page' => $queue->currentPage(),
                'last_page' => $queue->lastPage(),
                'per_page' => $queue->perPage(),
                'total' => $queue->total(),
            ],
        ]);
    }

    /**
     * List all reports
     * 
     * Authorization: Admin only
     */
    public function reports(): JsonResponse
    {
        $reports = Report::with(['user', 'galleryPost.user'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $reports->items()->map(function ($report) {
                return [
                    'id' => $report->id,
                    'user_id' => $report->user_id,
                    'post_id' => $report->gallery_post_id,
                    'reason' => $report->reason,
                    'status' => $report->status,
                    'created_at' => $report->created_at->toISOString(),
                    'post' => [
                        'id' => $report->galleryPost->id,
                        'title' => $report->galleryPost->title,
                    ],
                    'reporter' => [
                        'id' => $report->user->id,
                        'name' => $report->user->name,
                    ],
                ];
            }),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * Approve content (remove from queue, keep visible)
     * 
     * Authorization: Admin only
     */
    public function approve(ModerationActionRequest $request, int $id): JsonResponse
    {
        $admin = auth()->user();
        $validated = $request->validated();

        try {
            app(ModerationService::class)->approveContent(
                $id,
                $admin->id,
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Content approved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to approve content', [
                'queue_id' => $id,
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Reject content (remove from queue, hide/remove content)
     * 
     * Authorization: Admin only
     */
    public function reject(ModerationActionRequest $request, int $id): JsonResponse
    {
        $admin = auth()->user();
        $validated = $request->validated();

        try {
            app(ModerationService::class)->rejectContent(
                $id,
                $admin->id,
                $validated['action'] ?? 'hide',
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Content rejected successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reject content', [
                'queue_id' => $id,
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Resolve a report
     * 
     * Authorization: Admin only
     */
    public function resolveReport(int $id): JsonResponse
    {
        $admin = auth()->user();
        $report = Report::findOrFail($id);

        if ($report->isResolved() || $report->isDismissed()) {
            return response()->json([
                'success' => false,
                'message' => 'Report is already resolved or dismissed',
            ], 400);
        }

        $report->status = 'resolved';
        // Note: No updated_at column, so we can't track when it was resolved
        // This is a limitation of the schema

        Log::info('Report resolved by admin', [
            'report_id' => $report->id,
            'admin_id' => $admin->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report resolved successfully',
            'data' => [
                'report_id' => $report->id,
                'status' => $report->status,
            ],
        ]);
    }

    /**
     * Dismiss a report
     * 
     * Authorization: Admin only
     */
    public function dismissReport(int $id): JsonResponse
    {
        $admin = auth()->user();
        $report = Report::findOrFail($id);

        if ($report->isResolved() || $report->isDismissed()) {
            return response()->json([
                'success' => false,
                'message' => 'Report is already resolved or dismissed',
            ], 400);
        }

        $report->status = 'dismissed';

        Log::info('Report dismissed by admin', [
            'report_id' => $report->id,
            'admin_id' => $admin->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report dismissed successfully',
            'data' => [
                'report_id' => $report->id,
                'status' => $report->status,
            ],
        ]);
    }
}

