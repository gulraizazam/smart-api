<?php

namespace App\Http\Controllers\Api;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PermissionDatatableRequest;
use App\Http\Requests\Admin\PermissionRequest;
use App\Models\Permission;
use App\Services\UserManagement\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class PermissionController extends Controller
{
    private int $success;
    private int $error;
    private int $unauthorized;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    public function index()
    {
        if (!Gate::allows('permissions_manage')) {
            return abort(401);
        }

        $filters = Filters::all(Auth::user()->id, 'permissions');

        return view('admin.permissions.index', compact('filters'));
    }

    public function datatable(PermissionDatatableRequest $request): JsonResponse
    {
        try {
            $isSuperAdmin = auth()->user()->hasRole('Super-Admin');

            $params = [
                'search' => $request->getSearchTerm(),
                'parent_id' => $request->getParentId(),
                'orderBy' => $request->getSortField(),
                'order' => $request->getSortDirection(),
                'offset' => $request->getOffset(),
                'limit' => $request->getPerPage(),
            ];

            $result = $this->permissionService->getDatatableData($params, $isSuperAdmin);

            $perPage = $request->getPerPage();
            $total = $result['total'];

            return ApiHelper::apiDataTable([
                'data' => $result['data'],
                'permissions' => $this->permissionService->getUserPermissions(),
                'meta' => [
                    'field' => $params['orderBy'],
                    'page' => $request->getPage(),
                    'pages' => $perPage > 0 ? ceil($total / $perPage) : 1,
                    'perpage' => $perPage,
                    'total' => $total,
                    'sort' => $params['order'],
                ],
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function create(): JsonResponse
    {
        try {
            if (!Gate::allows('permissions_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'permissions' => $this->permissionService->getParentGroups(),
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function store(PermissionRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('permissions_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to create permissions.', false);
            }

            $this->permissionService->create($request->validated());

            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function edit(Permission $permission): JsonResponse
    {
        try {
            if (!Gate::allows('permissions_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to edit permissions.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'permissions' => $this->permissionService->getParentGroups(),
                'permission' => $permission,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function show(Permission $permission): JsonResponse
    {
        try {
            if (!Gate::allows('permissions_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'permission' => $permission->load('parent:id,name'),
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function update(PermissionRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('permissions_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to edit permissions.', false);
            }

            $this->permissionService->update($id, $request->validated());

            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('permissions_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to delete permissions.', false);
            }

            $this->permissionService->delete($id);

            return ApiHelper::apiResponse($this->success, 'Record has been deleted successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function parentGroups(): JsonResponse
    {
        try {
            if (!Gate::allows('permissions_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Parent groups retrieved', true, [
                'parent_groups' => $this->permissionService->getParentGroups(),
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
