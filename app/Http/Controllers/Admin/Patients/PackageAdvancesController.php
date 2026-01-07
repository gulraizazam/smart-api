<?php

namespace App\Http\Controllers\Admin\Patients;

use App\Helpers\Filters;
use App\Helpers\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\PaymentModes;
use App\Models\User;
use App\Models\Patients;
use App\Models\Locations;
use Auth;
use Carbon\Carbon;
use Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PackageAdvancesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        if (! Gate::allows('patients_finance_manage')) {

            return abort(401);
        }
        $patient = User::finduser($id);

        if ($patient) {
            $paymentmodes = PaymentModes::get()->pluck('name', 'id');
            $paymentmodes->prepend('All', '');

            $package = Packages::get()->pluck('name', 'id');
            $package->prepend('All', '');

            $total_cash_in = PackageAdvances::where('cash_flow', '=', 'in')->where('patient_id', '=', $id)->sum('cash_amount');
            $total_cash_out = PackageAdvances::where('cash_flow', '=', 'out')->where('patient_id', '=', $id)->sum('cash_amount');

            $balance = $total_cash_in - $total_cash_out;

            $filters = Filters::all(Auth::User()->id, 'patient_packageAdvances');

            return view('admin.patients.card.packagesadvances.index', compact('patient', 'paymentmodes', 'package', 'total_cash_in', 'total_cash_out', 'balance', 'filters'));

        } else {

            return view('error_full');
        }

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($id)
    {
        if (! Gate::allows('patients_finance_create')) {

            return abort(401);
        }
        $patients = User::find($id);
        $paymentmodes = PaymentModes::get()->pluck('name', 'id');
        $paymentmodes->prepend('Select Payment Mode', '');

        return view('admin.patients.card.packagesadvances.create', compact('patients', 'paymentmodes'));
    }

    /*
    * Get the packages against patient id
    *
    * */
    public function getpackages(Request $request)
    {

        $packageinfo = Packages::where('patient_id', '=', $request->id)->get();
        $myarray = ['packageinfo' => $packageinfo];

        return response()->json([
            'status' => true,
            'myarray' => $myarray,
        ]);

    }

    /*
     * Get the packages information from packages advances
     *
     * */
    public function getpackagesinfo(Request $request)
    {
        $package_info = Packages::where('id', '=', $request->id)->first();
        $cash_amount = number_format(PackageAdvances::where([
            ['package_id', '=', $request->id],
            ['cash_flow', '=', 'in'],
        ])->sum('cash_amount'));
        $cash_amount_sum = (filter_var($cash_amount, FILTER_SANITIZE_NUMBER_INT) + $request->cash_amount);
        $total_price = number_format($package_info->total_price);

        if ($cash_amount_sum <= $package_info->total_price) {
            $cash_amount_sum = number_format($cash_amount_sum);

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
            $data['account_id'] = Auth::User()->account_id;
            $data['created_by'] = Auth::User()->id;
            $data['updated_by'] = Auth::User()->id;
            $data['package_id'] = $request->package_id;

            $package_advances = PackageAdvances::createRecord_onlyadvances($data);
            
            // Log payment received activity
            $package = Packages::find($request->package_id);
            $patient = Patients::find($request->patient_id);
            $location = $package ? Locations::with('city')->find($package->location_id) : null;
            if ($package_advances && $package && $patient) {
                ActivityLogger::logPaymentReceived($package_advances, $package, $patient, $location);
            }

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
     * Display a User As package advances  in datatables.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request, $id)
    {
        $filname = 'patient_packageAdvances';
        $apply_filter = false;
        if ($request->get('action')) {
            $action = $request->get('action');
            if (isset($action[0]) && $action[0] == 'filter_cancel') {
                Filters::flush(Auth::User()->id, $filname);
            } elseif ($action == 'filter') {
                $apply_filter = true;
            }
        }

        $records = [];
        $records['data'] = [];

        if ($request->get('customActionType') && $request->get('customActionType') == 'group_action') {
            $packagesadvances = PackageAdvances::getBulkData($request->get('id'));
            if ($packagesadvances) {
                foreach ($packagesadvances as $packageadvances) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! PackageAdvances::isChildExists($packageadvances->id, Auth::User()->account_id)) {
                        $packageadvances->delete();
                    }
                }
            }
            $records['customActionStatus'] = 'OK'; // pass custom message(useful for getting status of group actions)
            $records['customActionMessage'] = 'Records has been deleted successfully!'; // pass custom message(useful for getting status of group actions)
        }

        // Get Total Records
        $iTotalRecords = PackageAdvances::getTotalRecords($request, Auth::User()->account_id, $id, $apply_filter, $filname);

        $iDisplayLength = intval($request->get('length'));
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->get('start'));
        $sEcho = intval($request->get('draw'));

        $packagesadvances = PackageAdvances::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $id, $apply_filter, $filname);

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
                        break;
                }
                if ($packagesadvances->cash_amount != 0) {

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
                        'patient' => $packagesadvances->user->name,
                        'phone' => Gate::allows('contact') ? \App\Helpers\GeneralFunctions::prepareNumber4Call($packagesadvances->user->phone) : '***********',
                        'transtype' => $transtype,
                        'cash_in' => $cash_in,
                        'cash_out' => $cash_out,
                        'balance' => number_format($balance),
                        'cash_amount' => '1',
                        'created_at' => Carbon::parse($packagesadvances->created_at)->format('F j,Y h:i A'),
                        'actions' => view('admin.packagesadvances.actions', compact('packagesadvances'))->render(),
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
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function inactive($id)
    {
        if (! Gate::allows('packagesadvances_manage')) {
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
        if (! Gate::allows('packagesadvances_manage')) {
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
        if (! Gate::allows('packagesadvances_manage')) {

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
        if (! Gate::allows('packagesadvances_manage')) {
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
        if (! Gate::allows('packagesadvances_manage')) {
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
}
