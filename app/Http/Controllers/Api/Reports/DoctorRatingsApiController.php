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
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Doctor Ratings (Feedback) report — JSON API for the SPA.
 *
 * Mirrors the legacy Blade flow (FeedbacksReportController::feedbackReport
 * + loadFeedbackReport), but returns structured JSON so the new admin SPA
 * can render its own filter UI and table without scraping HTML.
 */
class DoctorRatingsApiController extends Controller
{
    private const DOCTOR_USER_TYPE_ID = 5;
    private const ROOT_PARENT_ID = 0;

    public function __construct(
        private readonly FeedbackService $feedbackService,
    ) {}

    /**
     * Per-doctor category breakdown — parent service rating + child treatment
     * ratings. Mirrors the legacy `/admin/dashboard/feedback/view/{id}` page
     * conceptually, but uses the `parentsOnly` scope so it picks up services
     * with `parent_id IS NULL` (the legacy `parent_id = 0` filter misses 16 of
     * 17 parent services in current data).
     *
     * Accepts an optional `date_from`/`date_to` window so the SPA can carry the
     * Doctor Ratings report's filter into the detail page. When both bounds are
     * present every aggregate is scoped to feedback recorded in
     * [from 00:00, to 23:59] (inclusive, today included — matching the report).
     * Absent → all-time, preserving the original behaviour for direct visits.
     */
    public function byService(Request $request, int $doctorId): JsonResponse
    {
        try {
            if (! Gate::allows('feedbacks_manage')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $validated = $request->validate([
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
            ]);

            $doctor = User::where('id', $doctorId)
                ->where('user_type_id', self::DOCTOR_USER_TYPE_ID)
                ->first(['id', 'name']);

            if ($doctor === null) {
                return $this->errorResponse('Doctor not found.', 404);
            }

            $start = isset($validated['date_from']) ? $validated['date_from'].' 00:00:00' : null;
            $end = isset($validated['date_to']) ? $validated['date_to'].' 23:59:59' : null;
            $scopeDate = static function ($query) use ($start, $end) {
                if ($start !== null && $end !== null) {
                    $query->whereBetween('created_at', [$start, $end]);
                }

                return $query;
            };

            $parents = Services::parentsOnly()
                ->orderBy('name')
                ->get(['id', 'name', 'color']);

            $childrenByParent = Services::whereIn('parent_id', $parents->pluck('id'))
                ->get(['id', 'name', 'color', 'parent_id'])
                ->groupBy('parent_id');

            $parentRatings = $scopeDate(
                \App\Models\Feedback::where('doctor_id', $doctorId)
                    ->whereIn('service_id', $parents->pluck('id'))
            )
                ->select('service_id', DB::raw('AVG(rating) as avg_rating'))
                ->groupBy('service_id')
                ->pluck('avg_rating', 'service_id');

            $childIds = $childrenByParent->flatten()->pluck('id');
            $childRatings = $childIds->isEmpty()
                ? collect()
                : $scopeDate(
                    \App\Models\Feedback::where('doctor_id', $doctorId)
                        ->whereIn('treatment_id', $childIds)
                )
                    ->select('treatment_id', DB::raw('AVG(rating) as avg_rating'))
                    ->groupBy('treatment_id')
                    ->pluck('avg_rating', 'treatment_id');

            $services = [];
            foreach ($parents as $service) {
                $parentRating = $parentRatings[$service->id] ?? null;
                $children = $childrenByParent[$service->id] ?? collect();

                $childData = [];
                foreach ($children as $child) {
                    $avgRating = $childRatings[$child->id] ?? null;
                    if ($avgRating !== null) {
                        $childData[] = [
                            'id' => (int) $child->id,
                            'name' => (string) $child->name,
                            'color' => $child->color,
                            'avg_rating' => round((float) $avgRating, 2),
                        ];
                    }
                }

                if ($parentRating !== null || ! empty($childData)) {
                    $services[] = [
                        'id' => (int) $service->id,
                        'name' => (string) $service->name,
                        'color' => $service->color,
                        'avg_rating' => $parentRating !== null ? round((float) $parentRating, 2) : 0.0,
                        'treatments' => $childData,
                    ];
                }
            }

            $aggregate = $scopeDate(\App\Models\Feedback::where('doctor_id', $doctorId))
                ->selectRaw('ROUND(AVG(CAST(rating AS DECIMAL(4,2))), 2) as avg_rating, COUNT(*) as total_feedbacks')
                ->first();

            return $this->successResponse('OK', [
                'doctor_id' => (int) $doctor->id,
                'doctor_name' => (string) $doctor->name,
                'overall_avg_rating' => $aggregate && $aggregate->avg_rating !== null ? (float) $aggregate->avg_rating : 0.0,
                'total_feedbacks' => (int) ($aggregate->total_feedbacks ?? 0),
                'services' => $services,
            ]);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    /**
     * Filter dropdown payload — doctors, locations, services. Plus an
     * "all-time" rating summary by doctor (matches the initial unfiltered
     * table the legacy view shows on first load).
     */
    public function filters(): JsonResponse
    {
        try {
            if (! Gate::allows('feedbacks_manage')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $accountId = Auth::user()->account_id;

            $doctors = User::getAllRecords($accountId)
                ->where('user_type_id', self::DOCTOR_USER_TYPE_ID)
                ->where('active', 1)
                ->values()
                ->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])
                ->all();

            $locations = collect(Locations::getActiveRecordsByCity('', ACL::getUserCentres(), $accountId))
                ->map(fn ($l) => ['id' => (int) $l->id, 'name' => $l->name])
                ->values()
                ->all();

            // Top-level (parent) services. The Services model stores parent
            // services as either `parent_id IS NULL` or `parent_id = 0` —
            // older rows used 0, newer rows use NULL — so the legacy
            // `parent_id = 0` filter dropped half the catalogue. Use the
            // model's `parentsOnly` scope which covers both shapes.
            $services = Services::parentsOnly()
                ->isActive()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($s) => ['id' => (int) $s->id, 'name' => $s->name])
                ->all();

            $summary = \App\Models\Feedback::select('doctor_id')
                ->selectRaw('ROUND(AVG(CAST(rating AS DECIMAL(4,2))), 2) as avg_rating, COUNT(*) as total_feedbacks')
                ->groupBy('doctor_id')
                ->with('doctor:id,name')
                ->get()
                ->map(fn ($row) => [
                    'doctor_id' => (int) $row->doctor_id,
                    'doctor' => $row->doctor ? ['id' => (int) $row->doctor->id, 'name' => $row->doctor->name] : null,
                    'avg_rating' => $row->avg_rating !== null ? (float) $row->avg_rating : 0.0,
                    'total_feedbacks' => (int) $row->total_feedbacks,
                ])
                ->values()
                ->all();

            return $this->successResponse('OK', [
                'doctors' => $doctors,
                'locations' => $locations,
                'services' => $services,
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    /**
     * Doctor list narrowed to allocated-and-active for the given centres.
     * When `centre_ids` is empty/missing, falls back to the same global
     * doctor list returned by `filters()` so the dropdown can be populated
     * upfront before any centre is chosen.
     *
     * Mirrors `FeedbackService::getAllocatedActiveDoctorIds()` so the
     * dropdown choices stay in sync with the report's underlying scope.
     */
    public function doctorsForCentres(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('feedbacks_manage')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $request->validate([
                'centre_ids' => 'nullable|array',
                'centre_ids.*' => 'integer|exists:locations,id',
            ]);

            $accountId = Auth::user()->account_id;
            $centreIds = (array) $request->input('centre_ids', []);
            $centreIds = array_values(array_filter(array_map('intval', $centreIds), fn ($id) => $id > 0));

            if (empty($centreIds)) {
                // No centre filter → fall back to all active doctors for the
                // account (same dataset as `filters()`).
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
            Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    /**
     * Filtered report rows. Accepts:
     *   centre_ids (int[]|null) — empty/null falls back to ACL centres
     *   doctor_id (int|null)
     *   service_id (int|null)
     *   date_from / date_to (Y-m-d) — combined into the legacy "Y-m-d - Y-m-d"
     *
     * Returns:
     *   { rows: [...], group_by: 'doctor' | 'service' | 'doctor+service' }
     */
    public function data(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('feedbacks_manage')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $validated = $request->validate([
                'centre_ids' => 'nullable|array',
                'centre_ids.*' => 'integer|exists:locations,id',
                'doctor_id' => 'nullable|integer|exists:users,id',
                'service_id' => 'nullable|integer|exists:services,id',
                'date_from' => 'required|date',
                'date_to' => 'required|date|after_or_equal:date_from',
            ]);

            $centreIds = $validated['centre_ids'] ?? [];
            if (empty($centreIds)) {
                $centreIds = array_map('intval', ACL::getUserCentres());
            }

            $doctorId = $validated['doctor_id'] ?? null;
            $serviceId = $validated['service_id'] ?? null;
            $dateRange = $validated['date_from'] . ' - ' . $validated['date_to'];

            $result = $this->feedbackService->getReportData(
                locationIds: $centreIds,
                doctorId: $doctorId !== null ? (int) $doctorId : null,
                serviceId: $serviceId !== null ? (int) $serviceId : null,
                dateRange: $dateRange,
            );

            $rows = collect($result)->map(function ($row) {
                $doctor = $row->doctor ?? null;
                $service = $row->service ?? null;
                return [
                    'doctor_id' => isset($row->doctor_id) ? (int) $row->doctor_id : null,
                    'doctor' => $doctor ? ['id' => (int) $doctor->id, 'name' => $doctor->name] : null,
                    'service_id' => isset($row->service_id) ? (int) $row->service_id : null,
                    'service' => $service ? ['id' => (int) $service->id, 'name' => $service->name] : null,
                    'avg_rating' => $row->avg_rating !== null ? (float) $row->avg_rating : 0.0,
                    'total_feedbacks' => (int) ($row->total_feedbacks ?? 0),
                ];
            })->values()->all();

            $groupBy = $this->resolveGroupKey($doctorId, $serviceId, ! empty($validated['centre_ids'] ?? null) || ! empty($centreIds));

            return $this->successResponse('OK', [
                'rows' => $rows,
                'group_by' => $groupBy,
                'filters' => [
                    'centre_ids' => array_values(array_map('intval', $centreIds)),
                    'doctor_id' => $doctorId !== null ? (int) $doctorId : null,
                    'service_id' => $serviceId !== null ? (int) $serviceId : null,
                    'date_from' => $validated['date_from'],
                    'date_to' => $validated['date_to'],
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    /**
     * Export the same filtered result as `data()` but as PDF (DomPDF) or
     * Excel (Maatwebsite). Reuses the data-shaping logic so the file
     * always matches what the SPA's table is showing.
     */
    public function export(Request $request): SymfonyResponse
    {
        if (! Gate::allows('feedbacks_manage')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,excel',
            'centre_ids' => 'nullable|array',
            'centre_ids.*' => 'integer|exists:locations,id',
            'doctor_id' => 'nullable|integer|exists:users,id',
            'service_id' => 'nullable|integer|exists:services,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $centreIds = $validated['centre_ids'] ?? [];
        if (empty($centreIds)) {
            $centreIds = array_map('intval', ACL::getUserCentres());
        }
        $doctorId = $validated['doctor_id'] ?? null;
        $serviceId = $validated['service_id'] ?? null;
        $dateRange = $validated['date_from'].' - '.$validated['date_to'];

        $result = $this->feedbackService->getReportData(
            locationIds: $centreIds,
            doctorId: $doctorId !== null ? (int) $doctorId : null,
            serviceId: $serviceId !== null ? (int) $serviceId : null,
            dateRange: $dateRange,
        );

        $groupBy = $this->resolveGroupKey($doctorId, $serviceId, ! empty($centreIds));

        // Build headings + flat rows in heading order. Columns vary with
        // grouping — same logic as the SPA's column picker.
        $headings = match ($groupBy) {
            'service' => ['Service', 'Average rating', 'Total feedbacks'],
            'doctor+service' => ['Doctor', 'Service', 'Average rating', 'Total feedbacks'],
            default => ['Doctor', 'Average rating', 'Total feedbacks'],
        };

        $rows = collect($result)->map(function ($row) use ($groupBy) {
            $rating = number_format((float) ($row->avg_rating ?? 0), 2).' / 10';
            $count = (int) ($row->total_feedbacks ?? 0);
            $doctorName = $row->doctor->name ?? '—';
            $serviceName = $row->service->name ?? '—';
            return match ($groupBy) {
                'service' => [$serviceName, $rating, $count],
                'doctor+service' => [$doctorName, $serviceName, $rating, $count],
                default => [$doctorName, $rating, $count],
            };
        })->values()->all();

        $title = 'Doctor Ratings';
        $subtitle = "Period: {$validated['date_from']} → {$validated['date_to']}";

        if ($validated['format'] === 'excel') {
            $filename = 'doctor-ratings-'.$validated['date_from'].'-to-'.$validated['date_to'].'.xlsx';
            return Excel::download(new GenericTableExport($headings, $rows), $filename);
        }

        $pdf = Pdf::loadView('admin.reports.exports.generic-table', [
            'title' => $title,
            'subtitle' => $subtitle,
            'headings' => $headings,
            'rows' => $rows,
        ])->setPaper('A4', 'landscape');

        return $pdf->download('doctor-ratings-'.$validated['date_from'].'-to-'.$validated['date_to'].'.pdf');
    }

    /**
     * Mirror the matching grid in FeedbackService::getReportData so the
     * client knows which columns are meaningful.
     */
    private function resolveGroupKey(?int $doctorId, ?int $serviceId, bool $hasLocation): string
    {
        if ($hasLocation && $doctorId !== null && $serviceId !== null) {
            return 'doctor+service';
        }
        if ($serviceId !== null && $doctorId !== null && ! $hasLocation) {
            return 'doctor+service';
        }
        if ($hasLocation && $doctorId !== null && $serviceId === null) {
            return 'service';
        }
        if ($hasLocation && $serviceId !== null && $doctorId === null) {
            return 'doctor';
        }
        if ($doctorId !== null && $serviceId === null && ! $hasLocation) {
            return 'service';
        }
        if ($serviceId !== null && $doctorId === null && ! $hasLocation) {
            return 'doctor';
        }
        return 'doctor';
    }
}
