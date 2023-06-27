<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\UserTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Validator;

class UserTypesController extends Controller
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
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! Gate::allows('user_types_manage')) {
            return abort(401);
        }

        return view('admin.user_types.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        if (! Gate::allows('user_types_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        $user_types = UserTypes::all();

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'types' => $user_types,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! Gate::allows('user_types_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }

        if (UserTypes::createRecord($request, Auth::User()->account_id, Auth::User()->id)) {

            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        } else {

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        }
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
            'type' => 'required',
        ]);
    }

    /*
     * function for datatable
     * */
    public function datatable(Request $request)
    {
        $records = [];
        $records['data'] = [];

        $filters = getFilters($request->all());

        if (count($filters) > 0 && hasFilter($filters, 'delete') != '') {
            $ids = explode(',', $filters['delete']);
            $usertypes = UserTypes::getBulkData($ids);
            if ($usertypes) {
                foreach ($usertypes as $usertype) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! UserTypes::isChildExists($usertype->id, Auth::User()->account_id)) {
                        $usertype->delete();
                    }
                }
            }
            $records['status'] = true;
            $records['message'] = 'Records has been deleted successfully!';
        }

        [$orderBy, $order] = getSortBy($request);

        // Get Total Records
        $iTotalRecords = UserTypes::getTotalRecords($request, Auth::User()->account_id);

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $user_types = UserTypes::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id);

        if ($user_types) {
            $records['data'] = $user_types;

            $records['permissions'] = [
                'edit' => Gate::allows('user_types_edit'),
            ];
            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];
        }

        return response()->json($records);
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function inactive($id)
    {
        if (! Gate::allows('user_types_inactive')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        UserTypes::inactiveRecord($id);

        return ApiHelper::apiResponse($this->success, 'Inactivated successfully.');
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function active($id)
    {
        if (! Gate::allows('user_types_active')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        UserTypes::activeRecord($id);

        return ApiHelper::apiResponse($this->success, 'Activated successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! Gate::allows('user_types_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $usertype = UserTypes::getData($id);

        $types = config('constants.user_types');

        if (! $usertype) {
            return ApiHelper::apiResponse($this->success, 'Record not found.', false);
        }

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'usertype' => $usertype,
            'types' => $types,
        ]);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! Gate::allows('user_types_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }

        if (UserTypes::updateRecord($id, $request, Auth::User()->account_id, Auth::User()->id)) {
            session()->flash('success', 'Record has been updated successfully.');

            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        } else {
            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! Gate::allows('user_types_destroy')) {
            return abort(401);
        }

        UserTypes::deleteRecord($id);

        return redirect()->route('admin.user_types.index');

    }
}
