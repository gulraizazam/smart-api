<?php

namespace App\Services\CashFlow;

use App\Models\CashFlow\CashflowNotification;
use App\Models\CashFlow\Expense;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Get notifications for current user.
     */
    public function getForUser(int $userId, int $limit = 20)
    {
        return CashflowNotification::forUser($userId)
            ->recent($limit)
            ->get();
    }

    /**
     * Get unread count for user.
     */
    public function getUnreadCount(int $userId): int
    {
        return CashflowNotification::forUser($userId)->unread()->count();
    }

    /**
     * Mark all as read for user.
     */
    public function markAllRead(int $userId): int
    {
        return CashflowNotification::markAllReadForUser($userId);
    }

    /**
     * Mark specific notification as read.
     */
    public function markRead(int $notificationId, int $userId): void
    {
        $notification = CashflowNotification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if ($notification) {
            $notification->markAsRead();
        }
    }

    /**
     * Create a notification for a specific user.
     */
    public function notify(int $userId, string $type, string $title, ?string $message = null, ?array $data = null, ?int $accountId = null): CashflowNotification
    {
        return CashflowNotification::create([
            'account_id' => $accountId ?? Auth::user()->account_id,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'created_at' => now(),
        ]);
    }

    /**
     * Notify admins of a pending expense needing approval.
     */
    public function notifyExpensePending(Expense $expense, int $accountId): void
    {
        $admins = $this->getUsersWithPermission('cashflow_expense_approve', $accountId);

        foreach ($admins as $admin) {
            if ($admin->id === $expense->created_by) continue;

            $this->notify(
                $admin->id,
                CashflowNotification::TYPE_EXPENSE_PENDING,
                'Expense Pending Approval',
                "Expense of PKR " . number_format($expense->amount, 2) . " by {$expense->creator->name} requires approval.",
                ['expense_id' => $expense->id, 'amount' => $expense->amount],
                $accountId
            );
        }
    }

    /**
     * Notify creator that expense was approved.
     */
    public function notifyExpenseApproved(Expense $expense): void
    {
        $this->notify(
            $expense->created_by,
            CashflowNotification::TYPE_EXPENSE_APPROVED,
            'Expense Approved',
            "Your expense of PKR " . number_format($expense->amount, 2) . " has been approved.",
            ['expense_id' => $expense->id]
        );
    }

    /**
     * Notify creator that expense was rejected.
     */
    public function notifyExpenseRejected(Expense $expense): void
    {
        $this->notify(
            $expense->created_by,
            CashflowNotification::TYPE_EXPENSE_REJECTED,
            'Expense Rejected',
            "Your expense of PKR " . number_format($expense->amount, 2) . " was rejected. Reason: " . $expense->rejection_reason,
            ['expense_id' => $expense->id]
        );
    }

    /**
     * Get users who have a specific permission in an account.
     */
    private function getUsersWithPermission(string $permission, int $accountId)
    {
        return User::where('account_id', $accountId)
            ->where('active', 1)
            ->permission($permission)
            ->get(['id', 'name']);
    }
}
