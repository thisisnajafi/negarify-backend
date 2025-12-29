<?php

namespace App\Services;

use App\Models\GalleryPost;
use App\Models\ModerationQueue;
use App\Models\Report;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ModerationService
{
    /**
     * Check content and add to moderation queue if flagged
     * 
     * This is a placeholder policy hook that can be extended with actual filtering logic.
     * For now, it only flags content based on reports (threshold-based).
     * 
     * @param GalleryPost $post Post to check
     * @return bool Whether content was flagged
     */
    public function checkContent(GalleryPost $post): bool
    {
        // Placeholder: No automated filtering for now
        // This method can be extended with:
        // - Image content analysis (NSFW detection)
        // - Text analysis (prompt/content filtering)
        // - Pattern matching (spam detection)
        // - ML-based classification
        
        // For now, only flag based on report threshold
        $reportCount = Report::where('gallery_post_id', $post->id)
            ->where('status', 'pending')
            ->count();

        // Threshold: 3 or more pending reports triggers moderation queue
        if ($reportCount >= 3) {
            $this->addToModerationQueue($post, 'multiple_reports', 'Automated flag: Multiple reports received');
            return true;
        }

        return false;
    }

    /**
     * Add content to moderation queue
     */
    public function addToModerationQueue(GalleryPost $post, string $reason, ?string $source = null): ModerationQueue
    {
        // Check if already in queue
        $existing = ModerationQueue::where('gallery_post_id', $post->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            Log::debug('Post already in moderation queue', [
                'post_id' => $post->id,
                'queue_id' => $existing->id,
            ]);
            return $existing;
        }

        $queue = ModerationQueue::create([
            'gallery_post_id' => $post->id,
            'reason' => $reason,
            'status' => 'pending',
        ]);

        Log::info('Post added to moderation queue', [
            'post_id' => $post->id,
            'queue_id' => $queue->id,
            'reason' => $reason,
            'source' => $source ?? 'manual',
        ]);

        return $queue;
    }

    /**
     * Approve content (remove from queue, keep visible)
     */
    public function approveContent(int $queueId, int $reviewerId, ?string $notes = null): void
    {
        $queue = ModerationQueue::findOrFail($queueId);

        if (!$queue->isPending()) {
            throw new \RuntimeException('Queue item is not pending');
        }

        $executeUpdate = function () use ($queue, $reviewerId, $notes) {
            $queue->status = 'approved';
            $queue->reviewed_by = $reviewerId;
            $queue->reviewed_at = now();
            $queue->save();

            Log::info('Content approved by moderator', [
                'queue_id' => $queue->id,
                'post_id' => $queue->gallery_post_id,
                'reviewer_id' => $reviewerId,
                'notes' => $notes,
            ]);
        };

        // If already in a transaction (e.g., test environment with LazilyRefreshDatabase),
        // execute directly to avoid nested transaction issues in SQLite
        if (DB::transactionLevel() > 0) {
            $executeUpdate();
        } else {
            DB::transaction($executeUpdate);
        }
    }

    /**
     * Reject content (remove from queue, hide/remove content)
     */
    public function rejectContent(int $queueId, int $reviewerId, string $action = 'hide', ?string $notes = null): void
    {
        $queue = ModerationQueue::with('galleryPost')->findOrFail($queueId);

        if (!$queue->isPending()) {
            throw new \RuntimeException('Queue item is not pending');
        }

        $executeUpdate = function () use ($queue, $reviewerId, $action, $notes) {
            $queue->status = 'rejected';
            $queue->reviewed_by = $reviewerId;
            $queue->reviewed_at = now();
            $queue->save();

            $post = $queue->galleryPost;

            // Take action based on action type
            switch ($action) {
                case 'hide':
                    // Hide content (set visibility to private)
                    $post->visibility = 'private';
                    $post->is_curated = false; // Remove from feed
                    $post->save();
                    break;

                case 'remove':
                    // Soft delete (if soft deletes are enabled)
                    // For now, just hide
                    $post->visibility = 'private';
                    $post->is_curated = false;
                    $post->save();
                    break;

                case 'delete':
                    // Hard delete (use with caution)
                    $post->delete();
                    break;
            }

            // Update all related reports to 'resolved'
            Report::where('gallery_post_id', $post->id)
                ->where('status', 'pending')
                ->update(['status' => 'resolved']);

            Log::info('Content rejected by moderator', [
                'queue_id' => $queue->id,
                'post_id' => $post->id,
                'reviewer_id' => $reviewerId,
                'action' => $action,
                'notes' => $notes,
            ]);
        };

        // If already in a transaction (e.g., test environment with LazilyRefreshDatabase),
        // execute directly to avoid nested transaction issues in SQLite
        if (DB::transactionLevel() > 0) {
            $executeUpdate();
        } else {
            DB::transaction($executeUpdate);
        }
    }

    /**
     * Remove content (hide or delete)
     */
    public function removeContent(GalleryPost $post, string $action = 'hide', ?string $reason = null): void
    {
        $executeUpdate = function () use ($post, $action, $reason) {
            switch ($action) {
                case 'hide':
                    $post->visibility = 'private';
                    $post->is_curated = false;
                    $post->save();
                    break;

                case 'remove':
                    $post->visibility = 'private';
                    $post->is_curated = false;
                    $post->save();
                    break;

                case 'delete':
                    $post->delete();
                    break;
            }

            Log::info('Content removed', [
                'post_id' => $post->id,
                'action' => $action,
                'reason' => $reason,
            ]);
        };

        // If already in a transaction (e.g., test environment with LazilyRefreshDatabase),
        // execute directly to avoid nested transaction issues in SQLite
        if (DB::transactionLevel() > 0) {
            $executeUpdate();
        } else {
            DB::transaction($executeUpdate);
        }
    }
}

