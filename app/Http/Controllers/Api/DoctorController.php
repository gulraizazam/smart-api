<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DoctorRequest;
use App\Http\Requests\Admin\DoctorDatatableRequest;
use App\Services\UserManagement\DoctorService;
use App\HelperModule\ApiHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Encryption\DecryptException;

class DoctorController extends Controller
{
    protected $doctorService;
    protected $success;
    protected $error;
    protected $unauthorized;

    public function __construct(DoctorService $doctorService)
    {
        $this->doctorService = $doctorService;
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Display the doctors listing page
     */
    public function index()
    {
        if (!Gate::allows('doctors_manage')) {
            return abort(401);
        }

        return view('admin.doctors.index');
    }

    /**
     * Get paginated doctors for datatable
     */
    public function datatable(DoctorDatatableRequest $request)
    {
        try {
            if (!Gate::allows('doctors_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            // Handle bulk delete if requested
            $deleteIds = $request->getDeleteIds();
            if (!empty($deleteIds)) {
                if (!Gate::allows('doctors_destroy')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to delete doctors.', false);
                }
                
                $deleted = $this->doctorService->bulkDelete($deleteIds);
                
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

            $result = $this->doctorService->getDatatableData($params);

            $page = $request->getPage();
            $perPage = $request->getPerPage();
            $total = $result['total'];

            return response()->json([
                'data' => $result['data'],
                'permissions' => $this->doctorService->getUserPermissions(),
                'filter_values' => $this->doctorService->getFilterValues(),
                'active_filters' => $this->doctorService->getActiveFilters(),
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
     * Get data for creating a new doctor
     */
    public function create()
    {
        try {
            if (!Gate::allows('doctors_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $data = $this->doctorService->getCreateData();

            return ApiHelper::apiResponse($this->success, 'Data found', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Store a new doctor
     */
    public function store(DoctorRequest $request)
    {
        try {
            if (!Gate::allows('doctors_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $user = $this->doctorService->create($request->validated());

            if ($user) {
                return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
            }

            return ApiHelper::apiResponse($this->error, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get data for editing a doctor
     */
    public function edit($id)
    {
        try {
            if (!Gate::allows('doctors_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $data = $this->doctorService->getEditData($id);

            if (!$data) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }

            return ApiHelper::apiResponse($this->success, 'Data found', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update a doctor
     */
    public function update(DoctorRequest $request, $id)
    {
        try {
            if (!Gate::allows('doctors_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $user = $this->doctorService->update($id, $request->all());

            if ($user) {
                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
            }

            return ApiHelper::apiResponse($this->error, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Delete a doctor
     */
    public function destroy($id)
    {
        try {
            if (!Gate::allows('doctors_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $result = $this->doctorService->delete($id);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Change doctor status
     */
    public function status(Request $request)
    {
        try {
            if ($request->status == 0) {
                if (!Gate::allows('doctors_inactive')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
                }
            } elseif ($request->status == 1) {
                if (!Gate::allows('doctors_active')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
                }
            }

            $result = $this->doctorService->changeStatus($request->id, $request->status);

            if ($result) {
                return ApiHelper::apiResponse($this->success, 'Status has been changed successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get data for changing password
     */
    public function changePassword($id)
    {
        try {
            if (!Gate::allows('doctors_change_password')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $user = $this->doctorService->getPasswordChangeData($id);

            if (!$user) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, $user);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Save new password
     */
    public function savePassword(Request $request)
    {
        try {
            if (!Gate::allows('doctors_change_password')) {
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

            $result = $this->doctorService->changePassword($request->get('id'), $request->get('password'));

            if ($result) {
                return ApiHelper::apiResponse($this->success, 'Password has been changed successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Display location allocation page
     */
    public function displayLocation($id)
    {
        try {
            if (!Gate::allows('doctors_allocate')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $data = $this->doctorService->getLocationAllocationData($id);

            return ApiHelper::apiResponse($this->success, 'Service Allocated', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get services for a location
     */
    public function getServices(Request $request)
    {
        try {
            if (!Gate::allows('doctors_allocate')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $data = $this->doctorService->getServicesForLocation($request);

            return ApiHelper::apiResponse($this->success, 'Success', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Save service allocation
     */
    public function saveServices(Request $request)
    {
        try {
            if (!Gate::allows('doctors_allocate')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $result = $this->doctorService->saveServiceAllocation($request->doctor_id, $request->id);

            if ($result['status']) {
                return ApiHelper::apiResponse($this->success, $result['message'], true, $result['data']);
            }

            return ApiHelper::apiResponse($this->success, $result['message'], false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Delete service allocation
     */
    public function deleteServices(Request $request)
    {
        try {
            if (!Gate::allows('doctors_allocate')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $result = $this->doctorService->deleteServiceAllocation($request->id);

            if ($result) {
                return ApiHelper::apiResponse($this->success, 'Location/Service has been unassigned to doctor!', true, ['id' => $request->id]);
            }

            return ApiHelper::apiResponse($this->success, 'Service not found.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
