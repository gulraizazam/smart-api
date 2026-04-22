<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Enums\LeaveDurationType;
use App\Enums\LeaveStatus;
use App\Models\EmployeeDetail;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    public static function currentFiscalYear(): string
    {
        $now = now();
        $year = $now->month >= 7 ? $now->year : $now->year - 1;
        $nextYear = ($year + 1) % 100;

        return $year . '-' . str_pad((string) $nextYear, 2, '0', STR_PAD_LEFT);
    }

    public static function fiscalYearDates(string $fiscalYear): array
    {
        $parts = explode('-', $fiscalYear);
        $startYear = (int) $parts[0];

        return [
            $startYear . '-07-01',
            ($startYear + 1) . '-06-30',
        ];
    }

    public function apply(array $data, int $userId, int $accountId): LeaveApplication
    {
        $durationType = LeaveDurationType::from($data['duration_type']);
        $shiftHours = $this->resolveShiftHours($userId);
        $customHours = $durationType === LeaveDurationType::Custom
            ? (float) ($data['custom_hours'] ?? 0)
            : null;

        $totalDays = LeaveApplication::calculateTotalDays(
            $durationType,
            $data['start_date'],
            $data['end_date'],
            $customHours,
            $shiftHours,
        );

        return LeaveApplication::create([
            'user_id' => $userId,
            'leave_type_id' => $data['leave_type_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'duration_type' => $durationType->value,
            'custom_hours' => $customHours,
            'shift_hours_snapshot' => $shiftHours,
            'total_days' => $totalDays,
            'reason' => $data['reason'],
            'status' => LeaveStatus::Pending->value,
            'account_id' => $accountId,
        ]);
    }

    public function update(LeaveApplication $application, array $data): LeaveApplication
    {
        $durationType = LeaveDurationType::from($data['duration_type']);
        // Re-snapshot on edit so the pending application reflects the employee's current shift.
        $shiftHours = $this->resolveShiftHours((int) $application->user_id);
        $customHours = $durationType === LeaveDurationType::Custom
            ? (float) ($data['custom_hours'] ?? 0)
            : null;

        $totalDays = LeaveApplication::calculateTotalDays(
            $durationType,
            $data['start_date'],
            $data['end_date'],
            $customHours,
            $shiftHours,
        );

        $application->update([
            'leave_type_id' => $data['leave_type_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'duration_type' => $durationType->value,
            'custom_hours' => $customHours,
            'shift_hours_snapshot' => $shiftHours,
            'total_days' => $totalDays,
            'reason' => $data['reason'],
        ]);

        return $application->fresh();
    }

    public function resolveShiftHours(int $userId): ?float
    {
        $hours = EmployeeDetail::where('user_id', $userId)->value('shift_hours');

        return $hours !== null ? (float) $hours : null;
    }

    public function approve(LeaveApplication $application, ?string $notes, int $reviewerId): LeaveApplication
    {
        return DB::transaction(function () use ($application, $notes, $reviewerId) {
            $fresh = LeaveApplication::lockForUpdate()->find($application->id);

            if (!$fresh || $fresh->status !== LeaveStatus::Pending) {
                throw new \RuntimeException('Application has already been processed.');
            }

            $fresh->update([
                'status' => LeaveStatus::Approved->value,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            $this->incrementBalance($fresh);

            return $fresh;
        });
    }

    public function reject(LeaveApplication $application, ?string $notes, int $reviewerId): LeaveApplication
    {
        return DB::transaction(function () use ($application, $notes, $reviewerId) {
            $fresh = LeaveApplication::lockForUpdate()->find($application->id);

            if (!$fresh || $fresh->status !== LeaveStatus::Pending) {
                throw new \RuntimeException('Application has already been processed.');
            }

            $fresh->update([
                'status' => LeaveStatus::Rejected->value,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            return $fresh;
        });
    }

    public function cancel(LeaveApplication $application): LeaveApplication
    {
        return DB::transaction(function () use ($application) {
            $fresh = LeaveApplication::lockForUpdate()->find($application->id);

            if (!$fresh || !in_array($fresh->status, [LeaveStatus::Pending, LeaveStatus::Approved])) {
                throw new \RuntimeException('Only pending or approved applications can be cancelled.');
            }

            $wasApproved = $fresh->status === LeaveStatus::Approved;

            $fresh->update([
                'status' => LeaveStatus::Cancelled->value,
            ]);

            if ($wasApproved) {
                $this->decrementBalance($fresh);
            }

            return $fresh;
        });
    }

    protected function decrementBalance(LeaveApplication $application): void
    {
        $fiscalYear = self::currentFiscalYear();

        $balance = LeaveBalance::where('user_id', $application->user_id)
            ->where('leave_type_id', $application->leave_type_id)
            ->where('fiscal_year', $fiscalYear)
            ->first();

        if ($balance) {
            $newUsed = max(0, (float) bcsub((string) $balance->used, (string) $application->total_days, 2));
            $balance->update(['used' => $newUsed]);
        }
    }

    public function hasOverlap(int $userId, string $startDate, string $endDate, ?int $excludeId = null): bool
    {
        return LeaveApplication::hasOverlap($userId, $startDate, $endDate, $excludeId);
    }

    /**
     * Return a LeaveApplication with the relationships the detail API needs,
     * already loaded — reviewer, employee + employeeDetail.designation, and
     * the leaveType. Single query, no N+1.
     */
    public function getDetail(LeaveApplication $application): LeaveApplication
    {
        return $application->load([
            'user.employeeDetail.designation',
            'leaveType',
            'reviewer',
        ]);
    }

    public function getBalanceWarning(int $userId, int $leaveTypeId, float $requestedDays): ?string
    {
        $fiscalYear = self::currentFiscalYear();
        $balance = LeaveBalance::forUser($userId)
            ->forFiscalYear($fiscalYear)
            ->where('leave_type_id', $leaveTypeId)
            ->first();

        if (!$balance) {
            return 'No leave balance allocated for this type.';
        }

        $remaining = bcsub((string) $balance->allocated, (string) $balance->used, 2);
        if (bccomp((string) $requestedDays, $remaining, 2) === 1) {
            return "Requested {$requestedDays} days but only {$remaining} remaining (allocated: {$balance->allocated}, used: {$balance->used}).";
        }

        return null;
    }

    public function allocateBalance(int $userId, int $leaveTypeId, float $days, string $fiscalYear, int $accountId): LeaveBalance
    {
        return LeaveBalance::updateOrCreate(
            [
                'user_id' => $userId,
                'leave_type_id' => $leaveTypeId,
                'fiscal_year' => $fiscalYear,
                'account_id' => $accountId,
            ],
            [
                'allocated' => $days,
            ]
        );
    }

    public function bulkAllocate(array $userIds, int $leaveTypeId, float $days, string $fiscalYear, int $accountId): int
    {
        $count = 0;
        foreach ($userIds as $userId) {
            $this->allocateBalance((int) $userId, $leaveTypeId, $days, $fiscalYear, $accountId);
            $count++;
        }

        return $count;
    }

    protected function incrementBalance(LeaveApplication $application): void
    {
        $fiscalYear = self::currentFiscalYear();

        $balance = LeaveBalance::firstOrCreate(
            [
                'user_id' => $application->user_id,
                'leave_type_id' => $application->leave_type_id,
                'fiscal_year' => $fiscalYear,
                'account_id' => $application->account_id,
            ],
            [
                'allocated' => 0,
                'used' => 0,
            ]
        );

        $balance->increment('used', (float) $application->total_days);
    }
}
