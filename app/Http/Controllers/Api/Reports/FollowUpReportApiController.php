<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Exports\GenericTableExport;
use App\Helpers\ACL;
use App\Helpers\GeneralFunctions;
use App\Http\Controllers\Controller;
use App\Models\Locations;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Follow Up report — SPA bridge over the legacy
 * `GeneralFunctions::PatientFollowUpReport` (consultancy mode) and
 * `LoadPatientFollowUpReportMonthly` (treatment mode) helpers.
 *
 *   GET  /api/reports/follow-up/filters  → centres
 *   POST /api/reports/follow-up          → patient list
 *   POST /api/reports/follow-up/export   → PDF / XLSX
 *
 * Permission: `follow_up_manage` (matches the legacy route middleware).
 */
class FollowUpReportApiController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! Gate::allows('follow_up_manage')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $validated = $this->validatePayload($request);

            $started = microtime(true);
            $rows = $this->loadRows($validated);
            $elapsed = round((microtime(true) - $started) * 1000, 1);

            // Hydrate centre name on each row so the SPA doesn't have to
            // look them up by id.
            $centreMap = $this->centreMap();
            $rows = array_map(function (array $r) use ($centreMap) {
                $cid = isset($r['location_id']) ? (int) $r['location_id'] : null;
                $r['centre'] = $cid !== null ? ($centreMap[$cid] ?? null) : null;
                $r['balance'] = (float) ($r['cash_receive'] ?? 0) - (float) ($r['settle_amount_with_tax'] ?? 0);
                return $r;
            }, $rows);

            return $this->successResponse('Follow up report generated successfully', [
                'rows' => $rows,
                'meta' => [
                    'report_type' => $validated['report_type'],
                    'count' => count($rows),
                    'elapsed_ms' => $elapsed,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'FollowUpReport');
        }
    }

    public function filters(): JsonResponse
    {
        if (! Gate::allows('follow_up_manage')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

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
            Log::error('FollowUpReport filters failed: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    public function export(Request $request): SymfonyResponse
    {
        if (! Gate::allows('follow_up_manage')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $this->validatePayload($request, true);

        $rows = $this->loadRows($validated);
        $centreMap = $this->centreMap();

        $isMonthly = $validated['report_type'] === 'monthly';
        $headings = $isMonthly
            ? ['Patient ID', 'Patient', 'Phone', 'Centre', 'Last treatment', 'Cash receive', 'Settle amount', 'Balance']
            : ['Patient ID', 'Patient', 'Phone', 'Centre', 'Conversion date', 'Cash receive', 'Settle amount', 'Balance'];

        $flat = array_map(function (array $r) use ($centreMap, $isMonthly) {
            $cid = isset($r['location_id']) ? (int) $r['location_id'] : null;
            $centre = $cid !== null ? ($centreMap[$cid] ?? '') : '';
            $balance = (float) ($r['cash_receive'] ?? 0) - (float) ($r['settle_amount_with_tax'] ?? 0);
            $dateField = $isMonthly ? ($r['scheduled_date'] ?? '') : ($r['created_at'] ?? '');
            return [
                $r['patient_id'] ?? '',
                $r['name'] ?? '',
                $r['phone'] ?? '',
                $centre,
                $dateField ? substr((string) $dateField, 0, 10) : '',
                number_format((float) ($r['cash_receive'] ?? 0), 2, '.', ''),
                number_format((float) ($r['settle_amount_with_tax'] ?? 0), 2, '.', ''),
                number_format($balance, 2, '.', ''),
            ];
        }, $rows);

        $title = $isMonthly ? 'Follow Up Report (Treatment)' : 'Follow Up Report (Consultancy)';
        $subtitle = $isMonthly
            ? 'Patients with last treatment 31+ days ago and outstanding balance.'
            : 'Patients with consultation > 7 days ago and outstanding balance.';
        $stamp = '-'.date('Ymd-His');

        if ($validated['format'] === 'excel') {
            return Excel::download(new GenericTableExport($headings, $flat), "follow-up{$stamp}.xlsx");
        }

        $pdf = Pdf::loadView('admin.reports.exports.generic-table', [
            'title' => $title,
            'subtitle' => $subtitle,
            'headings' => $headings,
            'rows' => $flat,
        ])->setPaper('A4', 'landscape');

        return $pdf->download("follow-up{$stamp}.pdf");
    }

    /**
     * @return array{report_type: string, location_id: ?array, patient_id: ?int, search: ?string, start_date: ?string, end_date: ?string, format?: string}
     */
    private function validatePayload(Request $request, bool $forExport = false): array
    {
        $rules = [
            'report_type' => 'nullable|in:default,monthly',
            'location_id' => 'nullable|array',
            'location_id.*' => 'integer|exists:locations,id',
            'patient_id' => 'nullable|integer',
            'search' => 'nullable|string|max:120',
            // "Y-m-d - Y-m-d" — same shape as the other reports.
            'date_range' => 'nullable|string',
        ];
        if ($forExport) {
            $rules['format'] = 'required|in:pdf,excel';
        }
        $validated = $request->validate($rules);

        $startDate = null;
        $endDate = null;
        if (! empty($validated['date_range'])) {
            $parts = explode(' - ', (string) $validated['date_range']);
            $startDate = isset($parts[0]) ? date('Y-m-d', strtotime($parts[0])) : null;
            $endDate = isset($parts[1]) ? date('Y-m-d', strtotime($parts[1])) : $startDate;
        }

        return [
            'report_type' => $validated['report_type'] ?? 'default',
            'location_id' => $validated['location_id'] ?? null,
            'patient_id' => isset($validated['patient_id']) ? (int) $validated['patient_id'] : null,
            'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'format' => $validated['format'] ?? null,
        ];
    }

    /**
     * Wrap the legacy helpers — they expect `$data` and `$where` arrays.
     * The `where` array is mostly relevant when paired with patient_id;
     * date filtering is hard-coded inside the helpers.
     */
    private function loadRows(array $payload): array
    {
        $data = [
            'location_id' => $payload['location_id'],
            'patient_id' => $payload['patient_id'],
            'report_type' => $payload['report_type'],
            'search' => $payload['search'] ?? null,
            'start_date' => $payload['start_date'] ?? null,
            'end_date' => $payload['end_date'] ?? null,
        ];
        $where = [];
        if (! empty($payload['patient_id'])) {
            $where[] = ['patient_id', '=', $payload['patient_id']];
        }

        if ($payload['report_type'] === 'monthly') {
            return GeneralFunctions::LoadPatientFollowUpReportMonthly($data, $where);
        }

        return GeneralFunctions::PatientFollowUpReport($data, $where);
    }

    /**
     * Lookup table for centre id → name. Scoped to ACL centres so a user
     * doesn't see names for centres they don't have access to.
     *
     * @return array<int, string>
     */
    private function centreMap(): array
    {
        $accountId = Auth::user()->account_id;
        return collect(Locations::getActiveRecordsByCity('', ACL::getUserCentres(), $accountId))
            ->mapWithKeys(fn ($l) => [(int) $l->id => (string) $l->name])
            ->all();
    }
}
