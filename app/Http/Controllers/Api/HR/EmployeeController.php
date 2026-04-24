<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HR\StoreEmployeeRequest;
use App\Http\Requests\Admin\HR\UpdateEmployeeRequest;
use App\Http\Resources\HR\EmployeeResource;
use App\Models\User;
use App\Services\HR\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EmployeeController extends Controller
{
    public function __construct(
        protected readonly EmployeeService $service,
    ) {}

    /**
     * POST /api/hr/employees
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        try {
            $accountId = (int) Auth::user()->account_id;
            $validated = $request->validated();

            $user = $this->service->createEmployee($validated, $accountId);

            $fresh = $this->service->getEmployeeProfile($user->id);

            return $this->successResponse(
                'Employee created successfully.',
                new EmployeeResource($fresh),
                201,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\EmployeeController@store');
        }
    }

    /**
     * GET /api/hr/employees/{user}
     */
    public function show(User $user): JsonResponse
    {
        try {
            if (!Gate::allows('hr_employees_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if ((int) $user->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Employee not found.', 404);
            }

            $profile = $this->service->getEmployeeProfile($user->id);

            return $this->successResponse(
                'Employee retrieved successfully.',
                new EmployeeResource($profile),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\EmployeeController@show');
        }
    }

    /**
     * PATCH /api/hr/employees/{user}
     */
    public function update(UpdateEmployeeRequest $request, User $user): JsonResponse
    {
        try {
            if ((int) $user->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Employee not found.', 404);
            }

            $accountId = (int) Auth::user()->account_id;
            $validated = $request->validated();

            DB::transaction(function () use ($validated, $user, $accountId): void {
                // `address` lives on employee_details (see migration
                // 2026_04_23_120000_add_address_to_employee_details_table.php) —
                // it flows through to $hrFields via the except() below.
                $profileFields = ['name', 'email', 'phone', 'cnic', 'dob', 'gender'];

                $profileUpdates = collect($validated)->only($profileFields)->toArray();
                if (!empty($validated['password'])) {
                    $profileUpdates['password'] = $validated['password'];
                }

                if (!empty($profileUpdates)) {
                    $user->update($profileUpdates);
                }

                $hrFields = collect($validated)
                    ->except([...$profileFields, 'password', 'location_ids'])
                    ->toArray();

                if (!empty($hrFields)) {
                    $this->service->updateEmployeeDetails($user, $hrFields, $accountId);
                }

                if (array_key_exists('location_ids', $validated)) {
                    $this->service->syncLocations(
                        $user,
                        $validated['location_ids'] ?? [],
                        $accountId,
                    );
                }
            });

            $fresh = $this->service->getEmployeeProfile($user->id);

            return $this->successResponse(
                'Employee updated successfully.',
                new EmployeeResource($fresh),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\EmployeeController@update');
        }
    }

    /**
     * DELETE /api/hr/employees/{user}
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            if (!Gate::allows('hr_employees_manage')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if ((int) $user->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Employee not found.', 404);
            }

            if ((int) $user->id === (int) Auth::id()) {
                return $this->errorResponse('You cannot delete your own account.', 422);
            }

            $this->service->deleteEmployee($user);

            return $this->successResponse('Employee deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\EmployeeController@destroy');
        }
    }

    /**
     * PATCH /api/hr/employees/{user}/status
     * Body: { status: 0|1 } — matches the Users/Doctors toggle pattern used
     * throughout the admin controllers.
     */
    public function status(Request $request, User $user): JsonResponse
    {
        try {
            if (!Gate::allows('hr_employees_manage')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if ((int) $user->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Employee not found.', 404);
            }

            $validated = $request->validate([
                'status' => ['required', 'integer', 'in:0,1'],
            ]);

            $updated = $this->service->toggleStatus($user, (int) $validated['status']);

            return $this->successResponse(
                'Employee status updated successfully.',
                [
                    'id' => $updated->id,
                    'active' => (int) $updated->active,
                ],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\EmployeeController@status');
        }
    }
}
