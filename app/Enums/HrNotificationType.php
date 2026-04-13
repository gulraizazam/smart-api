<?php

declare(strict_types=1);

namespace App\Enums;

enum HrNotificationType: string
{
    case LeaveSubmitted = 'leave_submitted';
    case LeaveApproved = 'leave_approved';
    case LeaveRejected = 'leave_rejected';
    case LeaveCancelled = 'leave_cancelled';

    public function label(): string
    {
        return match ($this) {
            self::LeaveSubmitted => 'Leave Submitted',
            self::LeaveApproved => 'Leave Approved',
            self::LeaveRejected => 'Leave Rejected',
            self::LeaveCancelled => 'Leave Cancelled',
        };
    }
}
