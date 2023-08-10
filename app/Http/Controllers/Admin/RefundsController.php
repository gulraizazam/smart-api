<?php

namespace App\Http\Controllers\Admin;

use Validator;
use Carbon\Carbon;
use App\Helpers\ACL;
use App\Models\User;
use App\Models\Refunds;
use App\Helpers\Filters;
use App\Models\Packages;
use App\Models\Settings;
use App\Models\Locations;
use App\Models\Appointments;
use App\Models\PaymentModes;
use Illuminate\Http\Request;
use App\Models\PackageBundles;
use App\Models\PackageService;
use App\HelperModule\ApiHelper;
use App\Models\PackageAdvances;
use App\Helpers\GeneralFunctions;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class RefundsController extends Controller
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
        if (! Gate::allows('refunds_manage')) {
            return abort(401);
        }

        return view('admin.refunds.index');
    }

    /**
     * Display a listing of Refunds.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request, $id = false)
    {
        try {

            $filename = 'plansrefunds';

            $filters = getFilters($request->all());

            $apply_filter = checkFilters($filters, $filename);

            $records = [];
            $records['data'] = [];

            // Get Total Records
            $iTotalRecords = PackageAdvances::getTotalRefundedRecords($request, Auth::User()->account_id, $id, $apply_filter, $filename);

            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $packages = PackageAdvances::getRefundedRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $id, $apply_filter, $filename);

            $records = $this->getFiltersData($records, $filename);
          
            if ($packages) {
                foreach ($packages as $package) {
                    
                    $cash_receive = PackageAdvances::where([
                        ['package_id', '=', $package->package_id],
                        ['cash_flow', '=', 'in'],
                        ['is_cancel', '=', '0'],
                        ['is_setteled', '=', '0']
                    ])->sum('cash_amount');
                    
                    $refunded_amount = PackageAdvances::where([
                        'package_id' => $package->package_id,
                        'cash_flow' => 'out',
                        'is_cancel' => '0',
                        'is_refund' => '1',
                    ])->sum('cash_amount');
                    $refunded_latest_date = PackageAdvances::where([
                        'package_id' => $package->package_id,
                        'cash_flow' => 'out',
                        'is_cancel' => '0',
                        'is_refund' => '1',
                    ])->latest()->first();
                    $is_case_setteled = PackageAdvances::where([
                        'package_id' =>  $package->package_id,
                        'cash_flow' => 'out',
                        'is_setteled' =>1
                    ])->first();

                    
                    if ($refunded_amount != 0) {

                        $records['data'][] = [
                            'id' => $package->package_id ?? 0,
                            'patient_id' => $package->user ? GeneralFunctions::patientSearchStringAdd($package->user->id) : '-',
                            'name' => $package->user?->name ?? '-',
                            'phone' => $package->user ? GeneralFunctions::prepareNumber4Call($package->user->phone) : '-',
                            'package_id' => $package?->package_id ?? '-',
                            'location_id' => $package->location->city->name.'-'.$package->location?->name,
                            'total' => number_format($package->total_price),
                            'cash_receive' => number_format($cash_receive),
                            'refunded' =>$refunded_amount,
                            'case_setteled' => isset($is_case_setteled) && $is_case_setteled->is_setteled == 1 ? 'Yes' : 'No',
                            'created_at' => $refunded_latest_date ? Carbon::parse($refunded_latest_date->created_at)->format('F j,Y h:i A') : Carbon::parse($package->created_at)->format('F j,Y h:i A'),
                        ];
                    } else {
                        $iTotalRecords--;
                    }
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
                'delete' => Gate::allows('refunds_destroy'),
                'active' => Gate::allows('refunds_active'),
                'inactive' => Gate::allows('refunds_inactive'),
                'refund' => Gate::allows('refunds_refund'),
            ];

            $patient_id = request('patient_id');
            if (isset($patient_id)) {
                $records['permissions'] = [
                    'refund' => Gate::allows('patients_refund_refund'),
                ];
            }

            return ApiHelper::apiDataTable($records);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    private function getFiltersData($records, $filename = 'plansrefunds')
    {

        $filters = Filters::all(Auth::User()->id, $filename);

        if ($user_id = Filters::get(Auth::User()->id, $filename, 'patient_id')) {
            $patient = User::where([
                'id' => $user_id,
            ])->first();
            if ($patient) {
                $patient = $patient->toArray();
            }
        } else {
            $patient = [];
        }
        $package_id = Filters::get(Auth::User()->id, $filename, 'package_id');
        if ($package_id) {
            $package = Packages::where([
                'id' => $package_id,
            ])->first();
            if ($package) {
                $package = $package->toArray();
            }
        } else {
            $package = [];
        }

        $locations = Locations::getActiveSorted(ACL::getUserCentres(), 'full_address');

        $records['active_filters'] = $filters;

        $records['filter_values'] = [
            'package' => $package,
            'locations' => $locations,
            'patient' => $patient,
        ];

        return $records;
    }

    /**
     * Show the form for create refund.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refund_create($id)
    {
        if (! Gate::allows('refunds_refund')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $return_tax_amount = '';

        $package_information = Packages::find($id);
        

        /*calculation for back date refund entry*/
        $package_advance_last_in = PackageAdvances::where([
            ['cash_flow', '=', 'in'],
            ['is_setteled', '=', '0'],
            ['cash_amount', '>', 0],
            ['package_id', '=', $package_information->id],
        ])->orderBy('created_at', 'desc')->first();
     
        $date_backend = date('Y-m-d', strtotime($package_advance_last_in->created_at));
        /*end*/

        /*first need to tax percentage*/
        $bundle_information = PackageBundles::where('package_id', '=', $id)->first();
        
        $tax_percentage = $bundle_information->tax_percenatage ?? '';
        /*ans is :: 16.0*/

        $is_adjustment_amount = 0;

        /*Give amount if already some amount refund*/
        $package_is_refunded_amount = PackageAdvances::where([
            'package_id' => $id,
            'cash_flow' => 'out',
            'is_refund' => '1',
            'is_tax' => '0',
        ])->sum('cash_amount');
        $package_is_setteled = PackageAdvances::where([
            'package_id' => $id,
            'cash_flow' => 'out',
            'is_setteled' => '1',
            'is_tax' => '0',
        ])->sum('cash_amount');
        
        /*ans is :: 0 */
       $amount_to_refund = $package_is_refunded_amount + $package_is_setteled;
        /*Document charges*/
        $documentationcharges = Settings::where('slug', '=', 'sys-documentationcharges')->first();
        /*ans is :: 10*/

        /*Give amount that patient give as advance of treatment plan*/
        $package_cash_receive = PackageAdvances::where([
            ['package_id', '=', $id],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
            ['is_setteled', '=', '0'],
        ])->sum('cash_amount');
        
        /*ans is :: 300*/
       
        if ($package_cash_receive) {
            /*Give amount that patient consume*/
            $package_service_originalPrice_consumed = PackageService::where([
                ['package_id', '=', $id],
                ['is_consumed', '=', '1'],
            ])->sum('price');
           
            /*ans is :: 240*/
            
            /*Consume amount tax calculate*/
            $cosume_amount_tax = 0; //$package_service_originalPrice_consumed*($tax_percentage/100);
            /*ans is :: 38.4*/

            $refund_1 = $package_service_originalPrice_consumed + $cosume_amount_tax + $documentationcharges->data;
           
            $refundable_amount = ceil(($package_cash_receive - $refund_1) - $amount_to_refund);
            
        }

        if ($refundable_amount > 0) {
            /*consume final price with tax*/
            $package_service_Price_consumed_tax = PackageService::where([
                ['package_id', '=', $id],
                ['is_consumed', '=', '1'],
            ])->sum('tax_including_price');
            /*ans is :: 232*/

            $package_service_Price_consumed_without_tax = PackageService::where([
                ['package_id', '=', $id],
                ['is_consumed', '=', '1'],
            ])->sum('tax_exclusive_price');
            /*ans is :: 200*/

            /*Tax amount that given from customer*/
            $given_tax_amount = $package_service_Price_consumed_tax - $package_service_Price_consumed_without_tax;
            /*ans is :: 32*/

            $return_tax_amount = ($cosume_amount_tax - $given_tax_amount);
            /*ans is 6.4*/

            $cal_adjustment_final = $package_service_Price_consumed_tax + ($package_cash_receive - $refund_1);
            /*ans is 248.6*/

            $is_adjustment_amount = ceil(($package_cash_receive - $cal_adjustment_final) - $return_tax_amount);

            $return_tax_amount = ceil($return_tax_amount);

        }
        if ($refundable_amount < 0) {
            $refundable_amount = 0;
        }
        $package_is_adjuestment_amount = PackageAdvances::where([
            ['package_id', '=', $id],
            ['cash_flow', '=', 'out'],
            ['is_adjustment', '=', '1'],
        ])->sum('cash_amount');

        if ($package_is_adjuestment_amount == 0) {
            $document = true;
        } else {
            $document = false;
        }
       
        $paymentmodes = PaymentModes::where('name' , "!=" , "Settle Amount")->get()->pluck('name', 'id');
        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'id' => $id,
            'refundable_amount' => $refundable_amount,
            'cash_amount' => $package_cash_receive,
            'is_adjustment_amount' => $is_adjustment_amount,
            'documentationcharges' => $documentationcharges,
            'document' => $document,
            'return_tax_amount' => $return_tax_amount,
            'date_backend' => $date_backend,
            'paymentmodes' => $paymentmodes,
            
        ]);
    }

    /**
     * Store a newly created Permission in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
       
        if (! Gate::allows('refunds_refund')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $validator = $this->verifyFields($request);
       
        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        $record = Refunds::createRecord($request, Auth::User()->account_id);
        if($record == 'setteled'){
            return ApiHelper::apiResponse($this->success, 'Plan is already settled. you can not refund amount against this plan.', false);
        }else if($record == 'amountexceed'){
            return ApiHelper::apiResponse($this->success, 'You can not refund amount more than amount received.', false);
        } else{
            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        }
        
        return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
    }

    /*
     *Display the detail of patient ledger
     *
     * @param patient id
     *
     * @return information of patient ledger
     */
    public function detail($id)
    {

        if (! Gate::allows('refunds_manage')) {
            return abort(401);
        }
        $patient_name = User::find($id);
        $package_advances = PackageAdvances::where([
            ['patient_id', '=', $id],
            ['cash_amount', '!=', '0'],
        ])->get();

        return view('admin.refunds.detail', compact('package_advances', 'patient_name'));

    }

    /**
     * Validate form fields
     *
     * @return Validator $validator;
     */
    protected function verifyFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'refund_amount' => ['required', 'numeric', 'regex:/^[0-9]+$/'],
            'refund_note' => 'required',
            'package_id' =>'required',
            'payment_mode_id' =>'required',

            
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function nonplansindex()
    {
        if (! Gate::allows('refunds_manage')) {
            return abort(401);
        }

        $filters = Filters::all(Auth::User()->id, 'nonplansrefunds');

        if ($user_id = Filters::get(Auth::User()->id, 'nonplansrefunds', 'patient_id')) {
            $patient = User::where([
                'id' => $user_id,
            ])->first();
            if ($patient) {
                $patient = $patient->toArray();
            }
        } else {
            $patient = [];
        }

        return view('admin.nonplansrefunds.index', compact('filters', 'patient'));
    }

    /**
     * Display a listing of non plans Refunds.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     */
    public function nonplansdatatable(Request $request)
    {
        try {

            $filename = 'nonplansrefunds';

            $filters = getFilters($request->all());

            $apply_filter = checkFilters($filters, $filename);

            $records = [];
            $records['data'] = [];

            // Get Total Records
            $data = Refunds::getTotalRecordsnonplansrefunds($request, Auth::User()->account_id, false, $apply_filter);

            $iTotalRecords = $data['iTotalRecords'];

            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $nonplansrefunds = $data['nonplansrefunds'];

            $records = $this->getFilters($records);

            if ($nonplansrefunds) {
                foreach ($nonplansrefunds as $nonplansrefunds) {
                    $appointmentinformation = Appointments::where('id', '=', $nonplansrefunds['appointment_id'])->first();
                    $records['data'][] = [
                        'patient_id' => \App\Helpers\GeneralFunctions::patientSearchStringAdd($appointmentinformation->patient_id),
                        'name' => $appointmentinformation->name,
                        'doctor' => $appointmentinformation->doctor->name,
                        'region' => $appointmentinformation->region->name,
                        'city' => $appointmentinformation->city->name,
                        'location' => $appointmentinformation->location->name,
                        'service' => $appointmentinformation->service->name,
                        'type' => $appointmentinformation->appointment_type->name,
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

            return ApiHelper::apiDataTable($records);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    private function getFilters($records)
    {

        $records['active_filters'] = Filters::all(Auth::User()->id, 'nonplansrefunds');

        return $records;

    }

    /**
     * Show the form for create refund for non plans refunds.
     *
     * @return \Illuminate\Http\Response
     */
    public function nonplans_refund_create($packageadvance_id)
    {
        if (! Gate::allows('refunds_refund')) {
            return abort(401);
        }
        $package_advance_information = PackageAdvances::find($packageadvance_id);

        /*calculation for back date refund entry*/
        $date_backend = date('Y-m-d', strtotime($package_advance_information->created_at));
        /*end*/

        $package_advance_id = $package_advance_information->id;

        $patient_id = $package_advance_information->patient_id;

        $documentationinformation = Settings::where('slug', '=', 'sys-documentationcharges')->first();

        $package_advance_adjustemnt_amount = PackageAdvances::where([
            ['patient_id', '=', $package_advance_information->patient_id],
            ['cash_flow', '=', 'out'],
            ['appointment_id', '=', $package_advance_information->appointment_id],
            ['is_adjustment', '=', '1'],
        ])->whereNull('package_id')->sum('cash_amount');

        if ($package_advance_adjustemnt_amount == 0) {
            $documentationcharges = $documentationinformation->data;
            $document = true;
        } else {
            $documentationcharges = 0;
            $document = false;
        }

        $singlepatient_cash_in = PackageAdvances::where([
            ['patient_id', '=', $package_advance_information->patient_id],
            ['cash_flow', '=', 'in'],
            ['appointment_id', '=', $package_advance_information->appointment_id],
        ])->whereNull('package_id')->sum('cash_amount');

        $singlepatient_cash_out = PackageAdvances::where([
            ['patient_id', '=', $package_advance_information->patient_id],
            ['cash_flow', '=', 'out'],
            ['appointment_id', '=', $package_advance_information->appointment_id],
        ])->whereNull('package_id')->sum('cash_amount');

        $refundable_amount = $singlepatient_cash_in - $singlepatient_cash_out - $documentationcharges;

        if ($refundable_amount < 0) {

            $refundable_amount == 0;

        } else {

            $is_adjustment_amount = $documentationcharges;
        }

        return view('admin.nonplansrefunds.create_refund', compact('patient_id', 'refundable_amount', 'is_adjustment_amount', 'documentationcharges', 'package_advance_id', 'document', 'date_backend'));
    }

    /**
     * Store refunds for non plans refunds
     *
     * @return \Illuminate\Http\Response
     */
    public function nonplans_refund_store(Request $request)
    {
        if (! Gate::allows('refunds_refund')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }

        if (Refunds::createRecordfornonplans($request, Auth::User()->account_id)) {

            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');

        }

        return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
    }
    
}
