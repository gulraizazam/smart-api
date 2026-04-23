<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Department;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only HR reports (birthdays, work anniversaries). Shares the same
 * employee-scope rules as the HR employees listing:
 *   - user_type_id ∈ {2, 5}
 *   - hr_managed = 1
 *   - active = 1
 *   - same account
 */
class HrReportService
{
    /**
     * Employees whose date of birth falls in the given calendar month,
     * optionally narrowed by department. Ordered by day-of-month so the
     * table reads calendar-wise.
     *
     * @return Collection<int, User>
     */
    public function birthdays(int $accountId, int $month, ?int $departmentId = null): Collection
    {
        return $this->baseQuery($accountId, $departmentId)
            ->whereNotNull('users.dob')
            ->whereRaw('MONTH(users.dob) = ?', [$month])
            ->orderByRaw('DAY(users.dob) ASC')
            ->orderBy('users.name')
            ->get();
    }

    /**
     * Employees whose hire_date falls in the given calendar month (any year),
     * optionally narrowed by department. Employees hired this same calendar
     * year are excluded — they haven't completed a work anniversary yet.
     *
     * @return Collection<int, User>
     */
    public function anniversaries(int $accountId, int $month, ?int $departmentId = null): Collection
    {
        $currentYear = (int) CarbonImmutable::now()->year;

        return $this->baseQuery($accountId, $departmentId)
            ->whereHas('employeeDetail', function (Builder $q) use ($month, $currentYear): void {
                $q->whereNotNull('hire_date')
                    ->whereRaw('MONTH(hire_date) = ?', [$month])
                    ->whereRaw('YEAR(hire_date) < ?', [$currentYear]);
            })
            ->get()
            ->sortBy(fn (User $user): int => (int) $user->employeeDetail?->hire_date?->day)
            ->values();
    }

    /**
     * Departments the caller can see in the filter dropdown.
     *
     * @return Collection<int, Department>
     */
    public function departmentsForFilter(int $accountId): Collection
    {
        return Department::where('account_id', $accountId)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function baseQuery(int $accountId, ?int $departmentId): Builder
    {
        $query = User::query()
            ->with([
                'employeeDetail.department',
                'employeeDetail.designation',
            ])
            ->where('users.account_id', $accountId)
            ->whereIn('users.user_type_id', [2, 5])
            ->where('users.hr_managed', 1)
            ->where('users.active', 1);

        if ($departmentId !== null) {
            $query->whereHas('employeeDetail', function (Builder $q) use ($departmentId): void {
                $q->where('department_id', $departmentId);
            });
        }

        return $query;
    }
}
