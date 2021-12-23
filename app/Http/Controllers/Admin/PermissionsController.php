<?php

namespace App\Http\Controllers\Admin;

use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
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
            //return abort(401);
        }

        return view('admin.permissions.index');
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
        $records["data"] = array();

        if(! is_null($request->get('query')) && isset($request->get('query')['delete'])) {
            $ids = explode(',', $request->get('query')['delete']);
            $Permissions = Permission::whereIn('id', $ids);
            if($Permissions->exists()) {
                $Permissions->delete();
            }
            $records["status"] = true;
            $records["message"] = "Records has been deleted successfully!";
        }

        $where = array();

        $orderBy = 'created_at';
        $order = 'desc';

        if ($request->has('sort')) {
            $orderColumn = $request->get('sort')['field'];
            $orderBy = $request->get('sort')['field'];
            $order = $request->get('sort')['sort'];
        }

        $query = Permission::query();

        if(! is_null($request->get('query')) && isset($request->get('query')['generalSearch'])) {
            $search = $request->get('query')['generalSearch'];
            $query = $query->where("name", "LIKE", "%{$search}%")
                ->orWhere("title", "LIKE", "%{$search}%")
                ->orWhere("parent_id", "LIKE", "%{$search}%");
                $iTotalRecords = $query->count();
        } else {
            $iTotalRecords = Permission::count();
        }

        $iDisplayLength = intval($request->pagination['perpage'] ?? 10);
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->pagination['page'] ?? 1);

        $pages = ceil($iTotalRecords / $iDisplayLength);

        if($request->has('query')) {
            $Permissions = $query->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
        } else {
            $Permissions = $query->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
        }

        $PermissionsData = Permission::where('main_group', 1)->select('id','title', 'name', 'parent_id')->OrderBy('name', 'asc')->get()->keyBy('id');

        if($Permissions) {
            foreach($Permissions as $permission) {

                $records["data"][] = array(
                    'id' => $permission->id,
                    'title' => $permission->title,
                    'name' => $permission->name,
                    'parent_id' => ($permission->parent_id) ? $PermissionsData[$permission->parent_id]->name : '-',
                    //'actions' => view('admin.permissions.actions', compact('permission'))->render(),
                );
            }
        }

        $records["meta"] = [
            'field' => 'name',
            'page' => $iDisplayStart,
            'pages' => $pages,
            'perpage' => $iDisplayLength,
            'total' => $iTotalRecords,
            'sort' => $orderBy,
        ];
       // $records["recordsTotal"] = $iTotalRecords;
        //$records["recordsFiltered"] = $iTotalRecords;

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

        return view('admin.permissions.create', compact('permissions'));
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
            'status' => 1,
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
            foreach ($PermissionsData as $permission) {
                $permissions[$permission->id] = $permission->title . ' (' . $permission->name . ')';
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
        dd($id);
        if (! Gate::allows('permissions_destroy')) {
            return abort(401);
        }
        $permission = Permission::findOrFail($id);
        $permission->delete();

        flash('Record has been deleted successfully.')->success()->important();

        return redirect()->route('admin.permissions.index');
    }

}
