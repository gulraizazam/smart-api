<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashFlow\CashflowNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lightweight controller for cashflow notifications.
 * Separated from CashFlowController to avoid instantiating 14 service dependencies
 * on every notification poll (which runs on every page for users with cashflow_manage).
 */
class CashflowNotificationController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $userId = Auth::id();

            return response()->json([
                'success' => true,
                'data' => CashflowNotification::forUser($userId)->recent(20)->get(),
                'unread_count' => CashflowNotification::forUser($userId)->unread()->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function markRead(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();

            if ($request->has('notification_id')) {
                $notification = CashflowNotification::where('id', $request->input('notification_id'))
                    ->where('user_id', $userId)
                    ->first();
                if ($notification) {
                    $notification->markAsRead();
                }
            } else {
                CashflowNotification::markAllReadForUser($userId);
            }

            return response()->json(['success' => true, 'message' => 'Notifications marked as read.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
