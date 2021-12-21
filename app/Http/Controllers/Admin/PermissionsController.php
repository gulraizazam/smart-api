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
            return abort(401);
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

        if ($request->get('customActionType') && $request->get('customActionType') == "group_action") {
            $Permissions = Permission::whereIn('id', $request->get('id'));
            if($Permissions) {
                $Permissions->delete();
            }
            $records["customActionStatus"] = "OK"; // pass custom message(useful for getting status of group actions)
            $records["customActionMessage"] = "Records has been deleted successfully!"; // pass custom message(useful for getting status of group actions)
        }

        $where = array();

        $orderBy = 'created_at';
        $order = 'desc';

        if($request->get('order')[0]['dir']) {
            $orderColumn = $request->get('order')[0]['column'];
            $orderBy = $request->get('columns')[$orderColumn]['data'];
            $order = $request->get('order')[0]['dir'];
        }


        if($request->get('name')) {
            $where[] = array(
                'name',
                'like',
                '%' . $request->get('name') . '%'
            );
        }

        if(count($where)) {
            $iTotalRecords = Permission::where($where)->count();
        } else {
            $iTotalRecords = Permission::count();
        }


        $iDisplayLength = intval($request->get('length'));
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->get('start'));
        $sEcho = intval($request->get('draw'));

        $end = $iDisplayStart + $iDisplayLength;
        $end = $end > $iTotalRecords ? $iTotalRecords : $end;

        if(count($where)) {
            $Permissions = Permission::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
        } else {
            $Permissions = Permission::limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
        }

        $PermissionsData = Permission::where('main_group', 1)->select('id','title', 'name', 'parent_id')->OrderBy('name', 'asc')->get()->keyBy('id');

        if($Permissions) {
            foreach($Permissions as $permission) {
//                echo '<pre>';
//                print_r($permission->toArray());
//                print_r($PermissionsData->toArray());
//                exit;
                $records["data"][] = array(
                    'id' => '<label class="mt-checkbox mt-checkbox-single mt-checkbox-outline"><input name="id[]" type="checkbox" class="checkboxes" value="'.$permission->id.'"/><span></span></label>',
                    'title' => $permission->title,
                    'name' => $permission->name,
                    'parent_id' => ($permission->parent_id) ? $PermissionsData[$permission->parent_id]->name : '-',
                    'actions' => view('admin.permissions.actions', compact('permission'))->render(),
                );
            }
        }

        $records["draw"] = $sEcho;
        $records["recordsTotal"] = $iTotalRecords;
        $records["recordsFiltered"] = $iTotalRecords;

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
        if (! Gate::allows('permissions_destroy')) {
            return abort(401);
        }
        $permission = Permission::findOrFail($id);
        $permission->delete();

        flash('Record has been deleted successfully.')->success()->important();

        return redirect()->route('admin.permissions.index');
    }

}
