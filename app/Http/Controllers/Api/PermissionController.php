<?php

declare(strict_types=1);
namespace App\Http\Controllers\Api;

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
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    public function index(): \Illuminate\View\View
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

            return response()->json([
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
            return $this->handleException($e, 'PermissionController');
        }
    }

    public function create(): JsonResponse
    {
        try {
            if (!Gate::allows('permissions_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            return $this->successResponse('Record found', [
                'permissions' => $this->permissionService->getParentGroups(),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PermissionController');
        }
    }

    public function store(PermissionRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('permissions_create')) {
                return $this->errorResponse('You are not authorized to create permissions.', 401);
            }

            $this->permissionService->create($request->validated());

            return $this->successResponse('Record has been created successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'PermissionController');
        }
    }

    public function edit(Permission $permission): JsonResponse
    {
        try {
            if (!Gate::allows('permissions_edit')) {
                return $this->errorResponse('You are not authorized to edit permissions.', 401);
            }

            return $this->successResponse('Record found', [
                'permissions' => $this->permissionService->getParentGroups(),
                'permission' => $permission,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PermissionController');
        }
    }

    public function show(Permission $permission): JsonResponse
    {
        try {
            if (!Gate::allows('permissions_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            return $this->successResponse('Record found', [
                'permission' => $permission->load('parent:id,name'),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PermissionController');
        }
    }

    public function update(PermissionRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('permissions_edit')) {
                return $this->errorResponse('You are not authorized to edit permissions.', 401);
            }

            $this->permissionService->update($id, $request->validated());

            return $this->successResponse('Record has been updated successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'PermissionController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('permissions_destroy')) {
                return $this->errorResponse('You are not authorized to delete permissions.', 401);
            }

            $this->permissionService->delete($id);

            return $this->successResponse('Record has been deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'PermissionController');
        }
    }

    /**
     * Two-level catalogue for the SPA's tree view. Returns parent groups
     * with their children nested. Cached server-side; bust on every CRUD
     * via PermissionService::clearCache.
     */
    public function tree(): JsonResponse
    {
        try {
            if (! Gate::allows('permissions_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $isSuperAdmin = Auth::user()?->hasRole('Super-Admin') ?? false;

            return $this->successResponse('Permissions tree retrieved', [
                'tree' => $this->permissionService->getTree($isSuperAdmin),
                'permissions' => $this->permissionService->getUserPermissions(),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PermissionController');
        }
    }

    public function parentGroups(): JsonResponse
    {
        try {
            if (!Gate::allows('permissions_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            return $this->successResponse('Parent groups retrieved', [
                'parent_groups' => $this->permissionService->getParentGroups(),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PermissionController');
        }
    }
}
