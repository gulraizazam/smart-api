<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HR\StoreLeaveTypeRequest;
use App\Http\Requests\Admin\HR\UpdateLeaveTypeRequest;
use App\Http\Resources\HR\LeaveTypeResource;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LeaveTypeController extends Controller
{
    /**
     * GET /api/hr/leave-types
     *
     * Permission: hr_leave_view. Returns all active types by default; callers
     * with hr_leave_manage get inactive ones too.
     */
    public function index(): JsonResponse
    {
        try {
            if (!Gate::allows('hr_leave_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = (int) Auth::user()->account_id;

            $query = LeaveType::query()
                ->where('account_id', $accountId)
                ->orderBy('name');

            if (!Gate::allows('hr_leave_manage')) {
                $query->where('active', 1);
            }

            return $this->successResponse(
                'Leave types retrieved successfully.',
                LeaveTypeResource::collection($query->get()),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveTypeController@index');
        }
    }

    /**
     * POST /api/hr/leave-types
     */
    public function store(StoreLeaveTypeRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $leaveType = LeaveType::create([
                'name' => $validated['name'],
                'is_paid' => (bool) $validated['is_paid'],
                'active' => array_key_exists('active', $validated) ? (bool) $validated['active'] : true,
                'account_id' => (int) Auth::user()->account_id,
                'created_by' => (int) Auth::id(),
            ]);

            return $this->successResponse(
                'Leave type created successfully.',
                new LeaveTypeResource($leaveType),
                201,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveTypeController@store');
        }
    }

    /**
     * PATCH /api/hr/leave-types/{leaveType}
     */
    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType): JsonResponse
    {
        try {
            if ((int) $leaveType->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Leave type not found.', 404);
            }

            $validated = $request->validated();

            $leaveType->update([
                'name' => $validated['name'],
                'is_paid' => (bool) $validated['is_paid'],
                'active' => array_key_exists('active', $validated)
                    ? (bool) $validated['active']
                    : $leaveType->active,
            ]);

            return $this->successResponse(
                'Leave type updated successfully.',
                new LeaveTypeResource($leaveType->fresh()),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveTypeController@update');
        }
    }

    /**
     * DELETE /api/hr/leave-types/{leaveType}
     *
     * Soft-delete. 409 if any leave applications reference this type — we never
     * want to break the audit trail of approved/cancelled leave records.
     */
    public function destroy(LeaveType $leaveType): JsonResponse
    {
        try {
            if (!Gate::allows('hr_leave_manage')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if ((int) $leaveType->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Leave type not found.', 404);
            }

            if (LeaveApplication::where('leave_type_id', $leaveType->id)->exists()) {
                return $this->errorResponse(
                    'Cannot delete a leave type with existing applications.',
                    409,
                );
            }

            $leaveType->delete();

            return $this->successResponse('Leave type deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveTypeController@destroy');
        }
    }
}
