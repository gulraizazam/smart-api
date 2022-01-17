<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Filters;
use App\Models\DiscountHasLocations;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use DB;
use App\Models\Discounts;
use App\Models\Locations;
use App\Models\Services;
use App\Helpers\NodesTree;
use Auth;
use PHPUnit\Util\Filter;
use Validator;
use Session;
use Illuminate\Support\Facades\Input;
use Carbon\Carbon;
use App\Helpers\Widgets\LocationsWidget;
use App\Helpers\Widgets\ServiceWidget;
use Config;


class DiscountsController extends Controller
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
     * Display a listing of the discount in datatable.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Gate::allows('discounts_manage')) {
            return abort(401);
        }

        return view('admin.discounts.index');
    }

    /**
     * Show the form for creating a new Discount.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!Gate::allows('discounts_create')) {
            return abort(401);
        }
        $discount = new \stdClass();
        $discount->service_id = null;
        $discount->location_id = null;

        $locations = Locations::getActiveSorted();

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id, true, true);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $discountServices = array();

        return view('admin.discounts.create', compact('discount', 'locations', 'Services', 'discountServices'));
    }

    /**
     * Store a newly created discount in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!Gate::allows('discounts_create')) {
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
        $data['account_id'] = Auth::User()->account_id;
        if ($request->slug == 'custom' || $request->slug == 'default') {
            $data['pre_days'] = 0;
            $data['post_days'] = 0;
        }

        if (Input::get('active') == null) {
            $data['active'] = '0';
        }

        if ($request->start <= $request->end) {

            if (Discounts::createDiscount($data)) {
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
        } else {
            return response()->json(array(
                'status' => 0,
                'message' => array('Date range invalid, Kindly define again'),
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
            'amount' => 'required',
            'start' => 'required',
            'end' => 'required',
        ]);
    }


    /**
     * Display the discount in datatable form.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request)
    {
        list($orderBy, $order) = getSortBy($request);

        $filename = 'discounts';
        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, $filename);

        $where = $this->applyFilters($request, $filters, $apply_filter, $filename);

        $total_query = Discounts::select('id');
        if (count($where)) {
            $total_query->where($where);
        }
        $iTotalRecords = $total_query->count();

        list($iDisplayLength, $iDisplayStart, $pages, $page) = getPaginationElement($request, $iTotalRecords);

        $records = array();
        $records["data"] = array();

        $query = Discounts::select('*');
        if ($request->get('startdate') && $request->get('startdate') != '') {
            $query->whereDate('start', '>=', $request->get('startdate'));
        }
        if ($request->get('enddate') && $request->get('enddate') != '') {
            $query->whereDate('end', '<=', $request->get('enddate'));
        }

        if (count($where)) {
            $query->where($where);
        }

        $Discounts = $query->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy, $order)->get();

        $records = $this->getFiltersData($records);

        if ($Discounts) {
            foreach ($Discounts as $discount) {
                $serviceExplod = explode(",", $discount->service_id);
                $locationExplod = explode(",", $discount->location_id);
                $records["data"][] = array(
                    'id' => '<label class="mt-checkbox mt-checkbox-single mt-checkbox-outline"><input name="id[]" type="checkbox" class="checkboxes" value="' . $discount->id . '"/><span></span></label>',
                    'name' => $discount->name,
                    'type' => $discount->type,
                    'amount' => $discount->amount,
                    'discount_type' => $discount->discount_type,
                    'start' => $discount->start ? \Carbon\Carbon::parse($discount->start)->format('D M, j Y') : null,
                    'end' => $discount->end ? \Carbon\Carbon::parse($discount->end)->format('D M, j Y') : null,
                    'created_at' => Carbon::parse($discount->created_at)->format('F j,Y h:i A'),
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

        if (count($filters) > 0 && hasFilter($filters, 'delete') != '') {
            $ids = explode(',', $filters['delete']);
            $Discounts = Discounts::whereIn('id', $ids);
            if ($Discounts) {
                $Discounts->delete();
            }
            $records["status"] = true; // pass custom message(useful for getting status of group actions)
            $records["message"] = "Records has been deleted successfully!"; // pass custom message(useful for getting status of group actions)
        }

        return response()->json($records);
    }

    private function applyFilters($request, $filters, $apply_filter, $filename = 'discounts') {

        $where = [];

        if (Auth::user()->account_id && Auth::user()->account_id != ''){
            $where[] = array(
                'account_id',
                '=',
                Auth::user()->account_id
            );
            Filters::put(Auth::User()->id, $filename, 'account_id', Auth::user()->account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'account_id')) {
                    $where[] = array(
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'account_id')
                    );
                }
            }
        }

        if (count($filters) > 0 && hasFilter($filters, 'name')) {
            $where[] = array(
                'name',
                'like',
                '%' . $filters['name'] . '%'
            );
            Filters::put(Auth::User()->id, $filename, 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'name');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'name')) {
                    $where[] = array(
                        'name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, $filename, 'name') . '%'
                    );
                }
            }
        }

        if (count($filters) > 0 && hasFilter($filters, 'type')) {
            $where[] = array(
                'type',
                'like',
                '%' . $filters['type'] . '%'
            );
            Filters::put(Auth::User()->id, $filename, 'type', $filters['type']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'type');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'type')) {
                    $where[] = array(
                        'type',
                        'like',
                        '%' . Filters::get(Auth::User()->id, $filename, 'type') . '%'
                    );
                }
            }
        }

        if (count($filters) > 0 && hasFilter($filters, 'amount')) {
            $where[] = array(
                'amount',
                'like',
                '%' . $filters['amount'] . '%'
            );
            Filters::put(Auth::User()->id, $filename, 'amount', $request->get('amount'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'amount');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'amount')) {
                    $where[] = array(
                        'amount',
                        'like',
                        '%' . Filters::get(Auth::User()->id, $filename, 'amount') . '%'
                    );
                }
            }
        }

        if (count($filters) > 0 && hasFilter($filters, 'discount_type')) {
            $where[] = array(
                'discount_type',
                '=',
                $filters['discount_type']
            );
            Filters::put(Auth::User()->id, $filename, 'discount_type', $filters['discount_type']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'discount_type');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'discount_type')) {
                    $where[] = array(
                        'discount_type',
                        '=',
                        Filters::get(Auth::user()->id, $filename ,'discount_type')
                    );
                }
            }
        }

        if (count($filters) > 0 && hasFilter($filters, 'created_from')) {
            $where[] = array(
                'created_at',
                '>=',
                $filters['created_from'] . ' 00:00:00'
            );
            Filters::put(Auth::User()->id, $filename, 'created_from', $filters['created_from'] . ' 00:00:00');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_from');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_from')) {
                    $where[] = array(
                        'created_at',
                        '>=',
                        Filters::get(Auth::User()->id, $filename, 'created_from')
                    );
                }
            }
        }

        if (count($filters) > 0 && hasFilter($filters, 'created_to')) {
            $where[] = array(
                'created_at',
                '<=',
                $filters['created_to'] . ' 23:59:59'
            );
            Filters::put(Auth::User()->id, $filename, 'created_to', $filters['created_to'] . ' 23:59:59');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_to');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_to')) {
                    $where[] = array(
                        'created_at',
                        '<=',
                        Filters::get(Auth::User()->id, $filename, 'created_to')
                    );
                }
            }
        }
        if (count($filters) > 0 && hasFilter($filters, 'startdate')) {
            $where[] = array(
                'start',
                '>=',
                $filters['startdate']
            );
            Filters::put(Auth::user()->id , $filename, 'startdate', $filters['startdate']);
        } else {
            if ($apply_filter){
                Filters::forget(Auth::User()->id, $filename, 'startdate');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'startdate')) {
                    $where[] = array(
                        'start',
                        '>=',
                        Filters::get(Auth::user()->id , $filename, 'startdate')
                    );
                }
            }
        }

        if (count($filters) > 0 && hasFilter($filters, 'enddate')) {
            $where[] = array(
                'end',
                '<=',
                $filters['enddate']
            );
            Filters::put(Auth::user()->id , $filename, 'enddate', $filters['enddate']);
        } else {
            if ($apply_filter){
                Filters::forget(Auth::User()->id, $filename, 'enddate');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'enddate')) {
                    $where[] = array(
                        'end',
                        '<=',
                        Filters::get(Auth::user()->id , $filename, 'enddate')
                    );
                }
            }
        }

        if (count($filters) > 0 && hasFilter($filters, 'status') || hasFilter($filters, 'status') && $filters['status'] == 0 && $filters['status'] != null) {
            $where[] = array(
                'active',
                '=',
                $filters['status']
            );
            Filters::put(Auth::user()->id, $filename, 'status',  $filters['status']);
        } else {
            if ( $apply_filter ){
                Filters::forget( Auth::user()->id, $filename, 'status');
            } else {
                if ( Filters::get(Auth::user()->id, $filename, 'status') == 0 || Filters::get(Auth::user()->id, $filename, 'status') == 1){
                    if ( Filters::get(Auth::user()->id, $filename, 'status') != null ){
                        $where[] = array(
                            'active',
                            '=',
                            Filters::get( Auth::user()->id, $filename, 'status')
                        );
                    }
                }
            }
        }

        return $where;
    }

    private function getFiltersData($records) {

        $filters = Filters::all(Auth::User()->id, 'discounts');

        $locations = Locations::getlocation();

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $records['active_filters'] = $filters;

        $records['filter_values'] = [
            'services' => $Services,
            'locations' => $locations,
        ];

        return $records;

    }

    /**
     * Show the form for editing the specified discount information.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!Gate::allows('discounts_edit')) {
            return abort(401);
        }

        $discount = Discounts::getData($id);

        if ($discount == null) {

            return view('error');

        } else {

            $discountServices = explode(",", $discount->service_id);

            if (!$discountServices) {

                $discountServices = array();
            }
            /* Create Nodes with Parents */
            $parentGroups = new NodesTree();
            $parentGroups->current_id = -1;
            $parentGroups->build(0, Auth::User()->account_id, true, true);
            $parentGroups->toList($parentGroups, -1);

            $Services = $parentGroups->nodeList;

            $locations = Locations::getActiveSorted();

            return view('admin.discounts.edit', compact('discount', 'locations', 'Services', 'discountServices'));
        }
    }

    /**
     * Update the specified discount in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (!Gate::allows('discounts_edit')) {
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

        if ($request->slug == 'custom' || $request->slug == 'default') {
            $data['pre_days'] = 0;
            $data['post_days'] = 0;
        }

        if (Input::get('active') == null) {
            $data['active'] = '0';
        }

        if ($request->start <= $request->end) {

            if (Discounts::updateDiscount($data, $id)) {
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
        } else {
            return response()->json(array(
                'status' => 0,
                'message' => array('Date range invalid, Kindly define again'),
            ));
        }

    }

    /**
     * Inactive discount record from storage or database.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function inactive($id)
    {
        if (!Gate::allows('discounts_inactive')) {
            return abort(401);
        }
        Discounts::inactiveRecord($id);

        return redirect()->route('admin.discounts.index');
    }

    /**
     * Active discount record from storage or database.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function active($id)
    {
        if (!Gate::allows('discounts_active')) {
            return abort(401);
        }
        Discounts::activeRecord($id);

        return redirect()->route('admin.discounts.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!Gate::allows('discounts_destroy')) {
            return abort(401);
        }
        Discounts::deleteRecord($id);
        return redirect()->route('admin.discounts.index');

    }

    /**
     * Display lcoation to add service for doctor.
     *
     * @param  int $id
     */
    public function displayDlocation($id)
    {
        if (!Gate::allows('discounts_allocate')) {
            return abort(401);
        }
        $discount = Discounts::find($id);

        $location = LocationsWidget::generateDropDownArray(Auth::User()->account_id);

        $discount_has_location = DiscountHasLocations::where('discount_id', '=', $discount->id)->get();

        return view('admin.discounts.location', compact('discount', 'location', 'discount_has_location'));
    }

    /**
     * display services against location id.
     *
     * @param  request
     */
    public function getDservices(Request $request)
    {
        if (!Gate::allows('discounts_allocate')) {
            return abort(401);
        }
        $discount_info = Discounts::find($request->discount_id);

        if($discount_info->discount_type == Config::get('constants.Service')){
            $serive = ServiceWidget::generateServiceArrayArray($request, Auth::User()->account_id);
        } else {
            $serive = ServiceWidget::generateServiceArrayConsultancy($request, Auth::User()->account_id);
        }
        return response()->json(array(
            'status' => true,
            'd' => $serive,
            'locaiton_id_1' => $request->id,
        ));
    }

    /**
     * save services against location id.
     *
     * @param  $request
     */
    public function saveDservices(Request $request)
    {
        if (!Gate::allows('discounts_allocate')) {
            return abort(401);
        }

        $myString = $request->id;
        $myArray = explode(',', $myString);
        $data = [];

        $data['discount_id'] = $request->discount_id;
        $data['location_id'] = $myArray[0];
        $data['service_id'] = $myArray[1];

        $checked = DiscountHasLocations::where([
            ['location_id', '=', $myArray[0]],
            ['service_id', '=', $myArray[1]],
            ['discount_id', '=', $request->discount_id]
        ])->get();

        if (count($checked) == '0') {

            $record = DiscountHasLocations::create($data);

            $record_location_name = $record->location->city->name . "-" . $record->location->name;
            $record_service_name = $record->service->name;

            $myarray = ['record' => $record, 'record_locaiton_name' => $record_location_name, 'record_service_name' => $record_service_name];

            return response()->json(array(
                'status' => true,
                'mydata' => $myarray
            ));

        } else {
            return response()->json(array(
                'status' => false,
                'mydata' => null
            ));
        }
    }

    /**
     * delete serive
     *
     * @param  request
     */
    public function deleteDservice(Request $request)
    {

        if (!Gate::allows('discounts_allocate')) {
            return abort(401);
        }

        DiscountHasLocations::find($request->id)->delete();
        return response()->json($request->id);

    }
}
