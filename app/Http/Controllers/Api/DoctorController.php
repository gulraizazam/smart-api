<?php

namespace App\Http\Controllers\Api;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangePasswordRequest;
use App\Http\Requests\Admin\DoctorDatatableRequest;
use App\Http\Requests\Admin\DoctorRequest;
use App\Services\UserManagement\DoctorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DoctorController extends Controller
{
    private int $success;
    private int $error;
    private int $unauthorized;

    public function __construct(
        private readonly DoctorService $doctorService,
    ) {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    public function index()
    {
        if (!Gate::allows('doctors_manage')) {
            return abort(401);
        }

        return view('admin.doctors.index');
    }

    public function datatable(DoctorDatatableRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $deleteIds = $request->getDeleteIds();
            if (!empty($deleteIds)) {
                if (!Gate::allows('doctors_destroy')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to delete doctors.', false);
                }

                $this->doctorService->bulkDelete($deleteIds);

                return response()->json([
                    'status' => true,
                    'message' => 'Records have been deleted successfully!',
                ]);
            }

            $params = array_merge($request->getFilters(), [
                'orderBy' => $request->getSortField(),
                'order' => $request->getSortDirection(),
                'offset' => $request->getOffset(),
                'limit' => $request->getPerPage(),
            ]);

            $result = $this->doctorService->getDatatableData($params);

            $perPage = $request->getPerPage();
            $total = $result['total'];

            return response()->json([
                'data' => $result['data'],
                'permissions' => $this->doctorService->getUserPermissions(),
                'filter_values' => $this->doctorService->getFilterValues(),
                'active_filters' => $this->doctorService->getActiveFilters(),
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
            if (!Gate::allows('doctors_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Data found', true, $this->doctorService->getCreateData());
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function store(DoctorRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $user = $this->doctorService->create($request->validated());

            return $user
                ? ApiHelper::apiResponse($this->success, 'Record has been created successfully.')
                : ApiHelper::apiResponse($this->error, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $data = $this->doctorService->getEditData($id);

            return $data
                ? ApiHelper::apiResponse($this->success, 'Data found', true, $data)
                : ApiHelper::apiResponse($this->success, 'No Record Found!', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function update(DoctorRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $user = $this->doctorService->update($id, $request->all());

            return $user
                ? ApiHelper::apiResponse($this->success, 'Record has been updated successfully.')
                : ApiHelper::apiResponse($this->error, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function destroy(int $id): JsonResponse
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

    public function status(Request $request): JsonResponse
    {
        try {
            $permission = match ((int) $request->status) {
                0 => 'doctors_inactive',
                1 => 'doctors_active',
                default => null,
            };

            if ($permission && !Gate::allows($permission)) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $result = $this->doctorService->changeStatus($request->id, $request->status);

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
            if (!Gate::allows('doctors_change_password')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $user = $this->doctorService->getPasswordChangeData($id);

            return $user
                ? ApiHelper::apiResponse($this->success, 'Record found', true, $user)
                : ApiHelper::apiResponse($this->success, 'No Record Found!', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function savePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_change_password')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $result = $this->doctorService->changePassword($request->validated('id'), $request->validated('password'));

            return $result
                ? ApiHelper::apiResponse($this->success, 'Password has been changed successfully.')
                : ApiHelper::apiResponse($this->success, 'Something went wrong, please try again.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function displayLocation(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_allocate')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Service Allocated', true, $this->doctorService->getLocationAllocationData($id));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getServices(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_allocate')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Success', true, $this->doctorService->getServicesForLocation($request));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function saveServices(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_allocate')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $result = $this->doctorService->saveServiceAllocation($request->doctor_id, $request->id);

            return $result['status']
                ? ApiHelper::apiResponse($this->success, $result['message'], true, $result['data'])
                : ApiHelper::apiResponse($this->success, $result['message'], false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function deleteServices(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_allocate')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $result = $this->doctorService->deleteServiceAllocation($request->id);

            return $result
                ? ApiHelper::apiResponse($this->success, 'Location/Service has been unassigned to doctor!', true, ['id' => $request->id])
                : ApiHelper::apiResponse($this->success, 'Service not found.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
