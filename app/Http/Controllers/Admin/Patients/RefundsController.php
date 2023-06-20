<?php

namespace App\Http\Controllers\Admin\Patients;

use App\HelperModule\ApiHelper;
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\Appointments;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\PackageService;
use App\Models\Refunds;
use App\Models\Settings;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Validator;

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
    public function index($id)
    {
        if (! Gate::allows('patients_refund_manage')) {
            return abort(401);
        }
        $patient = User::finduser($id);

        $package = Packages::get()->pluck('name', 'id');
        $package->prepend('All', '');

        $locations = Locations::getActiveSorted(ACL::getUserCentres(), 'full_address');
        $locations->prepend('All', '');

        $filters = Filters::all(Auth::User()->id, 'patient_refunds');

        if ($patient) {
            return view('admin.patients.card.refunds.index', compact('patient', 'package', 'locations', 'filters'));
        } else {
            return view('error_full');
        }
    }

    /**
     * Display a listing of Refunds.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request, $id)
    {

        $apply_filter = false;
        if ($request->get('action')) {
            $action = $request->get('action');
            if (isset($action[0]) && $action[0] == 'filter_cancel') {
                Filters::flush(Auth::User()->id, 'patient_refunds');
            } elseif ($action == 'filter') {
                $apply_filter = true;
            }
        }

        $records = [];
        $records['data'] = [];

        // Get Total Records
        $iTotalRecords = Packages::getTotalRecords($request, Auth::User()->account_id, $id, $apply_filter, 'patient_refunds');

        $iDisplayLength = intval($request->get('length'));
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->get('start'));
        $sEcho = intval($request->get('draw'));

        $packages = Packages::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $id, $apply_filter, 'patient_refunds');

        if ($packages) {
            foreach ($packages as $package) {
                $session_count = count(PackageBundles::where('package_id', '=', $package->id)->get());
                /*We discuss in future what happen next*/
                $cash_receive = PackageAdvances::where([
                    ['package_id', '=', $package->id],
                    ['cash_flow', '=', 'in'],
                    ['is_cancel', '=', '0'],
                ])->sum('cash_amount');
                if ($cash_receive != 0) {
                    $records['data'][] = [
                        'name' => $package->user->name,
                        'phone' => \App\Helpers\GeneralFunctions::prepareNumber4Call($package->user->phone),
                        'package_id' => $package->name,
                        'location_id' => $package->location->city->name.'-'.$package->location->name,
                        'session_count' => $session_count,
                        'total' => number_format($package->total_price),
                        'cash_receive' => number_format($cash_receive),
                        'created_at' => Carbon::parse($package->created_at)->format('F j,Y h:i A'),
                        'actions' => view('admin.patients.card.refunds.actions', compact('package'))->render(),
                    ];
                } else {
                    $iTotalRecords--;
                }
            }
        }
        $records['draw'] = $sEcho;
        $records['recordsTotal'] = $iTotalRecords;
        $records['recordsFiltered'] = $iTotalRecords;

        return response()->json($records);
    }

    /**
     * Show the form for create refund.
     *
     * @return \Illuminate\Http\Response
     */
    public function refund_create($id)
    {

        if (! Gate::allows('patients_refund_refund')) {
            return abort(401);
        }
        $package_information = Packages::find($id);

        /*calculation for back date refund entry*/
        $package_advance_last_in = PackageAdvances::where([
            ['cash_flow', '=', 'in'],
            ['cash_amount', '>', 0],
            ['package_id', '=', $package_information->id],
        ])->orderBy('created_at', 'desc')->first();
        $date_backend = date('Y-m-d', strtotime($package_advance_last_in->created_at));
        /*end*/

        /*first need to tax percentage*/
        $bundle_information = PackageBundles::where('package_id', '=', $id)->first();
        $tax_percentage = $bundle_information->tax_percenatage;
        /*ans is :: 16.0*/

        $is_adjustment_amount = 0;

        /*Give amount if already some amount refund*/
        $package_is_refunded_amount = PackageAdvances::where([
            ['package_id', '=', $id],
            ['cash_flow', '=', 'out'],
            ['is_refund', '=', '1'],
            ['is_tax', '=', '0'],
        ])->sum('cash_amount');
        /*ans is :: 0 */

        /*Document charges*/
        $documentationcharges = Settings::where('slug', '=', 'sys-documentationcharges')->first();
        /*ans is :: 10*/

        /*Give amount that patient give as advance of treatment plan*/
        $package_cash_receive = PackageAdvances::where([
            ['package_id', '=', $id],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
        ])->sum('cash_amount');
        /*ans is :: 300*/

        if ($package_cash_receive) {
            /*Give amount that patient consume*/
            $package_service_originalPrice_consumed = PackageService::where([
                ['package_id', '=', $id],
                ['is_consumed', '=', '1'],
            ])->sum('orignal_price');
            /*ans is :: 240*/

            /*Consume amount tax calculate*/
            $cosume_amount_tax = 0; //$package_service_originalPrice_consumed*($tax_percentage/100);
            /*ans is :: 38.4*/

            $refund_1 = $package_service_originalPrice_consumed + $cosume_amount_tax + $documentationcharges->data;

            $refundable_amount = ceil(($package_cash_receive - $refund_1) - $package_is_refunded_amount);
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

        return view('admin.patients.card.refunds.create_refund', compact('id', 'refundable_amount', 'is_adjustment_amount', 'documentationcharges', 'document', 'return_tax_amount', 'date_backend'));
    }

    /**
     * Store a newly created Permission in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! Gate::allows('patients_refund_refund')) {
            return abort(401);
        }
        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'patient_id' => $request->patient_id,
                'message' => $validator->messages()->all(),
            ]);
        }

        $package_information = Packages::find($request->package_id);

        if (Refunds::createRecord($request, Auth::User()->account_id)) {

            return response()->json([
                'status' => 1,
                'patient_id' => $package_information->patient_id,
                'message' => 'Record has been created successfully.',
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'patient_id' => $package_information->patient_id,
                'message' => 'Something went wrong, please try again later.',
            ]);
        }
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

        if (! Gate::allows('refunds_manage') || ! Gate::allows('users_manage')) {
            return abort(401);
        }
        $patient_name = User::find($id);
        $package_advances = PackageAdvances::where([
            ['patient_id', '=', $id],
            ['cash_amount', '!=', '0'],
        ])->get();

        return view('admin.patients.card.refunds.detail', compact('package_advances', 'patient_name'));

    }

    /**
     * Validate form fields
     *
     * @return Validator $validator;
     */
    protected function verifyFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'refund_amount' => 'required',
            'refund_note' => 'required',
        ]);
    }

    /**
     * Display a listing of the non plans refunds.
     *
     * @return \Illuminate\Http\Response
     */
    public function nonplansrefundsindex($id)
    {
        if (! Gate::allows('patients_refund_manage')) {
            return abort(401);
        }
        $patient = User::finduser($id);

        if ($patient) {

            return view('admin.patients.card.nonplansrefunds.index', compact('patient'));

        } else {

            return view('error_full');

        }
    }

    /**
     * Display a listing of Refunds for non plans in patient card.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     */
    public function nonplansdatatable(Request $request, $id)
    {
        $records = [];
        $records['data'] = [];

        // Get Total Records
        $data = Refunds::getTotalRecordsnonplansrefunds($request, Auth::User()->account_id, $id);

        $iTotalRecords = $data['iTotalRecords'];

        [$orderBy, $order] = getSortBy($request);

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $nonplansrefunds = $data['nonplansrefunds'];

        if ($nonplansrefunds) {
            foreach ($nonplansrefunds as $nonplansrefund) {
                $appointmentinformation = Appointments::where('id', '=', $nonplansrefund['appointment_id'])->first();
                $records['data'][] = [
                    'name' => $appointmentinformation->name,
                    'doctor' => $appointmentinformation->doctor->name,
                    'region' => $appointmentinformation->region->name,
                    'city' => $appointmentinformation->city->name,
                    'location' => $appointmentinformation->location->name,
                    'service' => $appointmentinformation->service->name,
                    'type' => $appointmentinformation->appointment_type->name,
                ];
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

        return ApiHelper::apiDataTable($records);
    }

    /**
     * Show the form for create refund for non plans refunds.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function nonplansrefundscreate($packageadvance_id)
    {

        if (! Gate::allows('patients_refund_refund')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
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

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'patient_id' => $patient_id,
            'refundable_amount' => $refundable_amount,
            'is_adjustment_amount' => $is_adjustment_amount,
            'documentationcharges' => $documentationcharges,
            'package_advance_id' => $package_advance_id,
            'document' => $document,
            'date_backend' => $date_backend,
        ]);

    }

    /**
     * Store a newly refunds for non plans refunds in patient card.
     *
     * @return \Illuminate\Http\Response
     */
    public function nonplansrefundsstore(Request $request)
    {
        if (! Gate::allows('patients_refund_refund')) {
            return abort(401);
        }
        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'patient_id' => $request->patient_id,
                'message' => $validator->messages()->all(),
            ]);
        }

        if (Refunds::createRecordfornonplans($request, Auth::User()->account_id)) {

            return response()->json([
                'status' => 1,
                'patient_id' => $request->patient_id,
                'message' => 'Record has been created successfully.',
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'patient_id' => $request->patient_id,
                'message' => 'Something went wrong, please try again later.',
            ]);
        }
    }
}
