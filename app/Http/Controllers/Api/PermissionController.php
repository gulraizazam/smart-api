<?php

namespace App\Http\Controllers\Api;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PermissionDatatableRequest;
use App\Http\Requests\Admin\PermissionRequest;
use App\Services\UserManagement\PermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class PermissionController extends Controller
{
    private PermissionService $permissionService;
    private int $success;
    private int $error;
    private int $unauthorized;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Display permissions listing page
     */
    public function index()
    {
        if (!Gate::allows('permissions_manage')) {
            return abort(401);
        }

        $filters = Filters::all(Auth::user()->id, 'permissions');

        return view('admin.permissions.index', compact('filters'));
    }

    /**
     * Get paginated permissions for datatable
     */
    public function datatable(PermissionDatatableRequest $request)
    {
        try {
            // Handle bulk delete if requested
            $deleteIds = $request->getDeleteIds();
            if (!empty($deleteIds)) {
                if (!Gate::allows('permissions_destroy')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to delete permissions.', false);
                }
                
                $this->permissionService->bulkDelete($deleteIds);
                
                return response()->json([
                    'status' => true,
                    'message' => 'Records have been deleted successfully!',
                ]);
            }

            $user = auth()->user();
            $isSuperAdmin = $user->hasRole('Super-Admin');

            $params = [
                'search' => $request->getSearchTerm(),
                'parent_id' => $request->getParentId(),
                'orderBy' => $request->getSortField(),
                'order' => $request->getSortDirection(),
                'offset' => $request->getOffset(),
                'limit' => $request->getPerPage(),
            ];

            $result = $this->permissionService->getDatatableData($params, $isSuperAdmin);

            $page = $request->getPage();
            $perPage = $request->getPerPage();
            $total = $result['total'];

            return ApiHelper::apiDataTable([
                'data' => $result['data'],
                'permissions' => $this->permissionService->getUserPermissions(),
                'meta' => [
                    'field' => $params['orderBy'],
                    'page' => $page,
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

    /**
     * Get data for creating a new permission
     */
    public function create()
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

    /**
     * Store a newly created permission
     */
    public function store(PermissionRequest $request)
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

    /**
     * Get permission data for editing
     */
    public function edit(int $id)
    {
        try {
            if (!Gate::allows('permissions_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to edit permissions.', false);
            }

            $permission = $this->permissionService->findOrFail($id);

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'permissions' => $this->permissionService->getParentGroups(),
                'permission' => $permission,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Show a specific permission
     */
    public function show(int $id)
    {
        try {
            if (!Gate::allows('permissions_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $permission = $this->permissionService->findOrFail($id);

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'permission' => $permission->load('parent:id,name'),
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update the specified permission
     */
    public function update(PermissionRequest $request, int $id)
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

    /**
     * Remove the specified permission
     */
    public function destroy(int $id)
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

    /**
     * Get all parent groups (for dropdowns)
     */
    public function parentGroups()
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
