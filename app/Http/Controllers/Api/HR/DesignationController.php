<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HR\StoreDesignationRequest;
use App\Http\Requests\Admin\HR\UpdateDesignationRequest;
use App\Http\Resources\HR\DesignationResource;
use App\Models\Designation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DesignationController extends Controller
{
    /**
     * GET /api/hr/designations
     *
     * Permission: hr_designations_view. Returns all designations by default;
     * callers with hr_designations_manage get inactive ones too.
     */
    public function index(): JsonResponse
    {
        try {
            if (! Gate::allows('hr_designations_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = (int) Auth::user()->account_id;

            $query = Designation::query()
                ->with('department:id,name')
                ->where('account_id', $accountId)
                ->orderBy('name');

            if (! Gate::allows('hr_designations_manage')) {
                $query->where('active', 1);
            }

            if ($this->filled('department_id')) {
                $query->where('department_id', (int) request('department_id'));
            }

            return $this->successResponse(
                'Designations retrieved successfully.',
                DesignationResource::collection($query->get()),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\DesignationController@index');
        }
    }

    /**
     * GET /api/hr/designations/{designation}
     */
    public function show(Designation $designation): JsonResponse
    {
        try {
            if (! Gate::allows('hr_designations_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if ((int) $designation->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Designation not found.', 404);
            }

            $designation->load('department:id,name');

            return $this->successResponse(
                'Designation retrieved successfully.',
                new DesignationResource($designation),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\DesignationController@show');
        }
    }

    /**
     * POST /api/hr/designations
     */
    public function store(StoreDesignationRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $user = Auth::user();

            $designation = Designation::create([
                'name' => $validated['name'],
                'department_id' => $validated['department_id'] ?? null,
                'active' => array_key_exists('active', $validated) ? (bool) $validated['active'] : true,
                'account_id' => (int) $user->account_id,
                'created_by' => (int) $user->id,
            ]);

            $designation->load('department:id,name');

            return $this->successResponse(
                'Designation created successfully.',
                new DesignationResource($designation),
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\DesignationController@store');
        }
    }

    /**
     * PATCH /api/hr/designations/{designation}
     */
    public function update(UpdateDesignationRequest $request, Designation $designation): JsonResponse
    {
        try {
            if ((int) $designation->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Designation not found.', 404);
            }

            $validated = $request->validated();

            $designation->update([
                'name' => $validated['name'],
                'department_id' => $validated['department_id'] ?? null,
                'active' => array_key_exists('active', $validated)
                    ? (bool) $validated['active']
                    : $designation->active,
                'updated_by' => (int) Auth::id(),
            ]);

            return $this->successResponse(
                'Designation updated successfully.',
                new DesignationResource($designation->fresh()->load('department:id,name')),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\DesignationController@update');
        }
    }

    /**
     * DELETE /api/hr/designations/{designation}
     *
     * Soft-delete. 409 if any employees are currently assigned — we never want
     * to orphan employee designation references.
     */
    public function destroy(Designation $designation): JsonResponse
    {
        try {
            if (! Gate::allows('hr_designations_manage')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if ((int) $designation->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Designation not found.', 404);
            }

            if ($designation->employeeDetails()->exists()) {
                return $this->errorResponse(
                    'Cannot delete designation. It has employees assigned to it.',
                    409,
                );
            }

            $designation->delete();

            return $this->successResponse('Designation deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\DesignationController@destroy');
        }
    }

    private function filled(string $key): bool
    {
        return request()->filled($key);
    }
}
