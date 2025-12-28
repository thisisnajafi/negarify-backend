<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    // Notification types
    public const TYPE_GENERATION_COMPLETED = 'generation_completed';
    public const TYPE_POST_LIKED = 'post_liked';
    public const TYPE_COMMENT_ADDED = 'comment_added';
    public const TYPE_POST_CURATED = 'post_curated';
    public const TYPE_REPORT_RESOLVED = 'report_resolved';

    /**
     * Create a notification for a user
     * 
     * @param int $userId User ID to notify
     * @param string $type Notification type
     * @param array $data Notification data (sanitized, no sensitive info)
     * @param bool $checkPreferences Whether to check user preferences
     * @return Notification|null Created notification or null if skipped
     */
    public function createNotification(
        int $userId,
        string $type,
        array $data,
        bool $checkPreferences = true
    ): ?Notification {
        try {
            // Check user preferences
            if ($checkPreferences && !$this->isNotificationEnabled($userId, $type)) {
                Log::debug('Notification skipped due to user preference', [
                    'user_id' => $userId,
                    'type' => $type,
                ]);
                return null;
            }

            // Check for duplicate notification (same type, same user, within 1 minute)
            $duplicate = Notification::where('user_id', $userId)
                ->where('type', $type)
                ->where('created_at', '>=', now()->subMinute())
                ->where('data_json', json_encode($data))
                ->first();

            if ($duplicate) {
                Log::debug('Duplicate notification prevented', [
                    'user_id' => $userId,
                    'type' => $type,
                    'existing_id' => $duplicate->id,
                ]);
                return null;
            }

            // Check rate limit (max 100 notifications per user per hour)
            $recentCount = Notification::where('user_id', $userId)
                ->where('created_at', '>=', now()->subHour())
                ->count();

            if ($recentCount >= 100) {
                Log::warning('Notification rate limit exceeded', [
                    'user_id' => $userId,
                    'type' => $type,
                    'recent_count' => $recentCount,
                ]);
                return null; // Silently skip to prevent notification spam
            }

            // Create notification
            $notification = Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'data_json' => $data,
            ]);

            Log::info('Notification created', [
                'notification_id' => $notification->id,
                'user_id' => $userId,
                'type' => $type,
            ]);

            return $notification;
        } catch (\Exception $e) {
            // Log error but don't throw - notification creation is non-critical
            Log::error('Failed to create notification', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if notification type is enabled for user
     */
    protected function isNotificationEnabled(int $userId, string $type): bool
    {
        // Check if preferences table exists
        if (!\Schema::hasTable('notification_preferences')) {
            return true; // Default: enabled if preferences table doesn't exist
        }

        $preference = NotificationPreference::where('user_id', $userId)
            ->where('type', $type)
            ->first();

        // Default: enabled if no preference exists
        return $preference ? $preference->enabled : true;
    }

    /**
     * Notify user when generation job completes
     */
    public function notifyGenerationCompleted(int $userId, int $jobId, string $jobType): void
    {
        $this->createNotification(
            $userId,
            self::TYPE_GENERATION_COMPLETED,
            [
                'job_id' => $jobId,
                'job_type' => $jobType,
                'message' => "Your {$jobType} generation is complete!",
            ]
        );
    }

    /**
     * Notify user when their post is liked
     */
    public function notifyPostLiked(int $postOwnerId, int $postId, int $likerId, string $likerName): void
    {
        // Don't notify if user liked their own post
        if ($postOwnerId === $likerId) {
            return;
        }

        $this->createNotification(
            $postOwnerId,
            self::TYPE_POST_LIKED,
            [
                'post_id' => $postId,
                'liker_id' => $likerId,
                'liker_name' => $likerName,
                'message' => "{$likerName} liked your post",
            ]
        );
    }

    /**
     * Notify user when comment is added to their post or reply to their comment
     */
    public function notifyCommentAdded(
        int $postOwnerId,
        int $postId,
        int $commentId,
        int $commenterId,
        string $commenterName,
        ?int $parentCommentId = null
    ): void {
        // Don't notify if user commented on their own post
        if ($postOwnerId === $commenterId) {
            return;
        }

        $message = $parentCommentId
            ? "{$commenterName} replied to your comment"
            : "{$commenterName} commented on your post";

        $this->createNotification(
            $postOwnerId,
            self::TYPE_COMMENT_ADDED,
            [
                'post_id' => $postId,
                'comment_id' => $commentId,
                'commenter_id' => $commenterId,
                'commenter_name' => $commenterName,
                'parent_comment_id' => $parentCommentId,
                'message' => $message,
            ]
        );
    }

    /**
     * Notify user when their post is curated
     */
    public function notifyPostCurated(int $postOwnerId, int $postId): void
    {
        $this->createNotification(
            $postOwnerId,
            self::TYPE_POST_CURATED,
            [
                'post_id' => $postId,
                'message' => 'Your post has been curated and added to the public feed!',
            ]
        );
    }

    /**
     * Notify user when their report is resolved
     */
    public function notifyReportResolved(int $userId, int $reportId, string $resolution): void
    {
        $this->createNotification(
            $userId,
            self::TYPE_REPORT_RESOLVED,
            [
                'report_id' => $reportId,
                'resolution' => $resolution,
                'message' => "Your report has been {$resolution}",
            ]
        );
    }
}

