<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Helpers\NodesTree;
use App\Helpers\Widgets\LocationsWidget;
use App\Helpers\Widgets\ServiceWidget;
use App\Http\Controllers\Controller;
use App\Models\DiscountHasLocations;
use App\Models\Discounts;
use App\Models\Locations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Exception;

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
        if (! Gate::allows('discounts_manage')) {
            return abort(401);
        }

        return view('admin.discounts.index');
    }

    /**
     * Show the form for creating a new Discount.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        if (! Gate::allows('discounts_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'discount_types' => config('constants.discount_types'),
                'discount_groups' => config('constants.discount_groups'),
                'amount_types' => config('constants.amount_types'),
            ]);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Store a newly created discount in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if (! Gate::allows('discounts_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
            }
            $data = $request->all();
            $data['account_id'] = Auth::User()->account_id;
            if ($request->slug == 'custom' || $request->slug == 'default') {
                $data['pre_days'] = 0;
                $data['post_days'] = 0;
            }

            if ($request->active == null) {
                $data['active'] = '0';
            }

            if ($request->start <= $request->end) {

                if (Discounts::createDiscount($data)) {

                    return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
                }

                return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);

            }

            return ApiHelper::apiResponse($this->success, 'Date range invalid, Kindly define again', false);

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
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {

        try {

            $records = [];
            $records['data'] = [];

            $filename = 'discounts';
            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, $filename);

            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $Discounts = Discounts::whereIn('id', $ids);
                if ($Discounts) {
                    $Discounts->delete();
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            $where = $this->applyFilters($filters, $apply_filter, $filename);

            $total_query = Discounts::select('id');
            if (count($where)) {
                if (\Illuminate\Support\Facades\Gate::allows('view_inactive_discounts')) {
                    $total_query->where($where);
                } else {
                    $total_query->where($where)->where('active', 1);
                }
            }
            $iTotalRecords = $total_query->count();

            [$orderBy, $order] = getSortBy($request);

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $query = Discounts::select('*');
            if ($request->get('startdate') && $request->get('startdate') != '') {
                $query->whereDate('start', '>=', $request->get('startdate'));
            }
            if ($request->get('enddate') && $request->get('enddate') != '') {
                $query->whereDate('end', '<=', $request->get('enddate'));
            }

            if (count($where)) {
                if (\Illuminate\Support\Facades\Gate::allows('view_inactive_discounts')) {
                    $query->where($where);
                } else {
                    $query->where($where)->where('active', 1);
                }
            }

            $Discounts = $query->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy, $order)->get();

            $records = $this->getFiltersData($records, $filename);

            if ($Discounts) {

                $records['data'] = $Discounts;

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
                'edit' => Gate::allows('discounts_edit'),
                'delete' => Gate::allows('discounts_destroy'),
                'active' => Gate::allows('discounts_active'),
                'inactive' => Gate::allows('discounts_inactive'),
                'create' => Gate::allows('discounts_create'),
                'allocate' => Gate::allows('discounts_allocate'),
            ];

            return ApiHelper::apiDataTable($records);

        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    private function applyFilters($filters, $apply_filter, $filename = 'discounts')
    {

        $where = [];

        if (Auth::user()->account_id && Auth::user()->account_id != '') {
            $where[] = [
                'account_id',
                '=',
                Auth::user()->account_id,
            ];
            Filters::put(Auth::User()->id, $filename, 'account_id', Auth::user()->account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'account_id')) {
                    $where[] = [
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'account_id'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'name')) {
            $where[] = [
                'name',
                'like',
                '%'.$filters['name'].'%',
            ];
            Filters::put(Auth::User()->id, $filename, 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'name');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'name')) {
                    $where[] = [
                        'name',
                        'like',
                        '%'.Filters::get(Auth::User()->id, $filename, 'name').'%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'type')) {
            $where[] = [
                'type',
                'like',
                '%'.$filters['type'].'%',
            ];
            Filters::put(Auth::User()->id, $filename, 'type', $filters['type']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'type');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'type')) {
                    $where[] = [
                        'type',
                        'like',
                        '%'.Filters::get(Auth::User()->id, $filename, 'type').'%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'amount')) {
            $where[] = [
                'amount',
                'like',
                '%'.$filters['amount'].'%',
            ];
            Filters::put(Auth::User()->id, $filename, 'amount', $filters['amount']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'amount');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'amount')) {
                    $where[] = [
                        'amount',
                        'like',
                        '%'.Filters::get(Auth::User()->id, $filename, 'amount').'%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'discount_type')) {
            $where[] = [
                'discount_type',
                '=',
                $filters['discount_type'],
            ];
            Filters::put(Auth::User()->id, $filename, 'discount_type', $filters['discount_type']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'discount_type');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'discount_type')) {
                    $where[] = [
                        'discount_type',
                        '=',
                        Filters::get(Auth::user()->id, $filename, 'discount_type'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'created_from')) {
            $where[] = [
                'created_at',
                '>=',
                $filters['created_from'].' 00:00:00',
            ];
            Filters::put(Auth::User()->id, $filename, 'created_from', $filters['created_from'].' 00:00:00');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_from');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_from')) {
                    $where[] = [
                        'created_at',
                        '>=',
                        Filters::get(Auth::User()->id, $filename, 'created_from'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'created_to')) {
            $where[] = [
                'created_at',
                '<=',
                $filters['created_to'].' 23:59:59',
            ];
            Filters::put(Auth::User()->id, $filename, 'created_to', $filters['created_to'].' 23:59:59');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_to');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_to')) {
                    $where[] = [
                        'created_at',
                        '<=',
                        Filters::get(Auth::User()->id, $filename, 'created_to'),
                    ];
                }
            }
        }
        if (hasFilter($filters, 'startdate')) {
            $where[] = [
                'start',
                '>=',
                $filters['startdate'],
            ];
            Filters::put(Auth::user()->id, $filename, 'startdate', $filters['startdate']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'startdate');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'startdate')) {
                    $where[] = [
                        'start',
                        '>=',
                        Filters::get(Auth::user()->id, $filename, 'startdate'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'enddate')) {
            $where[] = [
                'end',
                '<=',
                $filters['enddate'],
            ];
            Filters::put(Auth::user()->id, $filename, 'enddate', $filters['enddate']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'enddate');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'enddate')) {
                    $where[] = [
                        'end',
                        '<=',
                        Filters::get(Auth::user()->id, $filename, 'enddate'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'status')) {
            $where[] = [
                'active',
                '=',
                $filters['status'],
            ];
            Filters::put(Auth::user()->id, $filename, 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'status');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'status') == 0 || Filters::get(Auth::user()->id, $filename, 'status') == 1) {
                    if (Filters::get(Auth::user()->id, $filename, 'status') != null) {
                        $where[] = [
                            'active',
                            '=',
                            Filters::get(Auth::user()->id, $filename, 'status'),
                        ];
                    }
                }
            }
        }

        return $where;
    }

    private function getFiltersData($records, $filename)
    {

        $locations = Locations::getlocation();

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $records['active_filters'] = Filters::all(Auth::User()->id, $filename);

        $records['filter_values'] = [
            'services' => $Services,
            'locations' => $locations,
            'status' => config('constants.status'),
        ];

        return $records;

    }

    /**
     * Show the form for editing the specified discount information.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        if (! Gate::allows('discounts_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            $discount = Discounts::getData($id);

            if ($discount == null) {

                return ApiHelper::apiResponse($this->success, 'Resource not found.', false);

            } else {

                $discountServices = explode(',', $discount->service_id);

                if (! $discountServices) {

                    $discountServices = [];
                }
                /* Create Nodes with Parents */
                $parentGroups = new NodesTree();
                $parentGroups->current_id = -1;
                $parentGroups->build(0, Auth::User()->account_id, true, true);
                $parentGroups->toList($parentGroups, -1);

                $Services = $parentGroups->nodeList;

                $locations = Locations::getActiveSorted();

                if ($discount) {
                    $Discount = $discount->toArray();

                    if ($Discount['start']) {
                        $Discount['start'] = $discount->dateFormat($Discount['start'], 'Y-m-d');
                    }
                    if ($Discount['end']) {
                        $Discount['end'] = $discount->dateFormat($Discount['end'], 'Y-m-d');
                    }
                }

                return ApiHelper::apiResponse($this->success, 'Record found', true, [
                    'discount' => $Discount ?? $discount,
                    'locations' => $locations,
                    'services' => $Services,
                    'discount_services' => $discountServices,
                ]);
            }
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update the specified discount in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        if (! Gate::allows('discounts_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }

        $data = $request->all();

        if ($request->slug == 'custom' || $request->slug == 'default') {
            $data['pre_days'] = 0;
            $data['post_days'] = 0;
        }

        if ($request->active == null) {
            $data['active'] = '0';
        }

        if ($request->start <= $request->end) {

            if (Discounts::updateDiscount($data, $id)) {

                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');

            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);

        }

        return ApiHelper::apiResponse($this->success, 'Date range invalid, Kindly define again', false);

    }

    /**
     * Active discount record from storage or database.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        if (! Gate::allows('discounts_active')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            if ($request->status == 1) {
                $response = Discounts::activeRecord($request->id);
            } else {
                $response = Discounts::inactiveRecord($request->id);
            }

            if ($response) {
                return ApiHelper::apiResponse($this->success, 'Status has been changed successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Resource not found.', false);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        if (! Gate::allows('discounts_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            $record = Discounts::deleteRecord($id);

            return ApiHelper::apiResponse($this->success, $record);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }

    }

    /**
     * Display lcoation to add service for doctor.
     *
     * @param  int  $id
     */
    public function displayDlocation($id)
    {
        if (! Gate::allows('discounts_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            $discount = Discounts::find($id);

            $location = LocationsWidget::generateDropDownArray(Auth::User()->account_id);

            $discount_has_location = DiscountHasLocations::with(['service', 'location.city'])->where('discount_id', '=', $discount->id)->get();

            return ApiHelper::apiResponse($this->success, 'Service Allocated', true, [
                'discount' => $discount,
                'location' => $location,
                'discount_has_location' => $discount_has_location,
            ]);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);

        }
    }

    /**
     * display services against location id.
     *
     * @param  request
     */
    public function getDservices(Request $request)
    {
        if (! Gate::allows('discounts_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $discount_info = Discounts::find($request->discount_id);
        if ($discount_info->discount_type == Config::get('constants.Service')) {
            $serive = ServiceWidget::generateServiceArrayArray($request, Auth::User()->account_id);
        } else {
            $serive = ServiceWidget::generateServiceArrayConsultancy($request, Auth::User()->account_id);
        }

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'services' => $serive,
            'locaiton_id_1' => $request->id,
        ]);
    }

    /**
     * save services against location id.
     */
    public function saveDservices(Request $request)
    {
        if (! Gate::allows('discounts_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
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
            ['discount_id', '=', $request->discount_id],
        ])->count();

        if ($checked == '0') {

            $record = DiscountHasLocations::create($data);

            $record_location_name = $record->location->city->name.'-'.$record->location->name;
            $record_service_name = $record->service->name;

            $myarray = ['record' => $record, 'record_locaiton_name' => $record_location_name, 'record_service_name' => $record_service_name];

            return ApiHelper::apiResponse($this->success, 'Record Saved successfully.', true, $myarray);

        }

        return ApiHelper::apiResponse($this->success, 'Duplicate record found.', false);
    }

    /**
     * delete serive
     *
     * @param  request
     */
    public function deleteDservice(Request $request)
    {

        if (! Gate::allows('discounts_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        DiscountHasLocations::find($request->id)->delete();

        return ApiHelper::apiResponse($this->success, 'Row deleted', true, [
            'id' => $request->id,
        ]);

    }
}
