<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\HR;

use App\Http\Controllers\Controller;
use App\Services\HR\HrReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HrReportController extends Controller
{
    public function __construct(
        protected readonly HrReportService $service,
    ) {}

    public function birthdays(Request $request): View
    {
        [$month, $departmentId] = $this->resolveFilters($request);
        $accountId = (int) Auth::user()->account_id;

        $employees = $this->service->birthdays($accountId, $month, $departmentId);
        $departments = $this->service->departmentsForFilter($accountId);

        return view('admin.hr.reports.birthdays', [
            'employees' => $employees,
            'departments' => $departments,
            'selectedMonth' => $month,
            'selectedDepartmentId' => $departmentId,
            'months' => $this->monthOptions(),
        ]);
    }

    public function anniversaries(Request $request): View
    {
        [$month, $departmentId] = $this->resolveFilters($request);
        $accountId = (int) Auth::user()->account_id;

        $employees = $this->service->anniversaries($accountId, $month, $departmentId);
        $departments = $this->service->departmentsForFilter($accountId);

        return view('admin.hr.reports.anniversaries', [
            'employees' => $employees,
            'departments' => $departments,
            'selectedMonth' => $month,
            'selectedDepartmentId' => $departmentId,
            'months' => $this->monthOptions(),
        ]);
    }

    /**
     * @return array{0:int,1:?int} [month 1-12, optional department id]
     */
    private function resolveFilters(Request $request): array
    {
        $validated = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $month = (int) ($validated['month'] ?? CarbonImmutable::now()->month);
        $departmentId = isset($validated['department_id']) ? (int) $validated['department_id'] : null;

        return [$month, $departmentId];
    }

    /**
     * @return array<int, string>
     */
    private function monthOptions(): array
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = CarbonImmutable::create(null, $i, 1)->format('F');
        }

        return $months;
    }
}
