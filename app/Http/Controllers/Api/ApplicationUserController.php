<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApplicationUserDatatableRequest;
use App\Http\Requests\Admin\ApplicationUserRequest;
use App\Http\Requests\Admin\ChangePasswordRequest;
use App\Http\Requests\Admin\ChangeUserStatusRequest;
use App\Services\UserManagement\ApplicationUserService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use App\Helpers\ACL;
use App\Models\Patients;
use App\Services\Plan\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ApplicationUserController extends Controller
{


    public function __construct(
        private readonly ApplicationUserService $userService,
        private readonly PlanService $planService,
    ) {}

    public function index(): \Illuminate\View\View
    {
        if (!Gate::allows('users_manage')) {
            return abort(401);
        }

        return view('admin.users.index');
    }

    public function datatable(ApplicationUserDatatableRequest $request): JsonResponse
    {
        try {
            $params = array_merge($request->getFilters(), [
                'orderBy' => $request->getSortField(),
                'order' => $request->getSortDirection(),
                'offset' => $request->getOffset(),
                'limit' => $request->getPerPage(),
            ]);

            $result = $this->userService->getDatatableData($params);
            $perPage = $request->getPerPage();
            $total = $result['total'];

            return response()->json([
                'success' => true,
                'message' => 'Records retrieved successfully.',
                'data' => $result['data'],
                'permissions' => $this->userService->getUserPermissions(),
                'filter_values' => $this->userService->getFilterValues(),
                'active_filters' => $this->userService->getActiveFilters(),
                'meta' => [
                    'field' => $request->getSortField(),
                    'page' => $request->getPage(),
                    'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
                    'perpage' => $perPage,
                    'total' => $total,
                    'sort' => $request->getSortDirection(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function create(): JsonResponse
    {
        try {
            if (!Gate::allows('users_create')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            return $this->successResponse('Record found', $this->userService->getCreateData());
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function store(ApplicationUserRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('users_create')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $this->userService->create($request->validated());

            return $this->successResponse('Record has been created successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('users_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $data = $this->userService->getEditData($id);

            if (!$data) {
                return $this->errorResponse('Record not found.', 404);
            }

            return $this->successResponse('Record found', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function update(ApplicationUserRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('users_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $this->userService->update($id, $request->validated());

            return $this->successResponse('Record has been updated successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('users_destroy')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->userService->delete($id);

            return $result
                ? $this->successResponse('Record has been deleted successfully.')
                : $this->errorResponse('Resource not found.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function status(ChangeUserStatusRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('users_active')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->userService->changeStatus(
                (int) $request->validated('id'),
                (int) $request->validated('status'),
            );

            return $result
                ? $this->successResponse('Status has been changed successfully.')
                : $this->errorResponse('Resource not found.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function changePassword(int $id): JsonResponse|\Illuminate\Contracts\View\View
    {
        try {
            if (!Gate::allows('users_change_password')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $user = $this->userService->findByAccountId($id);

            if (!$user) {
                return $this->errorResponse('User not found.', 404);
            }

            return view('admin.users.change_password', compact('user'));
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function savePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('users_change_password')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            try {
                $id = decrypt($request->validated('id'));
            } catch (DecryptException) {
                return $this->errorResponse('Something went wrong, please try again.', 500);
            }

            $result = $this->userService->changePassword((int) $id, $request->validated('password'));

            return $result
                ? $this->successResponse('Password has been changed successfully.')
                : $this->errorResponse('Something went wrong, please try again.', 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function getpatientOptimized(Request $request): JsonResponse
    {
        try {
            $patients = $this->userService->searchPatientsOptimized(
                $request->input('search', ''),
                Auth::user()->account_id,
            );

            return $this->successResponse('Record found.', [
                'patients' => $patients,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    /**
     * @deprecated Use getpatientOptimized() for new implementations
     */
    public function getpatientid(Request $request): JsonResponse
    {
        try {
            $patients = Patients::getPatientidAjax(
                $request->input('search', ''),
                Auth::user()->account_id,
            );

            return $this->successResponse('Record found.', [
                'patients' => $patients,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function getpatientidOrder(Request $request): JsonResponse
    {
        try {
            $patients = Patients::getPatientidAjaxOrder(
                $request->input('search', ''),
                Auth::user()->account_id,
            );

            return $this->successResponse('Record found.', [
                'patients' => $patients,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function phoneSearch(Request $request): JsonResponse
    {
        try {
            $patients = Patients::getPatientPhoneAjax(
                $request->input('search', ''),
                Auth::user()->account_id,
            );

            return $this->successResponse('Record found.', [
                'patients' => $patients,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function getpatientnumber(Request $request): JsonResponse
    {
        try {
            $patientId = (int) $request->input('patient_id');
            $patient = Patients::find($patientId);

            return $this->successResponse('Record found.', [
                'patient' => $patient,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function getUserCities(): JsonResponse
    {
        try {
            $cities = array_values(ACL::getUserCities());

            if (empty($cities)) {
                return $this->errorResponse('City not found', 404);
            }

            // `city` is the single-city auto-select hint kept for backward
            // compatibility with existing JS (locations, appointments, leads,
            // memberships views) — populated only when the user has exactly
            // one city. `cities` is the full list.
            return $this->successResponse('City found', [
                'city' => count($cities) === 1 ? $cities[0] : null,
                'cities' => $cities,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function getUserCenters(): JsonResponse
    {
        try {
            $result = $this->planService->getUserDefaultCenter();

            if ($result['status']) {
                return $this->successResponse('Center found', ['center' => $result['center']]);
            }

            return response()->json([
                'success' => false,
                'status'  => false,
                'message' => 'No default center',
                'data'    => ['center' => null],
                'errors'  => [],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Get User Centers Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to get user centers.', 500);
        }
    }
}
