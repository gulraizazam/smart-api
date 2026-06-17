<?php

declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DoctorDatatableRequest;
use App\Http\Requests\Admin\DoctorRequest;
use App\Services\UserManagement\DoctorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class DoctorController extends Controller
{
    public function __construct(
        private readonly DoctorService $doctorService,
    ) {}

    public function index(): \Illuminate\View\View
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
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $deleteIds = $request->getDeleteIds();
            if (!empty($deleteIds)) {
                if (!Gate::allows('doctors_destroy')) {
                    return $this->errorResponse('You are not authorized to delete doctors.', 403);
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
            return $this->handleException($e, 'DoctorController');
        }
    }

    public function create(): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_create')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            return $this->successResponse('Data found', $this->doctorService->getCreateData());
        } catch (\Exception $e) {
            return $this->handleException($e, 'DoctorController');
        }
    }

    public function store(DoctorRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_create')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $user = $this->doctorService->create($request->validated());

            return $user
                ? $this->successResponse('Record has been created successfully.')
                : $this->errorResponse('Something went wrong, please try again later.', 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'DoctorController');
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $data = $this->doctorService->getEditData($id);

            return $data
                ? $this->successResponse('Data found', $data)
                : $this->errorResponse('No Record Found!', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'DoctorController');
        }
    }

    public function update(DoctorRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $user = $this->doctorService->update($id, $request->all());

            return $user
                ? $this->successResponse('Record has been updated successfully.')
                : $this->errorResponse('Something went wrong, please try again later.', 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'DoctorController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_destroy')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $result = $this->doctorService->delete($id);

            return $result['status'] ? $this->successResponse($result['message']) : $this->errorResponse($result['message'], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'DoctorController');
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
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $result = $this->doctorService->changeStatus((int) $request->id, (int) $request->status);

            return $result
                ? $this->successResponse('Status has been changed successfully.')
                : $this->errorResponse('Resource not found.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'DoctorController');
        }
    }

    /**
     * Change a doctor's password — path-bound JSON, mirrors
     * ApplicationUserController::updatePassword. Tenant scoping is server-side
     * via getPasswordChangeData (account_id-scoped): a cross-account id returns
     * null -> 404 (no enumeration), and that scoped check GATES the password
     * write — closing the cross-tenant IDOR the removed GET-then-PATCH
     * `savePassword` carried (it called the unscoped DoctorService::changePassword
     * with the id straight from the request body).
     */
    public function updatePassword(Request $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_change_password')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            if (!$this->doctorService->getPasswordChangeData($id)) {
                return $this->errorResponse('Doctor not found.', 404);
            }

            $validator = Validator::make($request->all(), [
                'password' => [
                    'required',
                    'string',
                    Password::min(8)->mixedCase()->numbers()->symbols(),
                    'confirmed',
                ],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    $validator->errors()->first(),
                    422,
                    $validator->errors()->all(),
                );
            }

            $result = $this->doctorService->changePassword($id, (string) $request->input('password'));

            return $result
                ? $this->successResponse('Password has been changed successfully.')
                : $this->errorResponse('Something went wrong, please try again.', 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'DoctorController');
        }
    }

    public function displayLocation(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_allocate')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            return $this->successResponse('Service Allocated', $this->doctorService->getLocationAllocationData($id));
        } catch (\Exception $e) {
            return $this->handleException($e, 'DoctorController');
        }
    }

    public function getServices(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_allocate')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            return $this->successResponse('Success', $this->doctorService->getServicesForLocation($request));
        } catch (\Exception $e) {
            return $this->handleException($e, 'DoctorController');
        }
    }

    public function saveServices(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_allocate')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $result = $this->doctorService->saveServiceAllocation((int) $request->doctor_id, $request->id);

            return $result['status']
                ? $this->successResponse($result['message'], $result['data'])
                : $this->errorResponse($result['message'], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'DoctorController');
        }
    }

    public function deleteServices(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('doctors_allocate')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $result = $this->doctorService->deleteServiceAllocation((int) $request->id);

            return $result
                ? $this->successResponse('Location/Service has been unassigned to doctor!', ['id' => $request->id])
                : $this->errorResponse('Service not found.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'DoctorController');
        }
    }
}
