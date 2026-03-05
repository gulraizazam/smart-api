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
     * Notify admins of a new vendor request.
     */
    public function notifyVendorRequest(string $vendorName, string $requestedByName, int $accountId): void
    {
        $admins = $this->getUsersWithPermission('cashflow_vendor_manage', $accountId);

        foreach ($admins as $admin) {
            $this->notify(
                $admin->id,
                CashflowNotification::TYPE_VENDOR_REQUEST,
                'New Vendor Request',
                "{$requestedByName} has requested a new vendor: {$vendorName}.",
                ['vendor_name' => $vendorName],
                $accountId
            );
        }
    }

    /**
     * Notify admins of a new category request.
     */
    public function notifyCategoryRequest(string $categoryName, string $requestedByName, int $accountId): void
    {
        $admins = $this->getUsersWithPermission('cashflow_category_manage', $accountId);

        foreach ($admins as $admin) {
            $this->notify(
                $admin->id,
                CashflowNotification::TYPE_CATEGORY_REQUEST,
                'New Category Request',
                "{$requestedByName} has suggested a new category: {$categoryName}.",
                ['category_name' => $categoryName],
                $accountId
            );
        }
    }

    /**
     * Notify admins when a staff advance is given.
     */
    public function notifyStaffAdvanceGiven(string $staffName, float $amount, int $accountId): void
    {
        $admins = $this->getUsersWithPermission('cashflow_expense_approve', $accountId);

        foreach ($admins as $admin) {
            $this->notify(
                $admin->id,
                CashflowNotification::TYPE_STAFF_ADVANCE,
                'Staff Advance Given',
                "PKR " . number_format($amount, 0) . " advance given to {$staffName}.",
                ['staff_name' => $staffName, 'amount' => $amount],
                $accountId
            );
        }
    }

    /**
     * Notify admin + branch manager when a pool goes negative.
     */
    public function notifyNegativePool(string $poolName, float $balance, ?int $branchId, int $accountId): void
    {
        $admins = $this->getUsersWithPermission('cashflow_expense_approve', $accountId);
        $notifiedIds = [];

        foreach ($admins as $admin) {
            $this->notify(
                $admin->id,
                CashflowNotification::TYPE_NEGATIVE_POOL,
                'Negative Pool Balance',
                "Pool \"{$poolName}\" has gone negative: PKR " . number_format($balance, 0) . ".",
                ['pool_name' => $poolName, 'balance' => $balance],
                $accountId
            );
            $notifiedIds[] = $admin->id;
        }

        // Also notify branch managers of that branch
        if ($branchId) {
            $branchManagers = $this->getBranchManagers($branchId, $accountId);
            foreach ($branchManagers as $bm) {
                if (in_array($bm->id, $notifiedIds)) continue;
                $this->notify(
                    $bm->id,
                    CashflowNotification::TYPE_NEGATIVE_POOL,
                    'Negative Pool Balance',
                    "Your branch pool \"{$poolName}\" has gone negative: PKR " . number_format($balance, 0) . ".",
                    ['pool_name' => $poolName, 'balance' => $balance],
                    $accountId
                );
            }
        }
    }

    /**
     * Notify branch manager when an expense is recorded for their branch.
     */
    public function notifyExpenseForBranch(Expense $expense, int $accountId): void
    {
        if (!$expense->for_branch_id) return;

        $branchManagers = $this->getBranchManagers($expense->for_branch_id, $accountId);

        foreach ($branchManagers as $bm) {
            if ($bm->id === $expense->created_by) continue;
            $this->notify(
                $bm->id,
                CashflowNotification::TYPE_EXPENSE_FOR_BRANCH,
                'Expense Recorded for Your Branch',
                "PKR " . number_format($expense->amount, 0) . " expense recorded for your branch — {$expense->description}.",
                ['expense_id' => $expense->id, 'amount' => $expense->amount],
                $accountId
            );
        }
    }

    /**
     * Notify branch manager when a transfer involves their branch pool.
     */
    public function notifyTransferForBranch(int $fromPoolId, int $toPoolId, float $amount, int $accountId): void
    {
        $pools = \App\Models\CashFlow\CashPool::whereIn('id', [$fromPoolId, $toPoolId])
            ->where('type', 'branch_cash')
            ->whereNotNull('location_id')
            ->get();

        $notifiedIds = [];
        foreach ($pools as $pool) {
            $branchManagers = $this->getBranchManagers($pool->location_id, $accountId);
            $direction = ($pool->id === $fromPoolId) ? 'out of' : 'into';

            foreach ($branchManagers as $bm) {
                if (in_array($bm->id, $notifiedIds)) continue;
                $this->notify(
                    $bm->id,
                    CashflowNotification::TYPE_TRANSFER_FOR_BRANCH,
                    'Transfer Involving Your Branch',
                    "PKR " . number_format($amount, 0) . " transferred {$direction} your branch pool ({$pool->name}).",
                    ['amount' => $amount, 'pool_name' => $pool->name],
                    $accountId
                );
                $notifiedIds[] = $bm->id;
            }
        }
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

    /**
     * Get branch managers assigned to a specific branch.
     */
    private function getBranchManagers(int $branchId, int $accountId)
    {
        return User::where('account_id', $accountId)
            ->where('active', 1)
            ->permission('cashflow_dashboard')
            ->whereHas('user_has_locations', function ($q) use ($branchId) {
                $q->where('location_id', $branchId);
            })
            ->get(['id', 'name']);
    }
}
