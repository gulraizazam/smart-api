<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Exports\GenericTableExport;
use App\Helpers\ACL;
use App\Helpers\ActivityLogger;
use App\Helpers\ActivityLogRenderer;
use App\Http\Controllers\Controller;
use App\Models\Locations;
use App\Models\Services;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\Reports\Concerns\ParsesDateRange;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Activity Logs report — SPA bridge over `ActivityLogService`. Returns
 * structured rows (plain-text description + tag) instead of the legacy
 * HTML feed so the React table can render its own layout.
 *
 *   GET  /api/reports/activity-logs/filters → centres, services, employees, tags, activity types
 *   POST /api/reports/activity-logs         → cursor-paginated rows
 *   POST /api/reports/activity-logs/export  → PDF / XLSX
 *
 * Gated on `activity_logs_manage` (matches the legacy route middleware).
 */
class ActivityLogsApiController extends Controller
{
    use ParsesDateRange;

    private const PER_PAGE = 100;

    public function __invoke(Request $request): JsonResponse
    {
        if (! Gate::allows('activity_logs_report')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $filters = $this->buildFilters($request);
            $cursor = $request->input('cursor');
            if ($cursor === '') {
                $cursor = null;
            }

            // Meta-audit: log who viewed the audit log (first-page only).
            if ($cursor === null) {
                try {
                    ActivityLogger::logAuditLogAccessed($filters, 'view');
                } catch (\Throwable $e) {
                    Log::warning('activities.audit_log_viewed.write_failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $started = microtime(true);
            $result = ActivityLogService::paginateActivityLogs(
                filters: $filters,
                cursor: is_string($cursor) ? $cursor : null,
                perPage: self::PER_PAGE,
            );
            $elapsed = round((microtime(true) - $started) * 1000, 1);

            return $this->successResponse('Activity logs fetched successfully', [
                'rows' => array_map(fn ($a) => [
                    'type' => $a['type'] ?? null,
                    'description' => $this->stripDescription((string) ($a['description'] ?? '')),
                    'description_html' => $a['description'] ?? '',
                    'created_at' => $a['created_at'],
                    'time_formatted' => $a['time_formatted'] ?? null,
                    'time_short' => $a['time_short'] ?? null,
                ], $result['data']),
                'next_cursor' => $result['next_cursor'],
                'total' => $result['total'],
                'meta' => [
                    'start_date' => $filters['start_date'] ?? null,
                    'end_date' => $filters['end_date'] ?? null,
                    'elapsed_ms' => $elapsed,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'ActivityLogsReport');
        }
    }

    public function filters(): JsonResponse
    {
        if (! Gate::allows('activity_logs_report')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $accountId = Auth::user()->account_id;

            $locations = collect(Locations::getActiveRecordsByCity('', ACL::getUserCentres(), $accountId))
                ->map(fn ($l) => ['id' => (int) $l->id, 'name' => $l->name])
                ->values()
                ->all();

            // `parentsOnly()` covers both `parent_id IS NULL` and `parent_id = 0`
            // — a plain `where('parent_id', 0)` only catches one of those and
            // ends up showing a single service in the dropdown.
            $services = Services::parentsOnly()
                ->where('slug', '!=', 'all')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($s) => ['id' => (int) $s->id, 'name' => $s->name])
                ->all();

            // `getAllActiveEmployeeRecords` joins `user_has_locations` without
            // distinct, so a user assigned to N centres returns N rows. Dedupe
            // by id before sending the list to the SPA.
            $users = collect(User::getAllActiveEmployeeRecords($accountId, ACL::getUserCentres()))
                ->unique('id')
                ->map(fn ($u) => ['id' => (int) $u->id, 'name' => (string) $u->name])
                ->sortBy('name')
                ->values()
                ->all();

            $tags = ActivityLogRenderer::knownTags();
            $tagColors = (array) config('activity_log.tag_colors', []);

            return $this->successResponse('OK', [
                'locations' => $locations,
                'services' => $services,
                'users' => $users,
                'tags' => array_values(array_map(
                    fn ($t) => ['id' => $t, 'name' => $t, 'color' => $tagColors[$t] ?? null],
                    $tags,
                )),
            ]);
        } catch (\Throwable $e) {
            Log::error('ActivityLogsReport filters failed: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    public function export(Request $request): SymfonyResponse
    {
        if (! Gate::allows('activity_logs_report')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,excel',
        ]);

        $filters = $this->buildFilters($request);

        // Meta-audit — distinguish export from view in the feed.
        try {
            ActivityLogger::logAuditLogAccessed($filters, 'export');
        } catch (\Throwable $e) {
            Log::warning('activities.audit_log_viewed.export_write_failed', [
                'error' => $e->getMessage(),
            ]);
        }

        $headings = ['Date/Time', 'Tag', 'Type', 'Actor', 'Description'];
        $rows = [];
        foreach (ActivityLogService::streamActivityLogs($filters) as $r) {
            $rows[] = [
                (string) ($r['created_at'] ?? ''),
                (string) ($r['tag'] ?? ''),
                (string) ($r['activity_type'] ?? ''),
                (string) ($r['actor'] ?? ''),
                (string) ($r['description_plain'] ?? ''),
            ];
        }

        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $stamp = $startDate && $endDate ? "-{$startDate}-to-{$endDate}" : '-'.date('Ymd-His');
        $title = 'Activity Logs Report';
        $subtitle = $startDate && $endDate ? "Period: {$startDate} → {$endDate}" : 'All dates';

        if ($validated['format'] === 'excel') {
            return Excel::download(new GenericTableExport($headings, $rows), "activity-logs{$stamp}.xlsx");
        }

        $pdf = Pdf::loadView('admin.reports.exports.generic-table', [
            'title' => $title,
            'subtitle' => $subtitle,
            'headings' => $headings,
            'rows' => $rows,
        ])->setPaper('A4', 'landscape');

        return $pdf->download("activity-logs{$stamp}.pdf");
    }

    /**
     * Normalise SPA payload into the array shape `ActivityLogService` expects.
     * Accepts either an explicit start_date/end_date pair or a `date_range`
     * "Y-m-d - Y-m-d" string the rest of the SPA reports use.
     *
     * @return array<string, mixed>
     */
    private function buildFilters(Request $request): array
    {
        $validated = $request->validate([
            'date_range' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'service_id' => ['nullable', 'string'],
            'user_id' => ['nullable'],
            'location_id' => ['nullable'],
            'location_id.*' => ['integer'],
            'activity_type' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:200'],
            'patient_id' => ['nullable', 'integer'],
            'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'min:0'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:20'],
            'cursor' => ['nullable', 'string'],
        ]);

        [$startDate, $endDate] = self::parseDateRange($validated['date_range'] ?? null);
        $startDate = $startDate ?? ($validated['start_date'] ?? null);
        $endDate = $endDate ?? ($validated['end_date'] ?? null);

        // Default to last 30 days when neither side is provided so the
        // unfiltered first paint stays bounded (mirrors other reports).
        if (! $startDate || ! $endDate) {
            $endDate = $endDate ?? date('Y-m-d', strtotime('-1 day'));
            $startDate = $startDate ?? date('Y-m-d', strtotime('-31 days'));
        }

        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        if (! empty($validated['service_id']) && $validated['service_id'] !== 'all') {
            $filters['service_id'] = $validated['service_id'];
        }
        if (! empty($validated['user_id'])) {
            $filters['user_id'] = $validated['user_id'];
        }

        $locationIds = ! empty($validated['location_id'])
            ? array_values(array_filter(
                array_map('intval', (array) $validated['location_id']),
                fn (int $id): bool => $id > 0,
            ))
            : [];
        if (empty($locationIds)) {
            $locationIds = array_map('intval', ACL::getUserCentres());
        }
        if (! empty($locationIds)) {
            $filters['location_id'] = $locationIds;
        }

        if (! empty($validated['activity_type']) && $validated['activity_type'] !== 'all') {
            $filters['activity_type'] = $validated['activity_type'];
        }
        if (! empty($validated['search'])) {
            $filters['search'] = $validated['search'];
        }
        if (! empty($validated['patient_id'])) {
            $filters['patient_id'] = (int) $validated['patient_id'];
        }
        if (isset($validated['amount_min']) && $validated['amount_min'] !== '' && $validated['amount_min'] !== null) {
            $filters['amount_min'] = $validated['amount_min'];
        }
        if (isset($validated['amount_max']) && $validated['amount_max'] !== '' && $validated['amount_max'] !== null) {
            $filters['amount_max'] = $validated['amount_max'];
        }
        if (! empty($validated['tags'])) {
            $known = ActivityLogRenderer::knownTags();
            $filters['tags'] = array_values(array_intersect($validated['tags'], $known));
        }

        return $filters;
    }

    /**
     * Strip HTML from the rendered description but keep tag spans readable
     * by collapsing whitespace. The SPA shows plain text — render layer
     * (icon + colour) is recreated client-side based on `type` / `tag`.
     */
    private function stripDescription(string $html): string
    {
        $plain = trim(strip_tags($html));
        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;

        return trim($plain);
    }
}
