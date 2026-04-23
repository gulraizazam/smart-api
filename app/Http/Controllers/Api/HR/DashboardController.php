<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\HR;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\HR\LeaveApplicationResource;
use App\Models\LeaveApplication;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    /**
     * GET /api/hr/dashboard
     *
     * Unified payload powering the HRM landing screen. Mirrors the data shown
     * in `resources/views/admin/hr/dashboard/index.blade.php` plus two
     * mobile-friendly extras (upcoming leaves, recent hires).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('hr_dashboard_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'pending_limit' => ['nullable', 'integer', 'min:1', 'max:50'],
                'upcoming_days' => ['nullable', 'integer', 'min:1', 'max:90'],
                'recent_hire_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            ]);

            $accountId = (int) Auth::user()->account_id;
            $now = now();
            $pendingLimit = (int) ($request->integer('pending_limit') ?: 10);
            $upcomingDays = (int) ($request->integer('upcoming_days') ?: 7);
            $recentHireDays = (int) ($request->integer('recent_hire_days') ?: 30);

            return $this->successResponse(
                'HR dashboard retrieved successfully.',
                [
                    'kpis' => $this->computeKpis($accountId, $now),
                    'pending_leaves' => LeaveApplicationResource::collection(
                        $this->pendingLeavesQuery($accountId)->limit($pendingLimit)->get(),
                    ),
                    'upcoming_leaves' => LeaveApplicationResource::collection(
                        $this->upcomingLeavesQuery($accountId, $now, $upcomingDays)->limit(20)->get(),
                    ),
                    'recent_hires' => $this->recentHires($accountId, $now, $recentHireDays),
                    'generated_at' => $now->toIso8601String(),
                ],
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\DashboardController@index');
        }
    }

    /**
     * GET /api/hr/dashboard/kpis
     *
     * Lightweight counts only — intended for mobile pull-to-refresh where the
     * full dashboard payload would be wasteful.
     */
    public function kpis(): JsonResponse
    {
        try {
            if (! Gate::allows('hr_dashboard_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = (int) Auth::user()->account_id;

            return $this->successResponse(
                'HR KPIs retrieved successfully.',
                $this->computeKpis($accountId, now()),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\DashboardController@kpis');
        }
    }

    /**
     * GET /api/hr/dashboard/pending-leaves
     */
    public function pendingLeaves(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('hr_dashboard_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $accountId = (int) Auth::user()->account_id;
            $perPage = (int) ($request->integer('per_page') ?: 10);

            $paginated = $this->pendingLeavesQuery($accountId)->paginate($perPage);

            return $this->successResponse(
                'Pending leave applications retrieved successfully.',
                [
                    'items' => LeaveApplicationResource::collection($paginated->items()),
                    'meta' => [
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                    ],
                ],
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\DashboardController@pendingLeaves');
        }
    }

    /**
     * GET /api/hr/dashboard/upcoming-leaves
     *
     * Approved leaves starting within the next `days` window. Useful for
     * roster planning on the admin home screen.
     */
    public function upcomingLeaves(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('hr_dashboard_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'days' => ['nullable', 'integer', 'min:1', 'max:90'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $accountId = (int) Auth::user()->account_id;
            $days = (int) ($request->integer('days') ?: 7);
            $perPage = (int) ($request->integer('per_page') ?: 20);

            $paginated = $this->upcomingLeavesQuery($accountId, now(), $days)->paginate($perPage);

            return $this->successResponse(
                'Upcoming leaves retrieved successfully.',
                [
                    'window_days' => $days,
                    'items' => LeaveApplicationResource::collection($paginated->items()),
                    'meta' => [
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                    ],
                ],
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\DashboardController@upcomingLeaves');
        }
    }

    /**
     * GET /api/hr/dashboard/recent-hires
     */
    public function recentHiresEndpoint(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('hr_dashboard_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            ]);

            $accountId = (int) Auth::user()->account_id;
            $days = (int) ($request->integer('days') ?: 30);

            return $this->successResponse(
                'Recent hires retrieved successfully.',
                [
                    'window_days' => $days,
                    'items' => $this->recentHires($accountId, now(), $days),
                ],
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\DashboardController@recentHiresEndpoint');
        }
    }

    // ── Internal helpers ──

    /**
     * @return array<string, int>
     */
    private function computeKpis(int $accountId, Carbon $now): array
    {
        $totalEmployees = User::where('account_id', $accountId)
            ->whereIn('user_type_id', [2, 5])
            ->where('active', 1)
            ->where('hr_managed', 1)
            ->count();

        $pendingLeaves = LeaveApplication::where('account_id', $accountId)
            ->pending()
            ->count();

        $approvedThisMonth = LeaveApplication::where('account_id', $accountId)
            ->approved()
            ->whereMonth('reviewed_at', $now->month)
            ->whereYear('reviewed_at', $now->year)
            ->count();

        $openCandidates = RecruitmentCandidate::where('account_id', $accountId)
            ->open()
            ->count();

        $onLeaveToday = LeaveApplication::where('account_id', $accountId)
            ->where('status', LeaveStatus::Approved->value)
            ->where('start_date', '<=', $now->toDateString())
            ->where('end_date', '>=', $now->toDateString())
            ->count();

        return [
            'total_employees' => $totalEmployees,
            'pending_leaves' => $pendingLeaves,
            'approved_this_month' => $approvedThisMonth,
            'open_candidates' => $openCandidates,
            'on_leave_today' => $onLeaveToday,
        ];
    }

    private function pendingLeavesQuery(int $accountId)
    {
        return LeaveApplication::query()
            ->with([
                'user:id,name',
                'user.employeeDetail:id,user_id,designation_id',
                'user.employeeDetail.designation:id,name',
                'leaveType:id,name,is_paid',
            ])
            ->where('account_id', $accountId)
            ->pending()
            ->orderByDesc('created_at');
    }

    private function upcomingLeavesQuery(int $accountId, Carbon $now, int $days)
    {
        $start = $now->copy()->startOfDay();
        $end = $now->copy()->addDays($days)->endOfDay();

        return LeaveApplication::query()
            ->with([
                'user:id,name',
                'user.employeeDetail:id,user_id,designation_id',
                'user.employeeDetail.designation:id,name',
                'leaveType:id,name,is_paid',
            ])
            ->where('account_id', $accountId)
            ->where('status', LeaveStatus::Approved->value)
            ->whereBetween('start_date', [$start, $end])
            ->orderBy('start_date');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentHires(int $accountId, Carbon $now, int $days): array
    {
        $since = $now->copy()->subDays($days)->toDateString();

        return User::query()
            ->select(['users.id', 'users.name'])
            ->join('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->leftJoin('designations', 'employee_details.designation_id', '=', 'designations.id')
            ->leftJoin('departments', 'employee_details.department_id', '=', 'departments.id')
            ->addSelect([
                'employee_details.hire_date as hire_date',
                'designations.name as designation_name',
                'departments.name as department_name',
            ])
            ->where('users.account_id', $accountId)
            ->whereIn('users.user_type_id', [2, 5])
            ->where('users.active', 1)
            ->where('users.hr_managed', 1)
            ->whereNotNull('employee_details.hire_date')
            ->where('employee_details.hire_date', '>=', $since)
            ->orderByDesc('employee_details.hire_date')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'designation' => $row->designation_name,
                'department' => $row->department_name,
                'hire_date' => $row->hire_date
                    ? Carbon::parse($row->hire_date)->toDateString()
                    : null,
            ])
            ->all();
    }
}
