<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\Centertarget;
use App\Models\Locations;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

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
        if (! Gate::allows('centre_targets_manage')) {
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

            $records = [];
            $records['data'] = [];

            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $centretarget = Centertarget::getBulkData($ids);
                if ($centretarget) {
                    foreach ($centretarget as $target) {
                        Centertarget::deleteRecord($target->id);
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            // Get Total Records
            $iTotalRecords = Centertarget::getTotalRecords($request, Auth::User()->account_id, $apply_filter);

            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $centretargets = Centertarget::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter, $filters);

            $records = $this->getFilterData($records, $filename);

            if ($centretargets) {
                $months_data = Config::get('constants.months_array');
                foreach ($centretargets as $centretarget) {
                    //$month = "constants.month_array[$centretarget->month]";
                    $records['data'][] = [
                        'id' => $centretarget->id,
                        'year' => $centretarget->year,
                        'month' => $months_data[$centretarget->month],
                        'created_at' => Carbon::parse($centretarget->created_at)->format('F j,Y h:i A'),
                    ];

                }

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
                'edit' => Gate::allows('centre_targets_edit'),
                'delete' => Gate::allows('centre_targets_destroy'),
                'active' => Gate::allows('centre_targets_active'),
                'inactive' => Gate::allows('centre_targets_inactive'),
                'create' => Gate::allows('centre_targets_create'),
                'allocate' => Gate::allows('centre_targets_allocate'),
                'manage' => Gate::allows('centre_targets_manage'),
            ];

            return ApiHelper::apiDataTable($records);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    private function getFilterData($records, $filename)
    {

        $months_data = Config::get('constants.months_array');
        foreach ($months_data as $key => $value) {
            $months[$key] = $value;
        }

        $years_data = range(Carbon::now()->year, Carbon::now()->subYears(10)->year);
        foreach ($years_data as $key => $value) {
            $years[$value] = $value;
        }

        $records['active_filters'] = Filters::all(Auth::user()->id, $filename);

        $records['filter_values'] = [
            'months' => $months,
            'years' => $years,
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
        if (! Gate::allows('centre_targets_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            $months_data = Config::get('constants.months_array');
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
        if (! Gate::allows('centre_targets_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            $validator = $this->verifyFields($request);

            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
            }

            $record = Centertarget::where([
                'month' => $request->get('month'),
                'year' => $request->get('year'),
                'account_id' => Auth::User()->account_id,
            ])->first();

            if ($record) {
                $staff_target = Centertarget::updateRecord($record->id, $request, Auth::User()->account_id);
            } else {
                $staff_target = Centertarget::createRecord($request, Auth::User()->account_id);
            }

            if ($staff_target) {
                return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.');

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Validate form fields
     *
     * @return \Illuminate\Contracts\Validation\Validator $validator;
     */
    protected function verifyFields(Request $request)
    {
        return Validator::make($request->all(), [
            'year' => 'required',
            'month' => 'required',
        ]);
    }

    /**
     * Show the form for editing center target.
     *
     * @param  int  $id ,$request
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {

        if (! Gate::allows('centre_targets_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $center_target = Centertarget::find($id);

        if (! $center_target) {
            return ApiHelper::apiResponse($this->success, 'Resource not found', false);
        }

        $months_data = Config::get('constants.months_array');
        foreach ($months_data as $key => $value) {
            $months[$key] = $value;
        }

        $years_data = range(Carbon::now()->year, Carbon::now()->subYears(10)->year);
        foreach ($years_data as $value) {
            $years[$value] = $value;
        }

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'center_target' => $center_target,
            'months' => $months,
            'years' => $years,
        ]);
    }

    /**
     * Update Centre target in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        if (! Gate::allows('centre_targets_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        /*$record = Centertarget::where(array(
            'month' => $request->get('month'),
            'year' => $request->get('year'),
            'account_id' => Auth::User()->account_id
        ))->first();*/
        $record = Centertarget::find($id);

        if ($record) {
            $staff_target = Centertarget::updateRecord($record->id, $request, Auth::User()->account_id);
        } else {
            $staff_target = Centertarget::createRecord($request, Auth::User()->account_id);
        }

        if ($staff_target) {
            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
        }

        return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
    }

    /**
     * Show details of center target.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function display($id)
    {
        if (! Gate::allows('centre_targets_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $centertarget = Centertarget::with('center_target_meta.location')->find($id);

        return ApiHelper::apiResponse($this->success, 'Record Found.', true, [
            'center_target' => $centertarget,
        ]);

    }

    /**
     * Remove StaffTarget from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id, Request $request)
    {
        if (! Gate::allows('centre_targets_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        try {

            Centertarget::deleteRecord($id);

            return ApiHelper::apiResponse($this->success, 'Record has been deleted successfully.');

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
