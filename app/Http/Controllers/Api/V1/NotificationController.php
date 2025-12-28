<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\NotificationListRequest;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * List user's notifications
     * 
     * Authorization: User can only view their own notifications
     */
    public function index(NotificationListRequest $request): JsonResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        $query = Notification::where('user_id', $user->id);

        // Filter by read/unread status
        if (isset($validated['read'])) {
            if ($validated['read']) {
                $query->read();
            } else {
                $query->unread();
            }
        }

        // Filter by type
        if (isset($validated['type'])) {
            $query->byType($validated['type']);
        }

        // Paginate
        $perPage = $validated['per_page'] ?? 20;
        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Get unread count
        $unreadCount = Notification::where('user_id', $user->id)
            ->unread()
            ->count();

        return response()->json([
            'success' => true,
            'data' => $notifications->items()->map(function ($notification) {
                return $this->formatNotification($notification);
            }),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    /**
     * Get notification details
     * 
     * Authorization: User can only view their own notifications
     */
    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $notification = Notification::where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->formatNotification($notification),
        ]);
    }

    /**
     * Mark notification as read
     * 
     * Authorization: User can only mark their own notifications as read
     */
    public function markAsRead(int $id): JsonResponse
    {
        $user = auth()->user();
        $notification = Notification::where('user_id', $user->id)
            ->findOrFail($id);

        if (!$notification->isRead()) {
            $notification->markAsRead();

            Log::info('Notification marked as read', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'data' => $this->formatNotification($notification),
        ]);
    }

    /**
     * Mark notification as unread
     * 
     * Authorization: User can only mark their own notifications as unread
     */
    public function markAsUnread(int $id): JsonResponse
    {
        $user = auth()->user();
        $notification = Notification::where('user_id', $user->id)
            ->findOrFail($id);

        if ($notification->isRead()) {
            $notification->update(['read_at' => null]);

            Log::info('Notification marked as unread', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as unread',
            'data' => $this->formatNotification($notification),
        ]);
    }

    /**
     * Mark all notifications as read
     * 
     * Authorization: User can only mark their own notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = auth()->user();

        $updated = Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        Log::info('All notifications marked as read', [
            'user_id' => $user->id,
            'updated_count' => $updated,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Marked {$updated} notifications as read",
            'data' => [
                'updated_count' => $updated,
            ],
        ]);
    }

    /**
     * Format notification response
     */
    protected function formatNotification(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'data' => $notification->data_json,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at->toISOString(),
        ];
    }
}

