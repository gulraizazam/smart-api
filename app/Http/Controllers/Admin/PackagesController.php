<?php

namespace App\Http\Controllers\Admin;

use Validator;
use Carbon\Carbon;
use App\Helpers\ACL;
use App\Models\User;
use App\Models\Bundles;
use App\Models\SMSLogs;
use App\Helpers\Filters;
use App\Models\Accounts;
use App\Models\Activity;
use App\Models\Invoices;
use App\Models\Packages;
use App\Models\Services;
use App\Models\Settings;
use App\Models\Discounts;
use App\Models\Locations;
use App\Helpers\Financelog;
use App\Helpers\JazzSMSAPI;
use App\Models\AuditTrails;
use App\Models\Appointments;
use App\Models\PaymentModes;
use Illuminate\Http\Request;
use Composer\Package\Package;
use App\Helpers\TelenorSMSAPI;
use App\Models\PackageBundles;
use App\Models\PackageService;
use App\HelperModule\ApiHelper;
use App\Models\PackageAdvances;
use App\Models\UserHasLocations;
use App\Helpers\GeneralFunctions;
use App\Models\AuditTrailChanges;
use App\Models\BundleHasServices;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\ServiceHasLocations;
use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use App\Models\UserOperatorSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Helpers\Widgets\ServiceWidget;
use Illuminate\Support\Facades\Config;
use App\Helpers\Widgets\DiscountWidget;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Helpers\Invoice_Plan_Refund_Sms_Functions;
use App\Helpers\Widgets\PlanAppointmentCalculation;
use App\Models\InvoiceDetails;

class PackagesController extends Controller
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
     * Display a listing of the package.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! Gate::allows('plans_manage')) {
            return abort(401);
        }

        return view('admin.packages.index');
    }

    /**
     * Show the form for creating a new package.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        if (! Gate::allows('plans_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $locations = Locations::getActiveSorted(ACL::getUserCentres(), 'full_address');

        $random_id = md5(time().rand(0001, 9999).rand(78599, 99999));

        $paymentmodes = PaymentModes::where('type', '=', 'application')->pluck('name', 'id');

        $customdiscountrange = Settings::where('slug', '=', 'sys-discounts')->first();
        $range = explode(':', $customdiscountrange->data);

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'locations' => $locations,
            'random_id' => $random_id,
            'paymentmodes' => $paymentmodes,
            'range' => $range,
            'discount_type' => config('constants.amount_types'),
            'discounts' => Discounts::where('active', 1)->get(['id', 'name']),
        ]);
    }

    /**
     * Return an array of location base service.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getservices(Request $request)
    {

        $service_has_location = ServiceHasLocations::where('location_id', '=', $request->location_id)->get();
        if ($service_has_location) {

            $locationhasservice = ServiceWidget::generateServicelcoationArray($service_has_location, Auth::User()->account_id);

            return ApiHelper::apiResponse($this->success, 'Recode found', true, [
                'service' => $locationhasservice,
            ]);

        }

        return ApiHelper::apiResponse($this->success, 'Recode not found', false);
    }

    /**
     * get discount information.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getdiscountinfo(Request $request)
    {

        if ($request->discount_id) {

            $service_id = $request->service_id;

            $service_data = Bundles::find($service_id);

            $discount_id = $request->discount_id;

            $discount_data = Discounts::find($discount_id);

            if ($discount_data->slug == 'custom') {

                return ApiHelper::apiResponse($this->success, 'custom', true, [
                    'custom_checked' => 1,
                ]);

            } else {
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

                return ApiHelper::apiResponse($this->success, 'Record Found', true, [
                    'discount_type' => $discount_type,
                    'discount_price' => $discount_price,
                    'net_amount' => $net_amount,
                    'custom_checked' => 0,
                ]);

            }
        }

        return ApiHelper::apiResponse($this->success, 'No Record Found', false);
    }

    /**
     * save packages services information.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function savepackages_service(Request $request)
    {

        $status = true;

        $service_data = Bundles::find($request->bundle_id);
        $find_package = Packages::where('random_id',$request->random_id)->first();
        $check_is_setteled = PackageAdvances::where([
            ['cash_flow', '=', 'out'],
            ['cash_amount', '>', 0],
            ['is_setteled', '=', '1'],
            ['package_id', '=', $find_package->id],
        ])->first();
        if($check_is_setteled){
            return ApiHelper::apiResponse($this->success, 'Plan is already settled. you can not add further treatment in this plan.', false,['setteled'=>1]);
        }
        
        /*Total belongs to total Amount that increase when we enter new bundle*/
        $total = str_replace(',', '', $request->package_total); //filter_var($request->package_total, FILTER_SANITIZE_NUMBER_INT);
        if ($total == '') {
            $total = 0;
        }
        if ($request->is_exclusive == '') {
            $request->merge(['is_exclusive' => 1]);
        }
        if ($request->get('package_bundles')) {
            $package_bundles = PackageBundles::whereIn('id', $request->get('package_bundles'))->get();
            if ($package_bundles) {
                foreach ($package_bundles as $bundle) {
                    if ($service_data->tax_treatment_type_id == 1) {

                        if ($bundle->bundle_id == $request->bundle_id && ($bundle->net_amount != $request->net_amount || $bundle->is_exclusive != (int) $request->is_exclusive)) {

                            $status = false;
                        }
                    } else { /*this is commented because we want to add services with different prices*/
                        if ($bundle->bundle_id == $request->bundle_id && $bundle->net_amount != $request->net_amount) {
                            //$status = false;
                            $status = true;
                        }
                    }
                }
            }
        }

        if ($status == true) {
            /*First we need to make the data to save in package bundle*/
            $data = $request->all();
            $location_information = Locations::find($request->location_id);

            $discount_info = Discounts::find($request->discount_id);

            $data['qty'] = '1';
            $data['bundle_id'] = $service_data->id;
            $data['service_price'] = $service_data->price;

            if ($discount_info) {
                $data['discount_name'] = $discount_info->name;
            }
            /*Checked it exclusive or not*/
            if ($service_data->tax_treatment_type_id == Config::get('constants.tax_both')) {
                if ($request->is_exclusive == '1') {
                    $data['tax_exclusive_net_amount'] = $request->net_amount;
                    $data['tax_percenatage'] = $location_information->tax_percentage;
                    $data['tax_price'] = ceil($data['tax_exclusive_net_amount'] * ($location_information->tax_percentage / 100));
                    $data['tax_including_price'] = ceil($data['tax_exclusive_net_amount'] + (($data['tax_exclusive_net_amount'] * $data['tax_percenatage']) / 100));

                    $data['is_exclusive'] = 1;
                } else {
                    $data['tax_including_price'] = $request->net_amount;
                    $data['tax_percenatage'] = $location_information->tax_percentage;
                    $data['tax_exclusive_net_amount'] = ceil((100 * $data['tax_including_price']) / ($data['tax_percenatage'] + 100));
                    $data['tax_price'] = ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                    $data['is_exclusive'] = 0;
                }
            } elseif ($service_data->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
                $data['tax_exclusive_net_amount'] = $request->net_amount;
                $data['tax_percenatage'] = $location_information->tax_percentage;
                $data['tax_price'] = ceil($data['tax_exclusive_net_amount'] * ($location_information->tax_percentage / 100));
                $data['tax_including_price'] = ceil($data['tax_exclusive_net_amount'] + (($data['tax_exclusive_net_amount'] * $data['tax_percenatage']) / 100));

                $data['is_exclusive'] = 1;
            } else {
                $data['tax_including_price'] = $request->net_amount;
                $data['tax_percenatage'] = $location_information?->tax_percentage ?? '00.00';
                $data['tax_exclusive_net_amount'] = ceil((100 * $data['tax_including_price']) / ($data['tax_percenatage'] + 100));
                $data['tax_price'] = ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                $data['is_exclusive'] = 0;
            }
            /*In case If you not select any discount*/
            if ($request->discount_id == '0' || $request->discount_id == '') {
                $data['discount_id'] = null;
            }
            $data['created_at'] = Filters::getCurrentTimeStamp();
            $data['updated_at'] = Filters::getCurrentTimeStamp();
            /*date is develop to save package bundle*/

            /*Save package bundle information*/
            $packagesbundly = PackageBundles::createPackagebundle($data);

            /*Get the package service information*/
            $bundle_details = BundleHasServices::where('bundle_id', '=', $packagesbundly->bundle_id)->get();

            $calculable_servcies = [];

            foreach ($bundle_details as $detail) {
                $calculable_servcies[] = [
                    'service_price' => $detail->calculated_price,
                    'calculated_price' => $detail->calculated_price,
                    'service_id' => $detail->service_id,
                ];
            }
            /*calculate price of services according to their prices*/
            $calculated_services = Bundles::calculatePrices($calculable_servcies, $data['service_price'], $data['net_amount']);

            /*Second we need to make the data to save in package services*/
            foreach ($calculated_services as $detail) {

                $data_service['random_id'] = $request->random_id;
                $data_service['package_bundle_id'] = $packagesbundly->id;
                $data_service['service_id'] = $detail['service_id'];
                $data_service['price'] = $detail['calculated_price'];
                $data_service['orignal_price'] = $detail['service_price'];

                /*Checked it exclusive or not*/
                if ($service_data->tax_treatment_type_id == Config::get('constants.tax_both')) {
                    if ($request->is_exclusive == '1') {
                        $data_service['tax_exclusive_price'] = $detail['calculated_price'];
                        $data_service['tax_percenatage'] = $location_information->tax_percentage;
                        $data_service['tax_price'] = ceil($detail['calculated_price'] * ($location_information->tax_percentage / 100));
                        $data_service['tax_including_price'] = ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percenatage']) / 100));

                        $data_service['is_exclusive'] = 1;
                    } else {
                        $data_service['tax_including_price'] = $detail['calculated_price'];
                        $data_service['tax_percenatage'] = $location_information->tax_percentage;
                        $data_service['tax_exclusive_price'] = ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percenatage'] + 100));
                        $data_service['tax_price'] = ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);

                        $data_service['is_exclusive'] = 0;
                    }
                } elseif ($service_data->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
                    $data_service['tax_exclusive_price'] = $detail['calculated_price'];
                    $data_service['tax_percenatage'] = $location_information->tax_percentage;
                    $data_service['tax_price'] = ceil($detail['calculated_price'] * ($location_information->tax_percentage / 100));
                    $data_service['tax_including_price'] = ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percenatage']) / 100));

                    $data_service['is_exclusive'] = 1;
                } else {
                    $data_service['tax_including_price'] = $detail['calculated_price'];
                    $data_service['tax_percenatage'] = $location_information->tax_percentage;
                    $data_service['tax_exclusive_price'] = ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percenatage'] + 100));
                    $data_service['tax_price'] = ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);

                    $data_service['is_exclusive'] = 0;
                }
                $data_service['created_at'] = Filters::getCurrentTimeStamp();
                $data_service['updated_at'] = Filters::getCurrentTimeStamp();
                $packageservice = PackageService::createPackageService($data_service);
            }
            /*calculate package value to return*/
            $total = number_format((float) $total + (float) $packagesbundly->tax_including_price);

            /*Set variables for return to show information*/
            $net_amount = $packagesbundly->net_amount;
            $service_name = $packagesbundly->bundle->name;
            $service_price = $packagesbundly->service_price;

            /*use user giving attributes for custom package*/

            if ($request->discount_id == '0' || $request->discount_id == null) {
                $discount_name = '-';
                $discount_type = '-';
                $discount_price = '0.00';
            } else {
                $discount_name = $packagesbundly->discount_name;
                $discount_type = $packagesbundly->discount_type;
                $discount_price = $packagesbundly->discount_price;
            }
            $package_service = Services::join('package_services', 'services.id', '=', 'package_services.service_id')
                ->select('package_services.*', 'services.name')
                ->where('package_services.package_bundle_id', '=', $packagesbundly->id)
                ->get();
            $package_bundles = PackageBundles::find($packagesbundly->id);
            $myarray = [
                'record' => $package_bundles,
                'record_detail' => $package_service,
                'random_id' => $request->random_id,
                'service_name' => $service_name,
                'service_price' => $service_price,
                'discount_name' => $discount_name,
                'discount_type' => $discount_type,
                'discount_price' => $discount_price,
                'net_amount' => $net_amount,
                'total' => $total,
            ];

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'myarray' => $myarray,
            ]);
        }

        return ApiHelper::apiResponse($this->success, 'No Record found', false);
    }

    /**
     * get discount information for custom package.
     *
     * @return Response
     */
    public function getdiscountinfocustom(Request $request)
    {
        $status = true;
        $service_id = $request->service_id;
        $service_data = Bundles::find($service_id);
        $discount_id = $request->discount_id;
        $discount_data = Discounts::find($discount_id);
        if ($discount_data->slug == 'custom') {
            $discount_id = $request->discount_id;
        } else {
            $request->discount_value = $discount_data->amount;
        }
        if ($discount_data->type == 'Fixed') {
            if ($request->discount_type == Config::get('constants.Fixed')) {
                if ($request->discount_value > $discount_data->amount || $request->discount_value > $service_data->price) {
                    return false;
                }
                $discount_type = Config::get('constants.Fixed');
                $discount_price = $request->discount_value;
                $discount_price_in_percentage = ($discount_price / $service_data->price) * 100;
                $net_amount = ($service_data->price) - ($discount_price);
            } else {
                $discount_type = Config::get('constants.Percentage');
                $discount_price = $request->discount_value;
                $discount_price_cal = ($discount_data->amount / $service_data->price) * 100;
                if ($request->discount_value > $discount_price_cal) {
                    $status = false;
                }
                $amount_after_per = ($request->discount_value / 100) * $service_data->price;
                $net_amount = $service_data->price - $amount_after_per;
            }
        } else {
            if ($request->discount_type == Config::get('constants.Fixed')) {
                $discount_price = $request->discount_value;
                $discount_price_in_percentage = ($discount_price / $service_data->price) * 100;
                if ($discount_price_in_percentage > $discount_data->amount) {
                    return false;
                }
                $net_amount = ($service_data->price) - ($request->discount_value);
            } else {
                if ($request->discount_value > $discount_data->amount) {
                    return false;
                }
                $discount_price = $request->discount_value;
                $discount_price_in_percentage = ($request->discount_value / 100) * $service_data->price;
                $net_amount = ($service_data->price) - ($discount_price_in_percentage);
            }
        }
        if ($status == true) {
            return ApiHelper::apiResponse($this->success, 'Net Amount', true, [
                'net_amount' => $net_amount,
            ]);
        }

        return ApiHelper::apiResponse($this->success, 'Net Amount', false);
    }

    /**
     * delete serive from packages
     *
     * @param request
     */
    public function deletepackagesservice(Request $request)
    {
        $status = PackageService::where([
            ['package_bundle_id', '=', $request->id],
            ['is_consumed', '=', '1'],
        ])->first();
        if ($status) {

            return ApiHelper::apiResponse($this->success, 'Unable to delete consume amount.', false);

        } else {

            $packageService = PackageBundles::find($request->id);
            if ($request->package_total == '') {
                $request->merge(['package_total' => 0]);
            }
            $package_total = str_replace(',', '', $request->package_total); //filter_var($request->package_total, FILTER_SANITIZE_NUMBER_INT);

            $total = $package_total - $packageService->tax_including_price;

            PackageService::where('package_bundle_id', '=', $request->id)->delete();

            PackageBundles::find($request->id)->forcedelete();

            if ($request->update_status == 1) {
                if ($packageService->package_id) {
                    $record = Packages::find($packageService->package_id);
                    $record->update(['total_price' => $total, 'updated_at' => Filters::getCurrentTimeStamp()]);
                }
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'total' => $total,
                'id' => $request->id,
            ]);
        }
    }

    /**
     * delete serive from packages
     *
     * @param request
     */
    public function deletepackagesexclusive(Request $request)
    {
        $data = $request->all();
        if (isset($data['random_id']) && $data['random_id']) {
            PackageService::where('random_id', '=', $request->random_id)->forcedelete();
            PackageBundles::where('random_id', '=', $request->random_id)->forcedelete();

            return response()->json([
                'status' => true,
            ]);
        }

        return response()->json([
            'status' => false,
        ]);
    }

    /**
     * save package
     *
     * @param request
     */
    public function savepackages(Request $request)
    {
        DB::beginTransaction();
        try {
            if (isset($request->appointment_id)) {
                $tag_appoint = explode('.', $request->appointment_id);
                if ($tag_appoint[1] == 'A') {
                    $appointment_id = $tag_appoint[0];
                } else {
                    $PlanAppointmentCalculation = new PlanAppointmentCalculation();
                    $appointment_id = $PlanAppointmentCalculation->storeAppointment($request->patient_id, $request->location_id, $request, $tag_appoint[0], false);
                    $PlanAppointmentCalculation->saveinvoice($appointment_id);
                }
            } else {
                return response()->json([
                    'status' => false,
                ]);
            }
            /*save Package information and also update random id in package service table*/
            $data_package = $request->all();
            $data_package['total_price'] = filter_var($request->total, FILTER_SANITIZE_NUMBER_INT);
            $data_package['sessioncount'] = '1';
            $data_package['account_id'] = Auth::User()->account_id;
            $data_package['is_exclusive'] = $request->is_exclusive;
            $data_package['appointment_id'] = $appointment_id;
            $data_package['created_at'] = Filters::getCurrentTimeStamp();
            $data_package['updated_at'] = Filters::getCurrentTimeStamp();
            $package = Packages::createRecord($data_package, $request);
            /*End*/
            if ($request->cash_amount == '0') {
                // Commit Transaction
                DB::commit();

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
                $data_packageAdvances['location_id'] = $request->location_id;
                $data_packageAdvances['created_at'] = Filters::getCurrentTimeStamp();
                $data_packageAdvances['updated_at'] = Filters::getCurrentTimeStamp();
                /*End*/
                $packageAdavances = PackageAdvances::createRecord($data_packageAdvances, $package);
                /////Save activity////
                $patient = User::whereId($request->patient_id)->first();
                $location = Locations::whereId($request->location_id)->first();
                $activity = new Activity();
                $activity->action = 'received';
                $activity->patient = $patient->name;
                $activity->appointment_type = 'Plan';
                $activity->created_by = Auth::user()->name;
                $activity->planId = $package->id;
                $activity->amount = $request->cash_amount;
                $activity->location = $location->name;
                $activity->created_at = Filters::getCurrentTimeStamp();
                $activity->updated_at = Filters::getCurrentTimeStamp();
                $activity->save();
                /*Now sent message to user about cash received*/
                Invoice_Plan_Refund_Sms_Functions::PlanCashReceived_SMS($package->id, $packageAdavances);
                // Commit Transaction
                DB::commit();

                return response()->json([
                    'status' => true,
                ]);
            }
        } catch (\Exception $e) {
            dd($e);
            // Rollback Transaction
            DB::rollback();

            return response()->json([
                'status' => false,
            ]);
        }
    }

    /**
     * Get service info
     *
     * @param request
     * @return mixed
     */
    public function getserviceinfo(Request $request)
    {
        /*because now we not give any discount to package if package have no permission to use. for this we introduce that empty collection */
        $discounts = Collection::make();
        /*end*/
        $today = Carbon::now()->toDateString();

        $bundle = Bundles::find($request->bundle_id);

        if ($bundle && $bundle->type == 'single') {

            $bundleService = BundleHasServices::where([
                'bundle_id' => $bundle->id,
            ])->first();

            $service_id = $bundleService->service_id;

            $location_id = $request->location_id;

            $discountIds = DiscountWidget::loadPlanDsicountByLocationService($location_id, $service_id, Auth::User()->account_id);

            $discounts = Discounts::whereIn('id', $discountIds)->where([
                ['discount_type', '=', 'Treatment'],
                ['active', '=', '1'],
            ])->whereDate('start', '<=', $today)->whereDate('end', '>=', $today)->get();

        } else {
            if ($bundle && $bundle->apply_discount == '1') {
                $bundleServices = BundleHasServices::where([
                    'bundle_id' => $bundle->id,
                ])->get();
                foreach ($bundleServices as $bundleService) {
                    $service_id = $bundleService->service_id;
                    $location_id = $request->location_id;
                    $discountIds[] = DiscountWidget::loadPlanDsicountByLocationService($location_id, $service_id, Auth::User()->account_id);
                }
                $uniq_array = [];
                foreach ($discountIds as $discountId) {
                    foreach ($discountId as $singledata) {
                        if (! in_array($singledata, $uniq_array)) {
                            $uniq_array[] = $singledata;
                        }
                    }
                }
                $discounts = Discounts::whereIn('id', $uniq_array)->where([
                    ['discount_type', '=', 'Treatment'],
                    ['active', '=', '1'],
                ])->whereDate('start', '<=', $today)->whereDate('end', '>=', $today)->get();
            }
        }

        $temp_discounts = [];

        /*Now Checked Brithday promotion valid or not*/
        foreach ($discounts as $key => $discount) {

            if ($discount->slug == 'birthday') {
                /*first get the pre and post days*/
                $pre_days = $discount->pre_days;
                $post_days = $discount->post_days;
                /*end*/

                $today_1 = Carbon::today();
                $today_2 = Carbon::today();
                $today_3 = Carbon::today();

                /*get the date range to checked patient birthday exist between or not*/
                $predate = $today_1->subDay($pre_days)->format('Y-m-d');
                $postdate = $today_2->addDay($post_days)->format('Y-m-d');

                $patient_info = User::find($request->patient_id);

                /*Now checked birthday valid or not*/
                if ($patient_info->dob) {

                    $patientbirthday = Carbon::parse($patient_info->dob)->format($today_3->year.'-'.'m-d');

                    if (($patientbirthday >= $predate) && ($patientbirthday <= $postdate)) {
                    } else {
                        $discounts->forget($key);
                    }
                } else {
                    $discounts->forget($key);
                }
            }
        }
        /*end*/
        $Discount_array = [];
        if (count($discounts) > 0) {
            $service_data = Bundles::where('id', '=', $request->bundle_id)->first();
            if ($service_data) {
                foreach ($discounts as $discount) {
                    if ($discount->slug != 'custom') {
                        if ($discount->type == Config::get('constants.Fixed')) {
                            $discount_type = $discount->type;
                            $discount_price = $discount->amount;
                            $net_amount = ($service_data->price) - ($discount_price);
                            $Discount_array[$discount->id] = [
                                'id' => $discount->id,
                                'discount_type' => $discount_type,
                                'discount_price' => $discount_price,
                                'net_amount' => $net_amount,
                            ];
                        } else {
                            $discount_type = $discount->type;
                            $discount_price = $discount->amount;
                            $discount_price_cal = $service_data->price * (($discount_price) / 100);
                            $net_amount = ($service_data->price) - ($discount_price_cal);
                            $Discount_array[$discount->id] = [
                                'id' => $discount->id,
                                'discount_type' => $discount_type,
                                'discount_price' => $discount_price,
                                'net_amount' => $net_amount,
                            ];
                        }
                    }
                }

                $select_discount = [];
                $lowest = false;
                if (count($Discount_array) > 0) {
                    foreach ($Discount_array as $value) {
                        if ($lowest === false || $value['net_amount'] < $lowest) {
                            $lowest = $value['net_amount'];
                            $select_discount = $value;
                        }
                    }
                    $discounts = $discounts->toArray();
                    // $select_discount = ["discount_type" => "Percentage","discount_price" => 0.0,"id" => 0,"net_amount" => 0.0];
                    // return response()->json(array(
                    //     'status' => true,
                    //     'discounts' => $discounts,
                    //     'checked_custom' => '0',
                    //     'dis_price_info' => $select_discount,
                    // ));
                    $service_data = Bundles::where('id', '=', $request->bundle_id)->first();

                    return ApiHelper::apiResponse($this->success, 'Records found.', true, [
                        'discounts' => $discounts,
                        'checked_custom' => '0',
                        'dis_price_info' => $select_discount,
                        'net_amount' => $service_data->price,
                    ]);
                } else {
                    $discounts = $discounts->toArray();
                    $service_data = Bundles::where('id', '=', $request->bundle_id)->first();

                    return ApiHelper::apiResponse($this->success, 'Records found.', true, [
                        'discounts' => $discounts,
                        'checked_custom' => '1',
                        'net_amount' => $service_data->price,
                    ]);
                }
            }
        }

        return ApiHelper::apiResponse($this->success, 'Records found.', false, [
            'net_amount' => isset($bundle) ? $bundle->price : 0,
        ]);
    }

    /**
     * Get service info whan discount not selected
     *
     * @param request
     * @return mixed
     */
    public function getservices_for_zero(Request $request)
    {

        $service_data = Bundles::where('id', '=', $request->bundle_id)->first();
        if ($service_data) {

            return ApiHelper::apiResponse($this->success, 'Records found', true, [
                'net_amount' => $service_data->price,
            ]);

        }

        return ApiHelper::apiResponse($this->success, 'No record found', false);
    }

    /**
     * calculate the grand total
     *
     * @param request
     * @return mixed
     */
    public function getgrandtotal(Request $request)
    {
        $package_total = str_replace(',', '', $request->total); //filter_var($request->total, FILTER_SANITIZE_NUMBER_INT);
        $grand_total = number_format($package_total - $request->cash_amount);

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'grand_total' => $grand_total,
        ]);
    }

    /**
     * Display a User As package in datatables.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request, $id = false)
    {

        $filename = 'packages';

        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, $filename);

        $records = [];
        $records['data'] = [];

        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $packages = Packages::getBulkData($ids);
            $any_deleted = false;
            if ($packages) {
                foreach ($packages as $package) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! Packages::isChildExists($package->id, Auth::User()->account_id)) {
                        $any_deleted = true;
                        $package->delete();
                    }
                }
            }
            if ($any_deleted) {
                $records['status'] = true; // pass custom message(useful for getting status of group actions)
                $records['message'] = 'One or more record has been deleted successfully!'; // pass custom message(useful for getting status of group actions)
            } else {
                $records['status'] = false; // pass custom message(useful for getting status of group actions)
                $records['message'] = 'Child records exist, unable to delete plan!'; // pass custom message(useful for getting status of group actions)
            }
        }

        // Get Total Records
        $iTotalRecords = Packages::getTotalRecords($request, Auth::User()->account_id, $id, $apply_filter, $filename);

        [$orderBy, $order] = getSortBy($request, 'id', 'DESC');

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $packages = Packages::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $id, $apply_filter, $filename);

        $records = $this->getFiltersData($records);
        if ($packages) {
            foreach ($packages as $package) {
                $packageservices_price = PackageService::with('service')->where('package_id', '=', $package->id)->sum('package_services.price');
                $session_count = count(PackageService::where('package_id', '=', $package->id)->get());
                /*We discuss in future what happen next*/
                $cash_receive = PackageAdvances::where([
                    ['package_id', '=', $package->id],
                    ['cash_flow', '=', 'in'],
                    ['is_cancel', '=', '0'],
                    
                ])->sum('cash_amount');
                $package_is_refunded_amount = PackageAdvances::where([
                    'package_id' => $package->id,
                    'cash_flow' => 'out',
                    'is_refund' => '1',
                    'is_tax' => '0',
                ])->sum('cash_amount');
                
                $settle_amount = PackageAdvances::where([
                    ['package_id', '=', $package->id],
                    ['cash_flow', '=', 'out'],
                    ['is_cancel', '=', '0'],
                    ['is_tax', '=', '0'],
                    ['is_adjustment', '=', '0'],
                    ['is_refund', '=', '0'],
                    ['is_setteled', '=', '0'],
                ])->sum('cash_amount');
                $settle_tax_amount = PackageAdvances::where([
                    ['package_id', '=', $package->id],
                    ['cash_flow', '=', 'out'],
                    ['is_cancel', '=', '0'],
                    ['is_tax', '=', '1'],
                    ['is_adjustment', '=', '0'],
                    ['is_refund', '=', '0'],
                    ['is_setteled', '=', '0'],
                ])->sum('cash_amount');
                $settle_amount_with_tax = $settle_amount + $settle_tax_amount;
                if ($package->is_refund == '0') {
                    $refund_status = 'No';
                } else {
                    $refund_status = 'Yes';
                }
                $records['data'][] = [
                    'id' => $package->id,
                    'patient_id' => GeneralFunctions::patientSearchStringAdd($package->user?->id),
                    'name' => $package->user?->name ?? '',
                    'package_id' => $package?->name ?? '',
                    'location_id' => $package->location->city->name.'-'.$package->location->name,
                    'session_count' => $session_count,
                    'total' => number_format($packageservices_price),
                    'cash_receive' => number_format($cash_receive ),
                    'refunded' => number_format($package_is_refunded_amount ),
                    'settle_amount' => number_format($settle_amount_with_tax),
                    'refund' => $refund_status,
                    'created_at' => Carbon::parse($package->created_at)->format('F j,Y h:i A'),
                    'active' => $package->active,
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
            'edit' => Gate::allows('plans_edit'),
            'delete' => Gate::allows('plans_destroy'),
            'active' => Gate::allows('plans_active'),
            'inactive' => Gate::allows('plans_inactive'),
            'create' => Gate::allows('plans_create'),
            'log' => Gate::allows('plans_log'),
            'sms_log' => Gate::allows('plans_sms_log'),
            'plans_cash_edit' => Gate::allows('plans_cash_edit'),
            'plans_cash_delete' => Gate::allows('plans_cash_delete'),
            'plans_cash_edit_payment_mode' => Gate::allows('plans_cash_edit_payment_mode'),
            'plans_cash_edit_amount' => Gate::allows('plans_cash_edit_amount'),
            'plans_cash_edit_date' => Gate::allows('plans_cash_edit_date'),
            'patients_plan_cash_edit' => Gate::allows('patients_plan_cash_edit'),
            'patients_plan_cash_delete' => Gate::allows('patients_plan_cash_delete'),
        ];

        if ($id) {
            $records['permissions'] = [
                'edit' => Gate::allows('patients_plan_edit'),
                'manage' => Gate::allows('patients_plan_manage'),
                'delete' => Gate::allows('patients_plan_destroy'),
                'active' => Gate::allows('patients_plan_active'),
                'inactive' => Gate::allows('patients_plan_inactive'),
                'create' => Gate::allows('patients_plan_create'),
                'log' => Gate::allows('patients_plan_log'),
                'sms_log' => Gate::allows('patients_plan_sms_log'),
                'plans_cash_edit' => Gate::allows('plans_cash_edit'),
                'plans_cash_delete' => Gate::allows('plans_cash_delete'),
                'plans_cash_edit_payment_mode' => Gate::allows('plans_cash_edit_payment_mode'),
                'plans_cash_edit_amount' => Gate::allows('plans_cash_edit_amount'),
                'plans_cash_edit_date' => Gate::allows('plans_cash_edit_date'),
                'patients_plan_cash_edit' => Gate::allows('patients_plan_cash_edit'),
                'patients_plan_cash_delete' => Gate::allows('patients_plan_cash_delete'),
            ];
        }

        return ApiHelper::apiDataTable($records);
    }

    private function getFiltersData($records)
    {

        $filters = Filters::all(Auth::User()->id, 'packages');

        if ($user_id = Filters::get(Auth::User()->id, 'packages', 'patient_id')) {
            $patient = User::where([
                'id' => $user_id,
            ])->first();
            if ($patient) {
                $patient = $patient->toArray();
            } else {
                $patient = [];
            }
        } else {
            $patient = [];
        }

        $locations = Locations::getActiveSorted(ACL::getUserCentres(), 'full_address');

        $records['filter_values'] = [
            'package' => [],
            'locations' => $locations,
            'patient' => $patient,
            'status' => config('constants.status'),
        ];

        if (isset($filters['created_from'])) {
            $filters['created_from'] = date('Y-m-d', strtotime($filters['created_from']));
        }
        if (isset($filters['created_to'])) {
            $filters['created_to'] = date('Y-m-d', strtotime($filters['created_to']));
        }

        $records['active_filters'] = $filters;

        return $records;

    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        if (! Gate::allows('plans_inactive')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        if ($request->status == 1) {
            $response = Packages::activeRecord($request->id);
        } else {
            $response = Packages::inactiveRecord($request->id);
        }

        return ApiHelper::apiResponse($this->success, $response['message'], $response['status']);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        if (! Gate::allows('plans_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {

            $package = Packages::with('user', 'location')->find($id);

            /*Due to finance editing we calculate that "total" through package bundle otherwise we can use package->total_amount*/
            $total_price = PackageBundles::where('package_id', '=', $id)->sum('tax_including_price');

            $packagebundles = PackageBundles::with('bundle')->where('package_id', '=', $package->id)->get();
            $packageservices = PackageService::with('service')->where('package_id', '=', $package->id)->get();

            $packageadvances = PackageAdvances::with('paymentmode')->where([
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
                ['is_setteled', '=', '0'],
            ])->sum('cash_amount');

            $cash_amount_out = PackageAdvances::where([
                ['package_id', '=', $package->id],
                ['cash_flow', '=', 'out'],
            ])->sum('cash_amount');
            $refunded = PackageAdvances::where([
                'package_id' => $package->id,
                'cash_flow' => 'out',
                'is_refund' => 1,
            ])->sum('cash_amount');
            $setteled = PackageAdvances::where([
                'package_id' => $package->id,
                'cash_flow' => 'out',
                'is_setteled' => 1,
            ])->sum('cash_amount');
           
            /*We discuss it in future what happen next*/
            $grand_total = $total_price - $cash_amount_in;
            $remaining_amount  = number_format($grand_total + $refunded + $setteled);
            $paymentmodes = PaymentModes::where('type', '=', 'application')->pluck('name', 'id');
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

            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'package' => $package,
                'locations' => $locations,
                'packagebundles' => $packagebundles,
                'packageservices' => $packageservices,
                'packageadvances' => $packageadvances,
                'paymentmodes' => $paymentmodes,
                'grand_total' => $remaining_amount,
                'range' => $range,
                'locationhasservice' => $locationhasservice,
                'total_price' => $total_price,
                'end_previous_date' => $end_previous_date,
                'appointmentArray' => $appointmentArray,
                'discount_type' => config('constants.amount_types'),
                'discounts' => Discounts::where('active', 1)->get(['id', 'name']),
            ]);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * calculate the grand total
     *
     * @param request
     * @return mixed
     */
    public function getgrandtotal_update(Request $request)
    {
        $package = Packages::where('random_id', '=', $request->random_id)->first();
        $packageadvances_cash_amount = PackageAdvances::where([
            ['package_id', '=', $package->id],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
            ['is_setteled', '=', '0'],
        ])->sum('cash_amount');
        $refunded = PackageAdvances::where([
            'package_id' => $package->id,
            'cash_flow' => 'out',
            'is_refund' => 1,
        ])->sum('cash_amount');
        $setteled = PackageAdvances::where([
            'package_id' => $package->id,
            'cash_flow' => 'out',
            'is_setteled' => 1,
        ])->sum('cash_amount');
        $package_advances_cash_amount = $packageadvances_cash_amount;
        $package_total = str_replace(',', '', $request->total);
        $total_with_refunded = $package_total + $refunded + $setteled;
        $grand_total = number_format(($total_with_refunded - $package_advances_cash_amount) - $request->cash_amount);
        $package_id = Packages::whereId($package->id)->first();
        $package_id->update(['total_price' => $request->total, 'updated_at' => Filters::getCurrentTimeStamp()]);

        return ApiHelper::apiResponse($this->success, 'Record Updated', true, [
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
       
        $find_package = Packages::where('random_id',$request->random_id)->first();
        $check_is_setteled = PackageAdvances::where([
            ['cash_flow', '=', 'out'],
            ['cash_amount', '>', 0],
            ['is_setteled', '=', '1'],
            ['package_id', '=', $find_package->id],
        ])->first();
        if($check_is_setteled){
            return ApiHelper::apiResponse($this->success, 'Plan is already settled. you can not add further treatment in this plan.', false,['setteled'=>1]);
        }
        DB::beginTransaction();
        try {
            if (isset($request->appointment_id)) {
                $tag_appoint = explode('.', $request->appointment_id);
                if ($tag_appoint[1] == 'A') {
                    $appointment_id = $tag_appoint[0];
                } else {
                    $PlanAppointmentCalculation = new PlanAppointmentCalculation();
                    $package_info_tag = Packages::where('random_id', '=', $request->random_id)->first();
                    $appointment_decision = Appointments::find($package_info_tag->appointment_id);
                    if (isset($appointment_decision)) {
                        $appointment_id = $PlanAppointmentCalculation->updateAppointment($request->patient_id, $request->location_id, $request, $tag_appoint[0], $package_info_tag);
                    } else {
                        $appointment_id = $PlanAppointmentCalculation->storeAppointment($request->patient_id, $request->location_id, $request, $tag_appoint[0], false);
                        $PlanAppointmentCalculation->saveinvoice($appointment_id);
                    }
                }
            } else {
                return ApiHelper::apiResponse($this->success, 'Appointment not found', false);
            }
            /*save Package information and also update random id in package service table*/

            $data_package = $request->all();
            $data_package['total_price'] = str_replace(',', '', $request->total); //filter_var($request->total, FILTER_SANITIZE_NUMBER_INT);
            $data_package['sessioncount'] = '1';
            $data_package['account_id'] = Auth::User()->account_id;
            $data_package['appointment_id'] = $appointment_id;
            $data_package['updated_at'] = Filters::getCurrentTimeStamp();
            $random_id = $request->random_id;

            $package = Packages::updateRecord($data_package, $random_id, $request);

            /*End*/
            if ($request->cash_amount == '0') {

                // Commit Transaction
                DB::commit();

                return ApiHelper::apiResponse($this->success, 'updated successfully');
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
                $data_packageAdvances['location_id'] = $request->location_id;
                $data_packageAdvances['updated_at'] = Filters::getCurrentTimeStamp();
                /*End*/

                $packageAdavances = PackageAdvances::updateRecord($data_packageAdvances, $package);
                $patient = User::whereId($request->patient_id)->first();
                $location = Locations::whereId($request->location_id)->first();
                $activity = new Activity();
                $activity->action = 'received';
                $activity->patient = $patient->name;
                $activity->appointment_type = 'Plan';
                $activity->created_by = Auth::user()->name;
                $activity->planId = $package->id;
                $activity->amount = $request->cash_amount;
                $activity->location = $location->name;
                $activity->created_at = Filters::getCurrentTimeStamp();
                $activity->updated_at = Filters::getCurrentTimeStamp();
                $activity->save();
                /*Now sent message to user about cash received*/
                Invoice_Plan_Refund_Sms_Functions::PlanCashReceived_SMS($package->id, $packageAdavances);

                // Commit Transaction
                DB::commit();

                return ApiHelper::apiResponse($this->success, 'updated successfully');
            }
        } catch (\Exception $e) {
            // Rollback Transaction
            DB::rollback();

            return ApiHelper::apiResponse($this->success, $e->getMessage().' - '.$e->getFile().' - '.$e->getLine(), false);
        }
    }
    protected function verifyRefundsFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'refund_amount' => 'required',
            'refund_note' => 'required',
            'payment_mode_id' =>'required',

            
        ]);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        if (! Gate::allows('plans_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        $response = Packages::deleteRecord($id);

        return ApiHelper::apiResponse($this->success, $response['message'], $response['status']);

    }

    /**
     * display the package.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function display($id)
    {
        if (! Gate::allows('plans_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        $package = Packages::with('user', 'location')->find($id);

        $packagebundles = PackageBundles::with('bundle')->where('package_id', '=', $package->id)->get();

        $packageservices = PackageService::with('service')->where('package_id', '=', $package->id)->get();
        $packageservices_price = PackageService::with('service')->where('package_id', '=', $package->id)->sum('package_services.price');
        $packageadvances = PackageAdvances::with('paymentmode')->where([
            ['package_id', '=', $package->id],
            ['is_cancel', '=', '0'],
            //['is_tax', '=', '0'],
            ['is_adjustment', '=', '0'],
            //['is_refund', '=', '0']
        ])->get();

        $packageadvances = $this->appointmentPackage($packageadvances);

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

        //$grand_total = number_format($package->total_price - $cash_amount_in);
        $grand_total = round($packageservices_price, 2);
        $services = Services::getServices();
        $discount = Discounts::getDiscount(Auth::User()->account_id);
        $paymentmodes = PaymentModes::get()->pluck('name', 'id');

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'package' => $package,
            'packagebundles' => $packagebundles,
            'packageservices' => $packageservices,
            'packageadvances' => $packageadvances,
            'services' => $services,
            'discount' => $discount,
            'paymentmodes' => $paymentmodes,
            'grand_total' => $grand_total,
        ]);

    }

    private function appointmentPackage($packageadvances)
    {

        if ($packageadvances->count() > 0) {

            $packageAdvancesCollection = [];
            foreach ($packageadvances as $packageadvance) {
                if ($packageadvance->cash_flow == 'out' && $packageadvance->is_tax == 0) {
                    if (! is_null($packageadvance->refund_note)) {
                        $packageadvance->package_refund_price = number_format(PackageAdvances::getAppointmentPackage($packageadvance->appointment_id, $packageadvance->patient_id, $packageadvance->id));
                    } else {
                        $packageadvance->package_refund_price = number_format(PackageAdvances::getAppointmentPackage($packageadvance->appointment_id, $packageadvance->patient_id));
                    }
                } elseif ($packageadvance->is_tax == 0) {
                    $packageadvance->package_refund_price = number_format($packageadvance->cash_amount);
                } else {
                    $packageadvance->package_refund_price = '00.00';
                }
                $packageadvance->created_at_formated = Carbon::parse($packageadvance->created_at)->format('F j,Y H:i A');

                $packageAdvancesCollection[] = $packageadvance;

            }

            return $packageAdvancesCollection;
        }

        return $packageadvances;
    }

    /**
     * Print the package.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function package_pdf($id)
    {

        if (! Gate::allows('plans_manage')) {
            return abort(401);
        }
        $package = Packages::find($id);

        $location_info = Locations::find($package->location_id);

        $account_info = Accounts::find($package->account_id);

        $packagebundles = PackageBundles::where('package_id', '=', $package->id)->get();

        $packageservices = PackageService::where('package_id', '=', $package->id)->get();

        $packageadvances = PackageAdvances::where([
            ['package_id', '=', $package->id],
            ['is_cancel', '=', '0'],
            ['is_adjustment', '=', '0'],
            ['is_setteled', '=', '0'],
        ])->get();

        $cash_amount_in = PackageAdvances::where([
            ['package_id', '=', $package->id],
            ['cash_flow', '=', 'in'],
            ['is_setteled', '=', '0'],
        ])->sum('cash_amount');

        $cash_amount_out = PackageAdvances::where([
            ['package_id', '=', $package->id],
            ['cash_flow', '=', 'out'],
        ])->sum('cash_amount');
        $packageservices_price = PackageService::with('service')->where('package_id', '=', $package->id)->sum('package_services.price');
        $cash_amount = $cash_amount_in - $cash_amount_out;
        /*We discuss it in future what happen next*/
        //$grand_total = number_format($package->total_price - $cash_amount_in);
        $grand_total = number_format($packageservices_price);
        $services = Services::getServices();
        $discount = Discounts::getDiscount(Auth::User()->account_id);

        $paymentmodes = PaymentModes::get()->pluck('name', 'id');
        $paymentmodes->prepend('Select Payment Mode', '');

        $company_phone_number = Settings::where('slug', '=', 'sys-headoffice')->first();

        $content = view('admin.packages.packagepdf', compact('package', 'packagebundles', 'packageservices', 'packageadvances', 'services', 'discount', 'paymentmodes', 'grand_total', 'location_info', 'account_info', 'company_phone_number'));
        $pdf = App::make('dompdf.wrapper');
        $pdf->loadHTML($content);

        return $pdf->stream('treatment-plans-invoice-C-'.$package->patient_id.'.pdf');

    }

    /*
     * $edit the cash that enter in package advances
     */
    public function editpackageadvancescashindex($id, $package_id)
    {
        $pack_adv_info = PackageAdvances::find($id);

        $paymentmodes = PaymentModes::where('type', '=', 'application')->get();

        return ApiHelper::apiResponse($this->success, 'data found', true, [
            'pack_adv_info' => $pack_adv_info,
            'package_id' => $package_id,
            'paymentmodes' => $paymentmodes,
        ]);
        //  return view('admin.packages.finance_edit.create', compact('pack_adv_info', 'package_id', 'paymentmodes'));
    }

    /*
     * Store the cash that is request to change
     */

    public function storepackageadvancescash(Request $request)
    {
        $package_total_price = PackageBundles::where('package_id', '=', $request->package_id)->sum('tax_including_price');
        $get_package_use_amount = PackageAdvances::where([
            ['package_id', '=', $request->package_id],
            ['cash_flow', '=', 'out'],
        ])->sum('cash_amount');
        $get_package_unused_amount_except_edit = PackageAdvances::where([
            ['id', '!=', $request->package_advances_id],
            ['package_id', '=', $request->package_id],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
        ])->sum('cash_amount');
        $get_package_unused_amount_with_edit = $request->cash_amount;
        $get_package_unuse_amount = $get_package_unused_amount_except_edit + $get_package_unused_amount_with_edit;
        $amount_status = true;
        $record = PackageAdvances::updateRecordFinanceedit($request, Auth::User()->account_id, $amount_status);
        if ($record) {
            return ApiHelper::apiResponse($this->success, 'Data Updated successfully.', true, [
                'amount_status' => $amount_status,
            ]);
        }
    }

    /*
     * Delete the cash that reqquire to delete
     */
    public function deletepackageadvancescash(Request $request)
    {
        $packageadvanceinfo = PackageAdvances::withTrashed()->find($request->package_advance_id);

        $get_package_use_amount = PackageAdvances::where([
            ['package_id', '=', $packageadvanceinfo->package_id],
            ['cash_flow', '=', 'out'],
        ])->sum('cash_amount');
        $get_package_unused_amount_except_edit = PackageAdvances::where([
            ['id', '!=', $request->package_advance_id],
            ['package_id', '=', $packageadvanceinfo->package_id],
            ['cash_flow', '=', 'in'],
        ])->sum('cash_amount');
        if ($get_package_use_amount <= $get_package_unused_amount_except_edit) {

            $record = PackageAdvances::deletefinaceRecord($request);
            $cash_receveive_remain = number_format(filter_var($request->cash_receveive_remain, FILTER_SANITIZE_NUMBER_INT) + $packageadvanceinfo->cash_amount);

            return ApiHelper::apiResponse($this->success, 'Record deleted successfully.', true, [
                'id' => $request->package_advance_id,
                'cash_receveive_remain' => $cash_receveive_remain,
            ]);

        }

        return ApiHelper::apiResponse($this->success, 'Unable to delete consume amount.', false);
    }

    /*
     *  Get the information of appointment against
     */
    public function getappointmentinfo(Request $request)
    {
        $appointmentArray = PlanAppointmentCalculation::tagAppointments($request);

        return ApiHelper::apiResponse($this->success, 'Recode found', true, [
            'appointments' => $appointmentArray,
        ]);
    }

    /*
     *  Function for log for package
     */
    public function packagelog($id, $type)
    {
        if (! Gate::allows('plans_log')) {
            return abort(401);
        }

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
            if (! isset($finance_log[$audittrail->id]['cash_flow']) && $action_array[$audittrail->audit_trail_action_name] != 'Delete') {

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
            return view('admin.packages.log');
        }

        return $this->packagelogexcel($id, $finance_log);

    }

    public function planDatatable(Request $request, $id)
    {

        $records = [];

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

        [$orderBy, $order] = getSortBy($request);

        $iTotalRecords = AuditTrails::whereIn('table_record_id', $find_ids)
            ->where('audit_trail_table_name',
                Config::get('constants.package_advance_table_name_log')
            )->count();

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $audittrails = AuditTrails::whereIn('table_record_id', $find_ids)
            ->where('audit_trail_table_name',
                Config::get('constants.package_advance_table_name_log')
            )->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('created_at', 'asc')->get();

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
            if (! isset($finance_log[$audittrail->id]['cash_flow']) && $action_array[$audittrail->audit_trail_action_name] != 'Delete') {

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

        if (! empty($finance_log)) {

            $records['data'] = $finance_log;

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

    /*
     *  Function for log for package
     */

    public function packagelogexcel($id, $finance_log)
    {
        if (! Gate::allows('plans_log')) {
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

    /**
     * Load plan Sms History.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showSMSLogs($id)
    {
        $SMSLogs = SMSLogs::where('package_id', '=', $id)->orderBy('created_at', 'desc')->get();

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'SMSLogs' => $SMSLogs,
        ]);
    }

    /**
     * Re-send Plan SMS
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateAppointmentsRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendLogSMS(Request $request)
    {

        $SMSLog = SMSLogs::findOrFail($request->get('id'));

        if ($SMSLog) {
            $response = $this->resendSMS($SMSLog->id, $SMSLog->to, $SMSLog->text, $SMSLog->package_id);

            if ($response['status']) {
                return ApiHelper::apiResponse($this->success, 'SMS sent successfully.');
            }
        }

        return ApiHelper::apiResponse($this->success, 'SMS not sent.', false);
    }

    /**
     * Calling sms log
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateAppointmentsRequest  $request
     * @return \Illuminate\Http\Response
     */
    private function resendSMS($smsId, $patient_phone, $preparedText, $package_id)
    {
        $package_info = Packages::find($package_id);

        $setting = Settings::whereSlug('sys-current-sms-operator')->first();

        $UserOperatorSettings = UserOperatorSettings::getRecord($package_info->account_id, $setting->data);

        if ($setting->data == 1) {
            $SMSObj = [
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'to' => $patient_phone,
                'text' => $preparedText,
                'mask' => $UserOperatorSettings->mask, // Setting ID 3 for Mask
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            ];
            $response = TelenorSMSAPI::SendSMS($SMSObj);
        } else {
            $SMSObj = [
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'from' => $UserOperatorSettings->mask,
                'to' => $patient_phone,
                'text' => $preparedText,
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            ];
            $response = JazzSMSAPI::SendSMS($SMSObj);
        }
        if ($response['status']) {
            SMSLogs::find($smsId)->update(['status' => 1]);
        }

        return $response;
    }

    /*
     * Function get the variable to search in database to get the package
     *
     * */
    public function getpackage(Request $request)
    {
        $package = Packages::where('name', 'LIKE', "%{$request->q}%")->select('name', 'id')->get();

        return response()->json($package);
    }
    public function getPlans(Request $request)
    {
        $plans  = Packages::where('patient_id',$request->patient_id)->pluck('name');
        return response()->json(['stataus' =>1,'message'=>'plan found','plans'=>$plans]);
    }
    public function editRefund($id)
    {
        $return_tax_amount = '';

        $package_information = Packages::find($id);
        
        $patient = User::whereId($package_information->patient_id)->first();
        /*calculation for back date refund entry*/
        $package_advance_last_in = PackageAdvances::where([
            ['cash_flow', '=', 'in'],
            ['is_setteled', '=', '0'],
            ['cash_amount', '>', 0],
            ['package_id', '=', $package_information->id],
        ])->orderBy('created_at', 'desc')->first();
     
        $date_backend = date('Y-m-d', strtotime($package_advance_last_in->created_at));
        $bundle_information = PackageBundles::where('package_id', '=', $id)->first();
        $tax_percentage = $bundle_information->tax_percenatage ?? '';
        $is_adjustment_amount = 0;
        $package_is_refunded_amount = PackageAdvances::where([
            ['package_id', '=', $id],
            ['cash_flow', '=', 'out'],
            ['is_refund', '=', '1'],
            ['is_tax', '=', '0'],
        ])->sum('cash_amount');
        $package_is_setteled = PackageAdvances::where([
            ['package_id', '=', $id],
            ['cash_flow', '=', 'out'],
            ['is_setteled', '=', '1'],
            ['is_tax', '=', '0'],
        ])->sum('cash_amount');
       $amount_to_refund = $package_is_refunded_amount + $package_is_setteled;
        /*Document charges*/
        $documentationcharges = Settings::where('slug', '=', 'sys-documentationcharges')->first();
        $package_cash_receive = PackageAdvances::where([
            ['package_id', '=', $id],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
            ['is_setteled', '=', '0'],
        ])->sum('cash_amount');
        $package_refunded_amount = PackageAdvances::where([
            ['package_id', '=', $id],
            ['cash_flow', '=', 'out'],
            ['is_cancel', '=', '0'],
            ['is_refund', '=', '1'],
            ['cash_amount', '>', '0'],
        ])->latest()->first();
        $latest_package_refunded_amount = PackageAdvances::where([
            ['package_id', '=', $id],
            ['cash_flow', '=', 'out'],
            ['is_cancel', '=', '0'],
            ['is_refund', '=', '1'],
        ])->latest()->first();
        $package_setteled_amount = PackageAdvances::where([
            ['package_id', '=', $id],
            ['cash_flow', '=', 'out'],
            ['is_cancel', '=', '0'],
            ['is_setteled', '=', '1'],
        ])->sum('cash_amount');
        if ($package_cash_receive) {
            $package_service_originalPrice_consumed = PackageService::where([
                ['package_id', '=', $id],
                ['is_consumed', '=', '1'],
            ])->sum('price');

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

            $package_service_Price_consumed_without_tax = PackageService::where([
                ['package_id', '=', $id],
                ['is_consumed', '=', '1'],
            ])->sum('tax_exclusive_price');
            /*Tax amount that given from customer*/
            $given_tax_amount = $package_service_Price_consumed_tax - $package_service_Price_consumed_without_tax;
            /*ans is :: 32*/

            $return_tax_amount = ($cosume_amount_tax - $given_tax_amount);
            $cal_adjustment_final = $package_service_Price_consumed_tax + ($package_cash_receive - $refund_1);
            $is_adjustment_amount = ceil(($package_cash_receive - $cal_adjustment_final) - $return_tax_amount);
            $return_tax_amount = ceil($return_tax_amount);

        }
        if ($refundable_amount < 0) {
            $refundable_amount = 0;
        }
        $package_is_adjuestment_amount = PackageAdvances::where([
            'package_id' => $id,
            'cash_flow' => 'out',
            'is_adjustment' => '1',
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
            'refunded_amount' =>$package_refunded_amount->cash_amount,
            'record_id' =>$package_refunded_amount->id,
            'package_setteled_amount'=>$package_setteled_amount,
            'patient_name' => $patient->name,
            'patient_id' =>$patient->id,
            'plan'=>$package_information->name,
            'created_date'=>Carbon::parse($latest_package_refunded_amount->created_at)->format('Y-m-d'),
            'refund_note' =>$latest_package_refunded_amount->refund_note,
            'payment_method_id' =>$latest_package_refunded_amount->payment_mode_id
        ]);
    }
    public function updateRefund(Request $request)
    {
       
        $latest_refund = PackageAdvances::where([
            ["package_id",'=',$request['package_id']],
            ['is_refund','=',1],
            ['cash_amount','>',0],
            ['is_tax','=',0],
        ]
            
        )->latest()->first();
        
       if($request['case_setteled'] == 'on'){
       
        $package_cash_receive = PackageAdvances::where([
            ['package_id', '=', $request->package_id],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
            
        ])->sum('cash_amount');
       
        $package_is_refunded_amount = PackageAdvances::where([
            ['package_id', '=', $request->package_id],
            ['cash_flow', '=', 'out'],
            ['is_refund', '=', '1'],
            ['is_tax', '=', '0'],
            ['is_setteled', '=', '0'],
        ])->sum('cash_amount');
        
        $package_is_consumed_amount = PackageAdvances::where([
            ['package_id', '=', $request->package_id],
            ['cash_flow', '=', 'out'],
            ['is_refund', '=', '0'],
            ['is_tax', '=', '0'],
            ['is_setteled', '=', '0'],
        ])->sum('cash_amount');
       
        $package_is_consumed_tax_amount = PackageAdvances::where([
            ['package_id', '=', $request->package_id],
            ['cash_flow', '=', 'out'],
            ['is_refund', '=', '0'],
            ['is_tax', '=', '1'],
            ['is_setteled', '=', '0'],
        ])->sum('cash_amount');
        $consumed_amount_with_tax = $package_is_consumed_amount + $package_is_consumed_tax_amount;
       
        $package_is_refunded_amount = PackageAdvances::where([
            ['package_id', '=', $request->package_id],
            ['cash_flow', '=', 'out'],
            ['is_refund', '=', '1'],
            ['is_tax', '=', '0'],
        ])->sum('cash_amount');
        $amount_after_refund = $consumed_amount_with_tax + $package_is_refunded_amount;
        $amount_left = $package_cash_receive - $amount_after_refund;
        $packageinformation = Packages::find($request->package_id);
       
        if($amount_left > 0){
            
            $data_adjustment['cash_flow'] = 'out';
            $data_adjustment['cash_amount'] = $amount_left;
            $data_adjustment['is_adjustment'] = '0';
            $data_adjustment['is_setteled'] = 1;
            $data_adjustment['patient_id'] = $request->get('patient_id');
            $data_adjustment['payment_mode_id'] = $request->payment_mode_id;
            $data_adjustment['account_id'] = Auth::User()->account_id;
            $data_adjustment['created_by'] = Auth::User()->id;
            $data_adjustment['updated_by'] = Auth::User()->id;
            $data_adjustment['package_id'] = $request->package_id;
            $data_adjustment['patient_id'] = $packageinformation->patient_id;
            $data_adjustment['location_id'] = $packageinformation->location_id;

            $data_adjustment['created_at'] = $request['created_at'];
            $data_adjustment['updated_at'] = $request['created_at'];

            PackageAdvances::create($data_adjustment);
            $package_service = PackageService::where('package_id',$request->package_id)->first();
            $dataInvoice['total_price'] = $amount_left;
                $dataInvoice['account_id'] = Auth::User()->account_id;
                $dataInvoice['patient_id'] = $packageinformation->patient_id;
                $dataInvoice['appointment_id'] = $packageinformation->appointment_id;
                $dataInvoice['invoice_status_id'] = 3;
                $dataInvoice['created_by'] = Auth::User()->id;
                $dataInvoice['location_id'] =$packageinformation->location_id;
                //$dataInvoice['doctor_id'] = Auth::User()->id;
                $dataInvoice['active'] = 1;
                $dataInvoice['is_exclusive'] = 0;
               $create_invoice =  Invoices::create($dataInvoice);
               $dataInvoiceDetail['qty'] = 1;
               $dataInvoiceDetail['service_id'] =$package_service->service_id;
               
                //$dataInvoice['doctor_id'] = Auth::User()->id;
               $dataInvoiceDetail['invoice_id'] = $create_invoice->id;
               InvoiceDetails::create($dataInvoiceDetail);
        }
    }
        $latest_refund->where('id',$request['record_id'])->update(['created_at' => $request['created_at'] , 'cash_amount' => $request['refund_amount'],'payment_mode_id' => $request['payment_mode_id']]);
        return ApiHelper::apiResponse($this->success, 'Record updated', true, [
        ]);
    }
}
