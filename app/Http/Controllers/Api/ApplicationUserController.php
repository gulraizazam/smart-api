<?php

namespace App\Http\Controllers\Api;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApplicationUserDatatableRequest;
use App\Http\Requests\Admin\ApplicationUserRequest;
use App\Services\UserManagement\ApplicationUserService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class ApplicationUserController extends Controller
{
    private ApplicationUserService $userService;
    private int $success;
    private int $error;
    private int $unauthorized;

    public function __construct(ApplicationUserService $userService)
    {
        $this->userService = $userService;
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Display users listing page
     */
    public function index()
    {
        if (!Gate::allows('users_manage')) {
            return abort(401);
        }

        return view('admin.users.index');
    }

    /**
     * Get paginated users for datatable
     */
    public function datatable(ApplicationUserDatatableRequest $request)
    {
        try {
            // Handle bulk delete if requested
            $deleteIds = $request->getDeleteIds();
            if (!empty($deleteIds)) {
                if (!Gate::allows('users_destroy')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to delete users.', false);
                }
                
                $deleted = $this->userService->bulkDelete($deleteIds);
                
                return response()->json([
                    'status' => true,
                    'message' => 'Records have been deleted successfully!',
                ]);
            }

            $filters = $request->getFilters();
            $params = array_merge($filters, [
                'orderBy' => $request->getSortField(),
                'order' => $request->getSortDirection(),
                'offset' => $request->getOffset(),
                'limit' => $request->getPerPage(),
            ]);

            $result = $this->userService->getDatatableData($params);

            $page = $request->getPage();
            $perPage = $request->getPerPage();
            $total = $result['total'];

            return response()->json([
                'data' => $result['data'],
                'permissions' => $this->userService->getUserPermissions(),
                'filter_values' => $this->userService->getFilterValues(),
                'active_filters' => $this->userService->getActiveFilters(),
                'meta' => [
                    'field' => $request->getSortField(),
                    'page' => $page,
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

    /**
     * Get data for creating a new user
     */
    public function create()
    {
        try {
            if (!Gate::allows('users_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $data = $this->userService->getCreateData();

            return ApiHelper::apiResponse($this->success, 'Record found', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Store a newly created user
     */
    public function store(ApplicationUserRequest $request)
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

    /**
     * Get user data for editing
     */
    public function edit(int $id)
    {
        try {
            if (!Gate::allows('users_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $data = $this->userService->getEditData($id);

            return ApiHelper::apiResponse($this->success, 'Record found', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update the specified user
     */
    public function update(ApplicationUserRequest $request, int $id)
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

    /**
     * Remove the specified user
     */
    public function destroy(int $id)
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

    /**
     * Change user status (active/inactive)
     */
    public function status(Request $request)
    {
        try {
            if (!Gate::allows('users_active')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $result = $this->userService->changeStatus($request->id, $request->status);

            if ($result) {
                return ApiHelper::apiResponse($this->success, 'Status has been changed successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Show change password form
     */
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

    /**
     * Save new password
     */
    public function savePassword(Request $request)
    {
        try {
            if (!Gate::allows('users_change_password')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $validator = Validator::make($request->all(), [
                'password' => 'required|confirmed|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,}$/',
            ], [
                'password.required' => 'Password field is required',
                'password.min' => 'Password must be at least 8 characters',
                'password.regex' => 'Password must be a combination of numbers, upper, lower, and special characters',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 0,
                    'message' => $validator->messages()->all(),
                ]);
            }

            try {
                $id = decrypt($request->get('id'));
            } catch (DecryptException $e) {
                return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again.', false);
            }

            $result = $this->userService->changePassword($id, $request->get('password'));

            if ($result) {
                return ApiHelper::apiResponse($this->success, 'Password has been changed successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * OPTIMIZED Patient Search - 50-100X faster
     * Use this for all new implementations
     */
    public function getpatientOptimized(Request $request)
    {
        $patients = \App\Models\Patients::getPatientSearchOptimized($request->search, Auth::user()->account_id);

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'patients' => $patients,
        ]);
    }

    /**
     * LEGACY - Search patients by ID (OLD SLOW METHOD)
     * Use getpatientOptimized() for new implementations
     */
    public function getpatientid(Request $request)
    {
        $patients = \App\Models\Patients::getPatientidAjax($request->search, Auth::user()->account_id);

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'patients' => $patients,
        ]);
    }

    /**
     * Search patients by ID for orders
     */
    public function getpatientidOrder(Request $request)
    {
        $patients = \App\Models\Patients::getPatientidAjaxOrder($request->search, Auth::user()->account_id);

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'patients' => $patients,
        ]);
    }

    /**
     * Search patients by phone
     */
    public function phoneSearch(Request $request)
    {
        $patients = \App\Models\Patients::getPatientPhoneAjax($request->search, Auth::user()->account_id);

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'patients' => $patients,
        ]);
    }

    /**
     * Get patient number/details
     */
    public function getpatientnumber(Request $request)
    {
        $patient = \App\Models\Patients::find($request->patient_id);

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'patient' => $patient,
        ]);
    }

    /**
     * Get user cities
     */
    public function getUserCities()
    {
        $cities = \App\Helpers\ACL::getUserCities();
        if (count($cities) == 1) {
            return ApiHelper::apiResponse($this->success, 'City found', true, [
                'city' => $cities[0],
            ]);
        }

        return ApiHelper::apiResponse($this->success, 'City not found', false);
    }

    /**
     * Get user centers
     */
   public function getUserCenters()
    {
        try {
            $planService = app(\App\Services\Plan\PlanService::class);
            $result = $planService->getUserDefaultCenter();

            if ($result['status']) {
                return ApiHelper::apiResponse($this->success, 'Center found', true, [
                    'center' => $result['center'],
                ]);
            }

            return ApiHelper::apiResponse($this->success, 'Center not found', false);
        } catch (\Exception $e) {
            \Log::error('Get User Centers Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, 'Failed to get user centers.', false);
        }
    }
}
