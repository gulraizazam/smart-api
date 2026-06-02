<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Enums\HrNotificationType;
use App\Models\HrNotification;
use App\Models\LeaveApplication;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

class HrNotificationService
{
    /**
     * Path (relative to the SPA host, config app.spa_url) the notification
     * recipient lands on when they click — the SPA's HR Leaves page. Was
     * `route('admin.hr.leave-applications.index')` and `route('admin.hr.
     * my.leaves')` — both legacy Blade routes that die at cutover. Kept
     * as a single SPA path for both HR-side and self-service flows since
     * the SPA hasn't shipped a dedicated "my leaves" view yet; the HR
     * Leaves page covers both surfaces in the meantime.
     */
    private const SPA_HR_LEAVE_APPLICATIONS_PATH = '/hr/leave-applications';

    /**
     * Absolute SPA deep-link to the HR Leaves page, built from the
     * configured SPA host (config app.spa_url, e.g. crm3.cutera.pk).
     */
    private function leaveApplicationsUrl(): string
    {
        return rtrim((string) config('app.spa_url'), '/').self::SPA_HR_LEAVE_APPLICATIONS_PATH;
    }

    /**
     * Create a notification for a specific user.
     */
    public function notify(
        int $userId,
        HrNotificationType $type,
        string $title,
        string $message,
        int $accountId,
        ?int $referenceId = null,
        ?string $referenceType = null,
        ?string $url = null,
    ): HrNotification {
        return HrNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'url' => $url,
            'is_read' => false,
            'account_id' => $accountId,
        ]);
    }

    /**
     * Notify all users with a given permission in the account.
     */
    public function notifyByPermission(
        string $permission,
        int $accountId,
        HrNotificationType $type,
        string $title,
        string $message,
        ?int $referenceId = null,
        ?string $referenceType = null,
        ?string $url = null,
    ): void {
        $userIds = User::permission($permission)
            ->where('account_id', $accountId)
            ->where('active', 1)
            ->pluck('id');

        if ($userIds->isEmpty()) {
            return;
        }

        $now = now();
        $records = $userIds->map(fn (int $userId) => [
            'user_id' => $userId,
            'type' => $type->value,
            'title' => $title,
            'message' => $message,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'url' => $url,
            'is_read' => false,
            'account_id' => $accountId,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        HrNotification::insert($records);
    }

    /**
     * Send notification when employee submits leave.
     */
    public function onLeaveSubmitted(LeaveApplication $application): void
    {
        $application->loadMissing(['user', 'leaveType']);
        $empName = $application->user->name;
        $leaveType = $application->leaveType->name;

        $this->notifyByPermission(
            'hr_leave_approve',
            $application->account_id,
            HrNotificationType::LeaveSubmitted,
            'New Leave Request',
            "{$empName} applied for {$leaveType} ({$application->total_days} days).",
            $application->id,
            LeaveApplication::class,
            $this->leaveApplicationsUrl(),
        );
    }

    /**
     * Send notification when HR approves leave.
     */
    public function onLeaveApproved(LeaveApplication $application): void
    {
        $application->loadMissing('leaveType');

        $this->notify(
            $application->user_id,
            HrNotificationType::LeaveApproved,
            'Leave Approved',
            "Your {$application->leaveType->name} leave ({$application->total_days} days) has been approved.",
            $application->account_id,
            $application->id,
            LeaveApplication::class,
            $this->leaveApplicationsUrl(),
        );
    }

    /**
     * Send notification when HR rejects leave.
     */
    public function onLeaveRejected(LeaveApplication $application): void
    {
        $application->loadMissing('leaveType');

        $this->notify(
            $application->user_id,
            HrNotificationType::LeaveRejected,
            'Leave Rejected',
            "Your {$application->leaveType->name} leave ({$application->total_days} days) has been rejected.",
            $application->account_id,
            $application->id,
            LeaveApplication::class,
            $this->leaveApplicationsUrl(),
        );
    }

    /**
     * Send notification when employee cancels leave.
     */
    public function onLeaveCancelled(LeaveApplication $application): void
    {
        $application->loadMissing(['user', 'leaveType']);
        $empName = $application->user->name;

        $this->notifyByPermission(
            'hr_leave_approve',
            $application->account_id,
            HrNotificationType::LeaveCancelled,
            'Leave Cancelled',
            "{$empName} cancelled their {$application->leaveType->name} leave request.",
            $application->id,
            LeaveApplication::class,
            $this->leaveApplicationsUrl(),
        );
    }

    /**
     * Get recent notifications for a user.
     */
    public function getForUser(int $userId, int $limit = 20): Collection
    {
        return HrNotification::forUser($userId)
            ->recent($limit)
            ->get();
    }

    /**
     * Get unread count for a user.
     */
    public function unreadCount(int $userId): int
    {
        return HrNotification::forUser($userId)->unread()->count();
    }
}
