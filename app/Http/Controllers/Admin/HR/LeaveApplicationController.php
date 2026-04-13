<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\HR;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Services\HR\HrNotificationService;
use App\Services\HR\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LeaveApplicationController extends Controller
{
    public function __construct(
        protected readonly LeaveService $leaveService,
        protected readonly HrNotificationService $notificationService,
    ) {}

    public function index(): View
    {
        $accountId = Auth::user()->account_id;

        $leaveTypes = LeaveType::active()
            ->where('account_id', $accountId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $designations = Designation::active()
            ->where('account_id', $accountId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $locations = \App\Models\Locations::where('account_id', $accountId)
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.hr.leave.applications.index', compact('leaveTypes', 'designations', 'locations'));
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $accountId = $user->account_id;

            $validated = $request->validate([
                'leave_type_id' => ['required', "exists:leave_types,id,account_id,{$accountId}"],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
                'duration_type' => ['required', 'in:full,half,short'],
                'reason' => ['required', 'string', 'max:1000'],
            ]);

            // Half/Short: end_date must equal start_date
            if (in_array($validated['duration_type'], ['half', 'short'])) {
                $validated['end_date'] = $validated['start_date'];
            }

            // Check overlap
            if ($this->leaveService->hasOverlap($user->id, $validated['start_date'], $validated['end_date'])) {
                return $this->errorResponse('You already have a leave application for these dates.');
            }

            $application = $this->leaveService->apply($validated, $user->id, $user->account_id);

            try {
                $this->notificationService->onLeaveSubmitted($application);
            } catch (\Exception) {
                // Notification failure should not block the operation
            }

            // Return balance warning if applicable
            $warning = $this->leaveService->getBalanceWarning(
                $user->id,
                (int) $validated['leave_type_id'],
                (float) $application->total_days
            );

            $message = 'Leave application submitted successfully.';
            if ($warning) {
                $message .= ' Warning: ' . $warning;
            }

            return $this->successResponse($message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeaveApplicationController@store');
        }
    }

    public function approve(Request $request, LeaveApplication $leaveApplication): JsonResponse
    {
        try {
            if ($leaveApplication->status->value !== 'pending') {
                return $this->errorResponse('Only pending applications can be approved.');
            }

            $this->leaveService->approve(
                $leaveApplication,
                $request->input('review_notes'),
                Auth::id()
            );

            try {
                $this->notificationService->onLeaveApproved($leaveApplication);
            } catch (\Exception) {
                // Notification failure should not block the operation
            }

            return $this->successResponse('Leave application approved.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeaveApplicationController@approve');
        }
    }

    public function reject(Request $request, LeaveApplication $leaveApplication): JsonResponse
    {
        try {
            if ($leaveApplication->status->value !== 'pending') {
                return $this->errorResponse('Only pending applications can be rejected.');
            }

            $this->leaveService->reject(
                $leaveApplication,
                $request->input('review_notes'),
                Auth::id()
            );

            try {
                $this->notificationService->onLeaveRejected($leaveApplication);
            } catch (\Exception) {
                // Notification failure should not block the operation
            }

            return $this->successResponse('Leave application rejected.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeaveApplicationController@reject');
        }
    }

    public function cancel(Request $request, LeaveApplication $leaveApplication): JsonResponse
    {
        try {
            $status = $leaveApplication->status->value;

            if ($status === 'approved') {
                if (!Gate::allows('hr_leave_approve')) {
                    return $this->errorResponse('Only HR managers can cancel approved leaves.', 403);
                }
            } elseif ($status !== 'pending') {
                return $this->errorResponse('Only pending or approved applications can be cancelled.');
            }

            $this->leaveService->cancel($leaveApplication);

            try {
                $this->notificationService->onLeaveCancelled($leaveApplication);
            } catch (\Exception) {
                // Notification failure should not block the operation
            }

            $msg = $status === 'approved'
                ? 'Approved leave cancelled and balance restored.'
                : 'Leave application cancelled.';

            return $this->successResponse($msg);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeaveApplicationController@cancel');
        }
    }

    public function calendar(): View
    {
        return view('admin.hr.leave.calendar');
    }

    public function calendarData(Request $request): JsonResponse
    {
        try {
            $accountId = Auth::user()->account_id;
            $month = $request->get('month', now()->month);
            $year = $request->get('year', now()->year);

            $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $applications = LeaveApplication::with(['user', 'leaveType'])
                ->where('account_id', $accountId)
                ->whereIn('status', ['pending', 'approved'])
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function ($q2) use ($startDate, $endDate) {
                          $q2->where('start_date', '<=', $startDate)
                             ->where('end_date', '>=', $endDate);
                      });
                })
                ->get();

            $items = $applications->map(fn (LeaveApplication $app) => [
                'id' => $app->id,
                'group' => $app->user_id,
                'content' => $app->leaveType->name . ' (' . $app->total_days . 'd)',
                'start' => $app->start_date->format('Y-m-d'),
                'end' => $app->end_date->addDay()->format('Y-m-d'),
                'className' => $app->status->value === 'approved' ? 'bg-success' : 'bg-warning',
                'title' => $app->user->name . ': ' . $app->leaveType->name . ' (' . $app->status->value . ')',
            ]);

            $groups = $applications->pluck('user')->unique('id')->map(fn ($u) => [
                'id' => $u->id,
                'content' => $u->name,
            ])->values();

            return $this->successResponse('OK', [
                'items' => $items,
                'groups' => $groups,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeaveApplicationController@calendarData');
        }
    }
}
