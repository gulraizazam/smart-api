<?php

declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashFlow\CashflowNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Lightweight controller for cashflow notifications.
 * Separated from CashFlowController to avoid instantiating 14 service dependencies
 * on every notification poll (which runs on every page for users with cashflow_manage).
 */
class CashflowNotificationController extends Controller
{
    public function index(): JsonResponse
    {
        if (! Gate::any(['cashflow.dashboard.view', 'cashflow.manage', 'cashflow.fdm.view'])) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

        try {
            $userId = Auth::id();

            return response()->json([
                'success' => true,
                'data' => CashflowNotification::forUser($userId)->recent(20)->get(),
                'unread_count' => CashflowNotification::forUser($userId)->unread()->count(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    public function markRead(Request $request): JsonResponse
    {
        if (! Gate::any(['cashflow.dashboard.view', 'cashflow.manage', 'cashflow.fdm.view'])) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

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
            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
        }
    }
}
