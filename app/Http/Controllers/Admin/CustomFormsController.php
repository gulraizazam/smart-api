<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUpdateCustomFormsRequest;
use App\Models\CustomFormFields;
use App\Models\CustomForms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Input;

class CustomFormsController extends Controller
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
        if (! Gate::allows('custom_forms_manage')) {
            return abort(401);
        }

        return view('admin.custom_forms.index');
    }

    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     *
     * @throws \Throwable
     */
    public function datatable(Request $request)
    {
        try {

            $filename = 'custom_forms';

            $filters = getFilters($request->all());

            $apply_filter = checkFilters($filters, $filename);

            $records = [];
            $records['data'] = [];

            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $CustomForms = CustomForms::getBulkData($ids);
                if ($CustomForms) {
                    foreach ($CustomForms as $custom_form) {
                        // Check if child records exists or not, If exist then disallow to delete it.
                        if (! CustomForms::isChildExists($custom_form->id, Auth::User()->account_id)) {
                            $custom_form->delete();
                        }
                    }
                }
                $records['status'] = true; // pass custom message(useful for getting status of group actions)
                $records['message'] = 'Records has been deleted successfully!'; // pass custom message(useful for getting status of group actions)
            }

            // Get Total Records
            $iTotalRecords = CustomForms::getTotalRecords($request, Auth::User()->account_id, $apply_filter);

            [$orderBy, $order] = getSortBy($request);

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $CustomForms = CustomForms::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);

            $records = $this->getFilters($records);

            if ($CustomForms) {
                $records['data'] = $CustomForms;

                $records['meta'] = [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => $iDisplayLength,
                    'total' => $iTotalRecords,
                    'sort' => $order,
                ];
            }

            $records['permissions'] = [
                'edit' => Gate::allows('custom_forms_edit'),
                'delete' => Gate::allows('custom_forms_destroy'),
                'active' => Gate::allows('custom_forms_active'),
                'inactive' => Gate::allows('custom_forms_inactive'),
                'preview' => Gate::allows('custom_forms_preview'),
                'submit' => Gate::allows('custom_forms_submit'),
            ];

            return ApiHelper::apiDataTable($records);

        } catch (\Exception $e) {

            return ApiHelper::apiException($e);
        }
    }

    private function getFilters($records)
    {

        $form_types = [
            '' => 'All',
            '1' => 'Measurement Form',
            '0' => 'General Form',
            '2' => 'Medical Form',
        ];

        $records['active_filters'] = Filters::all(Auth::User()->id, 'custom_forms');

        $records['filter_values'] = [
            'form_types' => $form_types,
            'status' => config('constants.status'),
        ];

        return $records;
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! Gate::allows('custom_forms_create_general') && ! Gate::allows('custom_forms_edit')) {
            return abort(401);
        }
        $data['custom_form_type'] = '0';
        $form = CustomForms::createForm(Auth::User()->account_id, $data);

        return redirect()->route('admin.custom_forms.edit', $form);
    }

    /**
     * Show the form for creating measurement new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create_measurement()
    {
        if (! Gate::allows('custom_forms_create_measurement') && ! Gate::allows('custom_forms_edit')) {
            return abort(401);
        }
        $data['custom_form_type'] = '1';
        $form = CustomForms::createForm(Auth::User()->account_id, $data);

        return redirect()->route('admin.custom_forms.edit', $form);
    }

    /**
     * Show the form for creating medical history from with new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create_medical()
    {
        if (! Gate::allows('custom_forms_create_medical_history_form') && ! Gate::allows('custom_forms_edit')) {
            return abort(401);
        }
        $data['custom_form_type'] = '2';
        $form = CustomForms::createForm(Auth::User()->account_id, $data);

        return redirect()->route('admin.custom_forms.edit', $form);
    }

    public function sortorder_save()
    {

        $custom_forms = DB::table('custom_forms')->where(['account_id' => Auth::User()->account_id])->orderBy('sort_number', 'ASC')->get();
        $itemID = Input::get('itemID');
        $itemIndex = Input::get('itemIndex');
        if ($itemID) {
            foreach ($custom_forms as $custom_form) {
                $sort = DB::table('custom_forms')->where('id', '=', $itemID)->update(['sort_number' => $itemIndex]);
                $myarray = ['status' => 'Data Sort Successfully'];

                return response()->json($myarray);
            }
        } else {
            $myarray = ['status' => 'Data Not Sort'];

            return response()->json($myarray);
        }
    }

    public function sort_fields(Request $request, $id)
    {
        if (! Gate::allows('custom_forms_manage')) {
            return abort(401);
        }

        if (! CustomFormFields::sortFields($request, $id, Auth::User()->account_id, Auth::id())) {

            return response()->json(['Unprocessable Entities' => '.', 'code' => 422], 422);
        }

        return response()->json(['message' => 'Records has been sorted successfully.', 'code' => 200], 200);
    }

    public function sortorder()
    {

        $custom_forms = DB::table('custom_forms')->where(['account_id' => Auth::User()->account_id])->orderby('sort_number', 'ASC')->get();

        return view('admin.custom_forms.sort', compact('custom_forms'));
    }

    /**
     * Store a newly created Permission in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreUpdateCustomFormsRequest $request)
    {
        if (! Gate::allows('custom_forms_manage')) {
            return abort(401);
        }

        if (CustomForms::createRecord($request, Auth::User()->account_id, Atuh::id())) {

            flash('Record has been created successfully.')->success()->important();
        } else {
            flash('Something went wrong, please try again later.')->error()->important();
        }

        return redirect()->route('admin.custom_forms.index');
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! Gate::allows('custom_forms_edit') && ! Gate::allows('custom_forms_create_measurement') && ! Gate::allows('custom_forms_create_general')) {
            return abort(401);
        }

        $custom_form = CustomForms::get_all_fields_data($id);

        if (! $custom_form) {
            return view('error');
        }

        return view('admin.custom_forms.edit', compact('custom_form'));
    }

    /**
     * Update Permission in storage.
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateCustomFormsRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! Gate::allows('custom_forms_manage')) {
            return abort(401);
        }

        if (CustomForms::updateRecord($id, $request, Auth::User()->account_id)) {

            flash('Record has been updated successfully.')->success()->important();
        } else {
            flash('Something went wrong, please try again later.')->error()->important();
        }

        return redirect()->route('admin.custom_forms.index');
    }

    public function form_update(Request $request, $id)
    {
        if (! Gate::allows('custom_forms_manage')) {
            return abort(401);
        }

        if ($custom_form = CustomForms::updateRecord($id, $request, Auth::User()->account_id, Auth::id())) {

            return response()->json($custom_form, 200);

        } else {
            return response()->json(['message' => 'Some went wrong, please try again later'], 400);
        }
    }

    public function create_field(Request $request, $id)
    {

        if (! Gate::allows('custom_forms_manage')) {
            return abort(401);
        }

        $data = CustomFormFields::createRecord($request, Auth::User()->account_id, Auth::id(), $id);
        if ($data) {
            return response()->json(['request' => $request->all(), 'data' => $data]);
        } else {
            response()->json(['error' => $request->all(), 'id' => $id, 'data' => $data], 401);
        }

    }

    /**
     * update form field
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update_field(Request $request, $form_id, $field_id)
    {
        if (! Gate::allows('custom_forms_manage')) {
            return abort(401);
        }
        $data = CustomFormFields::updateRecord($request, Auth::User()->account_id, Auth::id(), $form_id, $field_id);

        if ($data) {
            return response()->json(['request' => $request->all(), 'data' => $data]);
        } else {
            response()->json(['error' => $request->all(), 'form_id' => $form_id, 'field_id' => $field_id, 'data' => $data], 401);
        }
    }

    public function delete_field(Request $request, $form_id, $field_id)
    {

        if (! Gate::allows('custom_forms_manage')) {
            return abort(401);
        }

        $custom_form_field = CustomFormFields::getData($field_id);

        if (! $custom_form_field) {

            return response()->json(['message' => 'Resource not found.', 'code' => 404], 404);
        }

        CustomFormFields::deleteRecord($form_id, $field_id);

        return response()->json(['message' => 'Record has been deleted successfully.', 'code' => 200], 200);

    }

    /**
     * Remove Permission from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! Gate::allows('custom_forms_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $custom_form = CustomForms::getData($id);

        if (! $custom_form) {
            return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (CustomForms::isChildExists($id, Auth::User()->account_id)) {
            return ApiHelper::apiResponse($this->success, 'Child records exist, unable to delete resource.', false);
        }

        CustomForms::deleteRecord($id);

        return ApiHelper::apiResponse($this->success, 'Record has been deleted successfully.');
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function status(Request $request)
    {
        if (! Gate::allows('custom_forms_inactive')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $custom_form = CustomForms::getData($request->id);

        if (! $custom_form) {
            return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
        }

        if ($request->status == 1) {
            $response = CustomForms::activateRecord($request->id);
        } else {
            $response = CustomForms::inactivateRecord($request->id);
        }

        return ApiHelper::apiResponse($this->success, $response['message'], $response['status']);

    }
}
