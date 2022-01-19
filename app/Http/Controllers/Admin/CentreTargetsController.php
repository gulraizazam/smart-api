<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\GeneralFunctions;
use App\Models\Centertarget;
use App\Models\Locations;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use App\Helpers\ACL;
use App\Helpers\Filters;
use Carbon\Carbon;use Illuminate\Support\Facades\Validator;


class CentreTargetsController extends Controller
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
     * Display a listing of Centre target.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Gate::allows('centre_targets_manage')) {
            return abort(401);
        }

        return view('admin.centre_targets.index');
    }

    /**
     * Display a listing of Centre target.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {

            $filename = 'centertarget';
            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, $filename);

            $records = array();
            $records["data"] = array();

            if (count($filters) > 0 && hasFilter($filters, 'delete') != '') {
                $ids = explode(',', $filters['delete']);
                $centretarget = Centertarget::getBulkData($ids);
                if ($centretarget) {
                    foreach ($centretarget as $target) {
                        Centertarget::deleteRecord($target->id);
                    }
                }
                $records["status"] = true;
                $records["message"] = "Records has been deleted successfully!";
            }

            // Get Total Records
            $iTotalRecords = Centertarget::getTotalRecords($request, Auth::User()->account_id, $apply_filter);

            list($orderBy, $order) = getSortBy($request);
            list($iDisplayLength, $iDisplayStart, $pages, $page) = getPaginationElement($request, $iTotalRecords);

            $centretargets = Centertarget::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter, $filters);

            $records = $this->getFilterData($records, $filename);

            if ($centretargets) {
                $months_data = Config::get("constants.months_array");
                foreach ($centretargets as $centretarget) {
                    //$month = "constants.month_array[$centretarget->month]";
                    $records["data"][] = array(
                        'id' => $centretarget->id,
                        'year' => $centretarget->year,
                        'month' => $months_data[$centretarget->month],
                        'created_at' => Carbon::parse($centretarget->created_at)->format('F j,Y h:i A'),
                    );

                }

                $records["meta"] = [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => $iDisplayLength,
                    'total' => $iTotalRecords,
                    'sort' => $order,
                ];
            }

            $records["permissions"] = [
                'edit' => Gate::allows('centre_targets_edit'),
                'delete' => Gate::allows('centre_targets_destroy'),
                'active' => Gate::allows('centre_targets_active'),
                'inactive' => Gate::allows('centre_targets_inactive'),
                'create' => Gate::allows('centre_targets_create'),
                'allocate' => Gate::allows('centre_targets_allocate'),
            ];

            return ApiHelper::apiDataTable($records);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    private function getFilterData($records, $filename) {

        $months_data = Config::get("constants.months_array");
        foreach ($months_data as $key => $value) {
            $months[$key] = $value;
        }

        $years_data = range(Carbon::now()->year, Carbon::now()->subYears(10)->year);
        foreach ($years_data as $key => $value) {
            $years[$value] = $value;
        }

        $records['active_filters'] = GeneralFunctions::getActiveFilters($filename);

        $records['filter_values'] = [
            'months' => $months,
            'years' => $years
        ];

        return $records;
    }


    /*
     * Show the form for creating new target.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!Gate::allows('centre_targets_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            $months_data = Config::get("constants.months_array");
            foreach ($months_data as $key => $value) {
                $months[$key] = $value;
            }

            $years_data = range(Carbon::now()->year, Carbon::now()->subYears(10)->year);
            foreach ($years_data as $key => $value) {
                $years[$value] = $value;
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'months' => $months,
                'years' => $years,
            ]);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Load target centre
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function leadtargetcentre(Request $request)
    {
        $locationdata = Locations::LoadtargetLocationdata($request);

        $targetlocation = $locationdata['CenterTargetArray'];

        $center_target_status = $locationdata['center_target_status'];

        $center_target_working_days = $locationdata['center_target_working_days'];

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'center_target_status' => $center_target_status,
            'center_target_working_days' => $center_target_working_days,
            'target_location' => $targetlocation,
        ]);
    }

    /*
     * Store the centre target
     */

    public function store(Request $request)
    {
        if (!Gate::allows('centre_targets_create')) {
            return abort(401);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->messages()->all(),
            ));
        }

        $record = Centertarget::where(array(
            'month' => $request->get('month'),
            'year' => $request->get('year'),
            'account_id' => Auth::User()->account_id
        ))->first();

        if ($record) {
            $staff_target = Centertarget::updateRecord($record->id, $request, Auth::User()->account_id);
        } else {
            $staff_target = Centertarget::createRecord($request, Auth::User()->account_id);
        }

        if ($staff_target) {

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
            'year' => 'required',
            'month' => 'required',
        ]);
    }

    /**
     * Show the form for editing center target.
     *
     * @param  int $id ,$request
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {

        if (!Gate::allows('centre_targets_edit')) {
            return abort(401);
        }

        $center_target = Centertarget::find($id);

        if (!$center_target) {
            return view('error', compact('lead_statuse'));
        }

        $months[''] = 'Select a Month';

        $months_data = Config::get("constants.months_array");
        foreach ($months_data as $key => $value) {
            $months[$key] = $value;
        }


        $years[''] = 'Select a Year';
        $years_data = range(Carbon::now()->year, Carbon::now()->subYears(10)->year);
        foreach ($years_data as $key => $value) {
            $years[$value] = $value;
        }

        return view('admin.centre_targets.edit', compact('center_target', 'months', 'years'));
    }

    /**
     * Update Centre target in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (!Gate::allows('centre_targets_edit')) {
            return abort(401);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->messages()->all(),
            ));
        }
        /*$record = Centertarget::where(array(
            'month' => $request->get('month'),
            'year' => $request->get('year'),
            'account_id' => Auth::User()->account_id
        ))->first();*/
        $record =Centertarget::find($id);

        if ($record) {
            $staff_target = Centertarget::updateRecord($record->id, $request, Auth::User()->account_id);
        } else {
            $staff_target = Centertarget::createRecord($request, Auth::User()->account_id);
        }

        if ($staff_target) {

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
     * Show details of center target.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function display($id)
    {
        if (!Gate::allows('centre_targets_manage')) {
            return abort(401);
        }

        $centertarget = Centertarget::find($id);

        return view('admin.centre_targets.target_view', compact('centertarget'));

    }

    /**
     * Remove StaffTarget from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        if (!Gate::allows('centre_targets_destroy')) {
            return abort(401);
        }
        Centertarget::deleteRecord($id);

        flash('Record has been deleted successfully.')->success()->important();

        return redirect()->route('admin.centre_targets.index');
    }


}
