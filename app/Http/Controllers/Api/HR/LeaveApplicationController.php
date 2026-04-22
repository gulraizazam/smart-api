<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\HR;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HR\ApproveLeaveRequest;
use App\Http\Requests\Admin\HR\RejectLeaveRequest;
use App\Http\Requests\Admin\HR\StoreLeaveApplicationRequest;
use App\Http\Resources\HR\LeaveApplicationResource;
use App\Models\LeaveApplication;
use App\Services\HR\HrNotificationService;
use App\Services\HR\LeaveService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveApplicationController extends Controller
{
    public function __construct(
        protected readonly LeaveService $leaveService,
        protected readonly HrNotificationService $notificationService,
    ) {}

    /**
     * GET /api/hr/leave-applications
     *
     * Paginated list with filters. Callers without `hr_leave_view` are force-
     * scoped to their own applications (still get the shape, not a 403, so
     * mobile can surface "My leaves" off the same endpoint if it wants).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'status' => ['nullable', 'in:pending,approved,rejected,cancelled'],
                'user_id' => ['nullable', 'integer'],
                'leave_type_id' => ['nullable', 'integer'],
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
                'search' => ['nullable', 'string', 'max:100'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;
            $canViewAll = Gate::allows('hr_leave_view');

            $query = LeaveApplication::query()
                ->with([
                    'user:id,name',
                    'user.employeeDetail:id,user_id,designation_id',
                    'user.employeeDetail.designation:id,name',
                    'leaveType:id,name,is_paid',
                    'reviewer:id,name',
                ])
                ->where('account_id', $accountId);

            if (! $canViewAll) {
                $query->where('user_id', (int) Auth::id());
            } elseif ($request->filled('user_id')) {
                $query->where('user_id', (int) $request->integer('user_id'));
            }

            if ($request->filled('status')) {
                $query->where('status', (string) $request->string('status'));
            }

            if ($request->filled('leave_type_id')) {
                $query->where('leave_type_id', (int) $request->integer('leave_type_id'));
            }

            if ($request->filled('date_from') && $request->filled('date_to')) {
                $from = $request->date('date_from')?->toDateString();
                $to = $request->date('date_to')?->toDateString();
                $query->where(function ($q) use ($from, $to): void {
                    $q->whereBetween('start_date', [$from, $to])
                        ->orWhereBetween('end_date', [$from, $to])
                        ->orWhere(function ($q2) use ($from, $to): void {
                            $q2->where('start_date', '<=', $from)
                                ->where('end_date', '>=', $to);
                        });
                });
            } elseif ($request->filled('date_from')) {
                $query->where('end_date', '>=', $request->date('date_from')?->toDateString());
            } elseif ($request->filled('date_to')) {
                $query->where('start_date', '<=', $request->date('date_to')?->toDateString());
            }

            if ($request->filled('search') && $canViewAll) {
                $term = '%'.$request->string('search')->trim().'%';
                $query->whereHas('user', fn ($q) => $q->where('name', 'like', $term));
            }

            $perPage = (int) ($request->integer('per_page') ?: 25);

            $paginated = $query->orderByDesc('id')->paginate($perPage);

            return $this->successResponse(
                'Leave applications retrieved successfully.',
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
            return $this->handleException($e, 'Api\\HR\\LeaveApplicationController@index');
        }
    }

    /**
     * POST /api/hr/leave-applications
     *
     * HR creates a leave application on behalf of an employee. Reuses
     * LeaveService::apply so overlap / shift-hours / balance logic stays in
     * one place. `auto_approve=true` immediately approves the application
     * (which in turn increments the balance via the service).
     */
    public function store(StoreLeaveApplicationRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $accountId = (int) Auth::user()->account_id;
            $userId = (int) $validated['user_id'];

            if ($this->leaveService->hasOverlap($userId, $validated['start_date'], $validated['end_date'])) {
                return $this->errorResponse(
                    'This employee already has a leave application overlapping these dates.',
                    409,
                );
            }

            $application = DB::transaction(function () use ($validated, $userId, $accountId, $request): LeaveApplication {
                $created = $this->leaveService->apply($validated, $userId, $accountId);

                if ((bool) ($validated['auto_approve'] ?? false)) {
                    return $this->leaveService->approve(
                        $created,
                        $request->input('review_notes'),
                        (int) Auth::id(),
                    );
                }

                return $created;
            });

            DB::afterCommit(function () use ($application): void {
                try {
                    if ($application->status === LeaveStatus::Approved) {
                        $this->notificationService->onLeaveApproved($application);
                    } else {
                        $this->notificationService->onLeaveSubmitted($application);
                    }
                } catch (\Exception) {
                    // Notification failure never blocks the operation.
                }
            });

            return $this->successResponse(
                $application->status === LeaveStatus::Approved
                    ? 'Leave application created and approved.'
                    : 'Leave application created.',
                new LeaveApplicationResource($this->leaveService->getDetail($application)),
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveApplicationController@store');
        }
    }

    /**
     * GET /api/hr/leave-applications/summary
     *
     * Status counts for the given filter window. Handy for mobile
     * dashboards and badge counters.
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('hr_leave_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
                'user_id' => ['nullable', 'integer'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = LeaveApplication::query()->where('account_id', $accountId);

            if ($request->filled('user_id')) {
                $query->where('user_id', (int) $request->integer('user_id'));
            }

            if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->whereBetween('start_date', [
                    $request->date('date_from')?->toDateString(),
                    $request->date('date_to')?->toDateString(),
                ]);
            }

            $counts = $query
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            $payload = [
                'pending' => (int) ($counts['pending'] ?? 0),
                'approved' => (int) ($counts['approved'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
                'cancelled' => (int) ($counts['cancelled'] ?? 0),
            ];
            $payload['total'] = array_sum($payload);

            return $this->successResponse('Leave application summary retrieved successfully.', $payload);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveApplicationController@summary');
        }
    }

    /**
     * GET /api/hr/leave-applications/calendar
     *
     * Month-scoped calendar entries for pending + approved applications.
     * Mirrors the admin `calendar-data` endpoint but lives under the REST
     * resource so mobile clients can discover it off the same base.
     */
    public function calendar(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('hr_leave_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'month' => ['nullable', 'integer', 'min:1', 'max:12'],
                'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            ]);

            $accountId = (int) Auth::user()->account_id;
            $month = (int) ($request->integer('month') ?: now()->month);
            $year = (int) ($request->integer('year') ?: now()->year);

            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $applications = LeaveApplication::with(['user:id,name', 'leaveType:id,name'])
                ->where('account_id', $accountId)
                ->whereIn('status', [LeaveStatus::Pending->value, LeaveStatus::Approved->value])
                ->where(function ($q) use ($startDate, $endDate): void {
                    $q->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function ($q2) use ($startDate, $endDate): void {
                            $q2->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                        });
                })
                ->get();

            $items = $applications->map(fn (LeaveApplication $app) => [
                'id' => (int) $app->id,
                'user_id' => (int) $app->user_id,
                'user_name' => (string) ($app->user?->name ?? ''),
                'leave_type' => (string) ($app->leaveType?->name ?? ''),
                'status' => $app->status?->value,
                'start_date' => $app->start_date?->toDateString(),
                'end_date' => $app->end_date?->toDateString(),
                'total_days' => (float) $app->total_days,
            ])->values();

            return $this->successResponse('Leave calendar retrieved successfully.', [
                'month' => $month,
                'year' => $year,
                'items' => $items,
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveApplicationController@calendar');
        }
    }

    /**
     * GET /api/hr/leave-applications/export
     *
     * CSV export of applications matching the filter window.
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        try {
            if (! Gate::allows('hr_leave_view')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            $request->validate([
                'status' => ['nullable', 'in:pending,approved,rejected,cancelled'],
                'user_id' => ['nullable', 'integer'],
                'leave_type_id' => ['nullable', 'integer'],
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = LeaveApplication::query()
                ->with(['user:id,name', 'leaveType:id,name'])
                ->where('account_id', $accountId);

            if ($request->filled('status')) {
                $query->where('status', (string) $request->string('status'));
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', (int) $request->integer('user_id'));
            }
            if ($request->filled('leave_type_id')) {
                $query->where('leave_type_id', (int) $request->integer('leave_type_id'));
            }
            if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->whereBetween('start_date', [
                    $request->date('date_from')?->toDateString(),
                    $request->date('date_to')?->toDateString(),
                ]);
            }

            $filename = 'leave_applications_'.now()->format('Ymd_His').'.csv';

            $callback = function () use ($query): void {
                $file = fopen('php://output', 'w');
                fputcsv($file, [
                    'ID', 'Employee', 'Leave Type', 'Start Date', 'End Date',
                    'Duration', 'Total Days', 'Status', 'Reason', 'Applied On',
                ]);

                $query->orderByDesc('id')->chunkById(500, function ($rows) use ($file): void {
                    foreach ($rows as $app) {
                        fputcsv($file, [
                            $app->id,
                            $app->user?->name ?? '',
                            $app->leaveType?->name ?? '',
                            $app->start_date?->toDateString() ?? '',
                            $app->end_date?->toDateString() ?? '',
                            $app->duration_type?->value ?? '',
                            (float) $app->total_days,
                            $app->status?->value ?? '',
                            $app->reason,
                            $app->created_at?->toIso8601String() ?? '',
                        ]);
                    }
                });

                fclose($file);
            };

            return response()->stream($callback, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveApplicationController@export');
        }
    }

    /**
     * GET /api/hr/leave-applications/{leaveApplication}
     */
    public function show(LeaveApplication $leaveApplication): JsonResponse
    {
        try {
            if (! $this->isOwnedByCurrentAccount($leaveApplication)) {
                return $this->errorResponse('Leave application not found.', 404);
            }

            $isOwner = (int) $leaveApplication->user_id === (int) Auth::id();
            if (! $isOwner && ! Gate::allows('hr_leave_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $application = $this->leaveService->getDetail($leaveApplication);

            return $this->successResponse(
                'Leave application retrieved successfully.',
                new LeaveApplicationResource($application),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveApplicationController@show');
        }
    }

    /**
     * POST /api/hr/leave-applications/{leaveApplication}/approve
     */
    public function approve(ApproveLeaveRequest $request, LeaveApplication $leaveApplication): JsonResponse
    {
        try {
            if (! $this->isOwnedByCurrentAccount($leaveApplication)) {
                return $this->errorResponse('Leave application not found.', 404);
            }

            if ($leaveApplication->status !== LeaveStatus::Pending) {
                return $this->errorResponse('Only pending applications can be approved.');
            }

            $fresh = $this->leaveService->approve(
                $leaveApplication,
                $request->input('review_notes'),
                (int) Auth::id(),
            );

            DB::afterCommit(function () use ($fresh): void {
                try {
                    $this->notificationService->onLeaveApproved($fresh);
                } catch (\Exception) {
                    // Notification failure never blocks the operation.
                }
            });

            return $this->successResponse(
                'Leave application approved.',
                new LeaveApplicationResource($this->leaveService->getDetail($fresh)),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveApplicationController@approve');
        }
    }

    /**
     * POST /api/hr/leave-applications/{leaveApplication}/reject
     */
    public function reject(RejectLeaveRequest $request, LeaveApplication $leaveApplication): JsonResponse
    {
        try {
            if (! $this->isOwnedByCurrentAccount($leaveApplication)) {
                return $this->errorResponse('Leave application not found.', 404);
            }

            if ($leaveApplication->status !== LeaveStatus::Pending) {
                return $this->errorResponse('Only pending applications can be rejected.');
            }

            $fresh = $this->leaveService->reject(
                $leaveApplication,
                (string) $request->input('review_notes'),
                (int) Auth::id(),
            );

            DB::afterCommit(function () use ($fresh): void {
                try {
                    $this->notificationService->onLeaveRejected($fresh);
                } catch (\Exception) {
                    // Notification failure never blocks the operation.
                }
            });

            return $this->successResponse(
                'Leave application rejected.',
                new LeaveApplicationResource($this->leaveService->getDetail($fresh)),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveApplicationController@reject');
        }
    }

    /**
     * POST /api/hr/leave-applications/{leaveApplication}/cancel
     *
     * Mirrors the web LeaveApplicationController@cancel rule exactly:
     *   - APPROVED leaves → HR (hr_leave_approve) only.
     *   - PENDING leaves → owner OR hr_leave_approve.
     *   - Anything else → 422.
     */
    public function cancel(Request $request, LeaveApplication $leaveApplication): JsonResponse
    {
        try {
            if (! $this->isOwnedByCurrentAccount($leaveApplication)) {
                return $this->errorResponse('Leave application not found.', 404);
            }

            $status = $leaveApplication->status?->value;

            if ($status === 'approved') {
                if (! Gate::allows('hr_leave_approve')) {
                    return $this->errorResponse('Only HR managers can cancel approved leaves.', 403);
                }
            } elseif ($status === 'pending') {
                $isOwner = (int) $leaveApplication->user_id === (int) Auth::id();
                if (! $isOwner && ! Gate::allows('hr_leave_approve')) {
                    return $this->errorResponse('You can only cancel your own leave applications.', 403);
                }
            } else {
                return $this->errorResponse('Only pending or approved applications can be cancelled.');
            }

            $fresh = $this->leaveService->cancel($leaveApplication);

            DB::afterCommit(function () use ($fresh): void {
                try {
                    $this->notificationService->onLeaveCancelled($fresh);
                } catch (\Exception) {
                    // Notification failure never blocks the operation.
                }
            });

            $message = $status === 'approved'
                ? 'Approved leave cancelled and balance restored.'
                : 'Leave application cancelled.';

            return $this->successResponse(
                $message,
                new LeaveApplicationResource($this->leaveService->getDetail($fresh)),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveApplicationController@cancel');
        }
    }

    private function isOwnedByCurrentAccount(LeaveApplication $application): bool
    {
        return (int) $application->account_id === (int) Auth::user()->account_id;
    }
}
