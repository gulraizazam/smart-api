<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\HR;

use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LeaveApplicationDatatableController extends Controller
{
    protected const FILTER_KEY = 'hr_leave_applications';

    public function datatable(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('hr_leave_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = Auth::user()->account_id;
            $userId = Auth::id();
            $filters = getFilters($request->all());
            $applyFilter = checkFilters($filters, self::FILTER_KEY);

            $query = LeaveApplication::query()
                ->select([
                    'leave_applications.*',
                    'users.name as employee_name',
                    'leave_types.name as leave_type_name',
                    'designations.name as designation_name',
                ])
                ->join('users', 'leave_applications.user_id', '=', 'users.id')
                ->join('leave_types', 'leave_applications.leave_type_id', '=', 'leave_types.id')
                ->leftJoin('employee_details', 'users.id', '=', 'employee_details.user_id')
                ->leftJoin('designations', 'employee_details.designation_id', '=', 'designations.id')
                ->where('leave_applications.account_id', $accountId)
                ->whereNull('leave_applications.deleted_at');

            // Filters
            if (hasFilter($filters, 'status')) {
                $query->where('leave_applications.status', $filters['status']);
                Filters::put($userId, self::FILTER_KEY, 'status', $filters['status']);
            } elseif ($applyFilter) {
                Filters::forget($userId, self::FILTER_KEY, 'status');
            } else {
                $stored = Filters::get($userId, self::FILTER_KEY, 'status');
                if ($stored) {
                    $query->where('leave_applications.status', $stored);
                }
            }

            if (hasFilter($filters, 'leave_type_id')) {
                $query->where('leave_applications.leave_type_id', $filters['leave_type_id']);
                Filters::put($userId, self::FILTER_KEY, 'leave_type_id', $filters['leave_type_id']);
            } elseif ($applyFilter) {
                Filters::forget($userId, self::FILTER_KEY, 'leave_type_id');
            }

            if (hasFilter($filters, 'employee_name')) {
                $query->where('users.name', 'like', '%' . $filters['employee_name'] . '%');
                Filters::put($userId, self::FILTER_KEY, 'employee_name', $filters['employee_name']);
            } elseif ($applyFilter) {
                Filters::forget($userId, self::FILTER_KEY, 'employee_name');
            }

            if (hasFilter($filters, 'date_from') && hasFilter($filters, 'date_to')) {
                $query->whereBetween('leave_applications.start_date', [$filters['date_from'], $filters['date_to']]);
                Filters::put($userId, self::FILTER_KEY, 'date_from', $filters['date_from']);
                Filters::put($userId, self::FILTER_KEY, 'date_to', $filters['date_to']);
            } elseif ($applyFilter) {
                Filters::forget($userId, self::FILTER_KEY, 'date_from');
                Filters::forget($userId, self::FILTER_KEY, 'date_to');
            }

            $total = (clone $query)->count();

            [$orderBy, $order] = getSortBy($request);
            [$displayLength, $displayStart, $pages, $page] = getPaginationElement($request, $total);

            $sortColumn = match ($orderBy) {
                'name', 'employee_name' => 'users.name',
                'leave_type' => 'leave_types.name',
                'designation' => 'designations.name',
                'status' => 'leave_applications.status',
                'start_date' => 'leave_applications.start_date',
                'end_date' => 'leave_applications.end_date',
                'total_days' => 'leave_applications.total_days',
                default => 'leave_applications.created_at',
            };

            $records = $query
                ->limit($displayLength)
                ->offset($displayStart)
                ->orderBy($sortColumn, $order ?: 'desc')
                ->get();

            $data = $records->map(function ($app) {
                $hoursDisplay = null;
                $durationValue = $app->duration_type?->value;

                if ($durationValue === 'custom' && $app->custom_hours !== null) {
                    $hoursDisplay = rtrim(rtrim((string) $app->custom_hours, '0'), '.') . ' hrs';
                } elseif (in_array($durationValue, ['half', 'short'], true) && $app->shift_hours_snapshot) {
                    $hoursDisplay = rtrim(rtrim(number_format((float) $app->total_days * (float) $app->shift_hours_snapshot, 2), '0'), '.') . ' hrs';
                }

                return [
                    'id' => $app->id,
                    'employee_name' => $app->employee_name,
                    'leave_type' => $app->leave_type_name,
                    'start_date' => \Carbon\Carbon::parse($app->start_date)->format('d M Y'),
                    'end_date' => \Carbon\Carbon::parse($app->end_date)->format('d M Y'),
                    'total_days' => (float) $app->total_days,
                    'hours_display' => $hoursDisplay,
                    'duration_type' => $app->duration_type?->label(),
                    'status' => $app->status,
                    'applied_on' => $app->created_at->format('d M Y'),
                    'designation' => $app->designation_name ?? 'â€”',
                ];
            });

            return response()->json([
                'data' => $data,
                'permissions' => [
                    'approve' => Gate::allows('hr_leave_approve'),
                ],
                'active_filters' => Filters::all($userId, self::FILTER_KEY),
                'filter_values' => [
                    'statuses' => ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'],
                ],
                'meta' => [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => $displayLength,
                    'total' => $total,
                    'sort' => $order,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeaveApplicationDatatableController');
        }
    }
}
