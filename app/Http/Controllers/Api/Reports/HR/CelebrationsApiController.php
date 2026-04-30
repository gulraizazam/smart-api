<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports\HR;

use App\Http\Controllers\Controller;
use App\Services\HR\HrReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SPA-facing JSON wrapper around HrReportService. Mirrors the data the
 * legacy Blade page renders so the SPA can build the same tabs / calendar /
 * cards without HTML-scraping. Permission gate matches the web side
 * (hr_employees_view).
 */
class CelebrationsApiController extends Controller
{
    public function __construct(
        protected readonly HrReportService $service,
    ) {}

    /** GET /api/hr/reports/celebrations */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('hr_employees_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $validated = $request->validate([
                'tab' => ['nullable', 'in:birthdays,anniversaries'],
                'month' => ['nullable', 'integer', 'between:1,12'],
                'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            ]);

            $tab = ($validated['tab'] ?? 'birthdays') === 'anniversaries' ? 'anniversaries' : 'birthdays';
            $month = (int) ($validated['month'] ?? CarbonImmutable::now()->month);
            $departmentId = isset($validated['department_id']) ? (int) $validated['department_id'] : null;
            $accountId = (int) Auth::user()->account_id;
            $year = (int) CarbonImmutable::now()->year;

            $employees = $tab === 'anniversaries'
                ? $this->service->anniversaries($accountId, $month, $departmentId)
                : $this->service->birthdays($accountId, $month, $departmentId);

            return $this->successResponse('OK', [
                'tab' => $tab,
                'month' => $month,
                'year' => $year,
                'department_id' => $departmentId,
                'employees' => $this->mapEmployees($employees, $tab, $year),
                'events_by_day' => $this->groupByDay($employees, $tab, $year),
                'calendar' => $this->buildCalendar($year, $month),
                'departments' => $this->service->departmentsForFilter($accountId)
                    ->map(fn ($d) => ['id' => (int) $d->id, 'name' => (string) $d->name])
                    ->values(),
                'months' => collect(range(1, 12))->map(fn ($m) => [
                    'value' => $m,
                    'label' => CarbonImmutable::create(null, $m, 1)->format('F'),
                ])->values(),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\Reports\\HR\\CelebrationsApiController@__invoke');
        }
    }

    /** GET /api/hr/reports/celebrations/birthdays/export?scope=month|all&month=&department_id= */
    public function exportBirthdays(Request $request): StreamedResponse|JsonResponse
    {
        if (! Gate::allows('hr_employees_view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        [$scope, $month, $departmentId] = $this->resolveExportFilters($request);
        $accountId = (int) Auth::user()->account_id;
        $year = (int) CarbonImmutable::now()->year;

        $employees = $scope === 'all'
            ? $this->service->allBirthdays($accountId, $departmentId)
            : $this->service->birthdays($accountId, $month, $departmentId);

        $filename = $scope === 'all'
            ? 'employee_birthdays_all.csv'
            : sprintf('employee_birthdays_%s.csv', CarbonImmutable::create(null, $month, 1)->format('Y_m_M'));

        return $this->streamCsv($filename, ['#', 'Name', 'Department', 'Designation', 'Date of Birth', 'Turning'], (function () use ($employees, $year) {
            $i = 0;
            foreach ($employees as $u) {
                $dob = $u->dob;
                yield [
                    ++$i,
                    (string) $u->name,
                    (string) ($u->employeeDetail?->department?->name ?? ''),
                    (string) ($u->employeeDetail?->designation?->name ?? ''),
                    $dob?->format('Y-m-d') ?? '',
                    $dob ? (string) ($year - (int) $dob->year) : '',
                ];
            }
        })());
    }

    /** GET /api/hr/reports/celebrations/anniversaries/export */
    public function exportAnniversaries(Request $request): StreamedResponse|JsonResponse
    {
        if (! Gate::allows('hr_employees_view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        [$scope, $month, $departmentId] = $this->resolveExportFilters($request);
        $accountId = (int) Auth::user()->account_id;
        $year = (int) CarbonImmutable::now()->year;

        $employees = $scope === 'all'
            ? $this->service->allAnniversaries($accountId, $departmentId)
            : $this->service->anniversaries($accountId, $month, $departmentId);

        $filename = $scope === 'all'
            ? 'employee_anniversaries_all.csv'
            : sprintf('employee_anniversaries_%s.csv', CarbonImmutable::create(null, $month, 1)->format('Y_m_M'));

        return $this->streamCsv($filename, ['#', 'Name', 'Department', 'Designation', 'Hire Date', 'Years'], (function () use ($employees, $year) {
            $i = 0;
            foreach ($employees as $u) {
                $hire = $u->employeeDetail?->hire_date;
                yield [
                    ++$i,
                    (string) $u->name,
                    (string) ($u->employeeDetail?->department?->name ?? ''),
                    (string) ($u->employeeDetail?->designation?->name ?? ''),
                    $hire?->format('Y-m-d') ?? '',
                    $hire ? (string) max(0, $year - (int) $hire->year) : '',
                ];
            }
        })());
    }

    /**
     * @param  Collection<int, \App\Models\User>  $employees
     */
    private function mapEmployees(Collection $employees, string $tab, int $year): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $out = [];
        foreach ($employees as $u) {
            $eventDate = $tab === 'anniversaries' ? $u->employeeDetail?->hire_date : $u->dob;
            if (! $eventDate) {
                continue;
            }
            $magnitude = $year - (int) $eventDate->year;
            if ($tab === 'anniversaries' && $magnitude <= 0) {
                $magnitudeLabel = null;
            } elseif ($tab === 'anniversaries') {
                $magnitudeLabel = $magnitude . ' ' . ($magnitude === 1 ? 'year' : 'years');
            } else {
                $magnitudeLabel = 'Turning ' . $magnitude;
            }
            $thisYearEvent = $eventDate->copy()->year($year)->startOfDay();
            $isToday = $thisYearEvent->isSameDay($today);
            $isPast = $thisYearEvent->isPast() && ! $isToday;
            $daysAway = (int) $today->diffInDays($thisYearEvent, false);

            $out[] = [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
                'department' => $u->employeeDetail?->department?->name,
                'designation' => $u->employeeDetail?->designation?->name,
                'event_date' => $eventDate->format('Y-m-d'),
                'day' => (int) $eventDate->day,
                'month_short' => $eventDate->format('M'),
                'magnitude' => $magnitude,
                'magnitude_label' => $magnitudeLabel,
                'is_event_today' => $isToday,
                'is_event_past' => $isPast,
                'days_away' => $daysAway,
            ];
        }
        return $out;
    }

    /**
     * @param  Collection<int, \App\Models\User>  $employees
     */
    private function groupByDay(Collection $employees, string $tab, int $year): array
    {
        $grouped = [];
        foreach ($employees as $u) {
            $source = $tab === 'anniversaries' ? $u->employeeDetail?->hire_date : $u->dob;
            if (! $source) {
                continue;
            }
            $magnitude = $year - (int) $source->year;
            $sub = $tab === 'anniversaries'
                ? ($magnitude > 0 ? $magnitude . ' ' . ($magnitude === 1 ? 'year' : 'years') : '')
                : ('Turning ' . $magnitude);
            $day = (int) $source->day;
            $grouped[$day] ??= [];
            $grouped[$day][] = [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
                'sub' => $sub,
            ];
        }
        return $grouped;
    }

    private function buildCalendar(int $year, int $month): array
    {
        $first = CarbonImmutable::create($year, $month, 1);
        $today = CarbonImmutable::now();
        return [
            'days_in_month' => $first->daysInMonth,
            'leading_blanks' => (int) $first->dayOfWeek, // 0=Sun..6=Sat
            'today' => ($today->year === $year && $today->month === $month) ? (int) $today->day : null,
        ];
    }

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

    private function streamCsv(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->stream(function () use ($header, $rows): void {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, $header);
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }
}
