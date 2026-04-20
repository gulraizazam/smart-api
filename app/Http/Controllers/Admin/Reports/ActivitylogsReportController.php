<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Helpers\ACL;
use App\Helpers\ActivityLogRenderer;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Appointments;
use App\Models\Locations;
use App\Models\Services;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivitylogsReportController extends Controller
{
    private const PER_PAGE = 100;

    public function index(): View
    {
        $services = Services::where(['parent_id' => 0])->where('slug', '!=', 'all')->pluck('id', 'name');
        $employees = User::getAllActiveEmployeeRecords(Auth::user()->account_id, ACL::getUserCentres())->pluck('name', 'id');
        $select_All = ['' => 'All'];
        $operators = ($select_All + $employees->toArray());
        $locations = Locations::getActiveSorted(ACL::getUserCentres());
        if (! Auth::user()->hasRole('FDM')) {
            $locations->prepend('All', '');
        }
        $locations_com = Locations::getActiveSorted(ACL::getUserCentres());

        $tags = ActivityLogRenderer::knownTags();
        $tagColors = (array) config('activity_log.tag_colors', []);

        return view('admin.reports.activity_logs.index', compact(
            'services', 'operators', 'locations', 'locations_com',
            'tags', 'tagColors'
        ));
    }

    public function fetchActivityReport(Request $request): JsonResponse
    {
        $filters = $this->buildFiltersFromRequest($request);
        $cursor = $request->input('cursor');
        if ($cursor === '') {
            $cursor = null;
        }

        $result = ActivityLogService::paginateActivityLogs(
            filters: $filters,
            cursor: $cursor,
            perPage: self::PER_PAGE,
        );

        $colorClasses = ['text-warning', 'text-success', 'text-primary', 'text-danger'];
        $offset = (int) $request->input('cursor_offset', 0);
        $data = [];

        foreach ($result['data'] as $index => $activity) {
            $data[] = [
                'colorClass' => $colorClasses[($offset + $index) % 4],
                'time' => $activity['time_short'],
                'message' => $activity['description'],
            ];
        }

        $html = view('admin.reports.activity_logs.activities', [
            'data' => $data,
            'isFirstPage' => $cursor === null,
            'total' => $result['total'],
        ])->render();

        return response()->json([
            'success' => true,
            'data' => [
                'html' => $html,
                'next_cursor' => $result['next_cursor'],
                'total' => $result['total'],
                'next_offset' => $offset + count($data),
            ],
        ]);
    }

    /**
     * Stream current filtered feed as CSV. Reuses the same buildQuery path
     * so the download matches what the user sees on screen. Uses a
     * StreamedResponse + lazy() to keep memory bounded even on 100k+ rows.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->buildFiltersFromRequest($request);

        $filename = 'activity-logs-'.date('Ymd-His').'.csv';

        return new StreamedResponse(function () use ($filters): void {
            $handle = fopen('php://output', 'w');
            // BOM for Excel UTF-8 compatibility
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Date/Time', 'Tag', 'Type', 'Actor', 'Description']);

            foreach (ActivityLogService::streamActivityLogs($filters) as $row) {
                fputcsv($handle, [
                    $row['created_at'] ?? '',
                    $row['tag'] ?? '',
                    $row['activity_type'] ?? '',
                    $row['actor'] ?? '',
                    $row['description_plain'] ?? '',
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFiltersFromRequest(Request $request): array
    {
        $validated = $request->validate([
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'service_id' => ['nullable', 'string'],
            'user_id' => ['nullable'],
            'location_id' => ['nullable'],
            'activity_type' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:200'],
            'patient_id' => ['nullable', 'integer'],
            'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'min:0'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:20'],
            'cursor' => ['nullable', 'string'],
            'cursor_offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $filters = [
            'start_date' => $validated['startDate'],
            'end_date' => $validated['endDate'],
        ];

        if (! empty($validated['service_id']) && $validated['service_id'] !== 'all') {
            $filters['service_id'] = $validated['service_id'];
        }
        if (! empty($validated['user_id'])) {
            $filters['user_id'] = $validated['user_id'];
        }
        if (! empty($validated['location_id'])) {
            $locationIds = array_values(array_filter(
                array_map('intval', (array) $validated['location_id']),
                fn (int $id): bool => $id > 0,
            ));
            if (!empty($locationIds)) {
                $filters['location_id'] = $locationIds;
            }
        }
        if (! empty($validated['activity_type']) && $validated['activity_type'] !== 'all') {
            $filters['activity_type'] = $validated['activity_type'];
        }
        if (! empty($validated['search'])) {
            $filters['search'] = $validated['search'];
        }
        if (! empty($validated['patient_id'])) {
            $filters['patient_id'] = $validated['patient_id'];
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

    public function InsertLogs(): bool
    {
        $startDate = '2023-11-01 00:00:00';
        $endDate = '2023-11-05 23:59:59';

        $appointments = Appointments::select('id', 'location_id', 'service_id', 'patient_id', 'scheduled_date', 'created_at', 'updated_at', 'first_scheduled_date', 'created_by')
            ->where('appointment_type_id', 1)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['location', 'service', 'patient'])
            ->get();

        $action = 'booked';
        $activityType = 'Consultancy';

        $activities = [];
        foreach ($appointments as $appointment) {

            if ($appointment->first_scheduled_date == $appointment->scheduled_date) {
                $action = 'booked';
            } else {
                $action = 'rescheduled';
            }
            $location = $appointment->location;
            $service = $appointment->service;
            $patient = $appointment->patient;

            $activity = [
                'created_by' => $appointment->created_by,
                'user_id' => $appointment->created_by,
                'action' => $action,
                'appointment_type' => $activityType,
                'appointment_id' => $appointment->id,
                'activity_type' => $activityType,
                'location' => $location?->name ?? '',
                'centre_id' => $location?->id,
                'service_id' => $service?->id,
                'service' => $service?->name,
                'patient_id' => $patient?->id,
                'patient' => $patient?->name,
                'schedule_date' => $appointment->scheduled_date,
                'created_at' => $appointment->created_at,
                'updated_at' => $appointment->updated_at,
            ];

            $activities[] = $activity;
        }

        Activity::insert($activities);

        return true;
    }
}
