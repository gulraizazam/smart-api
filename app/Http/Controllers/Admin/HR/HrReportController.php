<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\HR;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HR\HrReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function exportBirthdays(Request $request): StreamedResponse
    {
        [$scope, $month, $departmentId] = $this->resolveExportFilters($request);
        $accountId = (int) Auth::user()->account_id;

        $employees = $scope === 'all'
            ? $this->service->allBirthdays($accountId, $departmentId)
            : $this->service->birthdays($accountId, $month, $departmentId);

        $filename = $scope === 'all'
            ? 'employee_birthdays_all.csv'
            : sprintf('employee_birthdays_%s.csv', CarbonImmutable::create(null, $month, 1)->format('Y_m_M'));

        return $this->streamCsv(
            $filename,
            ['#', 'Name', 'Department', 'Designation', 'Date of Birth', 'Turning (this year)'],
            $this->buildBirthdayRows($employees),
        );
    }

    public function exportAnniversaries(Request $request): StreamedResponse
    {
        [$scope, $month, $departmentId] = $this->resolveExportFilters($request);
        $accountId = (int) Auth::user()->account_id;

        $employees = $scope === 'all'
            ? $this->service->allAnniversaries($accountId, $departmentId)
            : $this->service->anniversaries($accountId, $month, $departmentId);

        $filename = $scope === 'all'
            ? 'employee_anniversaries_all.csv'
            : sprintf('employee_anniversaries_%s.csv', CarbonImmutable::create(null, $month, 1)->format('Y_m_M'));

        return $this->streamCsv(
            $filename,
            ['#', 'Name', 'Department', 'Designation', 'Hire Date', 'Years Completing (this year)'],
            $this->buildAnniversaryRows($employees),
        );
    }

    /**
     * @param  Collection<int, User>  $employees
     * @return iterable<int, array<int, string|int>>
     */
    private function buildBirthdayRows(Collection $employees): iterable
    {
        $currentYear = (int) CarbonImmutable::now()->year;
        $i = 0;

        foreach ($employees as $employee) {
            $dob = $employee->dob;
            $turning = $dob ? $currentYear - (int) $dob->year : null;

            yield [
                ++$i,
                (string) $employee->name,
                (string) ($employee->employeeDetail?->department?->name ?? ''),
                (string) ($employee->employeeDetail?->designation?->name ?? ''),
                $dob?->format('Y-m-d') ?? '',
                $turning !== null ? (string) $turning : '',
            ];
        }
    }

    /**
     * @param  Collection<int, User>  $employees
     * @return iterable<int, array<int, string|int>>
     */
    private function buildAnniversaryRows(Collection $employees): iterable
    {
        $currentYear = (int) CarbonImmutable::now()->year;
        $i = 0;

        foreach ($employees as $employee) {
            $hire = $employee->employeeDetail?->hire_date;
            $years = $hire ? $currentYear - (int) $hire->year : null;

            yield [
                ++$i,
                (string) $employee->name,
                (string) ($employee->employeeDetail?->department?->name ?? ''),
                (string) ($employee->employeeDetail?->designation?->name ?? ''),
                $hire?->format('Y-m-d') ?? '',
                $years !== null ? (string) max(0, $years) : '',
            ];
        }
    }

    /**
     * @param  array<int, string>  $header
     * @param  iterable<int, array<int, string|int>>  $rows
     */
    private function streamCsv(string $filename, array $header, iterable $rows): StreamedResponse
    {
        $callback = function () use ($header, $rows): void {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens non-ASCII names correctly.
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, $header);
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    /**
     * @return array{0:'month'|'all',1:int,2:?int}
     */
    private function resolveExportFilters(Request $request): array
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'in:month,all'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $scope = ($validated['scope'] ?? 'month') === 'all' ? 'all' : 'month';
        $month = (int) ($validated['month'] ?? CarbonImmutable::now()->month);
        $departmentId = isset($validated['department_id']) ? (int) $validated['department_id'] : null;

        return [$scope, $month, $departmentId];
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
