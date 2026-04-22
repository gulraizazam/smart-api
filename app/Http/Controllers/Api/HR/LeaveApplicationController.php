<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\HR;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HR\ApproveLeaveRequest;
use App\Http\Requests\Admin\HR\RejectLeaveRequest;
use App\Http\Resources\HR\LeaveApplicationResource;
use App\Models\LeaveApplication;
use App\Services\HR\HrNotificationService;
use App\Services\HR\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LeaveApplicationController extends Controller
{
    public function __construct(
        protected readonly LeaveService $leaveService,
        protected readonly HrNotificationService $notificationService,
    ) {}

    /**
     * GET /api/hr/leave-applications/{leaveApplication}
     */
    public function show(LeaveApplication $leaveApplication): JsonResponse
    {
        try {
            if (!$this->isOwnedByCurrentAccount($leaveApplication)) {
                return $this->errorResponse('Leave application not found.', 404);
            }

            $isOwner = (int) $leaveApplication->user_id === (int) Auth::id();
            if (!$isOwner && !Gate::allows('hr_leave_view')) {
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
            if (!$this->isOwnedByCurrentAccount($leaveApplication)) {
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
            if (!$this->isOwnedByCurrentAccount($leaveApplication)) {
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
            if (!$this->isOwnedByCurrentAccount($leaveApplication)) {
                return $this->errorResponse('Leave application not found.', 404);
            }

            $status = $leaveApplication->status?->value;

            if ($status === 'approved') {
                if (!Gate::allows('hr_leave_approve')) {
                    return $this->errorResponse('Only HR managers can cancel approved leaves.', 403);
                }
            } elseif ($status === 'pending') {
                $isOwner = (int) $leaveApplication->user_id === (int) Auth::id();
                if (!$isOwner && !Gate::allows('hr_leave_approve')) {
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
