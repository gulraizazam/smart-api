<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Exports\GenericTableExport;
use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Http\Requests\MembershipReportRequest;
use App\Http\Resources\Reports\MembershipReportResource;
use App\Services\Reports\MembershipReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MembershipReportController extends Controller
{
    public function __construct(
        private readonly MembershipReportService $reportService,
    ) {}

    public function __invoke(MembershipReportRequest $request): JsonResponse
    {
        if (! Gate::allows('operations_reports_manage')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        try {
            // Centre fallback: when the SPA didn't pick any centres, scope
            // the report to the user's ACL-allowed centres (same as the
            // legacy `MembershipReportsController::loadMembershipReport`).
            // Without this, the underlying query scans every centre's
            // packages and the request hangs on data sets of any real
            // size — that's the symptom we were seeing on the SPA where
            // the spinner never resolved.
            $locationIds = $request->locationIds();
            if (empty($locationIds)) {
                $locationIds = array_map('intval', (array) ACL::getUserCentres());
            }

            $started = microtime(true);

            $data = $this->reportService->generate(
                locationIds: $locationIds,
                membershipTypeId: $request->membershipTypeId(),
                startDate: $request->startDate(),
                endDate: $request->endDate(),
            );

            $elapsed = round((microtime(true) - $started) * 1000, 1);
            Log::info('MembershipReport generated', [
                'rows' => $data->count(),
                'elapsed_ms' => $elapsed,
                'centres' => count($locationIds),
                'date_from' => $request->startDate(),
                'date_to' => $request->endDate(),
                'membership_type_id' => $request->membershipTypeId(),
            ]);

            // Hard cap so the SPA never sees a multi-megabyte payload from
            // an unscoped run. The legacy view didn't paginate either; if
            // the truncation kicks in we surface it via meta so the UI can
            // tell the user to narrow filters.
            $limit = 5000;
            $truncated = $data->count() > $limit;
            $rows = $truncated ? $data->take($limit) : $data;

            return $this->successResponse('Membership report generated successfully', [
                'rows' => MembershipReportResource::collection($rows),
                'meta' => [
                    'total' => $data->count(),
                    'returned' => $rows->count(),
                    'truncated' => $truncated,
                    'start_date' => $request->startDate(),
                    'end_date' => $request->endDate(),
                    'elapsed_ms' => $elapsed,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'MembershipReport');
        }
    }

    /**
     * Stream the same filtered result as `__invoke()` but as PDF or Excel.
     */
    public function export(Request $request): SymfonyResponse
    {
        if (! Gate::allows('operations_reports_manage')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,excel',
            'location_id' => 'nullable|array',
            'location_id.*' => 'integer|exists:locations,id',
            'membership_type_id' => 'nullable|string',
            'date_range' => 'nullable|string',
        ]);

        $locationIds = $validated['location_id'] ?? [];
        $locationIds = array_values(array_filter(array_map('intval', $locationIds), fn ($id) => $id > 0));
        if (empty($locationIds)) {
            $locationIds = array_map('intval', (array) ACL::getUserCentres());
        }

        $membershipTypeId = $validated['membership_type_id'] ?? null;
        if ($membershipTypeId === '') {
            $membershipTypeId = null;
        }

        [$startDate, $endDate] = $this->parseDateRangeOrNull($validated['date_range'] ?? null);

        $rows = $this->reportService->generate(
            locationIds: $locationIds,
            membershipTypeId: $membershipTypeId,
            startDate: $startDate,
            endDate: $endDate,
        );

        $headings = ['User', 'Centre', 'Service', 'Service status', 'Membership type', 'Code', 'Assigned'];
        $flatRows = $rows->map(fn ($r) => [
            $r['user_name'],
            $r['location'],
            $r['service_name'],
            $r['service_status'],
            $r['membership_type'],
            $r['membership_code'],
            $r['assigned_at'] ? substr((string) $r['assigned_at'], 0, 10) : '',
        ])->values()->all();

        $title = 'Membership Report';
        $subtitle = $startDate && $endDate ? "Period: {$startDate} → {$endDate}" : 'All dates';
        $stamp = ($startDate && $endDate) ? "-{$startDate}-to-{$endDate}" : '';

        if ($validated['format'] === 'excel') {
            return Excel::download(new GenericTableExport($headings, $flatRows), "memberships{$stamp}.xlsx");
        }

        $pdf = Pdf::loadView('admin.reports.exports.generic-table', [
            'title' => $title,
            'subtitle' => $subtitle,
            'headings' => $headings,
            'rows' => $flatRows,
        ])->setPaper('A4', 'landscape');

        return $pdf->download("memberships{$stamp}.pdf");
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function parseDateRangeOrNull(?string $range): array
    {
        if (! $range) {
            return [null, null];
        }
        $parts = explode(' - ', $range);
        if (count($parts) !== 2) {
            return [null, null];
        }
        return [
            date('Y-m-d', strtotime($parts[0])),
            date('Y-m-d', strtotime($parts[1])),
        ];
    }
}
