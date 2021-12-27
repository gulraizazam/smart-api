<?php

namespace App\Http\Controllers\Admin;

use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use DB;
use App\Models\UserTypes;
use Session;
use Auth;
use Validator;


class UserTypesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Gate::allows('user_types_manage')) {
            return abort(401);
        }

        return view('admin.user_types.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!Gate::allows('user_types_create')) {
            return abort(401);
        }

        $user_types = UserTypes::all();

        return view('admin.user_types.create', compact('user_types'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!Gate::allows('user_types_create')) {
            return abort(401);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->messages()->all(),
            ));
        }

        if (UserTypes::createRecord($request, Auth::User()->account_id, Auth::User()->id)) {
            flash('Record has been created successfully.')->success()->important();

            return response()->json(array(
                'status' => 1,
                'message' => 'Record has been created successfully.',
            ));
        } else {
            return response()->json(array(
                'status' => 0,
                'message' => 'Something went wrong, please try again later.',
            ));
        }
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
            'type' => 'required',
        ]);
    }

    /*
     * function for datatable
     * */
    public function datatable(Request $request)
    {
        $records = array();
        $records["data"] = array();

        if ($request->get('customActionType') && $request->get('customActionType') == "group_action") {
            $usertypes = UserTypes::getBulkData($request->get('id'));
            if ($usertypes) {
                foreach ($usertypes as $usertype) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (!UserTypes::isChildExists($usertype->id, Auth::User()->account_id)) {
                        $usertype->delete();
                    }
                }
            }
            $records["customActionStatus"] = "OK"; // pass custom message(useful for getting status of group actions)
            $records["customActionMessage"] = "Records has been deleted successfully!"; // pass custom message(useful for getting status of group actions)
        }

        // Get Total Records
        $iTotalRecords = UserTypes::getTotalRecords($request, Auth::User()->account_id);

        $iDisplayLength = intval($request->get('length'));
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->get('start'));
        $sEcho = intval($request->get('draw'));

        $user_types = UserTypes::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id);

        if ($user_types) {
            foreach ($user_types as $usertype) {
                if ($usertype->name != 'Administrator') {
                    $records["data"][] = array(
                        'name' => $usertype->name,
                        'type' => $usertype->type,
                        'actions' => view('admin.user_types.actions', compact('usertype'))->render(),
                    );
                }

            }
        }

        $records["draw"] = $sEcho;
        $records["recordsTotal"] = $iTotalRecords;
        $records["recordsFiltered"] = $iTotalRecords;

        return response()->json($records);
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function inactive($id)
    {
        if (!Gate::allows('user_types_inactive')) {
            return abort(401);
        }

        UserTypes::inactiveRecord($id);

        return redirect()->route('admin.user_types.index');
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function active($id)
    {
        if (!Gate::allows('user_types_active')) {
            return abort(401);
        }
        UserTypes::activeRecord($id);

        return redirect()->route('admin.user_types.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!Gate::allows('user_types_edit')) {
            return abort(401);
        }

        $usertpes = UserTypes::getData($id);

        if (!$usertpes) {
            return view('error');
        }

        return view('admin.user_types.edit', compact('usertpes'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (!Gate::allows('user_types_edit')) {
            return abort(401);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->messages()->all(),
            ));
        }

        if (UserTypes::updateRecord($id, $request, Auth::User()->account_id, Auth::User()->id)) {
            flash('Record has been updated successfully.')->success()->important();

            return response()->json(array(
                'status' => 1,
                'message' => 'Record has been updated successfully.',
            ));
        } else {
            return response()->json(array(
                'status' => 0,
                'message' => 'Something went wrong, please try again later.',
            ));
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!Gate::allows('user_types_destroy')) {
            return abort(401);
        }

        UserTypes::deleteRecord($id);

        return redirect()->route('admin.user_types.index');

    }
}
