<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Exports\GenericTableExport;
use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorIncentiveReportRequest;
use App\Models\DoctorHasLocations;
use App\Models\Locations;
use App\Models\User;
use App\Services\Reports\Concerns\ParsesDateRange;
use App\Services\Reports\DoctorIncentiveReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Doctor Incentive report — three endpoints:
 *
 *   GET  /api/reports/doctor-incentive/filters   → centres + active doctors
 *   GET  /api/reports/doctor-incentive/doctors   → centre-narrowed doctor list
 *   POST /api/reports/doctor-incentive           → JSON data (aggregate or per-doctor)
 *   POST /api/reports/doctor-incentive/export    → PDF or XLSX download
 *
 * Gated on its own dedicated `doctor_incentive_report` permission so a
 * role's access to this report can be toggled independently of Staff Wise
 * Arrival (which previously shared the `staff_wise_arrival_manage` gate).
 * Centre fallback to ACL centres mirrors the
 * MembershipReport pattern so an empty location_id never scans every
 * centre's package advances.
 */
class DoctorIncentiveReportController extends Controller
{
    use ParsesDateRange;

    private const DOCTOR_USER_TYPE_ID = 5;

    public function __construct(
        private readonly DoctorIncentiveReportService $reportService,
    ) {}

    public function __invoke(DoctorIncentiveReportRequest $request): JsonResponse
    {
        if (! Gate::allows('doctor_incentive_report')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $locationIds = $request->locationIds();
            if (empty($locationIds)) {
                $locationIds = array_map('intval', (array) ACL::getUserCentres());
            }

            [$startDate, $endDate] = $this->resolveDateRange(
                $request->startDate(),
                $request->endDate(),
            );

            $started = microtime(true);
            $data = $this->reportService->generate(
                centreIds: $locationIds,
                doctorId: $request->doctorId(),
                startDate: $startDate,
                endDate: $endDate,
            );
            $elapsed = round((microtime(true) - $started) * 1000, 1);

            return $this->successResponse('Doctor incentive report generated successfully', [
                ...$data,
                'meta' => [
                    'mode' => $data['mode'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'doctor_id' => $request->doctorId(),
                    'centres' => count($locationIds),
                    'elapsed_ms' => $elapsed,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'DoctorIncentiveReport');
        }
    }

    public function filters(): JsonResponse
    {
        if (! Gate::allows('doctor_incentive_report')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $accountId = Auth::user()->account_id;

            $locations = collect(Locations::getActiveRecordsByCity('', ACL::getUserCentres(), $accountId))
                ->map(fn ($l) => ['id' => (int) $l->id, 'name' => $l->name])
                ->values()
                ->all();

            $doctors = User::getAllRecords($accountId)
                ->where('user_type_id', self::DOCTOR_USER_TYPE_ID)
                ->where('active', 1)
                ->values()
                ->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])
                ->all();

            return $this->successResponse('OK', [
                'locations' => $locations,
                'doctors' => $doctors,
            ]);
        } catch (\Throwable $e) {
            Log::error('DoctorIncentiveReport filters failed: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    public function doctorsForCentres(Request $request): JsonResponse
    {
        if (! Gate::allows('doctor_incentive_report')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $request->validate([
                'centre_ids' => 'nullable|array',
                'centre_ids.*' => 'integer|exists:locations,id',
            ]);

            $accountId = Auth::user()->account_id;
            $centreIds = (array) $request->input('centre_ids', []);
            $centreIds = array_values(array_filter(array_map('intval', $centreIds), fn ($id) => $id > 0));

            if (empty($centreIds)) {
                $doctors = User::getAllRecords($accountId)
                    ->where('user_type_id', self::DOCTOR_USER_TYPE_ID)
                    ->where('active', 1)
                    ->values()
                    ->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])
                    ->all();

                return $this->successResponse('OK', ['doctors' => $doctors]);
            }

            $allocated = DoctorHasLocations::where('is_allocated', 1)
                ->whereIn('location_id', $centreIds)
                ->distinct()
                ->pluck('user_id')
                ->all();

            $doctors = empty($allocated)
                ? []
                : User::whereIn('id', $allocated)
                    ->where('account_id', $accountId)
                    ->where('user_type_id', self::DOCTOR_USER_TYPE_ID)
                    ->where('active', 1)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])
                    ->all();

            return $this->successResponse('OK', ['doctors' => $doctors]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('DoctorIncentiveReport doctorsForCentres failed: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    /**
     * Stream the same filtered result as `__invoke()` but as PDF or Excel.
     * Heading set differs between the aggregate and doctor-scoped views.
     */
    public function export(Request $request): SymfonyResponse
    {
        if (! Gate::allows('doctor_incentive_report')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,excel',
            'location_id' => 'nullable|array',
            'location_id.*' => 'integer|exists:locations,id',
            'doctor_id' => 'nullable|integer|exists:users,id',
            'date_range' => 'nullable|string',
        ]);

        $locationIds = $validated['location_id'] ?? [];
        $locationIds = array_values(array_filter(array_map('intval', $locationIds), fn ($id) => $id > 0));
        if (empty($locationIds)) {
            $locationIds = array_map('intval', (array) ACL::getUserCentres());
        }

        $doctorId = $validated['doctor_id'] ?? null;
        $doctorId = $doctorId !== null && $doctorId !== '' ? (int) $doctorId : null;

        [$startRaw, $endRaw] = self::parseDateRange($validated['date_range'] ?? null);
        [$startDate, $endDate] = $this->resolveDateRange($startRaw, $endRaw);

        $data = $this->reportService->generate(
            centreIds: $locationIds,
            doctorId: $doctorId,
            startDate: $startDate,
            endDate: $endDate,
        );

        $stamp = "-{$startDate}-to-{$endDate}";
        $title = 'Doctor Incentive Report';
        $subtitle = "Period: {$startDate} → {$endDate}";

        if ($data['mode'] === 'doctor') {
            $doctorName = $doctorId
                ? (User::whereKey($doctorId)->value('name') ?? "Doctor #{$doctorId}")
                : '—';
            $subtitle .= " · Doctor: {$doctorName}";
            $headings = ['Patient ID', 'Patient', 'Appointment Date', 'Payment Date', 'Amount'];
            $flatRows = collect($data['patients'])->map(fn ($p) => [
                $p['patient_id'],
                $p['patient_name'],
                $p['scheduled_date'],
                $p['payment_date'] ? substr((string) $p['payment_date'], 0, 10) : '',
                number_format((float) $p['cash_amount'], 2, '.', ''),
            ])->all();
            // Append a totals row so the export shows revenue / conversion / diff.
            $flatRows[] = ['', '', '', 'Total Revenue', number_format($data['total_doctor_revenue'], 2, '.', '')];
            $flatRows[] = ['', '', '', 'Total Conversion', number_format($data['total_cash_amount'], 2, '.', '')];
            $flatRows[] = ['', '', '', 'Difference', number_format($data['diff'], 2, '.', '')];
        } else {
            $headings = ['Month', 'Revenue'];
            $flatRows = collect($data['month_wise_revenue'])->map(fn ($r) => [
                date('F Y', strtotime($r['month'].'-01')),
                number_format((float) $r['monthly_total'], 2, '.', ''),
            ])->all();
            $flatRows[] = ['Total Revenue', number_format($data['total_revenue'], 2, '.', '')];
        }

        if ($validated['format'] === 'excel') {
            return Excel::download(new GenericTableExport($headings, $flatRows), "doctor-incentive{$stamp}.xlsx");
        }

        $pdf = Pdf::loadView('admin.reports.exports.generic-table', [
            'title' => $title,
            'subtitle' => $subtitle,
            'headings' => $headings,
            'rows' => $flatRows,
        ])->setPaper('A4', 'landscape');

        return $pdf->download("doctor-incentive{$stamp}.pdf");
    }

    /**
     * Default to last 30 days when the SPA doesn't pass a range — keeps
     * the unscoped query bounded so an unfiltered first paint is cheap.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(?string $start, ?string $end): array
    {
        if ($start && $end) {
            return [$start, $end];
        }
        $endDate = date('Y-m-d', strtotime('-1 day'));
        $startDate = date('Y-m-d', strtotime('-31 days'));

        return [$startDate, $endDate];
    }
}
