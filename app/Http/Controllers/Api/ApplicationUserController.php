<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\HelperModule\ApiHelper;
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
    private readonly int $success;
    private readonly int $error;
    private readonly int $unauthorized;

    public function __construct(
        private readonly ApplicationUserService $userService,
    ) {
        $this->success = (int) config('constants.api_status.success');
        $this->error = (int) config('constants.api_status.error');
        $this->unauthorized = (int) config('constants.api_status.unauthorized');
    }

    public function index()
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
            return ApiHelper::apiException($e);
        }
    }

    public function create(): JsonResponse
    {
        try {
            if (!Gate::allows('users_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, $this->userService->getCreateData());
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function store(ApplicationUserRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('users_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $this->userService->create($request->validated());

            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('users_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $data = $this->userService->getEditData($id);

            if (!$data) {
                return ApiHelper::apiResponse($this->success, 'Record not found.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function update(ApplicationUserRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('users_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $this->userService->update($id, $request->validated());

            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('users_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $result = $this->userService->delete($id);

            return $result
                ? ApiHelper::apiResponse($this->success, 'Record has been deleted successfully.')
                : ApiHelper::apiResponse($this->success, 'Resource not found.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function status(ChangeUserStatusRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('users_active')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $result = $this->userService->changeStatus(
                (int) $request->validated('id'),
                (int) $request->validated('status'),
            );

            return $result
                ? ApiHelper::apiResponse($this->success, 'Status has been changed successfully.')
                : ApiHelper::apiResponse($this->success, 'Resource not found.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function changePassword(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('users_change_password')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $user = $this->userService->findByAccountId($id);

            if (!$user) {
                return ApiHelper::apiResponse($this->success, 'User not found.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'user' => [
                    'id' => encrypt($user->id),
                    'name' => $user->name,
                ],
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function savePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('users_change_password')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            try {
                $id = decrypt($request->validated('id'));
            } catch (DecryptException) {
                return ApiHelper::apiResponse($this->error, 'Something went wrong, please try again.', false);
            }

            $result = $this->userService->changePassword((int) $id, $request->validated('password'));

            return $result
                ? ApiHelper::apiResponse($this->success, 'Password has been changed successfully.')
                : ApiHelper::apiResponse($this->error, 'Something went wrong, please try again.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getpatientOptimized(Request $request): JsonResponse
    {
        try {
            $patients = $this->userService->searchPatientsOptimized(
                $request->input('search', ''),
                Auth::user()->account_id,
            );

            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'patients' => $patients,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
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

            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'patients' => $patients,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getpatientidOrder(Request $request): JsonResponse
    {
        try {
            $patients = Patients::getPatientidAjaxOrder(
                $request->input('search', ''),
                Auth::user()->account_id,
            );

            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'patients' => $patients,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function phoneSearch(Request $request): JsonResponse
    {
        try {
            $patients = Patients::getPatientPhoneAjax(
                $request->input('search', ''),
                Auth::user()->account_id,
            );

            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'patients' => $patients,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getpatientnumber(Request $request): JsonResponse
    {
        try {
            $patientId = (int) $request->input('patient_id');
            $patient = Patients::find($patientId);

            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'patient' => $patient,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getUserCities(): JsonResponse
    {
        try {
            $cities = ACL::getUserCities();

            if (count($cities) === 1) {
                return ApiHelper::apiResponse($this->success, 'City found', true, [
                    'city' => $cities[0],
                ]);
            }

            return ApiHelper::apiResponse($this->success, 'City not found', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getUserCenters(): JsonResponse
    {
        try {
            $planService = app(PlanService::class);
            $result = $planService->getUserDefaultCenter();

            return $result['status']
                ? ApiHelper::apiResponse($this->success, 'Center found', true, ['center' => $result['center']])
                : ApiHelper::apiResponse($this->success, 'Center not found', false);
        } catch (\Exception $e) {
            Log::error('Get User Centers Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, 'Failed to get user centers.', false);
        }
    }
}
