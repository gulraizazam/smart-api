<?php

namespace App\Http\Controllers\Admin\Patients;

use App\HelperModule\ApiHelper;
use App\Helpers\ACL;
use App\Helpers\ActivityLogger;
use App\Helpers\Filters;
use App\Helpers\Financelog;
use App\Helpers\Widgets\PlanAppointmentCalculation;
use App\Helpers\Widgets\ServiceWidget;
use App\Http\Controllers\Controller;
use App\Models\AuditTrailChanges;
use App\Models\AuditTrails;
use App\Models\Discounts;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\PackageService;
use App\Models\PaymentModes;
use App\Models\ServiceHasLocations;
use App\Models\Services;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserHasLocations;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PackagesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        if (! Gate::allows('patients_plan_manage')) {
            return abort(401);
        }
        $patient = User::finduser($id);
        if ($patient) {
            $package = Packages::where('patient_id', '=', $id)->pluck('name', 'id');
            $package->prepend('All', '');

            $locations = Locations::getActiveSorted(ACL::getUserCentres(), 'full_address');
            $locations->prepend('All', '');

            $filters = Filters::all(Auth::User()->id, 'patient_packages');

            return view('admin.patients.card.plans.index', compact('patient', 'package', 'locations', 'filters'));
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
        if (! Gate::allows('patients_plan_create')) {
            return abort(401);
        }

        $locations = Locations::getActiveSorted(ACL::getUserCentres(), 'full_address');
        $locations->prepend('Select Centers', '');

        $patients = User::find($id);

        $random_id = md5(time().rand(0001, 9999).rand(78599, 99999));
        $paymentmodes = PaymentModes::where('type', '=', 'application')->pluck('name', 'id');
        $paymentmodes->prepend('Select Payment Mode', '');

        $customdiscountrange = Settings::where('slug', '=', 'sys-discounts')->first();
        $range = explode(':', $customdiscountrange->data);

        return view('admin.patients.card.plans.create', compact('patients', 'locations', 'random_id', 'paymentmodes', 'range'));
    }

    /**
     * get discount information.
     *
     * @return Response
     */
    public function getdiscountinfo(Request $request)
    {

        if ($request->discount_id) {

            $service_id = $request->service_id;
            $service_data = Services::find($service_id);

            $discount_id = $request->discount_id;
            $discount_data = Discounts::find($discount_id);

            if ($discount_data->type == Config::get('constants.Fixed')) {

                $discount_type = Config::get('constants.Fixed');
                $discount_price = $discount_data->amount;
                $net_amount = ($service_data->price) - ($discount_data->amount);

            } else {

                $discount_type = Config::get('constants.Percentage');
                $discount_price = $discount_data->amount;
                $discount_price_cal = $service_data->price * (($discount_price) / 100);
                $net_amount = ($service_data->price) - ($discount_price_cal);
            }

            return response()->json([
                'status' => true,
                'discount_type' => $discount_type,
                'discount_price' => $discount_price,
                'net_amount' => $net_amount,
            ]);
        } else {

            return response()->json([
                'status' => false,
            ]);
        }
    }

    /**
     * save packages services information.
     *
     * @return Response
     */
    public function savepackages_service(Request $request)
    {
        $status = true;
        $package_services = PackageBundles::where([
            ['service_id', '=', $request->service_id],
            ['random_id', '=', $request->random_id],
        ])->first();

        /*set variable total for total value of package*/
        $total = filter_var($request->package_total, FILTER_SANITIZE_NUMBER_INT);

        if ($package_services != null) {
            if ($package_services->service_id == $request->service_id && $package_services->net_amount != $request->net_amount) {
                $status = false;
            }
        }
        if ($status == true) {
            $data = $request->all();
            $data['qty'] = '1';
            $service_data = Services::find($request->service_id);
            $data['service_price'] = $service_data->price;

            /*In case If you not select any discount*/
            if ($request->discount_id == '0') {
                $data['discount_id'] = null;
            }

            /*Save package service information*/
            $packageservice = PackageBundles::createPackagebundle($data);

            /*calculate package value to return*/
            $total = number_format($total + $request->net_amount);

            /*Set variables for return to show information*/
            $net_amount = $packageservice->net_amount;
            $service_name = $packageservice->service->name;
            $service_price = $packageservice->service->price;

            /*use user giving attributes for custom package*/
            if ($request->discount_name == 'Custom') {

                $discount_name = $request->discount_name;
                $discount_type = $request->discount_type;
                $discount_price = $request->discount_price;

                $myarray = ['record' => $packageservice, 'random_id' => $request->random_id, 'service_name' => $service_name, 'service_price' => $service_price, 'discount_name' => $discount_name, 'discount_type' => $discount_type, 'discount_price' => $discount_price, 'net_amount' => $net_amount, 'total' => $total];

                return response()->json([
                    'status' => true,
                    'myarray' => $myarray,

                ]);
            } else {
                if ($request->discount_id == '0') {
                    $discount_name = '-';
                    $discount_type = '-';
                    $discount_price = '0.00';

                } else {

                    $discount_name = $packageservice->discount->name;
                    $discount_type = $packageservice->discount->type;
                    $discount_price = $packageservice->discount->amount;
                }

                $myarray = ['record' => $packageservice, 'random_id' => $request->random_id, 'service_name' => $service_name, 'service_price' => $service_price, 'discount_name' => $discount_name, 'discount_type' => $discount_type, 'discount_price' => $discount_price, 'net_amount' => $net_amount, 'total' => $total];

                return response()->json([
                    'status' => true,
                    'myarray' => $myarray,

                ]);
            }
        } else {

            return response()->json([
                'status' => false,
            ]);
        }
    }

    /**
     * get discount information for custom package.
     *
     * @return Response
     */
    public function getdiscountinfocustom(Request $request)
    {
        $service_id = $request->service_id;
        $service_data = Services::find($service_id);

        $discount_id = $request->discount_id;
        $discount_data = Discounts::find($discount_id);

        if ($request->discount_type == Config::get('constants.Fixed')) {

            $discount_type = Config::get('constants.Fixed');
            $discount_price = $request->discount_value;
            $net_amount = ($service_data->price) - ($discount_price);
        } else {

            $discount_type = Config::get('constants.Percentage');
            $discount_price = $request->discount_value;
            $discount_price_cal = $service_data->price * (($discount_price) / 100);
            $net_amount = ($service_data->price) - ($discount_price_cal);
        }

        return response()->json([
            'status' => true,
            'net_amount' => $net_amount,
        ]);
    }

    /**
     * delete serive from packages
     *
     * @param  request
     */
    public function deletepackagesservice(Request $request)
    {
        $packageService = PackageBundles::find($request->id);

        $package_total = filter_var($request->package_total, FILTER_SANITIZE_NUMBER_INT);

        $total = $package_total - $packageService->net_amount;

        PackageBundles::find($request->id)->forcedelete();

        return response()->json([
            'status' => true,
            'total' => $total,
            'id' => $request->id,
        ]);
    }

    /**
     * save package
     *
     * @param  request
     */
    public function savepackages(Request $request)
    {
        if ($request->grand_total < 0) {
            return response()->json([
                'status' => false,
            ]);
        }
        /*save Package information and also update random id in package service table*/
        $data_package = $request->all();
        $data_package['total_price'] = filter_var($request->total, FILTER_SANITIZE_NUMBER_INT);
        $data_package['sessioncount'] = '1';
        $data_package['account_id'] = Auth::User()->account_id;
        $package = Packages::createRecord($data_package);
        /*End*/

        /*Save data in package advances*/
        $data_packageAdvances['cash_flow'] = 'in';
        $data_packageAdvances['cash_amount'] = $request->cash_amount;
        $data_packageAdvances['account_id'] = Auth::User()->account_id;
        $data_packageAdvances['patient_id'] = $request->patient_id;
        $data_packageAdvances['payment_mode_id'] = $request->payment_mode_id;
        $data_packageAdvances['created_by'] = Auth::User()->id;
        $data_packageAdvances['updated_by'] = Auth::User()->id;
        $data_packageAdvances['package_id'] = $package->id;
        /*End*/

        $packageAdavances = PackageAdvances::createRecord($data_packageAdvances, $package);

        return response()->json([
            'status' => true,
        ]);
    }

    /**
     * Get service info
     *
     * @param  request
     * @return mixed
     */
    public function getserviceinfo(Request $request)
    {
        $service_data = Services::where('id', '=', $request->service_id)->first();
        $net_amount = $service_data->price;

        return response()->json([
            'status' => true,
            'net_amount' => $net_amount,
        ]);

    }

    /**
     * calculate the grand total
     *
     * @param  request
     * @return mixed
     */
    public function getgrandtotal(Request $request)
    {
        $package_total = filter_var($request->total, FILTER_SANITIZE_NUMBER_INT);
        $grand_total = number_format($package_total - $request->cash_amount);

        return response()->json([
            'status' => true,
            'grand_total' => $grand_total,
        ]);
    }


    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function inactive($id)
    {
        if (! Gate::allows('patients_plan_inactive')) {
            return abort(401);
        }
        $package = Packages::find($id);

        Packages::inactiveRecord($id);

        return redirect()->route('admin.plans.index', [$package->patient_id]);
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function active($id)
    {
        if (! Gate::allows('patients_plan_active')) {
            return abort(401);
        }
        $package = Packages::find($id);

        Packages::activeRecord($id);

        return redirect()->route('admin.plans.index', [$package->patient_id]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! Gate::allows('patients_plan_edit')) {
            return abort(401);
        }
        $package = Packages::find($id);

        $packagebundles = PackageBundles::where('package_id', '=', $package->id)->get();
        $packageservices = PackageService::where('package_id', '=', $package->id)->get();

        $packageadvances = PackageAdvances::where([
            ['package_id', '=', $package->id],
            ['is_cancel', '=', '0'],
            // ['is_tax', '=', '0'],
            ['is_adjustment', '=', '0'],
            //['is_refund', '=', '0']
        ])->get();

        $user_has_location = UserHasLocations::where('user_id', '=', Auth::User()->id)->get()->toArray();
        if ($user_has_location) {
            foreach ($user_has_location as $userhaslocation) {
                $location = Locations::where([
                    ['id', '=', $userhaslocation['location_id']],
                    ['account_id', '=', Auth::User()->account_id],
                ])->first();
                if ($location) {
                    if ($location->slug == 'custom') {
                        $locations[] = $location;
                    }
                }
            }
        } else {
            $locations = [];
        }

        $cash_amount_in = PackageAdvances::where([
            ['package_id', '=', $package->id],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
        ])->sum('cash_amount');

        $cash_amount_out = PackageAdvances::where([
            ['package_id', '=', $package->id],
            ['cash_flow', '=', 'out'],
        ])->sum('cash_amount');

        $cash_amount = $cash_amount_in - $cash_amount_out;

        $grand_total = number_format($package->total_price - $cash_amount_in);

        //        $patients = User::getPatients();
        //        $services = Services::getServices();

        $paymentmodes = PaymentModes::where('type', '=', 'application')->pluck('name', 'id');
        $paymentmodes->prepend('Select Payment Mode', '');

        $customdiscountrange = Settings::where('slug', '=', 'sys-discounts')->first();
        $range = explode(':', $customdiscountrange->data);

        $service_has_location = ServiceHasLocations::where('location_id', '=', $package->location_id)->get();
        if ($service_has_location) {
            $locationhasservice = ServiceWidget::generateServicelcoationArray($service_has_location, Auth::User()->account_id);
        }

        $finance_editing_days = Settings::where('slug', '=', 'sys-financeediting')->first();

        $end_previous_date = Carbon::now()->subDays($finance_editing_days->data)->toDateString();

        $data['patient_id'] = $package->patient_id;

        $data['location_id'] = $package->location_id;

        $data = (object) $data;

        $appointmentArray = PlanAppointmentCalculation::tagAppointments($data);

        return view('admin.patients.card.plans.edit', compact('package', 'packagebundles', 'packageservices', 'locations', 'packageadvances', 'paymentmodes', 'grand_total', 'range', 'locationhasservice', 'end_previous_date', 'appointmentArray'));
    }

    /**
     * calculate the grand total
     *
     * @param  request
     * @return mixed
     */
    public function getgrandtotal_update(Request $request)
    {

        $package = Packages::where('random_id', '=', $request->random_id)->first();

        $package_advances_cash_amount_1 = PackageAdvances::where([
            ['package_id', '=', $package->id],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
        ])->sum('cash_amount');

        $package_advances_cash_amount_2 = PackageAdvances::where([
            ['package_id', '=', $package->id],
            ['cash_flow', '=', 'out'],
        ])->sum('cash_amount');
        /*We discuss in future what happen next*/

        $package_advances_cash_amount = $package_advances_cash_amount_1;

        $package_total = filter_var($request->total, FILTER_SANITIZE_NUMBER_INT);
        $grand_total = number_format(($package_total - $package_advances_cash_amount) - $request->cash_amount);

        return response()->json([
            'status' => true,
            'grand_total' => $grand_total,
        ]);
    }

    /*
     * Update package
     * @param $request
     * @return mixed
     * */
    public function updatepackages(Request $request)
    {

        if ($request->grand_total < 0) {
            return response()->json([
                'status' => false,
            ]);
        }
        /*save Package information and also update random id in package service table*/
        $data_package = $request->all();
        $data_package['total_price'] = filter_var($request->total, FILTER_SANITIZE_NUMBER_INT);
        $data_package['sessioncount'] = '1';
        $data_package['account_id'] = Auth::User()->account_id;
        $random_id = $request->random_id;

        $package = Packages::updateRecord($data_package, $random_id);
        /*End*/
        if ($request->cash_amount == '0') {
            return response()->json([
                'status' => true,
            ]);
        } else {
            /*Save data in package advances*/
            $data_packageAdvances['cash_flow'] = 'in';
            $data_packageAdvances['cash_amount'] = $request->cash_amount;
            $data_packageAdvances['account_id'] = Auth::User()->account_id;
            $data_packageAdvances['patient_id'] = $request->patient_id;
            $data_packageAdvances['payment_mode_id'] = $request->payment_mode_id;
            $data_packageAdvances['created_by'] = Auth::User()->id;
            $data_packageAdvances['updated_by'] = Auth::User()->id;
            $data_packageAdvances['package_id'] = $package->id;
            /*End*/

            $packageAdavances = PackageAdvances::updateRecord($data_packageAdvances, $package);
            
            // Log payment received activity
            $patient = User::find($request->patient_id);
            $location = Locations::with('city')->find($package->location_id);
            if ($packageAdavances && $package && $patient) {
                ActivityLogger::logPaymentReceived($packageAdavances, $package, $patient, $location);
            }

            return response()->json([
                'status' => true,
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
        if (! Gate::allows('patients_plan_destroy')) {
            return abort(401);
        }
        $package = Packages::find($id);
        Packages::deleteRecord($id);

        return redirect()->route('admin.plans.index', [$package->patient_id]);
    }

    /**
     * display the package.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function display($id)
    {

        if (! Gate::allows('patients_plan_manage')) {
            return abort(401);
        }

        $package = Packages::find($id);

        $packagebundles = PackageBundles::where('package_id', '=', $package->id)->get();

        $packageservices = PackageService::where('package_id', '=', $package->id)->get();

        $packageadvances = PackageAdvances::where([
            ['package_id', '=', $package->id],
            ['is_cancel', '=', '0'],
            //['is_tax', '=', '0'],
            ['is_adjustment', '=', '0'],
            //['is_refund', '=', '0']
        ])->get();

        $cash_amount_in = PackageAdvances::where([
            ['package_id', '=', $package->id],
            ['cash_flow', '=', 'in'],
        ])->sum('cash_amount');

        $cash_amount_out = PackageAdvances::where([
            ['package_id', '=', $package->id],
            ['cash_flow', '=', 'out'],
        ])->sum('cash_amount');

        $cash_amount = $cash_amount_in - $cash_amount_out;
        /*We discuss it in future what happen next*/
        $grand_total = number_format($package->total_price - $cash_amount_in);

        $services = Services::getServices();
        $discount = Discounts::getDiscount(Auth::User()->account_id);
        $paymentmodes = PaymentModes::get()->pluck('name', 'id');
        $paymentmodes->prepend('Select Payment Mode', '');

        return view('admin.patients.card.plans.display', compact('package', 'packagebundles', 'packageservices', 'packageadvances', 'services', 'discount', 'paymentmodes', 'grand_total'));
    }

    /*
     * $edit the cash that enter in package advances (because of permission we need to duplicate that function but store method same)
     */
    public function editpackageadvancescashindex($id, $package_id)
    {
        $pack_adv_info = PackageAdvances::find($id);

        $paymentmodes = PaymentModes::where('type', '=', 'application')->get();

        return view('admin.patients.card.plans.finance_edit.create', compact('pack_adv_info', 'package_id', 'paymentmodes'));
    }

    /*
     *  Function for log for package
     */
    public function planlog($id, $patient_id, $type)
    {
        if (! Gate::allows('patients_plan_log')) {
            return abort(401);
        }
        $patient = User::finduser($patient_id);
        $action_array = [
            1 => 'Create',
            2 => 'Edit',
            3 => 'Delete',
            4 => 'Inactive',
            5 => 'Active',
            6 => 'Cancel',
        ];
        $table_array = [
            25 => 'Finance',
        ];
        $finance_log = [];

        $find_ids = PackageAdvances::withTrashed()->where('package_id', '=', $id)->pluck('id')->toArray();

        array_push($find_ids, $id);

        $audittrails = AuditTrails::whereIn('table_record_id', $find_ids)->where('audit_trail_table_name', '=', Config::get('constants.package_advance_table_name_log'))->orderBy('created_at', 'asc')->get();

        $count = 1;

        foreach ($audittrails as $audittrail) {

            $finance_log[$audittrail->id] = [
                'sr no' => $count++,
                'id' => $audittrail->id,
                'action' => $action_array[$audittrail->audit_trail_action_name],
                'table' => $table_array[$audittrail->audit_trail_table_name],
                'user_id' => $audittrail->user->name,
                'created_at_orignal' => $audittrail->created_at,
                'updated_at_orignal' => $audittrail->updated_at,
                'detail_log' => [],
            ];

            $audittrail_changes = AuditTrailChanges::where('audit_trail_id', '=', $audittrail->id)->get();

            foreach ($audittrail_changes as $changes) {
                if ($action_array[$audittrail->audit_trail_action_name] == 'Delete') {
                    if ($changes->field_name == 'cash_amount' || $changes->field_name == 'deleted_at') {
                        $result = Financelog::Calculate_Val_advance($changes);
                        $finance_log[$audittrail->id][$changes->field_name] = $result;
                    }
                } else {
                    $result = Financelog::Calculate_Val_advance($changes);
                    $finance_log[$audittrail->id][$changes->field_name] = $result;
                }
            }

            if (! isset($finance_log[$audittrail->id]['cash_flow'])) {

                $type_2_detail = AuditTrailChanges::where('audit_trail_id', '=', $finance_log[$audittrail->id]['id'])->get();

                foreach ($type_2_detail as $detail) {
                    $result = Financelog::Calculate_Val($detail);
                    $finance_log[$audittrail->id]['detail_log'][$detail->id] = [
                        'field_name' => $detail->field_name,
                        'field_before' => $result['before'],
                        'field_after' => $result['after'],
                    ];
                }
            }
        }

        foreach ($finance_log as $key => $log) {
            if ($log['sr no'] == 1 && $log['cash_flow'] == 'out' && $log['payment_mode_id'] == 'Settle Amount') {
                unset($finance_log[$key]);
            }
        }

        if ($type === 'web') {
            return ApiHelper::makeResponse([
                'finance_log' => $finance_log,
                'id' => $id,
                'patient' => $patient,
            ], 'admin.patients.card.plans.log');
        }

        return $this->packagelogexcel($id, $finance_log);
    }

    /*
     *  Function for log for package excel
     */

    public function packagelogexcel($id, $finance_log)
    {
        if (! Gate::allows('patients_plan_log')) {
            return abort(401);
        }

        $spreadsheet = new Spreadsheet();
        $Excel_writer = new Xlsx($spreadsheet);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'PACKAGE ID')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', $id);

        $activeSheet->setCellValue('A2', '#')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', 'Cash Flow')->getStyle('B2')->getFont()->setBold(true);
        $activeSheet->setCellValue('C2', 'Cash Amount')->getStyle('C2')->getFont()->setBold(true);
        $activeSheet->setCellValue('D2', 'Refund')->getStyle('D2')->getFont()->setBold(true);
        $activeSheet->setCellValue('E2', 'Adjustment')->getStyle('E2')->getFont()->setBold(true);
        $activeSheet->setCellValue('F2', 'Tax')->getStyle('F2')->getFont()->setBold(true);
        $activeSheet->setCellValue('G2', 'Cancel')->getStyle('G2')->getFont()->setBold(true);
        $activeSheet->setCellValue('H2', 'Delete')->getStyle('H2')->getFont()->setBold(true);
        $activeSheet->setCellValue('I2', 'Refund Note')->getStyle('I2')->getFont()->setBold(true);
        $activeSheet->setCellValue('J2', 'Payment Mode')->getStyle('J2')->getFont()->setBold(true);
        $activeSheet->setCellValue('K2', 'Appointment Type')->getStyle('K2')->getFont()->setBold(true);
        $activeSheet->setCellValue('L2', 'Location')->getStyle('L2')->getFont()->setBold(true);
        $activeSheet->setCellValue('M2', 'Created By')->getStyle('M2')->getFont()->setBold(true);
        $activeSheet->setCellValue('N2', 'Updated By')->getStyle('N2')->getFont()->setBold(true);
        $activeSheet->setCellValue('O2', 'Plan')->getStyle('O2')->getFont()->setBold(true);
        $activeSheet->setCellValue('P2', 'Invoice Id')->getStyle('P2')->getFont()->setBold(true);
        $activeSheet->setCellValue('Q2', 'Created At Shown')->getStyle('Q2')->getFont()->setBold(true);
        $activeSheet->setCellValue('R2', 'Updated At Shown')->getStyle('R2')->getFont()->setBold(true);
        $activeSheet->setCellValue('S2', 'Created At')->getStyle('S2')->getFont()->setBold(true);
        $activeSheet->setCellValue('T2', 'Updated At')->getStyle('T2')->getFont()->setBold(true);
        $activeSheet->setCellValue('U2', 'Deleted At')->getStyle('U2')->getFont()->setBold(true);

        $count = 1;
        $counter = 4;

        foreach ($finance_log as $log) {
            if ((isset($log['package_id']) && $log['package_id'] == $id) || ! isset($log['package_id'])) {
                $activeSheet->setCellValue('A'.$counter, $count++);
                $activeSheet->setCellValue('B'.$counter, isset($log['cash_flow']) ? $log['cash_flow'] : '-');
                $activeSheet->setCellValue('C'.$counter, isset($log['cash_amount']) ? $log['cash_amount'] : '-');
                $activeSheet->setCellValue('D'.$counter, isset($log['is_refund']) ? $log['is_refund'] : '-');
                $activeSheet->setCellValue('E'.$counter, isset($log['is_adjustment']) ? $log['is_adjustment'] : '-');
                $activeSheet->setCellValue('F'.$counter, isset($log['is_tax']) ? $log['is_tax'] : '-');
                $activeSheet->setCellValue('G'.$counter, isset($log['is_cancel']) ? $log['is_cancel'] : '-');
                $activeSheet->setCellValue('H'.$counter, ($log['action'] == 'Delete') ? 'Yes' : '-');
                $activeSheet->setCellValue('I'.$counter, isset($log['refund_note']) ? $log['refund_note'] : '-');
                $activeSheet->setCellValue('J'.$counter, isset($log['payment_mode_id']) ? $log['payment_mode_id'] : '-');
                $activeSheet->setCellValue('K'.$counter, isset($log['appointment_type_id']) ? $log['appointment_type_id'] : '-');
                $activeSheet->setCellValue('L'.$counter, isset($log['location_id']) ? $log['location_id'] : '-');
                $activeSheet->setCellValue('M'.$counter, isset($log['created_by']) ? $log['created_by'] : '-');
                $activeSheet->setCellValue('N'.$counter, isset($log['cash_flow']) ? isset($log['updated_by']) ? $log['updated_by'] : '-' : $log['user_id']);
                $activeSheet->setCellValue('O'.$counter, isset($log['package_id']) ? $log['package_id'] : '-');
                $activeSheet->setCellValue('P'.$counter, isset($log['invoice_id']) ? $log['invoice_id'] : '-');
                $activeSheet->setCellValue('Q'.$counter, isset($log['created_at']) ? $log['created_at'] == $log['created_at_orignal'] ? '-' : $log['created_at'] : '-');
                $activeSheet->setCellValue('R'.$counter, isset($log['updated_at']) ? $log['updated_at'] == $log['updated_at_orignal'] ? '-' : $log['updated_at'] : '-');

                if ($log['action'] == 'Delete') {
                    $activeSheet->setCellValue('S'.$counter, '-');
                    $activeSheet->setCellValue('T'.$counter, '-');
                } else {
                    $activeSheet->setCellValue('S'.$counter, isset($log['created_at_orignal']) ? \Carbon\Carbon::parse($log['created_at_orignal'])->format('F j,Y h:i A') : '-');
                    $activeSheet->setCellValue('T'.$counter, isset($log['updated_at_orignal']) ? \Carbon\Carbon::parse($log['updated_at_orignal'])->format('F j,Y h:i A') : '-');
                }

                $activeSheet->setCellValue('U'.$counter, isset($log['deleted_at']) ? \Carbon\Carbon::parse($log['deleted_at'])->format('F j, Y h:i A') : '-');

                $counter++;

                if (isset($log['detail_log']) && count($log['detail_log'])) {

                    $countt = 1;

                    $activeSheet->setCellValue('H'.$counter, '#')->getStyle('H'.$counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('I'.$counter, 'Field Name')->getStyle('I'.$counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('J'.$counter, 'Before')->getStyle('J'.$counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('K'.$counter, 'After')->getStyle('K'.$counter)->getFont()->setBold(true);

                    $counter++;

                    foreach ($log['detail_log'] as $detail) {
                        $activeSheet->setCellValue('H'.$counter, $countt++);
                        $activeSheet->setCellValue('I'.$counter, isset($detail['field_name']) ? $detail['field_name'] : '-');
                        $activeSheet->setCellValue('J'.$counter, isset($detail['field_before']) ? $detail['field_before'] : '-');
                        $activeSheet->setCellValue('K'.$counter, isset($detail['field_after']) ? $detail['field_after'] : '-');

                        $counter++;
                    }

                }

            }
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'PackageLog'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }
}
