<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * List the authenticated user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 10), 50);

        $notifications = $request->user()->notifications()
            ->when($request->filled('is_read'), fn ($q) => $q->where('is_read', $request->boolean('is_read')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->successCollection(
            NotificationResource::collection($notifications),
            200,
            [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
            ]
        );
    }

    /**
     * Return the unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->notifications()->where('is_read', false)->count();

        return $this->success(['count' => $count]);
    }

    /**
     * Mark a notification as read.
     */
    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $this->authorize('update', $notification);

        if (! $notification->is_read) {
            $notification->update(['is_read' => true, 'read_at' => now()]);
        }

        return $this->success(new NotificationResource($notification), 'Notification marked as read.');
    }

    /**
     * Mark all of the user's notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->notifications()
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return $this->success(null, 'All notifications marked as read.');
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        $this->authorize('delete', $notification);

        $notification->delete();

        return $this->success(null, 'Notification deleted successfully.', 204);
    }
}
