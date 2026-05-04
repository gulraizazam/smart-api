<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Exports\GenericTableExport;
use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Models\DoctorHasLocations;
use App\Models\Locations;
use App\Models\Services;
use App\Models\User;
use App\Services\Feedback\FeedbackService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Doctor Ratings Report (the detail view) — SPA bridge over
 * `FeedbackService::getReportData`. Different from the existing
 * `/reports/feedbacks` endpoint: that one drives a hero/dashboard view,
 * this one mirrors the legacy `feedback_report` flow which groups by
 * doctor or doctor+service depending on the filter combo.
 *
 *   GET  /api/reports/doctor-ratings-detail/filters → centres, doctors, services
 *   POST /api/reports/doctor-ratings-detail         → grouped rating rows
 *   POST /api/reports/doctor-ratings-detail/export  → PDF / XLSX
 *
 * Permission: `feedbacks_manage`.
 */
class DoctorRatingsDetailApiController extends Controller
{
    private const DOCTOR_USER_TYPE_ID = 5;

    public function __construct(private readonly FeedbackService $feedbackService) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! Gate::allows('feedbacks_manage')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $validated = $this->validatePayload($request);
            $locationIds = $this->resolveCentres($validated['location_id'] ?? null);

            $started = microtime(true);
            $result = $this->feedbackService->getReportData(
                locationIds: $locationIds,
                doctorId: $validated['doctor_id'],
                serviceId: $validated['service_id'],
                dateRange: $validated['date_range'] ?? '',
            );
            $elapsed = round((microtime(true) - $started) * 1000, 1);

            $rows = $this->shapeRows($result);
            $stats = $this->computeStats($rows);

            return $this->successResponse('Doctor ratings report generated successfully', [
                'rows' => $rows,
                'stats' => $stats,
                'meta' => [
                    'count' => count($rows),
                    'elapsed_ms' => $elapsed,
                    'grouping' => $this->detectGrouping($result),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'DoctorRatingsDetailReport');
        }
    }

    public function filters(): JsonResponse
    {
        if (! Gate::allows('feedbacks_manage')) {
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
                ->map(fn ($u) => ['id' => (int) $u->id, 'name' => (string) $u->name])
                ->all();

            $services = Services::parentsOnly()
                ->where('active', 1)
                ->where('slug', '!=', 'all')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($s) => ['id' => (int) $s->id, 'name' => (string) $s->name])
                ->all();

            return $this->successResponse('OK', [
                'locations' => $locations,
                'doctors' => $doctors,
                'services' => $services,
            ]);
        } catch (\Throwable $e) {
            Log::error('DoctorRatingsDetail filters failed: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    /**
     * Doctors narrowed to those allocated-and-active for the given centres.
     * Mirrors `DoctorRatingsApiController::doctorsForCentres` so the SPA
     * "centre change → reload doctors" pattern stays portable.
     */
    public function doctorsForCentres(Request $request): JsonResponse
    {
        if (! Gate::allows('feedbacks_manage')) {
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
                    ->map(fn ($u) => ['id' => (int) $u->id, 'name' => (string) $u->name])
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
                    ->map(fn ($u) => ['id' => (int) $u->id, 'name' => (string) $u->name])
                    ->all();

            return $this->successResponse('OK', ['doctors' => $doctors]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('DoctorRatingsDetail doctorsForCentres failed: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    public function export(Request $request): SymfonyResponse
    {
        if (! Gate::allows('feedbacks_manage')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $this->validatePayload($request, true);
        $locationIds = $this->resolveCentres($validated['location_id'] ?? null);

        $result = $this->feedbackService->getReportData(
            locationIds: $locationIds,
            doctorId: $validated['doctor_id'],
            serviceId: $validated['service_id'],
            dateRange: $validated['date_range'] ?? '',
        );

        $rows = $this->shapeRows($result);
        $grouping = $this->detectGrouping($result);

        $headings = $grouping === 'doctor_service'
            ? ['Doctor', 'Service', 'Average rating (/10)', 'Total feedbacks']
            : ($grouping === 'service'
                ? ['Service', 'Average rating (/10)', 'Total feedbacks']
                : ['Doctor', 'Average rating (/10)', 'Total feedbacks']);

        $flat = array_map(function (array $r) use ($grouping) {
            $avg = number_format((float) ($r['avg_rating'] ?? 0), 2, '.', '');
            $tot = (int) ($r['total_feedbacks'] ?? 0);
            return $grouping === 'doctor_service'
                ? [(string) ($r['doctor'] ?? ''), (string) ($r['service'] ?? ''), $avg, $tot]
                : ($grouping === 'service'
                    ? [(string) ($r['service'] ?? ''), $avg, $tot]
                    : [(string) ($r['doctor'] ?? ''), $avg, $tot]);
        }, $rows);

        $title = 'Doctor Ratings Report';
        $subtitle = $validated['date_range'] ? "Period: {$validated['date_range']}" : '';
        $stamp = '-'.date('Ymd-His');

        if ($validated['format'] === 'excel') {
            return Excel::download(new GenericTableExport($headings, $flat), "doctor-ratings{$stamp}.xlsx");
        }

        $pdf = Pdf::loadView('admin.reports.exports.generic-table', [
            'title' => $title,
            'subtitle' => $subtitle,
            'headings' => $headings,
            'rows' => $flat,
        ])->setPaper('A4', 'landscape');

        return $pdf->download("doctor-ratings{$stamp}.pdf");
    }

    /**
     * @return array{location_id: ?array, doctor_id: ?int, service_id: ?int, date_range: ?string, format?: string}
     */
    private function validatePayload(Request $request, bool $forExport = false): array
    {
        $rules = [
            'location_id' => 'nullable|array',
            'location_id.*' => 'integer|exists:locations,id',
            'doctor_id' => 'nullable|integer|exists:users,id',
            'service_id' => 'nullable|integer|exists:services,id',
            'date_range' => 'required|string',
        ];
        if ($forExport) {
            $rules['format'] = 'required|in:pdf,excel';
        }
        $validated = $request->validate($rules);

        return [
            'location_id' => $validated['location_id'] ?? null,
            'doctor_id' => isset($validated['doctor_id']) ? (int) $validated['doctor_id'] : null,
            'service_id' => isset($validated['service_id']) ? (int) $validated['service_id'] : null,
            'date_range' => $validated['date_range'] ?? null,
            'format' => $validated['format'] ?? null,
        ];
    }

    /**
     * @return int[]|null
     */
    private function resolveCentres(?array $raw): ?array
    {
        $ids = !empty($raw)
            ? array_values(array_filter(array_map('intval', (array) $raw), fn (int $id): bool => $id > 0))
            : [];
        if (empty($ids)) {
            $ids = array_map('intval', (array) ACL::getUserCentres());
        }

        return empty($ids) ? null : $ids;
    }

    /**
     * Normalise the polymorphic Collection|array response from
     * `getReportData` into a flat `[doctor, service, avg_rating, total_feedbacks]`
     * shape so the SPA doesn't have to branch on row geometry.
     *
     * @param  mixed  $result
     */
    private function shapeRows(mixed $result): array
    {
        $rows = [];
        foreach ($result as $r) {
            $rows[] = [
                'doctor_id' => isset($r->doctor) && $r->doctor ? (int) ($r->doctor->id ?? 0) : null,
                'doctor' => isset($r->doctor) && $r->doctor ? (string) ($r->doctor->name ?? '') : null,
                'service_id' => isset($r->service) && $r->service ? (int) ($r->service->id ?? 0) : null,
                'service' => isset($r->service) && $r->service ? (string) ($r->service->name ?? '') : null,
                'avg_rating' => (float) ($r->avg_rating ?? 0),
                'total_feedbacks' => (int) ($r->total_feedbacks ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Detect the grouping the service used for this filter combination.
     */
    private function detectGrouping(mixed $result): string
    {
        foreach ($result as $r) {
            $hasDoctor = isset($r->doctor) && $r->doctor;
            $hasService = isset($r->service) && $r->service;
            return match (true) {
                $hasDoctor && $hasService => 'doctor_service',
                $hasService && ! $hasDoctor => 'service',
                default => 'doctor',
            };
        }
        return 'doctor';
    }

    private function computeStats(array $rows): array
    {
        $totalFeedbacks = 0;
        $weightedSum = 0;
        $bestRow = null;
        $worstRow = null;
        foreach ($rows as $r) {
            $totalFeedbacks += $r['total_feedbacks'];
            $weightedSum += $r['avg_rating'] * $r['total_feedbacks'];
            if ($r['total_feedbacks'] === 0) {
                continue;
            }
            if ($bestRow === null || $r['avg_rating'] > $bestRow['avg_rating']) {
                $bestRow = $r;
            }
            if ($worstRow === null || $r['avg_rating'] < $worstRow['avg_rating']) {
                $worstRow = $r;
            }
        }

        return [
            'overall_avg_rating' => $totalFeedbacks > 0 ? round($weightedSum / $totalFeedbacks, 2) : 0.0,
            'total_feedbacks' => $totalFeedbacks,
            'best_rated' => $bestRow ? ['name' => $bestRow['doctor'] ?? $bestRow['service'], 'avg_rating' => $bestRow['avg_rating']] : null,
            'worst_rated' => $worstRow ? ['name' => $worstRow['doctor'] ?? $worstRow['service'], 'avg_rating' => $worstRow['avg_rating']] : null,
        ];
    }
}
