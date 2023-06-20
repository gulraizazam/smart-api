<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Validator;

class PermissionsController extends Controller
{
    public $success;

    public $error;

    public $unauthorized;

    public function __construct()
    {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Display a listing of Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! Gate::allows('permissions_manage')) {
            return abort(401);
        }

        $filters = Filters::all(Auth::User()->id, 'permissions');

        return view('admin.permissions.index', compact('filters'));
    }

    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request)
    {
        $records = [];

        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, 'permissions');

        if (count($filters) > 0 && hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $Permissions = Permission::whereIn('id', $ids);
            if ($Permissions->exists()) {
                $Permissions->delete();
            }
            $records['status'] = true;
            $records['message'] = 'Records has been deleted successfully!';
        }

        [$orderBy, $order] = getSortBy($request);
        $user = AUth::user();
        if ($user->hasRole('Super-Admin')) {
            $query = Permission::query()->with(['parent' => function ($query) {
                $query->select('id', 'name');
            }]);
        } else {
            $query = Permission::query()->with(['parent' => function ($query) {
                $query->select('id', 'name');
            }])->where('name', '!=', 'view_inactive_records');
        }

        $iTotalRecords = Permission::count();

        if (count($filters) > 0 && hasFilter($filters, 'search')) {
            Filters::put(Auth::user()->id, 'permissions', 'search', $filters['search']);
            $search = $filters['search'];
            $query = $query->where('name', 'LIKE', "%{$search}%")
                ->orWhere('title', 'LIKE', "%{$search}%")
                ->orWhere('parent_id', 'LIKE', "%{$search}%");
            $iTotalRecords = $query->count();
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'permissions', 'search');
            } else {
                if ($search = Filters::get(Auth::user()->id, 'permissions', 'search')) {
                    $query = $query->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('title', 'LIKE', "%{$search}%")
                        ->orWhere('parent_id', 'LIKE', "%{$search}%");
                    $iTotalRecords = $query->count();
                }
            }
        }

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        if ($orderBy === 'parent.name') {
            $orderBy = 'name';
        }
        $Permissions = $query->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();

        if ($Permissions) {

            $records['data'] = $Permissions;

            $records['permissions'] = [
                'edit' => Gate::allows('permissions_edit'),
                'delete' => Gate::allows('permissions_destroy'),
            ];

            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];

            return ApiHelper::apiDataTable($records);
        }

        return response()->json($records);
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        /*if (! Gate::allows('permissions_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }*/

        $permissions = ['' => 'Select a Parent Group', 0 => 'This is Parent Group'];

        $PermissionsData = Permission::where('main_group', 1)->OrderBy('name', 'asc')->get();
        if ($PermissionsData) {
            foreach ($PermissionsData as $permission) {
                $permissions[$permission->id] = $permission->title.' ('.$permission->name.')';
            }
        }

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'permissions' => $permissions,
        ]);

    }

    /**
     * Store a newly created Permission in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        /* if (! Gate::allows('permissions_create')) {
             return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
         }*/

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }

        $data = $request->all();
        if (! $data['parent_id']) {
            $data['main_group'] = 1;
        } else {
            $data['main_group'] = 0;
        }

        Permission::create($data);

        return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
    }

    /**
     * Validate form fields
     *
     * @return Validator $validator;
     */
    protected function verifyFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! Gate::allows('permissions_edit')) {
            return abort(401);
        }
        $permission = Permission::findOrFail($id);

        $permissions = ['' => 'Select a Parent Group', 0 => 'This is Parent Group'];

        $PermissionsData = Permission::where('main_group', 1)->OrderBy('name', 'asc')->get();
        if ($PermissionsData) {
            foreach ($PermissionsData as $permissionData) {
                $permissions[$permissionData->id] = $permissionData->title.' ('.$permissionData->name.')';
            }
        }

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'permissions' => $permissions,
            'permission' => $permission,
        ]);

    }

    /**
     * Update Permission in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! Gate::allows('permissions_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->all(),
            ]);

            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }

        $data = $request->all();
        if (! $data['parent_id']) {
            $data['main_group'] = 1;
        } else {
            $data['main_group'] = 0;
        }

        $permission = Permission::findOrFail($id);
        $permission->update($data);

        return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
    }

    /**
     * Remove Permission from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! Gate::allows('permissions_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $permission = Permission::findOrFail($id);
        $permission->delete();

        return ApiHelper::apiResponse($this->success, 'Record has been deleted successfully.');

    }
}
