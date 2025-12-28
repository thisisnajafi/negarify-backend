<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReportRequest;
use App\Models\GalleryPost;
use App\Models\Report;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Create a report for a gallery post
     * 
     * Authorization: All authenticated users can create reports
     */
    public function store(StoreReportRequest $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $post = GalleryPost::findOrFail($id);
        $validated = $request->validated();

        // Prevent users from reporting their own content
        if ($post->user_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot report your own content',
            ], 400);
        }

        // Check for duplicate report (same user, same post, within 24 hours)
        $recentReport = Report::where('user_id', $user->id)
            ->where('gallery_post_id', $post->id)
            ->where('created_at', '>=', now()->subDay())
            ->first();

        if ($recentReport) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reported this content recently',
            ], 400);
        }

        // Check rate limit (max 5 reports per user per day)
        $todayReports = Report::where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($todayReports >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'You have reached the daily report limit (5 reports per day)',
            ], 429);
        }

        try {
            DB::beginTransaction();

            $report = Report::create([
                'user_id' => $user->id,
                'gallery_post_id' => $post->id,
                'reason' => $validated['reason'],
                'status' => 'pending',
            ]);

            // Check if post should be added to moderation queue (threshold-based)
            $pendingReports = Report::where('gallery_post_id', $post->id)
                ->where('status', 'pending')
                ->count();

            if ($pendingReports >= 3) {
                app(ModerationService::class)->addToModerationQueue(
                    $post,
                    'multiple_reports',
                    'Automated flag: Multiple reports received'
                );
            }

            DB::commit();

            Log::info('Report created', [
                'report_id' => $report->id,
                'post_id' => $post->id,
                'user_id' => $user->id,
                'reason' => $validated['reason'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Report submitted successfully',
                'data' => [
                    'report_id' => $report->id,
                    'status' => $report->status,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create report', [
                'post_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit report',
            ], 500);
        }
    }
}

