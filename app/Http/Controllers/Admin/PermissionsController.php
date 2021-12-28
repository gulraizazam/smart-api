<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Filters;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use Validator;

class PermissionsController extends Controller
{
    /**
     * Display a listing of Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! Gate::allows('permissions_manage')) {
           // return abort(401);
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
        $records = array();

        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, 'permissions');

        if(count($filters) > 0 &&  hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $Permissions = Permission::whereIn('id', $ids);
            if($Permissions->exists()) {
                $Permissions->delete();
            }
            $records["status"] = true;
            $records["message"] = "Records has been deleted successfully!";
        }

        list($orderBy, $order) = getSortBy($request);

        $query = Permission::query()->with(['parent' => function ($query) {
            $query->select('id', 'name');
        }]);

        $iTotalRecords = Permission::count();

        if(count($filters) > 0  && hasFilter($filters, 'search')) {
            Filters::put(Auth::user()->id, 'permissions', 'search', $filters['search']);
            $search = $filters['search'];
            $query = $query->where("name", "LIKE", "%{$search}%")
                ->orWhere("title", "LIKE", "%{$search}%")
                ->orWhere("parent_id", "LIKE", "%{$search}%");
                $iTotalRecords = $query->count();
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id, 'permissions', 'search');
            } else {
                if ($search = Filters::get(Auth::user()->id,'permissions', 'search')){
                    $query = $query->where("name", "LIKE", "%{$search}%")
                        ->orWhere("title", "LIKE", "%{$search}%")
                        ->orWhere("parent_id", "LIKE", "%{$search}%");
                    $iTotalRecords = $query->count();
                }
            }
        }

        list( $iDisplayLength, $iDisplayStart, $pages) = getPaginationElement($iTotalRecords);

        if($request->has('query')) {
            $Permissions = $query->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
        } else {
            $Permissions = $query->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
        }

        $records["data"] = $Permissions;

        $records["meta"] = [
            'field' => 'name',
            'page' => $iDisplayStart,
            'pages' => $pages,
            'perpage' => $iDisplayLength,
            'total' => $iTotalRecords,
            'sort' => $orderBy,
        ];

        return response()->json($records);
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! Gate::allows('permissions_create')) {
            return abort(401);
        }

        $permissions = ['' => 'Select a Parent Group', 0 => 'This is Parent Group'];

        $PermissionsData = Permission::where('main_group', 1)->OrderBy('name', 'asc')->get();
        if($PermissionsData) {
            foreach ($PermissionsData as $permission) {
                $permissions[$permission->id] = $permission->title . ' (' . $permission->name . ')';
            }
        }

        return view('admin.permissions.create', compact('permissions'))->render();
    }

    /**
     * Store a newly created Permission in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! Gate::allows('permissions_create')) {
            return abort(401);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->messages()->all(),
            ));
        }

        $data = $request->all();
        if(!$data['parent_id']) {
            $data['main_group'] = 1;
        } else {
            $data['main_group'] = 0;
        }

        Permission::create($data);


        return response()->json(array(
            'status' => true,
            'message' => 'Record has been created successfully.',
        ));
    }

    /**
     * Validate form fields
     *
     * @param  \Illuminate\Http\Request $request
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
        if($PermissionsData) {
            foreach ($PermissionsData as $permissionData) {
                $permissions[$permissionData->id] = $permissionData->title . ' (' . $permissionData->name . ')';
            }
        }

        return view('admin.permissions.edit', compact('permission', 'permissions'));
    }

    /**
     * Update Permission in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! Gate::allows('permissions_edit')) {
            return abort(401);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->messages()->all(),
            ));
        }

        $data = $request->all();
        if(!$data['parent_id']) {
            $data['main_group'] = 1;
        } else {
            $data['main_group'] = 0;
        }

        $permission = Permission::findOrFail($id);
        $permission->update($data);

        return response()->json(array(
            'status' => 1,
            'message' => 'Record has been updated successfully.',
        ));
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
            return abort(401);
        }
        $permission = Permission::findOrFail($id);
        $permission->delete();

        return redirect()->back()->with('success', 'Record has been deleted successfully.');
    }

}
