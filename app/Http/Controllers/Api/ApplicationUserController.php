<?php

namespace App\Http\Controllers\Api;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApplicationUserDatatableRequest;
use App\Http\Requests\Admin\ApplicationUserRequest;
use App\Http\Requests\Admin\ChangePasswordRequest;
use App\Services\UserManagement\ApplicationUserService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ApplicationUserController extends Controller
{
    private int $success;
    private int $error;
    private int $unauthorized;

    public function __construct(
        private readonly ApplicationUserService $userService,
    ) {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
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
                'data' => $result['data'],
                'permissions' => $this->userService->getUserPermissions(),
                'filter_values' => $this->userService->getFilterValues(),
                'active_filters' => $this->userService->getActiveFilters(),
                'meta' => [
                    'field' => $request->getSortField(),
                    'page' => $request->getPage(),
                    'pages' => $perPage > 0 ? ceil($total / $perPage) : 1,
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

            session()->flash('success', 'Record has been created successfully.');

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

            return ApiHelper::apiResponse($this->success, 'Record found', true, $this->userService->getEditData($id));
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

            session()->flash('success', 'Record has been updated successfully.');

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

            $this->userService->delete($id);

            session()->flash('success', 'Record has been deleted successfully.');

            return ApiHelper::apiResponse($this->success, 'Record has been deleted successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function status(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('users_active')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $result = $this->userService->changeStatus($request->id, $request->status);

            return $result
                ? ApiHelper::apiResponse($this->success, 'Status has been changed successfully.')
                : ApiHelper::apiResponse($this->success, 'Resource not found.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function changePassword(int $id)
    {
        if (!Gate::allows('users_change_password')) {
            return abort(401);
        }

        $user = $this->userService->findByAccountId($id);

        if (!$user) {
            return view('error');
        }

        return view('admin.users.change_password', compact('user'));
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
                return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again.', false);
            }

            $result = $this->userService->changePassword($id, $request->validated('password'));

            return $result
                ? ApiHelper::apiResponse($this->success, 'Password has been changed successfully.')
                : ApiHelper::apiResponse($this->success, 'Something went wrong, please try again.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getpatientOptimized(Request $request): JsonResponse
    {
        $patients = \App\Models\Patients::getPatientSearchOptimized($request->search, Auth::user()->account_id);

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'patients' => $patients,
        ]);
    }

    /**
     * @deprecated Use getpatientOptimized() for new implementations
     */
    public function getpatientid(Request $request): JsonResponse
    {
        $patients = \App\Models\Patients::getPatientidAjax($request->search, Auth::user()->account_id);

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'patients' => $patients,
        ]);
    }

    public function getpatientidOrder(Request $request): JsonResponse
    {
        $patients = \App\Models\Patients::getPatientidAjaxOrder($request->search, Auth::user()->account_id);

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'patients' => $patients,
        ]);
    }

    public function phoneSearch(Request $request): JsonResponse
    {
        $patients = \App\Models\Patients::getPatientPhoneAjax($request->search, Auth::user()->account_id);

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'patients' => $patients,
        ]);
    }

    public function getpatientnumber(Request $request): JsonResponse
    {
        $patient = \App\Models\Patients::find($request->patient_id);

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'patient' => $patient,
        ]);
    }

    public function getUserCities(): JsonResponse
    {
        $cities = \App\Helpers\ACL::getUserCities();

        if (count($cities) === 1) {
            return ApiHelper::apiResponse($this->success, 'City found', true, [
                'city' => $cities[0],
            ]);
        }

        return ApiHelper::apiResponse($this->success, 'City not found', false);
    }

    public function getUserCenters(): JsonResponse
    {
        try {
            $planService = app(\App\Services\Plan\PlanService::class);
            $result = $planService->getUserDefaultCenter();

            return $result['status']
                ? ApiHelper::apiResponse($this->success, 'Center found', true, ['center' => $result['center']])
                : ApiHelper::apiResponse($this->success, 'Center not found', false);
        } catch (\Exception $e) {
            \Log::error('Get User Centers Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, 'Failed to get user centers.', false);
        }
    }
}
