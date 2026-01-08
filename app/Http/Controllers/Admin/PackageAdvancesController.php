<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Http\Controllers\Controller;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\PaymentModes;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;

class PackageAdvancesController extends Controller
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
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function index()
    {
        if (! Gate::allows('finances_manage')) {

            return abort(401);
        }

        $paymentmodes = PaymentModes::get()->pluck('name', 'id');
        $paymentmodes->prepend('All', '');

        $package = Packages::get()->pluck('name', 'id');
        $package->prepend('All', '');

        $total_cash_in = PackageAdvances::where('cash_flow', '=', 'in')->sum('cash_amount');
        $total_cash_out = PackageAdvances::where('cash_flow', '=', 'out')->sum('cash_amount');

        $balance = $total_cash_in - $total_cash_out;

        /**
         * I make the response according to request type, api or
         * web otherwise we need to create extra route for api
         */
        return ApiHelper::makeResponse([
            'paymentmodes' => $paymentmodes,
            'package' => $package,
            'total_cash_in' => $total_cash_in,
            'total_cash_out' => $total_cash_out,
            'balance' => $balance,
        ], 'admin.packagesadvances.index');

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        if (! Gate::allows('finances_create')) {

            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $paymentmodes = PaymentModes::get()->pluck('name', 'id');
        $paymentmodes->prepend('Select Payment Mode', '');

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'paymentmodes' => $paymentmodes,
        ]);
    }

    /*
     * Get the packages against patient id
     *
     * */
    public function getpackages(Request $request)
    {

        $packageinfo = Packages::where('patient_id', '=', $request->id)->get();

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'packageinfo' => $packageinfo,
        ]);

    }

    /*
     * Get the packages information from packages advances
     *
     * */
    public function getpackagesinfo(Request $request)
    {
        $package_info = Packages::where('id', '=', $request->id)->first();
        /*We discuss in future what happen next*/
        $cash_amount = number_format(PackageAdvances::where([
            ['package_id', '=', $request->id],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
        ])->sum('cash_amount'));
        $cash_amount_sum = (filter_var($cash_amount, FILTER_SANITIZE_NUMBER_INT) + $request->cash_amount);
        $total_price = number_format($package_info->total_price);

        if ($cash_amount_sum <= $package_info->total_price) {
            $cash_amount_sum = number_format($cash_amount_sum);

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'cash_amount_sum' => $cash_amount_sum,
                'total_price' => $total_price,
            ]);
        }

        return ApiHelper::apiResponse($this->success, 'Record not found', false);

    }

    /*
     * Get the packages information from packages advances
     *
     */
    public function getpackagesinfo_update(Request $request)
    {
        $cash_receive = PackageAdvances::where([
            ['package_id', '=', $request->id],
            ['cash_flow', '=', 'in'],
        ])->sum('cash_amount');
        $cash_receive_forupdate = $cash_receive - $request->cash_amount_update;

        $cash_amount_sum = $cash_receive_forupdate + $request->cash_amount;

        $total_price = filter_var($request->total_price, FILTER_SANITIZE_NUMBER_INT);

        if ($cash_amount_sum <= $total_price) {
            $cash_amount_sum = number_format($cash_amount_sum);
            $total_price = number_format($total_price);

            return response()->json([
                'status' => true,
                'cash_amount_sum' => $cash_amount_sum,
                'total_price' => $total_price,
            ]);
        } else {
            return response()->json([
                'status' => false,
            ]);
        }

    }

    /*
     * save the information in packages advances
     * */
    public function savepackagesadvances(Request $request)
    {
        $cash_amount = PackageAdvances::where([
            ['package_id', '=', $request->package_id],
            ['cash_flow', '=', 'in'],
        ])->sum('cash_amount');

        $cash_amount_check = $cash_amount + $request->cash_amount;
        $total_price = filter_var($request->total_price, FILTER_SANITIZE_NUMBER_INT);

        if ($cash_amount_check <= $total_price) {

            $data['cash_flow'] = 'in';
            $data['cash_amount'] = $request->cash_amount;
            $data['patient_id'] = $request->patient_id;
            $data['payment_mode_id'] = $request->payment_mode_id;
            $data['account_id'] = Auth::user()->account_id;
            $data['created_by'] = Auth::user()->id;
            $data['updated_by'] = Auth::user()->id;
            $data['package_id'] = $request->package_id;

            $package_advances = PackageAdvances::createRecord_onlyadvances($data);

            if ($package_advances) {
                return ApiHelper::apiResponse($this->success, 'Record saved successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Failed to save the record.', false);

        }

        return ApiHelper::apiResponse($this->success, 'Cash amount should be less then or equal to total amount.', false);
    }

    /**
     * Display a User As package advances  in datatables.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        $jason_var = 'packageAdvances';

        $filters = getFilters($request->all());
        $apply_filter = checkFilters($filters, $jason_var);

        $records = [];
        $records['data'] = [];

        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $packagesadvances = PackageAdvances::getBulkData($ids);
            if ($packagesadvances) {
                foreach ($packagesadvances as $packageadvances) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! PackageAdvances::isChildExists($packageadvances->id, Auth::User()->account_id)) {
                        $packageadvances->delete();
                    }
                }
            }
            $records['status'] = true; // pass custom message(useful for getting status of group actions)
            $records['message'] = 'Records has been deleted successfully!'; // pass custom message(useful for getting status of group actions)
        }

        $patient_id = $this->getPatientId();
        // Get Total Records
        $iTotalRecords = PackageAdvances::getTotalRecords($request, Auth::user()->account_id, $patient_id, $apply_filter, $jason_var);

        [$orderBy, $order] = getSortBy($request, 'created_at', 'DESC');
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $packagesadvances = PackageAdvances::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $patient_id, $apply_filter, $jason_var);
        $records = $this->getFilterData($records, $jason_var);

        if ($packagesadvances) {
            $balance = 0;
            foreach ($packagesadvances as $packagesadvances) {

                switch ($packagesadvances->cash_flow) {
                    case 'in':
                        $balance = $balance + $packagesadvances->cash_amount;
                        break;
                    case 'out':
                        $balance = $balance - $packagesadvances->cash_amount;
                        break;
                    default:
                        $balance = $balance - $packagesadvances->cash_amount;
                        break;
                }
                if ($packagesadvances->package_id) {
                    $transtype = Config::get('constants.trans_type.advance_in');
                }

                if ($packagesadvances->invoice_id && $packagesadvances->cash_flow == 'in') {
                    $transtype = Config::get('constants.trans_type.advance_in');
                }

                if ($packagesadvances->is_adjustment == '1') {
                    $transtype = Config::get('constants.trans_type.adjustment');
                }

                if ($packagesadvances->is_cancel == '1') {
                    $transtype = Config::get('constants.trans_type.invoice_cancel');
                }
                if ($packagesadvances->invoice_id && $packagesadvances->cash_flow == 'out') {
                    $transtype = Config::get('constants.trans_type.invoice_create');
                }
                if ($packagesadvances->is_refund == '1') {
                    $transtype = Config::get('constants.trans_type.refund_in');
                }
                if ($packagesadvances->is_tax == '1') {
                    $transtype = Config::get('constants.trans_type.tax_out');
                }
                if ($packagesadvances->cash_flow == 'in') {
                    $cash_in = number_format($packagesadvances->cash_amount);
                    $cash_out = '-';
                } else {
                    $cash_out = number_format($packagesadvances->cash_amount);
                    $cash_in = '-';
                }
                $records['data'][] = [
                    'patient_id' => GeneralFunctions::patientSearchStringAdd($packagesadvances->user->id),
                    'patient' => $packagesadvances->user->name,
                    'phone' => Gate::allows('contact') ? GeneralFunctions::prepareNumber4Call($packagesadvances->user->phone) : '***********',
                    'transtype' => $transtype,
                    'cash_in' => $cash_in,
                    'cash_out' => $cash_out,
                    'balance' => number_format($balance),
                    'cash_amount' => '1',
                    'created_at' => Carbon::parse($packagesadvances->created_at)->format('F j,Y h:i A'),
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
    }

    private function getPatientId()
    {

        $patient_id = false;
        $id = request('id');
        if (isset($id) && $id != '') {
            $patient_id = $id;
        }

        return $patient_id;
    }

    private function getFilterData($records, $filename)
    {

        $filters = Filters::all(Auth::User()->id, $filename);

        if ($user_id = Filters::get(Auth::User()->id, 'packageAdvances', 'patient_id')) {
            $patient = User::where([
                'id' => $user_id,
            ])->first();
            if ($patient) {
                $patient = $patient->toArray();
            }
        } else {
            $patient = [];
        }

        if (isset($filters['created_from'])) {
            $filters['created_from'] = date('Y-m-d', strtotime($filters['created_from']));
        }
        if (isset($filters['created_to'])) {
            $filters['created_to'] = date('Y-m-d', strtotime($filters['created_to']));
        }

        $records['active_filters'] = $filters;

        $records['filter_values'] = [
            'patient' => $patient,
        ];

        return $records;

    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function inactive($id)
    {
        if (! Gate::allows('finances_manage')) {
            return abort(401);
        }
        PackageAdvances::inactiveRecord($id);

        return redirect()->route('admin.packagesadvances.index');
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function active($id)
    {
        if (! Gate::allows('finances_manage')) {
            return abort(401);
        }
        PackageAdvances::activeRecord($id);

        return redirect()->route('admin.packagesadvances.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! Gate::allows('finances_manage')) {

            return abort(401);
        }
        $packageadvances = PackageAdvances::find($id);
        $package_info = Packages::where('patient_id', '=', $packageadvances->patient_id)->get();
        $total_price_cal = Packages::where('id', '=', $packageadvances->package_id)->first();
        $total_price = number_format($total_price_cal->total_price);
        $cash_sum = PackageAdvances::where([
            ['package_id', '=', $packageadvances->package_id],
            ['cash_flow', '=', 'in'],
        ])->sum('cash_amount');
        $cash_total_amount = number_format($cash_sum - $packageadvances->cash_amount);
        $total_amount = number_format(($cash_sum - $packageadvances->cash_amount) + $packageadvances->cash_amount);

        $leads = User::where('user_type_id', '=', Config::get('constants.patient_id'))->get();
        $paymentmodes = PaymentModes::get();

        return view('admin.packagesadvances.edit', compact('leads', 'paymentmodes', 'packageadvances', 'package_info', 'total_price', 'cash_total_amount', 'total_amount'));
    }

    /*
     * update package advance information
     * */
    public function updatepackagesadvances(Request $request)
    {
        $package_advances_info = PackageAdvances::find($request->package_advance_id);
        $cash_amount_sum = PackageAdvances::where([
            ['package_id', '=', $request->package_id],
            ['cash_flow', '=', 'in'],
        ])->sum('cash_amount');
        $cash_amount = $cash_amount_sum - $package_advances_info->cash_amount;
        $cash_amount_check = $cash_amount + $request->cash_amount;
        $total_price = filter_var($request->total_price, FILTER_SANITIZE_NUMBER_INT);

        if ($cash_amount_check <= $total_price) {

            $data['cash_flow'] = 'in';
            $data['cash_amount'] = $request->cash_amount;
            $data['patient_id'] = $request->patient_id;
            $data['payment_mode_id'] = $request->payment_mode_id;
            $data['account_id'] = Auth::User()->account_id;
            $data['created_by'] = Auth::User()->id;
            $data['updated_by'] = Auth::User()->id;
            $data['package_id'] = $request->package_id;

            $package_advances = PackageAdvances::updateRecord_onlyadvances($data, $request->package_advance_id);

            return response()->json([
                'status' => true,
            ]);
        } else {
            return response()->json([
                'status' => false,
            ]);
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
        if (! Gate::allows('finances_manage')) {
            return abort(401);
        }

        PackageAdvances::deleteRecord($id);

        return redirect()->route('admin.packagesadvances.index');

    }

    /**
     *cancel the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function cancel($id)
    {
        if (! Gate::allows('finances_manage')) {
            return abort(401);
        }
        $packageadvances = PackageAdvances::CancelRecord($id, Auth::User()->account_id);

        $package_advnaces = (PackageAdvances::find($id))->toArray();
        if ($package_advnaces['cash_flow'] == 'in') {

            $package_advnaces['cash_flow'] = 'out';
            $package_advnaces['is_cancel'] = '1';
        } else {
            $package_advnaces['cash_flow'] = 'in';
            $package_advnaces['is_cancel'] = '1';
        }
        $advance_cancel = PackageAdvances::createRecord_onlyadvances($package_advnaces);

        return redirect()->route('admin.packagesadvances.index');
    }

    /*
     * Function for update location id in package advances
     */

    public function update_record_final()
    {
        $package_adavances_data = PackageAdvances::get();
        foreach ($package_adavances_data as $package_advance) {
            if ($package_advance->package_id) {
                $location_id = $package_advance->package->location_id;
                $package_advance->update(['location_id' => $location_id]);
            } else {
                if ($package_advance->appointment_id) {
                    $location_id = $package_advance->appointment->location_id;
                    $package_advance->update(['location_id' => $location_id]);
                }
            }
        }

        return redirect()->route('admin.packagesadvances.index');
    }
}
