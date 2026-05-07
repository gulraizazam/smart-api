<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Exports\GenericTableExport;
use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Models\Locations;
use App\Services\Reports\Concerns\ParsesDateRange;
use App\Services\Upselling\UpsellingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * SPA bridge for the Upselling family of reports —
 *   - Doctor Upselling
 *   - Consultants Revenue
 *
 * Both endpoints share the same shape (centre filter + date range) and
 * permission gate (`upselling_report` / `consultant_revenue_report`).
 *
 *   GET  /api/reports/upselling/filters                 → centres list
 *   POST /api/reports/upselling/doctor                  → doctor upselling rows
 *   POST /api/reports/upselling/doctor/export           → PDF / XLSX
 *   POST /api/reports/upselling/consultant-revenue      → consultants revenue rows
 *   POST /api/reports/upselling/consultant-revenue/export → PDF / XLSX
 */
class UpsellingApiController extends Controller
{
    use ParsesDateRange;

    public function __construct(private readonly UpsellingService $service) {}

    public function filters(): JsonResponse
    {
        try {
            $accountId = Auth::user()->account_id;
            $locations = collect(Locations::getActiveRecordsByCity('', ACL::getUserCentres(), $accountId))
                ->map(fn ($l) => ['id' => (int) $l->id, 'name' => $l->name])
                ->values()
                ->all();

            return $this->successResponse('OK', [
                'locations' => $locations,
            ]);
        } catch (\Throwable $e) {
            Log::error('UpsellingApi filters failed: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    // ---------------------------------------------------------------------
    // Doctor upselling
    // ---------------------------------------------------------------------

    public function doctorUpselling(Request $request): JsonResponse
    {
        if (! Gate::allows('upselling_report')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            [$locationIds, $startDate, $endDate] = $this->normaliseInput($request);

            $started = microtime(true);
            $data = $this->service->getDoctorUpsellingData($locationIds, $startDate, $endDate);
            $elapsed = round((microtime(true) - $started) * 1000, 1);

            $rows = collect($data)->map(fn ($r) => (object) $r)->map(fn ($r) => [
                'doctor_id' => (int) ($r->doctor_id ?? 0),
                'doctor_name' => (string) ($r->doctor_name ?? ''),
                'total_upselling_amount' => (float) ($r->total_upselling_amount ?? 0),
            ])->sortByDesc('total_upselling_amount')->values()->all();

            return $this->successResponse('Doctor upselling report generated successfully', [
                'rows' => $rows,
                'meta' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'count' => count($rows),
                    'elapsed_ms' => $elapsed,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'DoctorUpsellingReport');
        }
    }

    /**
     * Per-doctor breakdown — every package-service this doctor sold in the
     * filter period, plus running totals. Wraps
     * `UpsellingService::getDoctorUpsellingDetailData` for the SPA.
     */
    public function doctorUpsellingDetail(Request $request, int $doctor): JsonResponse
    {
        if (! Gate::allows('upselling_report')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            [$locationIds, $startDate, $endDate] = $this->normaliseInput($request);

            $started = microtime(true);
            $data = $this->service->getDoctorUpsellingDetailData($doctor, $locationIds, $startDate, $endDate);
            $elapsed = round((microtime(true) - $started) * 1000, 1);

            $rows = collect($data['packageServices'] ?? [])->map(fn ($r) => [
                'package_id' => (int) ($r->package_id ?? 0),
                'service_id' => (int) ($r->service_id ?? 0),
                'service_name' => (string) ($r->service_name ?? ''),
                'patient_id' => isset($r->patient_id) ? (int) $r->patient_id : null,
                'patient_name' => (string) ($r->patient_name ?? ''),
                'scheduled_date' => $r->scheduled_date ? (string) $r->scheduled_date : null,
                'sold_at' => $r->created_at ? (string) $r->created_at : null,
                'amount' => (float) ($r->tax_including_price ?? 0),
            ])->values()->all();

            return $this->successResponse('Doctor upselling detail generated successfully', [
                'doctor_id' => $doctor,
                'doctor_name' => (string) ($data['doctorName'] ?? ''),
                'total_amount' => (float) ($data['totalAmount'] ?? 0),
                'unique_upsellings' => (int) ($data['uniqueUpsellings'] ?? 0),
                'rows' => $rows,
                'meta' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'count' => count($rows),
                    'elapsed_ms' => $elapsed,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'DoctorUpsellingDetail');
        }
    }

    public function doctorUpsellingExport(Request $request): SymfonyResponse
    {
        if (! Gate::allows('upselling_report')) {
            abort(403, 'Unauthorized.');
        }

        $request->validate(['format' => 'required|in:pdf,excel']);
        [$locationIds, $startDate, $endDate] = $this->normaliseInput($request);

        $data = $this->service->getDoctorUpsellingData($locationIds, $startDate, $endDate);
        $rows = collect($data)->map(fn ($r) => (object) $r);

        $headings = ['Doctor', 'Total upselling'];
        $flat = $rows->sortByDesc(fn ($r) => (float) $r->total_upselling_amount)
            ->map(fn ($r) => [
                (string) ($r->doctor_name ?? ''),
                number_format((float) ($r->total_upselling_amount ?? 0), 2, '.', ''),
            ])->values()->all();

        $title = 'Doctor Upselling Report';
        $subtitle = "Period: ".substr($startDate, 0, 10)." → ".substr($endDate, 0, 10);
        $stamp = '-'.substr($startDate, 0, 10).'-to-'.substr($endDate, 0, 10);

        if ($request->input('format') === 'excel') {
            return Excel::download(new GenericTableExport($headings, $flat), "doctor-upselling{$stamp}.xlsx");
        }

        $pdf = Pdf::loadView('admin.reports.exports.generic-table', [
            'title' => $title,
            'subtitle' => $subtitle,
            'headings' => $headings,
            'rows' => $flat,
        ])->setPaper('A4', 'landscape');

        return $pdf->download("doctor-upselling{$stamp}.pdf");
    }

    // ---------------------------------------------------------------------
    // Consultant revenue
    // ---------------------------------------------------------------------

    public function consultantRevenue(Request $request): JsonResponse
    {
        if (! Gate::allows('consultant_revenue_report')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            [$locationIds, $startDate, $endDate] = $this->normaliseInput($request);

            $started = microtime(true);
            $result = $this->service->getConsultantRevenueReportData($locationIds, $startDate, $endDate);
            $elapsed = round((microtime(true) - $started) * 1000, 1);

            $rows = collect($result['reportData'] ?? [])->map(fn ($r) => [
                'consultant_id' => (int) ($r->consultant_id ?? 0),
                'consultant_name' => (string) ($r->consultant_name ?? ''),
                'total_consultation_revenue' => (float) ($r->total_consultation_revenue ?? 0),
                'total_consumed_amount' => (float) ($r->total_consumed_amount ?? 0),
            ])->all();

            return $this->successResponse('Consultant revenue report generated successfully', [
                'rows' => $rows,
                'meta' => [
                    'empty' => (bool) ($result['empty'] ?? false),
                    'message' => $result['message'] ?? null,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'count' => count($rows),
                    'elapsed_ms' => $elapsed,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'ConsultantRevenueReport');
        }
    }

    public function consultantRevenueExport(Request $request): SymfonyResponse
    {
        if (! Gate::allows('consultant_revenue_report')) {
            abort(403, 'Unauthorized.');
        }

        $request->validate(['format' => 'required|in:pdf,excel']);
        [$locationIds, $startDate, $endDate] = $this->normaliseInput($request);

        $result = $this->service->getConsultantRevenueReportData($locationIds, $startDate, $endDate);

        $headings = ['Consultant', 'Total consultation revenue', 'Total consumed amount'];
        $flat = collect($result['reportData'] ?? [])->map(fn ($r) => [
            (string) ($r->consultant_name ?? ''),
            number_format((float) ($r->total_consultation_revenue ?? 0), 2, '.', ''),
            number_format((float) ($r->total_consumed_amount ?? 0), 2, '.', ''),
        ])->all();

        $title = 'Consultants Revenue Report';
        $subtitle = "Period: ".substr($startDate, 0, 10)." → ".substr($endDate, 0, 10);
        $stamp = '-'.substr($startDate, 0, 10).'-to-'.substr($endDate, 0, 10);

        if ($request->input('format') === 'excel') {
            return Excel::download(new GenericTableExport($headings, $flat), "consultant-revenue{$stamp}.xlsx");
        }

        $pdf = Pdf::loadView('admin.reports.exports.generic-table', [
            'title' => $title,
            'subtitle' => $subtitle,
            'headings' => $headings,
            'rows' => $flat,
        ])->setPaper('A4', 'landscape');

        return $pdf->download("consultant-revenue{$stamp}.pdf");
    }

    /**
     * Validate + normalise request to a `[centreIds, start, end]` tuple
     * with sensible defaults (last 30 days, ACL centres).
     *
     * @return array{0: int[], 1: string, 2: string}
     */
    private function normaliseInput(Request $request): array
    {
        $request->validate([
            'location_id' => 'nullable|array',
            'location_id.*' => 'integer|exists:locations,id',
            'date_range' => 'nullable|string',
        ]);

        $locationIds = $request->input('location_id') ?? [];
        $locationIds = array_values(array_filter(array_map('intval', (array) $locationIds), fn ($id) => $id > 0));
        if (empty($locationIds)) {
            $locationIds = array_map('intval', (array) ACL::getUserCentres());
        }

        [$start, $end] = self::parseDateRangeWithTimeBounds($request->input('date_range'));
        if (! $start || ! $end) {
            $end = date('Y-m-d', strtotime('-1 day')).' 23:59:59';
            $start = date('Y-m-d', strtotime('-31 days')).' 00:00:00';
        }

        return [$locationIds, $start, $end];
    }
}
