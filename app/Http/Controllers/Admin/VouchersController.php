<?php

namespace App\Http\Controllers\Admin;

use PHPUnit\Exception;
use App\Helpers\Filters;
use App\Models\Locations;
use App\Models\Discounts;
use App\Helpers\NodesTree;
use Illuminate\Http\Request;
use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\DiscountHasLocations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Helpers\Widgets\ServiceWidget;
use Illuminate\Support\Facades\Config;
use App\Helpers\Widgets\LocationsWidget;
use Illuminate\Support\Facades\Validator;

class VouchersController extends Controller
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
        if (!Gate::allows('voucher_types_manage')) {
            return abort(401);
        }

        return view('admin.voucherTypes.index');
    }

    /**
     * Show the form for creating a new Discount.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        if (!Gate::allows('voucher_types_create')) {
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

        if (!Gate::allows('voucher_types_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
           
            $validator = $this->verifyFields($request);
            

            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
            }
            $data = $request->all();
            $data['account_id'] = Auth::User()->account_id;
            

            if ($request->active == null) {
                $data['active'] = '0';
            }

            if ($request->start <= $request->end) {
                $data['type'] = 'Fixed';
                $data['discount_type'] = 'voucher';
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

            $filename = 'vouchers';
            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, $filename);

            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $vouchers = Voucher::whereIn('id', $ids);
                if ($vouchers) {
                    $vouchers->delete();
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            $where = $this->applyFilters($filters, $apply_filter, $filename);

            $total_query = Discounts::select('id')
            ->where('discount_type', 'voucher');
            if (count($where)) {
               
                $total_query->where($where);
                
            }
            $iTotalRecords = $total_query->count();

            [$orderBy, $order] = getSortBy($request);

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $query = Discounts::select('*')->where('discount_type', 'voucher');
            if ($request->get('startdate') && $request->get('startdate') != '') {
                $query->whereDate('start', '>=', $request->get('startdate'));
            }
            if ($request->get('enddate') && $request->get('enddate') != '') {
                $query->whereDate('end', '<=', $request->get('enddate'));
            }

            if (count($where)) {
               
                $query->where($where);
               
            }

            $vouchers = $query->limit($iDisplayLength)->offset($iDisplayStart)->orderby('created_at', 'desc')->get();

            $records = $this->getFiltersData($records, $filename);

            if ($vouchers) {

                $records['data'] = $vouchers;

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
                'edit' => Gate::allows('voucher_types_edit'),
                'delete' => Gate::allows('voucher_types_destroy'),
                'active' => Gate::allows('voucher_types_active'),
                'inactive' => Gate::allows('voucher_types_inactive'),
                'create' => Gate::allows('voucher_types_create'),
                'allocate' => Gate::allows('voucher_types_allocate'),
                'assign' => Gate::allows('voucher_types_assign'),
            ];

            return ApiHelper::apiDataTable($records);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    private function applyFilters($filters, $apply_filter, $filename = 'vouchers')
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
                '%' . $filters['name'] . '%',
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
                        '%' . Filters::get(Auth::User()->id, $filename, 'name') . '%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'created_from')) {
            $where[] = [
                'created_at',
                '>=',
                $filters['created_from'] . ' 00:00:00',
            ];
            Filters::put(Auth::User()->id, $filename, 'created_from', $filters['created_from'] . ' 00:00:00');
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
                $filters['created_to'] . ' 23:59:59',
            ];
            Filters::put(Auth::User()->id, $filename, 'created_to', $filters['created_to'] . ' 23:59:59');
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
        if (!Gate::allows('voucher_types_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            $voucher = Discounts::getData($id);

            if ($voucher == null) {

                return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
            } else {

                $voucherServices = explode(',', $voucher->service_id);

                if (!$voucherServices) {

                    $voucherServices = [];
                }

                /* Create Nodes with Parents */
                $Services = ServiceWidget::generateServiceArrayDiscount($id, Auth::User()->account_id);

                $locations = Locations::getActiveSorted();

                if ($voucher) {
                    $Voucher = $voucher->toArray();

                    if ($Voucher['start']) {
                        $Voucher['start'] = $voucher->dateFormat($voucher['start'], 'Y-m-d');
                    }
                    if ($Voucher['end']) {
                        $Voucher['end'] = $voucher->dateFormat($voucher['end'], 'Y-m-d');
                    }
                }

                // Check if voucher is assigned to any patient
                $isAssigned = \App\Models\UserVouchers::where('voucher_id', $id)->exists();

                return ApiHelper::apiResponse($this->success, 'Record found', true, [
                    'voucher' => $Voucher ?? $voucher,
                    'locations' => $locations,
                    'services' => $Services,
                    'is_assigned' => $isAssigned,

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

        if (!Gate::allows('voucher_types_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }

        $data = $request->all();

        // Check if voucher is assigned to any patient
        $isAssigned = \App\Models\UserVouchers::where('voucher_id', $id)->exists();

        // If voucher is assigned, prevent name change
        if ($isAssigned) {
            $currentVoucher = Discounts::find($id);
            if ($currentVoucher && $request->name !== $currentVoucher->name) {
                return ApiHelper::apiResponse($this->success, 'Cannot change voucher name as it is already assigned to patients.', false);
            }
            // Remove name from update data to prevent accidental changes
            unset($data['name']);
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
        if (!Gate::allows('voucher_types_active')) {
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
        if (!Gate::allows('voucher_types_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            // Check if voucher is assigned to any patient
            $isAssigned = \App\Models\UserVouchers::where('voucher_id', $id)->exists();

            if ($isAssigned) {
                return ApiHelper::apiResponse($this->error, 'Cannot delete voucher type as it is already assigned to patients.', false);
            }

            $record = Discounts::deleteRecord($id);
            if ($record) {
                return ApiHelper::apiResponse($this->success, $record);
            } else {
                return ApiHelper::apiResponse($this->success, $record);
            }
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
        if (!Gate::allows('voucher_types_allocate')) {
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
        if (!Gate::allows('voucher_types_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $discount_info = Discounts::find($request->discount_id);
        
            $serive = ServiceWidget::generateServiceArrayConsultancy($request, Auth::User()->account_id);
        
        

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'services' => $serive,
            'locaiton_id_1' => $request->id,
        ]);
    }
    public function getDiscountServices(Request $request)
    {
        if (!Gate::allows('voucher_types_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $serive = ServiceWidget::generateServiceArrayDiscount($request, Auth::User()->account_id);
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
        if (!Gate::allows('voucher_types_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $myString = $request->id;
        $myArray = explode(',', $myString);
        $data = [];

        $data['discount_id'] = $request->voucher_id;
        $data['location_id'] = $myArray[0];
        $data['service_id'] = $myArray[1];

        $checked = DiscountHasLocations::where([
            ['location_id', '=', $myArray[0]],
            ['service_id', '=', $myArray[1]],
            ['discount_id', '=', $request->voucher_id],
        ])->count();

        if ($checked == '0') {

            $record = DiscountHasLocations::create($data);

            $record_location_name = $record->location->city->name . '-' . $record->location->name;
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

        if (!Gate::allows('voucher_types_allocate')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        DiscountHasLocations::find($request->id)->delete();

        return ApiHelper::apiResponse($this->success, 'Row deleted', true, [
            'id' => $request->id,
        ]);
    }
    public function getListing()
    {
        $vouchers = Discounts::where('discount_type', 'voucher')->pluck('name','id')->toArray();
        return response()->json(['data'=> $vouchers]);

    }

    /**
     * Assign voucher to patient from voucher types screen.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignToPatient(Request $request)
    {
        if (!Gate::allows('voucher_types_assign')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $validator = Validator::make($request->all(), [
                'voucher_id' => 'required',
                'patient_id' => 'required',
                'amount' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
            }

            // Check if voucher type exists and is active
            $voucherType = Discounts::find($request->voucher_id);

            if (!$voucherType) {
                return ApiHelper::apiResponse($this->error, 'Voucher type not found', false);
            }

            if (!$voucherType->active) {
                return ApiHelper::apiResponse($this->error, 'Cannot assign inactive voucher type to patient', false);
            }

            $checkVoucher = \App\Models\UserVouchers::where('user_id', $request->patient_id)
                ->where('voucher_id', $request->voucher_id)
                ->first();

            // if ($checkVoucher) {
            //     return ApiHelper::apiResponse($this->error, 'Voucher is already assigned to this patient', false);
            // }

            \App\Models\UserVouchers::create([
                'user_id' => $request->patient_id,
                'voucher_id' => $request->voucher_id,
                'amount' => $request->amount,
                'total_amount' => $request->amount
            ]);

            return ApiHelper::apiResponse($this->success, 'Voucher assigned to patient successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
