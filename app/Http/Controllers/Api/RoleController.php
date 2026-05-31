<?php

declare(strict_types=1);
namespace App\Http\Controllers\Api;

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
    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    public function index(): \Illuminate\View\View
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
            return $this->handleException($e, 'RoleController');
        }
    }

    public function create(): \Illuminate\Http\JsonResponse
    {
        try {
            if (!Gate::allows('roles_create')) {
                return abort(401);
            }

            $mapping = $this->roleService->getAllPermissionsMapping();
            $allowedPermissions = $this->roleService->getAllowedPermissions();

            return $this->successResponse('Record found.', [
                'categories' => $mapping['categories'],
                'permissions' => $mapping['permissions'],
                'dashboard_permissions' => $mapping['dashboard_permissions'],
                'reports_permissions' => $mapping['reports_permissions'],
                'allowed_permissions' => $allowedPermissions,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'RoleController');
        }
    }

    public function store(RoleRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('roles_create')) {
                return $this->errorResponse('You are not authorized to create roles.', 403);
            }

            $this->roleService->create($request->validated());

            session()->flash('success', 'Record has been created successfully.');

            return $this->successResponse('Record has been created successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'RoleController');
        }
    }

    public function edit(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            if (!Gate::allows('roles_edit')) {
                return abort(401);
            }

            $role = $this->roleService->findOrFail($id);
            $mapping = $this->roleService->getAllPermissionsMapping();
            $allowedPermissions = $this->roleService->getAllowedPermissions($id);

            return $this->successResponse('Record found.', [
                'role' => $role,
                'allowed_permissions' => $allowedPermissions,
                'categories' => $mapping['categories'],
                'permissions' => $mapping['permissions'],
                'dashboard_permissions' => $mapping['dashboard_permissions'],
                'reports_permissions' => $mapping['reports_permissions'],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'RoleController');
        }
    }

    public function update(RoleRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('roles_edit')) {
                return $this->errorResponse('You are not authorized to edit roles.', 403);
            }

            $this->roleService->update($id, $request->validated());

            session()->flash('success', 'Record has been updated successfully.');

            return $this->successResponse('Record has been updated successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'RoleController');
        }
    }

    public function duplicate(int $id): \Illuminate\View\View
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
                'categories' => $mapping['categories'],
                'permissions' => $mapping['permissions'],
                'dashboard_permissions' => $mapping['dashboard_permissions'],
                'reports_permissions' => $mapping['reports_permissions'],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'RoleController');
        }
    }

    public function storeDuplicate(RoleRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('roles_duplicate')) {
                return $this->errorResponse('You are not authorized to duplicate roles.', 403);
            }

            $this->roleService->duplicate($request->validated());

            session()->flash('success', 'Role has been duplicated successfully.');

            return $this->successResponse('Role has been duplicated successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'RoleController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('roles_destroy')) {
                return $this->errorResponse('You are not authorized to delete roles.', 403);
            }

            $deleted = $this->roleService->delete($id);

            if (!$deleted) {
                return $this->errorResponse('Child records exist, unable to delete resource.', 404);
            }

            session()->flash('success', 'Record has been deleted successfully.');

            return $this->successResponse('Record has been deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'RoleController');
        }
    }
}
