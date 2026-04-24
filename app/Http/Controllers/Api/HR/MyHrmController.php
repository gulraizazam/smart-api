<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\HR;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HR\ApplyLeaveRequest;
use App\Http\Requests\Admin\HR\UpdateMyLeaveRequest;
use App\Http\Requests\Admin\HR\UpdateMyProfileRequest;
use App\Http\Resources\HR\EmployeeDocumentResource;
use App\Http\Resources\HR\LeaveApplicationResource;
use App\Http\Resources\HR\MyLeavesResource;
use App\Http\Resources\HR\MyProfileResource;
use App\Models\EmployeeDocument;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\HR\HrNotificationService;
use App\Services\HR\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Self-service HR endpoints. Every action operates on the authenticated user's
 * own data — we scope every query by Auth::id() and never accept an employee
 * identifier from the client.
 */
class MyHrmController extends Controller
{
    public function __construct(
        protected readonly LeaveService $leaveService,
        protected readonly HrNotificationService $notificationService,
    ) {}

    /**
     * GET /api/hr/my/profile
     */
    public function profile(): JsonResponse
    {
        try {
            $user = User::with([
                'employeeDetail.department',
                'employeeDetail.designation',
                'employeeDetail.reportingManager',
                'user_has_locations.location',
                'employeeDocuments' => fn ($q) => $q->orderByDesc('created_at'),
            ])->find(Auth::id());

            if (!$user) {
                return $this->errorResponse('Profile not found.', 404);
            }

            return $this->successResponse(
                'Profile retrieved successfully.',
                new MyProfileResource($user),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\MyHrmController@profile');
        }
    }

    /**
     * PATCH /api/hr/my/profile
     *
     * Allowlist-only update — the FormRequest enumerates every editable field.
     * Fields split between users and employee_details tables.
     */
    public function updateProfile(UpdateMyProfileRequest $request): JsonResponse
    {
        try {
            $user = User::find(Auth::id());
            if (!$user) {
                return $this->errorResponse('Profile not found.', 404);
            }

            $validated = $request->validated();

            $userFields = collect($validated)
                ->only(['phone', 'dob', 'gender'])
                ->toArray();

            // `address` lives on employee_details (see migration
            // 2026_04_23_120000_add_address_to_employee_details_table.php).
            $detailFields = collect($validated)
                ->only([
                    'emergency_contact_name',
                    'emergency_contact_phone',
                    'emergency_contact_relation',
                    'address',
                ])
                ->toArray();

            DB::transaction(function () use ($user, $userFields, $detailFields): void {
                if (!empty($userFields)) {
                    $user->update($userFields);
                }

                if (!empty($detailFields) && $user->employeeDetail) {
                    $user->employeeDetail->update($detailFields);
                }
            });

            $fresh = User::with([
                'employeeDetail.department',
                'employeeDetail.designation',
                'employeeDetail.reportingManager',
                'user_has_locations.location',
                'employeeDocuments' => fn ($q) => $q->orderByDesc('created_at'),
            ])->find($user->id);

            return $this->successResponse(
                'Profile updated successfully.',
                new MyProfileResource($fresh),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\MyHrmController@updateProfile');
        }
    }

    /**
     * GET /api/hr/my/leaves
     *
     * Mirrors Admin\HR\MyHrmController@leaves response shape.
     */
    public function leaves(): JsonResponse
    {
        try {
            $userId = (int) Auth::id();
            $accountId = (int) Auth::user()->account_id;
            $fiscalYear = LeaveService::currentFiscalYear();

            $leaveTypes = LeaveType::active()
                ->where('account_id', $accountId)
                ->orderBy('name')
                ->get(['id', 'name']);

            $balances = LeaveBalance::where('user_id', $userId)
                ->where('fiscal_year', $fiscalYear)
                ->get();

            $applications = LeaveApplication::with('leaveType', 'user.employeeDetail.designation', 'reviewer')
                ->forUser($userId)
                ->forFiscalYear($fiscalYear)
                ->orderByDesc('created_at')
                ->get();

            $shiftHours = $this->leaveService->resolveShiftHours($userId);

            return $this->successResponse(
                'Leave summary retrieved successfully.',
                new MyLeavesResource([
                    'leave_types' => $leaveTypes,
                    'balances' => $balances,
                    'applications' => $applications,
                    'shift_hours' => $shiftHours,
                    'fiscal_year' => $fiscalYear,
                ]),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\MyHrmController@leaves');
        }
    }

    /**
     * POST /api/hr/my/leaves/apply
     */
    public function applyLeave(ApplyLeaveRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $userId = (int) $user->id;
            $accountId = (int) $user->account_id;

            $validated = $request->validated();

            // Single-day duration types always collapse end_date → start_date.
            if (in_array($validated['duration_type'], ['half', 'short', 'custom'], true)) {
                $validated['end_date'] = $validated['start_date'];
            }

            if ($validated['duration_type'] === 'custom') {
                $shiftHours = $this->leaveService->resolveShiftHours($userId);
                if ($shiftHours === null) {
                    return $this->errorResponse(
                        'By-hours leave requires a shift duration on your profile. Please contact HR.',
                    );
                }
                if ((float) $validated['custom_hours'] >= $shiftHours) {
                    return $this->errorResponse(
                        'Hours must be less than your shift hours (' . $shiftHours . '). Use Full Day instead.',
                    );
                }
            }

            if ($this->leaveService->hasOverlap($userId, $validated['start_date'], $validated['end_date'])) {
                return $this->errorResponse('You already have a leave application for these dates.');
            }

            $application = DB::transaction(
                fn () => $this->leaveService->apply($validated, $userId, $accountId),
            );

            DB::afterCommit(function () use ($application): void {
                try {
                    $this->notificationService->onLeaveSubmitted($application);
                } catch (\Exception) {
                    // Notification failure never blocks the operation.
                }
            });

            $warning = $this->leaveService->getBalanceWarning(
                $userId,
                (int) $validated['leave_type_id'],
                (float) $application->total_days,
            );

            $message = 'Leave application submitted successfully.';
            if ($warning) {
                $message .= ' Warning: ' . $warning;
            }

            return $this->successResponse(
                $message,
                new LeaveApplicationResource($this->leaveService->getDetail($application)),
                201,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\MyHrmController@applyLeave');
        }
    }

    /**
     * PUT /api/hr/my/leaves/{leaveApplication}
     *
     * Owner + pending-status guards enforced HARD.
     */
    public function updateLeave(UpdateMyLeaveRequest $request, LeaveApplication $leaveApplication): JsonResponse
    {
        try {
            if ((int) $leaveApplication->user_id !== (int) Auth::id()) {
                return $this->errorResponse('You can only edit your own leave applications.', 403);
            }

            if ($leaveApplication->status !== LeaveStatus::Pending) {
                return $this->errorResponse('Only pending leaves can be edited.', 422);
            }

            $userId = (int) Auth::id();
            $validated = $request->validated();

            if (in_array($validated['duration_type'], ['half', 'short', 'custom'], true)) {
                $validated['end_date'] = $validated['start_date'];
            }

            if ($validated['duration_type'] === 'custom') {
                $shiftHours = $this->leaveService->resolveShiftHours($userId);
                if ($shiftHours === null) {
                    return $this->errorResponse(
                        'By-hours leave requires a shift duration on your profile. Please contact HR.',
                    );
                }
                if ((float) $validated['custom_hours'] >= $shiftHours) {
                    return $this->errorResponse(
                        'Hours must be less than your shift hours (' . $shiftHours . '). Use Full Day instead.',
                    );
                }
            }

            if ($this->leaveService->hasOverlap(
                $userId,
                $validated['start_date'],
                $validated['end_date'],
                $leaveApplication->id,
            )) {
                return $this->errorResponse('You already have a leave application for these dates.');
            }

            $fresh = DB::transaction(
                fn () => $this->leaveService->update($leaveApplication, $validated),
            );

            return $this->successResponse(
                'Leave application updated successfully.',
                new LeaveApplicationResource($this->leaveService->getDetail($fresh)),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\MyHrmController@updateLeave');
        }
    }

    /**
     * POST /api/hr/my/leaves/{leaveApplication}/cancel
     *
     * Self-service cancel is narrower than the HR cancel endpoint:
     *   - Only OWN pending leaves can be cancelled here.
     *   - Approved-leave cancellation requires hr_leave_approve and uses the
     *     admin/API LeaveApplicationController@cancel endpoint.
     */
    public function cancelLeave(LeaveApplication $leaveApplication): JsonResponse
    {
        try {
            if ((int) $leaveApplication->user_id !== (int) Auth::id()) {
                return $this->errorResponse('You can only cancel your own leave applications.', 403);
            }

            if ($leaveApplication->status !== LeaveStatus::Pending) {
                return $this->errorResponse('Only pending leaves can be cancelled.', 422);
            }

            $fresh = $this->leaveService->cancel($leaveApplication);

            DB::afterCommit(function () use ($fresh): void {
                try {
                    $this->notificationService->onLeaveCancelled($fresh);
                } catch (\Exception) {
                    // Notification failure never blocks the operation.
                }
            });

            return $this->successResponse(
                'Leave application cancelled.',
                new LeaveApplicationResource($this->leaveService->getDetail($fresh)),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\MyHrmController@cancelLeave');
        }
    }

    /**
     * GET /api/hr/my/documents
     */
    public function documents(): JsonResponse
    {
        try {
            $documents = EmployeeDocument::with('uploader')
                ->where('user_id', Auth::id())
                ->orderByDesc('created_at')
                ->get();

            return $this->successResponse(
                'Documents retrieved successfully.',
                EmployeeDocumentResource::collection($documents),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\MyHrmController@documents');
        }
    }
}
