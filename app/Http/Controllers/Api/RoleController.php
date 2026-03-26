<?php

namespace App\Http\Controllers\Api;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleDatatableRequest;
use App\Http\Requests\Admin\RoleRequest;
use App\Services\UserManagement\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    private int $success;
    private int $error;
    private int $unauthorized;

    public function __construct(
        private readonly RoleService $roleService,
    ) {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    public function index()
    {
        if (!Gate::allows('roles_manage')) {
            return abort(401);
        }

        $filters = Filters::all(Auth::user()->id, 'roles');

        return view('admin.roles.index', compact('filters'));
    }

    public function datatable(RoleDatatableRequest $request): JsonResponse
    {
        try {
            $params = [
                'name' => $request->getNameFilter(),
                'commission' => $request->getCommissionFilter(),
                'orderBy' => $request->getSortField(),
                'order' => $request->getSortDirection(),
                'offset' => $request->getOffset(),
                'limit' => $request->getPerPage(),
            ];

            $result = $this->roleService->getDatatableData($params);

            $perPage = $request->getPerPage();
            $total = $result['total'];

            return response()->json([
                'data' => $result['data'],
                'permissions' => $this->roleService->getUserPermissions(),
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

    public function create()
    {
        try {
            if (!Gate::allows('roles_create')) {
                return abort(401);
            }

            $mapping = $this->roleService->getAllPermissionsMapping();
            $allowedPermissions = $this->roleService->getAllowedPermissions();

            return ApiHelper::makeResponse([
                'permissions' => $mapping['permissions'],
                'dashboard_permissions' => $mapping['dashboard_permissions'],
                'reports_permissions' => $mapping['reports_permissions'],
                'allowed_permissions' => $allowedPermissions,
            ], 'admin.roles.create');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function store(RoleRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('roles_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to create roles.', false);
            }

            $this->roleService->create($request->validated());

            session()->flash('success', 'Record has been created successfully.');

            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function edit(int $id)
    {
        try {
            if (!Gate::allows('roles_edit')) {
                return abort(401);
            }

            $role = $this->roleService->findOrFail($id);
            $mapping = $this->roleService->getAllPermissionsMapping();
            $allowedPermissions = $this->roleService->getAllowedPermissions($id);

            return ApiHelper::makeResponse([
                'role' => $role,
                'allowed_permissions' => $allowedPermissions,
                'permissions' => $mapping['permissions'],
                'dashboard_permissions' => $mapping['dashboard_permissions'],
                'reports_permissions' => $mapping['reports_permissions'],
            ], 'admin.roles.edit');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function update(RoleRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('roles_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to edit roles.', false);
            }

            $this->roleService->update($id, $request->validated());

            session()->flash('success', 'Record has been updated successfully.');

            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function duplicate(int $id)
    {
        try {
            if (!Gate::allows('roles_duplicate')) {
                return abort(401);
            }

            $role = $this->roleService->findOrFail($id);
            $mapping = $this->roleService->getAllPermissionsMapping();
            $allowedPermissions = $this->roleService->getAllowedPermissions($id);

            return view('admin.roles.duplicate', [
                'role' => $role,
                'allowed_permissions' => $allowedPermissions,
                'permissions' => $mapping['permissions'],
                'dashboard_permissions' => $mapping['dashboard_permissions'],
                'reports_permissions' => $mapping['reports_permissions'],
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function storeDuplicate(RoleRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('roles_duplicate')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to duplicate roles.', false);
            }

            $this->roleService->duplicate($request->validated());

            session()->flash('success', 'Role has been duplicated successfully.');

            return ApiHelper::apiResponse($this->success, 'Role has been duplicated successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('roles_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to delete roles.', false);
            }

            $deleted = $this->roleService->delete($id);

            if (!$deleted) {
                return ApiHelper::apiResponse($this->success, 'Child records exist, unable to delete resource.', false);
            }

            session()->flash('success', 'Record has been deleted successfully.');

            return ApiHelper::apiResponse($this->success, 'Record has been deleted successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
