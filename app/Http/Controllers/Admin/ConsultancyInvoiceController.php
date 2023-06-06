<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Helpers\Widgets\ConsultancyPriceCalculationWidget;
use App\Helpers\Widgets\DiscountWidget;
use App\Http\Controllers\Controller;
use App\Jobs\IndexSingleAppointmentJob;
use App\Models\Activity;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\Discounts;
use App\Models\InvoiceDetails;
use App\Models\Invoices;
use App\Models\InvoiceStatuses;
use App\Models\Leads;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\PaymentModes;
use App\Models\Services;
use App\Models\User;
use Auth;
use Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConsultancyInvoiceController extends Controller
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

    /*
     *Function for display the consultancy invoice detail
     */
    public function invoiceconsultancy($id, $type = null)
    {
        if (! Gate::allows('appointments_manage') && ! Gate::allows('appointments_view')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        $invoice_status = InvoiceStatuses::where('slug', '=', 'paid')->first();

        $invoice = Invoices::where([
            ['appointment_id', '=', $id],
            ['invoice_status_id', '=', $invoice_status->id],
        ])->first();

        if ($invoice == null) {

            $balance = 0;
            $cash = 0;
            $price_tax = 0;
            $tax = 0;
            $price = 0;

            $appointment = Appointments::find($id);

            $location_info = Locations::find($appointment->location_id);

            $appointment_type = AppointmentTypes::find($appointment->appointment_type_id);

            $service = Services::find($appointment->service_id);

            /*Here We can find the possible discounts*/
            $discounts = DiscountWidget::Discount_data_consultancy($appointment, Auth::User()->account_id);
            /*End*/
            $price = $tax = $price_tax = $tax_amt = $cash = $balance = 0;

            if ($appointment_type->name == Config::get('constants.Consultancy')) {
                $serviceinfo = Services::where('id', '=', $appointment->service_id)->first();
                if ($serviceinfo) {

                    /*I calculate prices as exculsive*/
                    if ($serviceinfo->tax_treatment_type_id == Config::get('constants.tax_both') || $serviceinfo->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
                        $price = $price_tax = $serviceinfo->price;
                        $tax = ceil($price * ($location_info->tax_percentage / 100));
                        $tax_amt = ceil($price + $tax);
                    } else {
                        $tax_amt = $price_tax = $serviceinfo->price;
                        $price = ceil((100 * $tax_amt) / ($location_info->tax_percentage + 100));
                        $tax = ceil($tax_amt - $price);
                    }
                }
                /*End*/
            }
            $outstanding = $tax_amt - $cash - $balance;

            if ($outstanding < 0) {
                $outstanding = 0;
            }

            $settleamount_1 = $price - $cash;
            $settleamount = min($settleamount_1, $balance);

            $invoice_status = false;

        } else {

            $invoice_status = true;
            $price = null;
            $appointment_type = null;
            $service = null;
            $balance = null;
            $settleamount = null;
            $outstanding = null;
            $tax = null;
            $tax_amt = null;
            $location_info = null;
            $discounts = null;
            $cash = null;
        }
        $paymentmodes = PaymentModes::where('type', '=', 'application')->pluck('name', 'id');
        $paymentmodes->prepend('Select', '0');

        if (is_null($type)) {

            return ApiHelper::apiResponse($this->success, 'Data found.', true, [
                'price' => $price,
                'appointment_type' => $appointment_type,
                'id' => $id,
                'service' => $service,
                'balance' => $balance,
                'settleamount' => $settleamount,
                'outstanding' => $outstanding,
                'paymentmodes' => $paymentmodes,
                'location_info' => $location_info,
                'tax' => $tax,
                'tax_amt' => $tax_amt,
                'invoice_status' => $invoice_status,
                'discounts' => $discounts,
                'cash' => $cash,
                'price_tax' => $price_tax,
            ]);
        }

        return view('admin.appointments.consultancyinvoice.create', compact('price', 'appointment_type', 'id', 'service', 'balance', 'settleamount', 'outstanding', 'paymentmodes', 'location_info', 'tax', 'tax_amt', 'invoice_status', 'discounts', 'cash', 'price_tax'));
    }

    /*
     * Function for calculation of consultancy invoice
     */
    public function getconsultancycalculation(Request $request)
    {
        $appointment_info = Appointments::find($request->appointment_id);
        $location_info = Locations::find($request->location_id);
        $discount_info = Discounts::find($request->discount_id);
        $price_for_calculation = $request->price_for_calculation;
        $cash = 0;
        $balance = 0;

        if ($discount_info) {
            $data = ConsultancyPriceCalculationWidget::ConsultancyPriceCalculation($request, $price_for_calculation, $location_info, $cash, $balance);
            if ($discount_info->slug == 'custom') {
                return response()->json([
                    'status' => false,
                    'discount_ava_check' => 'true',
                    'price' => $data['price'],
                    'tax' => $data['tax'],
                    'tax_amt' => $data['tax_amt'],
                    'settleamount' => $data['settleamount'],
                    'outstanding' => $data['outstanding'],
                ]);
            } else {
                /*Here We find the discounted price*/
                if ($discount_info->type == Config::get('constants.Fixed')) {
                    $discount_type = Config::get('constants.Fixed');
                    $discount_price = $discount_info->amount;
                    $net_amount = ($price_for_calculation) - ($discount_info->amount);
                } else {
                    $discount_type = Config::get('constants.Percentage');
                    $discount_price = $discount_info->amount;
                    $discount_price_cal = $price_for_calculation * (($discount_price) / 100);
                    $net_amount = ($price_for_calculation) - ($discount_price_cal);
                }
                /*End*/
                /*Here We find price for exclusive or not */
                if ($request->tax_treatment_type_id == Config::get('constants.tax_both')) {
                    if ($request->is_exclusive_consultancy == '1') {
                        $price = $net_amount;
                        $tax = ceil(($price * ($location_info->tax_percentage / 100)));
                        $tax_amt = ceil(($price + (($price * $location_info->tax_percentage) / 100)));
                    } else {
                        $tax_amt = $net_amount;
                        $price = ceil(((100 * $tax_amt) / ($location_info->tax_percentage + 100)));
                        $tax = ceil(($tax_amt - $price));
                    }
                } elseif ($request->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
                    $price = $net_amount;
                    $tax = ceil(($price * ($location_info->tax_percentage / 100)));
                    $tax_amt = ceil(($price + (($price * $location_info->tax_percentage) / 100)));
                } else {
                    $tax_amt = $net_amount;
                    $price = ceil(((100 * $tax_amt) / ($location_info->tax_percentage + 100)));
                    $tax = ceil(($tax_amt - $price));
                }

                /*End*/
                $outstanding = $tax_amt - $cash - $balance;

                if ($outstanding < 0) {
                    $outstanding = 0;
                }

                $settleamount_1 = $price - $cash;
                $settleamount = min($settleamount_1, $balance);

                return response()->json([
                    'status' => true,
                    'discount_type' => $discount_type,
                    'discount_price' => $discount_price,
                    'price' => $price,
                    'tax' => $tax,
                    'tax_amt' => $tax_amt,
                    'settleamount' => $settleamount,
                    'outstanding' => $outstanding,
                ]);
            }
        } else {
            $data = ConsultancyPriceCalculationWidget::ConsultancyPriceCalculation($request, $price_for_calculation, $location_info, $cash, $balance);

            return response()->json([
                'status' => false,
                'discount_ava_check' => 'false',
                'price' => $data['price'],
                'tax' => $data['tax'],
                'tax_amt' => $data['tax_amt'],
                'settleamount' => $data['settleamount'],
                'outstanding' => $data['outstanding'],
            ]);
        }
    }

    /**
     * function for calculation of custom discounts.
     *
     * @return Response
     */
    public function getcustomcalculation(Request $request)
    {
        $status = true;
        $cash = 0;
        $balance = 0;
        $location_info = Locations::find($request->location_id);

        $discount_id = $request->discount_id;

        $discount_data = Discounts::find($discount_id);

        if ($request->discount_type == Config::get('constants.Fixed')) {

            $discount_type = Config::get('constants.Fixed');

            $discount_price = $request->discount_value;

            $discount_price_in_percentage = ($discount_price / $request->price) * 100;

            if ($discount_data->amount >= $discount_price_in_percentage) {

                $net_amount = ($request->price) - ($discount_price);

            } else {
                $status = false;
            }

        } else {

            $discount_type = Config::get('constants.Percentage');

            $discount_price = $request->discount_value;

            if ($discount_data->amount >= $discount_price) {

                $discount_price_cal = $request->price * (($discount_price) / 100);

                $net_amount = ($request->price) - ($discount_price_cal);

            } else {
                $status = false;
            }
        }
        if ($status == true) {
            $data = ConsultancyPriceCalculationWidget::ConsultancyPriceCalculation($request, $net_amount, $location_info, $cash, $balance);

            return response()->json([
                'status' => true,
                'price' => $data['price'],
                'tax' => $data['tax'],
                'tax_amt' => $data['tax_amt'],
                'settleamount' => $data['settleamount'],
                'outstanding' => $data['outstanding'],
            ]);
        } else {
            return response()->json([
                'status' => false,
            ]);
        }
    }

    /*
     * Function for check discount is custom or not
     */
    public function checkedcustom(Request $request)
    {
        $discount = Discounts::find($request->discount_id);
        if ($discount) {
            if ($discount->slug == 'custom') {
                return response()->json([
                    'status' => true,
                ]);
            } else {
                return response()->json([
                    'status' => false,
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
            ]);
        }
    }

    /*
     * Checked can we save invoice or not
     *
     * */
    public function getfinalcalculation(Request $request)
    {
        if ($request->amount_type == 0) {
            if ($request->cash == 0 || $request->cash < 0) {
                return response()->json([
                    'status' => true,
                    'outstdanding' => $request->outstanding,
                    'settleamount' => $request->settleamount,
                ]);
            }
            $outstdanding = $request->price - $request->cash - $request->balance;

            $balance = $request->balance;

            $settleamount = $request->price - $request->cash;

            $settleamount = min($settleamount, $balance);

            return response()->json([
                'status' => true,
                'outstdanding' => $outstdanding,
                'settleamount' => $settleamount,

            ]);
        } else {
            return response()->json([
                'status' => true,
                'outstdanding' => 0,
                'settleamount' => 0,

            ]);
        }
    }

    /*
     * Save Consultancy Invoice
     */
    public function saveinvoice(Request $request)
    {
        if ($request->payment_mode_id == '0') {
            $payment = PaymentModes::first();
            $payment_mode_id = $payment->id;
        } else {
            $payment_mode_id = $request->payment_mode_id;
        }
        $paymentmode_settle = PaymentModes::where(['payment_type' => Config::get('constants.payment_type_settle')])->first();
        $invoicestatus = InvoiceStatuses::where(['slug' => 'paid'])->first();
        $appointmentinfo = Appointments::find($request->appointment_id);
        if (! Gate::allows('appointments_log_excel')) {
            if ($appointmentinfo->scheduled_date < date('Y-m-d') || $appointmentinfo->scheduled_date > date('Y-m-d')) {
                return response()->json(['message' => 'Invoice can not be genersted in past and future dates.', 'status' => false]);
            }
        }
        if ($request->tax_treatment_type_id == Config::get('constants.tax_both')) {
            $is_exclusive = $request->is_exclusive;
        } elseif ($request->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
            $is_exclusive = 1;
        } else {
            $is_exclusive = 0;
        }
        $data['total_price'] = $request->price;
        $data['account_id'] = Auth::User()->account_id;
        $data['patient_id'] = $appointmentinfo->patient_id;
        $data['appointment_id'] = $request->appointment_id;
        $data['invoice_status_id'] = $invoicestatus->id;
        $data['created_by'] = Auth::User()->id;
        $data['location_id'] = $appointmentinfo->location_id;
        $data['doctor_id'] = $appointmentinfo->doctor_id;
        $data['is_exclusive'] = $is_exclusive;
        $data['created_at'] = Filters::getCurrentTimeStamp();
        $data['updated_at'] = Filters::getCurrentTimeStamp();

        $invoice = Invoices::CreateRecord($data);

        $data_detail['tax_exclusive_serviceprice'] = $request->amount_create;
        $data_detail['tax_percenatage'] = $appointmentinfo->location->tax_percentage;
        $data_detail['tax_price'] = $request->tax_create;
        $data_detail['tax_including_price'] = $request->price;
        $data_detail['net_amount'] = $request->price;
        $data_detail['is_exclusive'] = $is_exclusive;

        $data_detail['qty'] = '1';
        $data_detail['service_price'] = $appointmentinfo?->service?->price ?? 0;
        $data_detail['service_id'] = $appointmentinfo->service_id ?? 0;
        $data_detail['invoice_id'] = $invoice->id;

        $data_detail['created_at'] = Filters::getCurrentTimeStamp();
        $data_detail['updated_at'] = Filters::getCurrentTimeStamp();

        $discount_info = Discounts::find($request->discount_id);

        if ($discount_info) {

            $data_detail['discount_id'] = $request->discount_id;
            $data_detail['discount_name'] = $discount_info->name;
            $data_detail['discount_type'] = $request->discount_type;
            $data_detail['discount_price'] = $request->discount_value;
        }

        $invoice_detail = InvoiceDetails::createRecord($data_detail, $invoice);

        $data_package['cash_flow'] = 'in';
        $data_package['cash_amount'] = $request->cash;
        $data_package['patient_id'] = $appointmentinfo->patient_id;
        $data_package['payment_mode_id'] = $payment_mode_id;
        $data_package['account_id'] = Auth::User()->account_id;
        $data_package['appointment_type_id'] = $appointmentinfo->appointment_type_id;
        $data_package['appointment_id'] = $request->appointment_id;
        $data_package['invoice_id'] = $invoice->id;
        $data_package['location_id'] = $appointmentinfo->location_id;
        $data_package['created_by'] = Auth::User()->id;
        $data_package['updated_by'] = Auth::User()->id;

        $data_package['created_at'] = Filters::getCurrentTimeStamp();
        $data_package['updated_at'] = Filters::getCurrentTimeStamp();

        $package_advances = PackageAdvances::createRecord_forinvoice($data_package);

        $out_transcation = $request->cash + $request->settle;

        $out_transcation_price = $out_transcation - $invoice_detail->tax_price;
        $out_transcation_tax = $invoice_detail->tax_price;

        $tran = [
            '1' => $out_transcation_price,
            '2' => $out_transcation_tax,
        ];
        $count = 0;
        foreach ($tran as $trans) {
            if ($count == '1') {
                $data_package['is_tax'] = 1;
            }
            $data_package['cash_flow'] = 'out';
            $data_package['cash_amount'] = $trans;
            $data_package['patient_id'] = $appointmentinfo->patient_id;
            $data_package['payment_mode_id'] = $paymentmode_settle->id;
            $data_package['account_id'] = Auth::User()->account_id;
            $data_package['appointment_type_id'] = $appointmentinfo->appointment_type_id;
            $data_package['appointment_id'] = $request->appointment_id;
            $data_package['invoice_id'] = $invoice->id;
            $data_package['location_id'] = $appointmentinfo->location_id;
            $data_package['created_by'] = Auth::User()->id;
            $data_package['updated_by'] = Auth::User()->id;

            $data_package['created_at'] = Filters::getCurrentTimeStamp();
            $data_package['updated_at'] = Filters::getCurrentTimeStamp();

            if ($invoice_detail->package_id != null) {
                $data_package['package_id'] = $invoice_detail->package_id;
            }
            $package_advances = PackageAdvances::createRecord_forinvoice($data_package);

            $count++;
        }

        $arrivedStatus = AppointmentStatuses::where('is_arrived', '=', 1)->select('id')->first();

        if (! $arrivedStatus) {
            $arrivedStatus = AppointmentStatuses::where('name', 'LIKE', '%Arrived%')->select('id')->first();
        }

        if (Appointments::where('id', '=', $request->appointment_id)->where('appointment_type_id', '=', Config::get('constants.appointment_type_consultancy'))->exists()) {

            if (AppointmentStatuses::where('parent_id', '=', $arrivedStatus?->id)->exists()) {
                $appointmentStatus = AppointmentStatuses::where('parent_id', '=', $arrivedStatus->id)->where('active', '=', 1)->first();
                if ($appointmentStatus) {
                    Appointments::where('id', '=', $request->appointment_id)->update(['base_appointment_status_id' => $arrivedStatus->id, 'appointment_status_id' => $appointmentStatus->id]);
                } else {
                    Appointments::where('id', '=', $request->appointment_id)->update(['base_appointment_status_id' => $arrivedStatus->id, 'appointment_status_id' => $arrivedStatus->id]);
                }
            } else {
                Appointments::where('id', '=', $request->appointment_id)->update(['base_appointment_status_id' => $arrivedStatus?->id, 'appointment_status_id' => $arrivedStatus?->id]);
            }
        }

        // In case of auto change status we need to update by so that s why we did
        $appointment_data_status['converted_by'] = Auth::User()->id;
        $appointmentinfo->update($appointment_data_status);
        // End
        Leads::where('patient_id', $appointmentinfo->patient_id)->update(['lead_status_id' => 4]);
        /////Save activity////
        $patient = User::whereId($appointmentinfo->patient_id)->first();
        $location = Locations::whereId($appointmentinfo->location_id)->first();
        $activity = new Activity();
        $activity->action = 'received';
        $activity->patient = $patient->name;
        $activity->appointment_type = $appointmentinfo->service->name.' Consultation';
        $activity->created_by = Auth::user()->name;
        $activity->invoice_id = $invoice->id;
        $activity->amount = $request->price;
        $activity->location = $location->name;
        $activity->created_at = Filters::getCurrentTimeStamp();
        $activity->updated_at = Filters::getCurrentTimeStamp();
        $activity->save();
        ////

        /**
         * Dispatch Elastic Search Index
         */
        $this->dispatch(
            new IndexSingleAppointmentJob([
                'account_id' => Auth::User()->account_id,
                'appointment_id' => $appointmentinfo->id,
            ])
        );

        return ApiHelper::apiResponse($this->success, 'Invoice created successfully', true, [
            'invoice_id' => $invoice->id,
        ]);
    }
}
