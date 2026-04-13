<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\HR;

use App\Http\Controllers\Controller;
use App\Models\HrNotification;
use App\Services\HR\HrNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HrNotificationController extends Controller
{
    public function __construct(
        protected readonly HrNotificationService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $notifications = $this->service->getForUser($userId);
            $unreadCount = $this->service->unreadCount($userId);

            $data = $notifications->map(fn (HrNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'message' => $n->message,
                'url' => $n->url,
                'is_read' => $n->is_read,
                'time_ago' => $n->created_at->diffForHumans(),
            ]);

            return response()->json([
                'status' => true,
                'data' => $data,
                'unread_count' => $unreadCount,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'HrNotificationController@index');
        }
    }

    public function markRead(HrNotification $notification): JsonResponse
    {
        try {
            if ((int) $notification->user_id !== Auth::id()) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $notification->markAsRead();

            return $this->successResponse('Notification marked as read.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'HrNotificationController@markRead');
        }
    }

    public function markAllRead(): JsonResponse
    {
        try {
            HrNotification::markAllReadForUser(Auth::id());

            return $this->successResponse('All notifications marked as read.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'HrNotificationController@markAllRead');
        }
    }
}
