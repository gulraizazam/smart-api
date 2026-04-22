<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HR\StoreDepartmentRequest;
use App\Http\Requests\Admin\HR\UpdateDepartmentRequest;
use App\Http\Resources\HR\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DepartmentController extends Controller
{
    /**
     * GET /api/hr/departments
     *
     * Permission: hr_departments_view. Returns all departments by default;
     * callers with hr_departments_manage get inactive ones too.
     */
    public function index(): JsonResponse
    {
        try {
            if (! Gate::allows('hr_departments_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = (int) Auth::user()->account_id;

            $query = Department::query()
                ->withCount(['designations', 'employeeDetails'])
                ->where('account_id', $accountId)
                ->orderBy('name');

            if (! Gate::allows('hr_departments_manage')) {
                $query->where('active', 1);
            }

            return $this->successResponse(
                'Departments retrieved successfully.',
                DepartmentResource::collection($query->get()),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\DepartmentController@index');
        }
    }

    /**
     * GET /api/hr/departments/{department}
     */
    public function show(Department $department): JsonResponse
    {
        try {
            if (! Gate::allows('hr_departments_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if ((int) $department->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Department not found.', 404);
            }

            $department->loadCount(['designations', 'employeeDetails']);

            return $this->successResponse(
                'Department retrieved successfully.',
                new DepartmentResource($department),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\DepartmentController@show');
        }
    }

    /**
     * POST /api/hr/departments
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $user = Auth::user();

            $department = Department::create([
                'name' => $validated['name'],
                'active' => array_key_exists('active', $validated) ? (bool) $validated['active'] : true,
                'account_id' => (int) $user->account_id,
                'created_by' => (int) $user->id,
            ]);

            return $this->successResponse(
                'Department created successfully.',
                new DepartmentResource($department),
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\DepartmentController@store');
        }
    }

    /**
     * PATCH /api/hr/departments/{department}
     */
    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        try {
            if ((int) $department->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Department not found.', 404);
            }

            $validated = $request->validated();

            $department->update([
                'name' => $validated['name'],
                'active' => array_key_exists('active', $validated)
                    ? (bool) $validated['active']
                    : $department->active,
                'updated_by' => (int) Auth::id(),
            ]);

            return $this->successResponse(
                'Department updated successfully.',
                new DepartmentResource($department->fresh()),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\DepartmentController@update');
        }
    }

    /**
     * DELETE /api/hr/departments/{department}
     *
     * Soft-delete. 409 if any employees are still assigned, or if designations
     * under this department exist — we never want to orphan HR references.
     */
    public function destroy(Department $department): JsonResponse
    {
        try {
            if (! Gate::allows('hr_departments_manage')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if ((int) $department->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Department not found.', 404);
            }

            if ($department->employeeDetails()->exists()) {
                return $this->errorResponse(
                    'Cannot delete department. It has employees assigned to it.',
                    409,
                );
            }

            if ($department->designations()->exists()) {
                return $this->errorResponse(
                    'Cannot delete department. It has designations assigned to it.',
                    409,
                );
            }

            $department->delete();

            return $this->successResponse('Department deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\DepartmentController@destroy');
        }
    }
}
