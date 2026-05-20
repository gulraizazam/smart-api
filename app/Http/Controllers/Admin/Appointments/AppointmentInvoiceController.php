<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Appointments;

use App\Enums\AppointmentType;
use App\Helpers\Filters;
use App\Helpers\Invoice_Plan_Refund_Sms_Functions;
use App\Helpers\Widgets\PlanAppointmentCalculation;
use App\Jobs\IndexSingleAppointmentJob;
use App\Models\Accounts;
use App\Models\Activity;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\Bundles;
use App\Models\Discounts;
use App\Models\InvoiceDetails;
use App\Models\Invoices;
use App\Models\InvoiceStatuses;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\PackageService;
use App\Models\PaymentModes;
use App\Models\Services;
use App\Models\Settings;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AppointmentInvoiceController extends AppointmentBaseController
{
    /*
     * Create Invoice index
     *
     * @oaran $id
     *
     * @return mixed
     */

    public function invoice(int $id): View|JsonResponse
    {

        if (! Gate::allows('appointments_manage') && ! Gate::allows('appointments_view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }
        $invoice_status = InvoiceStatuses::where('slug', '=', 'paid')->first();
        $invoice = Invoices::where([
            ['appointment_id', '=', $id],
            ['invoice_status_id', '=', $invoice_status->id],
        ])->first();
        if ($invoice == null) {
            $price = 0;
            $packages = null;
            $amount_create_is_inclusive = 0;
            $status = 'true';
            $appointment = Appointments::find($id);
            $balance = 0;
            $appointment_type = AppointmentTypes::find($appointment->appointment_type_id);
            $service = Services::find($appointment->service_id);
            /* In case of treatment not belongs to treatment plans So i set but must be null in case of consultancy and treatment plans */
            $amount_create = 0;
            $tax_create = 0;
            $location_id = 0;
            $checked_treatment = 0;
            $appointmentArray = [];
            $service_in_plan = false;
            if ($appointment_type->name == Config::get('constants.Service')) {
                /* Check if service has */
                // Check if service exists in any plan (consumed or not)
                $serviceInAnyPlan = DB::table('packages')
                    ->leftjoin('package_services', 'packages.id', '=', 'package_services.package_id')
                    ->where([
                        'packages.active' => '1',
                        'packages.patient_id' => $appointment->patient_id,
                        'package_services.service_id' => $appointment->service_id,
                        'packages.location_id' => $appointment->location_id,
                    ])->exists();

                $service_in_plan = $serviceInAnyPlan;

                $packages = DB::table('packages')
                    ->leftjoin('package_services', 'packages.id', '=', 'package_services.package_id')
                    ->where([
                        'packages.active' => '1',
                        'packages.patient_id' => $appointment->patient_id,
                        'package_services.service_id' => $appointment->service_id,
                        'package_services.is_consumed' => '0',
                        'packages.location_id' => $appointment->location_id,
                    ])->select('packages.id', 'packages.name')->groupby('packages.id', 'packages.name')->orderBy('packages.id', 'desc')->get();

                $status = 'true';
                if ($packages->isEmpty()) {
                    $location_information = Locations::find($appointment->location_id);
                    $location_id = $appointment->location_id;
                    $serviceinfo = Services::where('id', '=', $appointment->service_id)->first();

                    // Get service price from package_services.actual_price if exists, otherwise use services.price
                    $service_price = $serviceinfo->price;
                    if ($service_in_plan) {
                        $package_service = DB::table('package_services')
                            ->join('packages', 'package_services.package_id', '=', 'packages.id')
                            ->where([
                                'packages.active' => '1',
                                'packages.patient_id' => $appointment->patient_id,
                                'package_services.service_id' => $appointment->service_id,
                                'packages.location_id' => $appointment->location_id,
                            ])
                            ->select('package_services.actual_price')
                            ->first();

                        if ($package_service && $package_service->actual_price !== null) {
                            $service_price = $package_service->actual_price;
                        }
                    }

                    $orgTaxTreatment = Settings::getOrgTaxTreatment(Auth::user()->account_id);
                    if ($orgTaxTreatment == Config::get('constants.tax_both') || $orgTaxTreatment == Config::get('constants.tax_is_exclusive')) {
                        $amount_create = $amount_create_is_inclusive = $service_price;
                        $tax_create = ceil($service_price * ($location_information->tax_percentage / 100));
                        $price = ceil($amount_create + (($amount_create * $location_information->tax_percentage) / 100));

                    } else {
                        $price = $amount_create_is_inclusive = $service_price;
                        $amount_create = ceil((100 * $price) / ($location_information->tax_percentage + 100));
                        $tax_create = ceil($price - $amount_create);
                    }
                    $checked_treatment = 1;
                    $status = 'false';
                    $data['patient_id'] = $appointment->patient_id;
                    $data['location_id'] = $appointment->location_id;
                    $data = (object) $data;
                    $appointmentArray = PlanAppointmentCalculation::tagAppointments($data);
                }
            }
            $cash = 0;
            $outstanding = $price - $cash - $balance;
            if ($outstanding < 0) {
                $outstanding = 0;
            }
            $settleamount_1 = $price - $cash;
            $settleamount = min($settleamount_1, $balance);
            $invoice_status = false;
        } else {
            $invoice_status = true;
            $price = null;
            $packages = null;
            $appointment_type = null;
            $status = null;
            $service = null;
            $balance = null;
            $settleamount = null;
            $outstanding = null;
            $amount_create = null;
            $tax_create = null;
            $location_id = null;
            $checked_treatment = null;
        }

        $paymentmodes = PaymentModes::where('type', '=', 'application')->pluck('name', 'id');
        $paymentmodes->prepend('Select', '0');

        // Get patient name for modal title
        $patient = null;
        if (isset($appointment)) {
            $patient = User::find($appointment->patient_id);
        }

        return view('admin.appointments.invoice_create', compact('price', 'packages', 'appointment_type', 'status', 'id', 'service', 'balance', 'settleamount', 'outstanding', 'invoice_status', 'paymentmodes', 'tax_create', 'amount_create', 'location_id', 'checked_treatment', 'appointmentArray', 'amount_create_is_inclusive', 'service_in_plan', 'patient'));
    }

    public function saveinvoice(Request $request): JsonResponse
    {
        // Dual-purpose endpoint — invoice save for either consultations or
        // treatments depending on the appointment type. Gate on whichever
        // module's invoice-create perm matches; legacy `appointments_invoice`
        // kept as a transitional cross-module fallback.
        if (
            !\Illuminate\Support\Facades\Gate::allows('treatments.invoice.create')
            && !\Illuminate\Support\Facades\Gate::allows('consultations.invoice.create')
            && !\Illuminate\Support\Facades\Gate::allows('appointments_invoice')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to save invoices.',
            ], 403);
        }

        // Hard validation gate on the money-touching fields. Before this
        // (pre-2026-05-15), `$request->cash` flowed unchecked into
        // `package_advances.cash_amount` via `createRecord_forinvoice`
        // at lines 380 & 409. A POST with `cash: -999999` would have
        // written a negative income row that the observer translates
        // into a pool DRAIN — a direct money-stealing primitive that
        // bypassed every other defence on the create / edit paths.
        //
        // `integer` rejects scientific notation, fractional values,
        // strings, and negatives that bypass the eventual cast. `min:0`
        // (not min:1) allows legitimate zero-cash invoicing (the rest
        // of the method handles the zero case explicitly at line 431
        // and elsewhere). Outstanding-related fields validated for the
        // same reason.
        $request->validate([
            'cash' => 'sometimes|integer|min:0|max:99999999',
            'settle' => 'sometimes|integer|min:0|max:99999999',
            'cash_create' => 'sometimes|integer|min:0|max:99999999',
            'outstanding_for_zero' => 'sometimes|integer|min:0|max:99999999',
            'price_create' => 'sometimes|integer|min:0|max:99999999',
        ]);

        $check_is_setteled = PackageAdvances::where([
            ['cash_flow', '=', 'out'],
            ['cash_amount', '>', 0],
            ['is_setteled', '=', '1'],
            ['package_id', '=', $request->package_id],
        ])->first();
        if ($check_is_setteled) {
            return $this->errorResponse('This plan is settled out and cannot consume any further treatments.', 404, ['setteled' => 1]);
        }

        // ============================================
        // CONSUMPTION LOCK: Server-side validation
        // ============================================
        if ($request->package_service_id && $request->package_id) {
            $packageService = PackageService::find($request->package_service_id);

            // Calculate plan-level payment totals once (used by both checks)
            $totalPlanPayments = PackageAdvances::where('package_id', $request->package_id)
                ->where('cash_flow', 'in')
                ->sum('cash_amount');

            $totalPlanValue = PackageService::where('package_id', $request->package_id)
                ->sum('tax_including_price');

            // Allow 1-rupee tolerance for rounding (e.g. 3 × 6996.67 = 20990.01 vs 20990 payment)
            $isPlanFullyPaid = ($totalPlanPayments >= ($totalPlanValue - 1));

            // Ordering check: enforce BUY-before-GET within configurable discount groups
            // Skip ordering if the plan is fully paid (safe because plan is locked after first consumption)
            if (! $isPlanFullyPaid && $packageService && $packageService->consumption_order > 0) {
                $packageBundle = PackageBundles::find($packageService->package_bundle_id);
                $configGroupId = $packageBundle?->config_group_id;

                if ($configGroupId) {
                    $hasUnconsumedPrior = PackageService::join('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
                        ->where('package_services.package_id', $request->package_id)
                        ->where('package_bundles.config_group_id', $configGroupId)
                        ->where('package_services.is_consumed', 0)
                        ->where('package_services.consumption_order', '<', $packageService->consumption_order)
                        ->where('package_services.id', '!=', $packageService->id)
                        ->exists();

                    if ($hasUnconsumedPrior) {
                        return $this->errorResponse('Cannot consume this service yet. Please consume the paid sessions first before discounted/free sessions.', 404, ['consumption_locked' => 1]);
                    }
                }
            }

            // Payment coverage check (for any service with price > 0)
            // Skip if plan is fully paid — rounding may cause SUM(services) to slightly exceed actual payment
            if (! $isPlanFullyPaid && $packageService && $packageService->tax_including_price > 0) {
                $totalConsumedValue = PackageService::where('package_id', $request->package_id)
                    ->where('is_consumed', 1)
                    ->sum('tax_including_price');

                if ($totalPlanPayments < ($totalConsumedValue + $packageService->tax_including_price)) {
                    $shortfall = ceil(($totalConsumedValue + $packageService->tax_including_price) - $totalPlanPayments);

                    return $this->errorResponse('Insufficient payment on this plan. Please collect Rs. '.number_format($shortfall).' before consuming this service.', 200);
                }
            }
        }
        // ============================================

        $paymentmode_settle = PaymentModes::where('payment_type', '=', Config::get('constants.payment_type_settle'))->first();
        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
        // Tenant-scope the appointment fetch — bare `Appointments::find`
        // accepts any id, letting an attacker who knew another tenant's
        // appointment_id trigger an invoice + cash-write against that
        // appointment in the attacker's account ledger. `firstOrFail()`
        // throws `ModelNotFoundException` → Laravel's exception handler
        // renders 404, which doesn't leak existence (same response for
        // "doesn't exist" and "belongs to another tenant").
        $appointmentinfo = Appointments::query()
            ->where('id', $request->appointment_id)
            ->where('account_id', (int) Auth::user()->account_id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        if (isset($request->appointment_id_consultancy)) {
            // Now we need to work our tag appointment for upselling
            $tag_appoint = explode('.', $request->appointment_id_consultancy);
            if ($tag_appoint[1] == 'A') {
                $appointment_id_consultancy = $tag_appoint[0];
            } else {
                $PlanAppointmentCalculation = new PlanAppointmentCalculation;
                $appointment_id_consultancy = $PlanAppointmentCalculation->storeAppointment($appointmentinfo->patient_id, $appointmentinfo->location_id, $appointmentinfo->service_id, $tag_appoint[0], true);
                $PlanAppointmentCalculation->saveinvoice($appointment_id_consultancy);
            }
            $appointmentinfo->update(['appointment_id' => $appointment_id_consultancy, 'updated_at' => Filters::getCurrentTimeStamp()]);
        }
        if ($request->package_mode_id == '0') {
            $paymemt = PaymentModes::first();
            $payment_mode_id = $paymemt->id;
        } else {
            $payment_mode_id = $request->package_mode_id;
        }
        if ($request->checked_treatment == '0') {
            /* Than First find that bundle package id */
            $package_service_info = PackageService::where([
                ['package_id', '=', $request->package_id],
                ['id', '=', $request->exclusive_or_bundle],
            ])->first();
            $is_exclusive = $package_service_info->is_exclusive;
        } else {
            if ($appointmentinfo->appointment_type->name == Config::get('constants.Service')) {
                $orgTaxTreatment = Settings::getOrgTaxTreatment(Auth::user()->account_id);
                if ($orgTaxTreatment == Config::get('constants.tax_both')) {
                    $is_exclusive = $request->exclusive_or_bundle;
                } elseif ($orgTaxTreatment == Config::get('constants.tax_is_exclusive')) {
                    $is_exclusive = 1;
                } else {
                    $is_exclusive = 0;
                }
            } else {
                $is_exclusive = 1;
            }
        }
        if ($request->remaining != 0) {
            $data['total_price'] = $request->remaining;
        } else {
            $data['total_price'] = $request->price;
        }
        $data['account_id'] = Auth::user()->account_id;
        $data['patient_id'] = $appointmentinfo->patient_id;
        $data['appointment_id'] = $request->appointment_id;
        $data['invoice_status_id'] = $invoicestatus->id;
        $data['created_by'] = Auth::user()->id;
        $data['location_id'] = $appointmentinfo->location_id;
        $data['doctor_id'] = $appointmentinfo->doctor_id;
        $data['is_exclusive'] = $is_exclusive;
        $data['created_at'] = Filters::getCurrentTimeStamp();
        $data['updated_at'] = Filters::getCurrentTimeStamp();
        $invoice = Invoices::CreateRecord($data);
        $data_detail['tax_exclusive_serviceprice'] = $request->amount_create;
        $data_detail['tax_percentage'] = $appointmentinfo->location->tax_percentage;
        $data_detail['tax_price'] = $request->tax_create;
        if ($request->remaining != 0) {
            $data_detail['tax_including_price'] = $request->remaining;
            $data_detail['net_amount'] = $request->remaining;
        } else {
            $data_detail['tax_including_price'] = $request->price;
            $data_detail['net_amount'] = $request->price;
        }
        $data_detail['is_exclusive'] = $is_exclusive;
        $data_detail['qty'] = '1';
        if ($request->remaining != 0) {
            $data_detail['service_price'] = $request->remaining;
        } else {
            $data_detail['service_price'] = $appointmentinfo->service->price;
        }
        $data_detail['service_id'] = $appointmentinfo->service_id;
        $data_detail['invoice_id'] = $invoice->id;
        $data_detail['created_at'] = Filters::getCurrentTimeStamp();
        $data_detail['updated_at'] = Filters::getCurrentTimeStamp();
        if ($request->package_service_id) {
            $tax_info_package_service = PackageService::with('packagebundle')->find($request->package_service_id);
            $data_detail['tax_percentage'] = $tax_info_package_service->tax_percentage;
            $data_detail['package_service_id'] = $request->package_service_id;

            // Get discount info from the package_bundle via package_service
            if ($tax_info_package_service->packagebundle) {
                $packageBundle = $tax_info_package_service->packagebundle;
                if ($packageBundle->discount_id || $packageBundle->discount_name) {
                    $data_detail['discount_type'] = $packageBundle->discount_type;
                    $data_detail['discount_price'] = $packageBundle->discount_price;
                    $data_detail['discount_id'] = $packageBundle->discount_id;
                    $data_detail['discount_name'] = $packageBundle->discount_name ?? '';
                }
            }
        }
        if ($request->package_id != null) {
            // Only fetch from package_bundles if we don't already have discount info from package_service
            if (! isset($data_detail['discount_name']) || empty($data_detail['discount_name'])) {
                $packages = DB::table('packages')
                    ->join('package_bundles', 'packages.id', '=', 'package_bundles.package_id')
                    ->join('package_services', 'package_bundles.id', '=', 'package_services.package_bundle_id')
                    ->where([
                        ['packages.id', '=', $request->package_id],
                        ['package_services.service_id', '=', $appointmentinfo->service_id],
                        ['package_services.is_consumed', '=', 0],
                    ])->select('package_bundles.discount_type', 'package_bundles.discount_price', 'package_bundles.discount_id', 'package_bundles.discount_name')->first();
                if ($packages) {
                    // Set discount data if discount_id or discount_name exists
                    if ($packages->discount_id || $packages->discount_name) {
                        $data_detail['discount_type'] = $packages->discount_type;
                        $data_detail['discount_price'] = $packages->discount_price;
                        $data_detail['discount_id'] = $packages->discount_id;
                        $data_detail['discount_name'] = $packages->discount_name ?? '';
                    }
                }
            }
            $data_detail['package_id'] = $request->package_id;
        }
        $invoice_detail = InvoiceDetails::createRecord($data_detail, $invoice);
        if ($invoice_detail->package_id != null) {
            $data_package['cash_flow'] = 'in';
            $data_package['cash_amount'] = $request->cash;
            $data_package['patient_id'] = $appointmentinfo->patient_id;
            $data_package['payment_mode_id'] = $payment_mode_id;
            $data_package['account_id'] = Auth::user()->account_id;
            $data_package['location_id'] = $appointmentinfo->location_id;
            $data_package['created_by'] = Auth::user()->id;
            $data_package['updated_by'] = Auth::user()->id;
            $data_package['package_id'] = $invoice_detail->package_id;
            $data_package['package_id'] = $invoice_detail->package_id;
            $packagebundle = PackageBundles::where([
                'package_id' => $invoice_detail->package_id,
                'is_allocate' => '1',
            ])->pluck('id');
            $GetAppointment = Appointments::join('invoices', 'appointments.id', 'invoices.appointment_id')
                ->select('appointments.id', 'appointments.service_id', 'invoices.created_at')
                ->where(['appointments.patient_id' => $appointmentinfo->patient_id, 'appointments.appointment_type_id' => AppointmentType::Consultancy->value])
                ->latest('invoices.created_at')->first();
            $GetInvoiceInfo = Invoices::where(['appointment_id' => $GetAppointment->id])->first();
            $packageservicez = PackageService::with('service')
                ->whereIn('package_bundle_id', $packagebundle)
                ->where('created_at', '>', Carbon::parse($GetInvoiceInfo->created_at))
                ->get();
            if (! empty($packageservicez)) {
                $data_package['appointment_id'] = $GetAppointment->id;
            } else {
                $data_package['appointment_id'] = $request->appointment_id;
            }
        } else {
            $data_package['cash_flow'] = 'in';
            $data_package['cash_amount'] = $request->cash;
            $data_package['patient_id'] = $appointmentinfo->patient_id;
            $data_package['payment_mode_id'] = $payment_mode_id;
            $data_package['account_id'] = Auth::user()->account_id;
            $data_package['appointment_type_id'] = $appointmentinfo->appointment_type_id;
            $data_package['appointment_id'] = $request->appointment_id;
            $data_package['location_id'] = $appointmentinfo->location_id;
            $data_package['invoice_id'] = $invoice->id;
            $data_package['created_by'] = Auth::user()->id;
            $data_package['updated_by'] = Auth::user()->id;
        }

        $data_package['created_at'] = Filters::getCurrentTimeStamp();
        $data_package['updated_at'] = Filters::getCurrentTimeStamp();
        // Skip writing a 0-amount cash-in row when consuming a session that
        // was already paid for. The legacy code wrote one regardless of
        // whether new cash was tendered, leaving placeholder rows in the
        // ledger that carried no information — every aggregator already
        // filters them out via `cash_amount > 0`, and the cash_out settle
        // pair below carries the actual ledger entry plus the invoice link.
        // Real cash collections (cash > 0) and non-plan consultancy invoices
        // (no package_id) still record their cash-in row as before.
        $shouldRecordCashIn = ! ($invoice_detail->package_id != null && (float) $request->cash <= 0);
        if ($shouldRecordCashIn) {
            $package_advances = PackageAdvances::createRecord_forinvoice($data_package);
            if ($request->package_id && $request->cash > 0) {
                Invoice_Plan_Refund_Sms_Functions::PlanCashReceived_SMS($request->package_id, $package_advances);
            }
        }
        if ($request->remaining != 0) {
            $out_transcation = $request->remaining;
        } else {
            $out_transcation = $request->cash + $request->settle;
        }
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
            $data_package['account_id'] = Auth::user()->account_id;
            $data_package['appointment_type_id'] = $appointmentinfo->appointment_type_id;
            $data_package['appointment_id'] = $request->appointment_id;
            $data_package['location_id'] = $appointmentinfo->location_id;
            $data_package['invoice_id'] = $invoice->id;
            $data_package['created_by'] = Auth::user()->id;
            $data_package['updated_by'] = Auth::user()->id;
            $data_package['created_at'] = Filters::getCurrentTimeStamp();
            $data_package['updated_at'] = Filters::getCurrentTimeStamp();
            if ($invoice_detail->package_id != null) {
                $data_package['package_id'] = $invoice_detail->package_id;
            }
            $package_advances = PackageAdvances::createRecord_forinvoice($data_package);
            $count++;
        }
        if ($package_advances->package_id != null) {
            PackageService::where('id', '=', $request->package_service_id)->update(['is_consumed' => 1, 'updated_at' => Filters::getCurrentTimeStamp(), 'consumed_at' => Filters::getCurrentTimeStamp()]);
            $packagesservice = PackageService::find($request->package_service_id);

            $package_service_log = PackageService::updateRecordInvoice($packagesservice);
            if ($request->cash > 0) {
                $patient = User::whereId($appointmentinfo->patient_id)->first();
                $location = Locations::whereId($appointmentinfo->location_id)->first();
                $servicename = Services::whereId($appointmentinfo->service_id)->first();
                $creatorName = Auth::user()->name ?? 'System';
                $locationLabel = $location?->name ?? '';
                $description = '<span class="highlight">'.e($creatorName).'</span> received payment <span class="highlight-green">Rs. '.number_format((float) $request->cash).'</span> from <span class="highlight-orange">'.e($patient->name).'</span> for <span class="highlight-purple">Plan Id: '.$package_advances->package_id.'</span>'.($locationLabel ? ' in <span class="highlight">'.e($locationLabel).'</span>' : '');

                $activity = new Activity;
                $activity->timestamps = false;
                $activity->action = 'received';
                $activity->activity_type = 'payment_received';
                $activity->description = $description;
                $activity->patient = $patient->name;
                $activity->patient_id = $patient->id;
                $activity->appointment_type = 'Plan';
                $activity->created_by = Auth::user()->id;
                $activity->account_id = Auth::user()->account_id;
                $activity->invoice_id = $invoice->id;
                $activity->plan_id = $package_advances->package_id;
                $activity->amount = $request->cash;
                $activity->location = $location->name;
                $activity->centre_id = $appointmentinfo->location_id;
                $activity->created_at = Filters::getCurrentTimeStamp();
                $activity->updated_at = Filters::getCurrentTimeStamp();
                $activity->save();
            }
        }
        if ($request->package_id && $invoice && $invoice_detail) {
            Invoice_Plan_Refund_Sms_Functions::InvoiceCashReceived_SMS($invoice, $invoice_detail, $request->package_id);
        } else {
            Invoice_Plan_Refund_Sms_Functions::InvoiceCashReceived_SMS($invoice, $invoice_detail, false);
        }
        $arrivedStatus = AppointmentStatuses::where('is_arrived', '=', 1)->select('id')->first();
        // Pre-fix: the second `->where('base_appointment_status_id', '!=', ...)`
        // condition compared a *status* id to the
        // `appointment_type_service` constant — copy-paste bug. Real
        // intent: only auto-arrive treatments that aren't already
        // Arrived. Now compares against `$arrivedStatus->id`.
        if ($arrivedStatus && Appointments::where('id', '=', $request->appointment_id)->where('appointment_type_id', '=', Config::get('constants.appointment_type_service'))->where('base_appointment_status_id', '!=', $arrivedStatus->id)->exists()) {
            if (AppointmentStatuses::where('parent_id', '=', $arrivedStatus->id)->exists()) {
                $appointmentStatus = AppointmentStatuses::where('parent_id', '=', $arrivedStatus->id)->where('active', '=', 1)->first();
                if ($appointmentStatus) {
                    Appointments::where('id', '=', $request->appointment_id)->update(['base_appointment_status_id' => $arrivedStatus->id, 'appointment_status_id' => $appointmentStatus->id, 'updated_at' => Filters::getCurrentTimeStamp()]);
                } else {
                    Appointments::where('id', '=', $request->appointment_id)->update(['base_appointment_status_id' => $arrivedStatus->id, 'appointment_status_id' => $arrivedStatus->id, 'updated_at' => Filters::getCurrentTimeStamp()]);
                }
            } else {
                Appointments::where('id', '=', $request->appointment_id)->update(['base_appointment_status_id' => $arrivedStatus->id, 'appointment_status_id' => $arrivedStatus->id, 'updated_at' => Filters::getCurrentTimeStamp()]);
            }
        }
        // In case of auto change status we need to update by so that s why we did
        $appointment_data_status['updated_by'] = Auth::user()->id;
        $appointmentinfo->update($appointment_data_status);

        // /Save activity//
        $patient = User::whereId($appointmentinfo->patient_id)->first();
        $location = Locations::whereId($appointmentinfo->location_id)->first();
        $servicename = Services::whereId($appointmentinfo->service_id)->first();
        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';
        $serviceName = $servicename->name ?? 'Service';
        $locationName = $location->name ?? '';
        $amount = number_format((float) $invoice_detail->net_amount);
        $scheduleDate = $appointmentinfo->scheduled_date ? date('M j, Y', strtotime((string) $appointmentinfo->scheduled_date)) : '';

        // Format description with highlights
        $description = '<span class="highlight">'.$creatorName.'</span> consumed <span class="highlight-green">Rs. '.$amount.'</span> from <span class="highlight-orange">'.$patientName.'</span> for <span class="highlight-orange">'.$serviceName.'</span> Treatment'.($locationName ? ' at <span class="highlight">'.$locationName.'</span>' : '').($scheduleDate ? ' on <span class="highlight-purple">'.$scheduleDate.'</span>' : '');

        $activity = new Activity;
        $activity->action = 'consumed';
        $activity->description = $description;
        $activity->patient = $patient->name;
        $activity->patient_id = $patient->id;
        $activity->appointment_type = $servicename->name.' Treatment';
        $activity->activity_type = 'Treatment';
        $activity->schedule_date = $appointmentinfo->scheduled_date;
        $activity->created_by = Auth::user()->id;
        $activity->account_id = Auth::user()->account_id;
        $activity->invoice_id = $invoice->id;
        $activity->amount = $invoice_detail->net_amount;
        $activity->location = $location->name;
        $activity->centre_id = $appointmentinfo->location_id;
        $activity->service_id = $appointmentinfo->service_id;
        $activity->service = $servicename->name;
        $activity->created_at = Filters::getCurrentTimeStamp();
        $activity->updated_at = Filters::getCurrentTimeStamp();
        $activity->save();
        /**
         * Dispatch Elastic Search Index
         */
        IndexSingleAppointmentJob::dispatch([
            'account_id' => Auth::user()->account_id,
            'appointment_id' => $appointmentinfo->id,
        ]);

        return $this->successResponse('Invoice created successfully', [
            'invoice_id' => $invoice?->id ?? 0,
        ]);
    }

    public function displayInvoiceAppointment(int $id): View|JsonResponse
    {
        if (! Gate::allows('appointments_invoice_display')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }
        $Invoiceinfo = DB::table('invoices')
            ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->join('appointments', 'appointments.id', '=', 'invoices.appointment_id')
            ->where('invoices.id', '=', $id)
            ->select('invoices.*',
                'invoice_details.discount_type',
                'invoice_details.discount_price',
                'invoice_details.discount_name',
                'invoice_details.service_price',
                'invoice_details.net_amount',
                'invoice_details.service_id',
                'invoice_details.discount_id',
                'invoice_details.package_id',
                'invoice_details.invoice_id',
                'invoice_details.tax_exclusive_serviceprice',
                'invoice_details.tax_percentage',
                'invoice_details.tax_price',
                'invoice_details.tax_including_price',
                'invoice_details.is_exclusive',
                'appointments.appointment_type_id'
            )
            ->first();
        $location_info = Locations::find($Invoiceinfo->location_id);
        $package_service = PackageService::where('package_id', '=', $Invoiceinfo->package_id)->where('service_id', '=', $Invoiceinfo->service_id)->first();
        if ($package_service) {
            if ($package_service->package_bundle_id != null) {
                $package_bundle = PackageBundles::find($package_service->package_bundle_id);
            } else {
                $package_bundle = PackageBundles::where('package_id', '=', $Invoiceinfo->package_id)->first();
            }
        } else {
            $package_bundle = PackageBundles::where('package_id', '=', $Invoiceinfo->package_id)->first();
        }
        $bundle = Bundles::find($package_bundle->bundle_id);
        $invoicestatus = InvoiceStatuses::find($Invoiceinfo->invoice_status_id);
        if ($Invoiceinfo->discount_id) {
            $discount = Discounts::find($Invoiceinfo->discount_id);
        } else {
            $discount = null;
        }
        $service = Services::find($Invoiceinfo->service_id);

        // Get service price from package_services.actual_price first, fallback to services.price
        if ($package_service && $package_service->actual_price !== null) {
            $service_price = $package_service->actual_price;
        } else {
            $service_price = $service->price;
        }

        $patient = User::find($Invoiceinfo->patient_id);
        $account = Accounts::find($Invoiceinfo->account_id);
        $company_phone_number = Settings::where('slug', '=', 'sys-headoffice')->first();

        // Get doctor name from appointment
        $doctor = null;
        if ($Invoiceinfo->appointment_id) {
            $appointment = Appointments::with('doctor')->find($Invoiceinfo->appointment_id);
            $doctor = $appointment?->doctor;
        }

        return view('admin.appointments..invoice.displayInvoice', compact('Invoiceinfo', 'patient', 'account', 'service', 'discount', 'invoicestatus', 'company_phone_number', 'location_info', 'bundle', 'service_price', 'doctor'));
    }

    /*
     * Load plans information
     *
     * @oaran $request
     *
     * @return mixed
     */
    public function getplansinformation(Request $request): JsonResponse
    {
        // Validate input parameters
        $appointmentId = $request->appointment_id_create;
        $packageId = $request->package_id_create;

        if (! $appointmentId || ! $packageId) {
            return response()->json([
                'status' => true,
                'packagebundles' => [],
                'packageservices' => [],
            ]);
        }

        // Get only the service_id we need from appointment
        $appointmentinfo = Appointments::select('service_id')->find($appointmentId);

        if (! $appointmentinfo) {
            return response()->json([
                'status' => true,
                'packagebundles' => [],
                'packageservices' => [],
            ]);
        }

        $serviceId = $appointmentinfo->service_id;

        // Get bundle IDs using pluck instead of loop
        $bundleIds = Bundles::join('bundle_has_services', 'bundles.id', '=', 'bundle_has_services.bundle_id')
            ->where('bundle_has_services.service_id', '=', $serviceId)
            ->pluck('bundles.id')
            ->toArray();

        // Check if package exists
        $package = Packages::select('id')->find($packageId);

        if (! $package) {
            return response()->json([
                'status' => true,
                'packagebundles' => [],
                'packageservices' => [],
            ]);
        }

        // Get package bundles — two paths:
        // 1. Bundle-type: bundle_id is a real bundles.id (JOIN works)
        // 2. Plan-type: bundle_id stores service_id (JOIN fails, use LEFT JOIN with service name)
        $bundleTypeBundles = collect();
        if (! empty($bundleIds)) {
            $bundleTypeBundles = PackageBundles::join('bundles', 'package_bundles.bundle_id', '=', 'bundles.id')
                ->where('package_bundles.package_id', '=', $package->id)
                ->whereIn('package_bundles.bundle_id', $bundleIds)
                ->select('package_bundles.*', 'package_bundles.discount_name as discountname', 'bundles.name as bundlename')
                ->get();
        }

        // Plan-type bundles: bundle_id = service_id, get service name instead
        $bundleTypeBundleIds = $bundleTypeBundles->pluck('id')->toArray();
        $planTypeBundles = PackageBundles::leftJoin('services', 'package_bundles.bundle_id', '=', 'services.id')
            ->where('package_bundles.package_id', '=', $package->id)
            ->whereNotIn('package_bundles.id', $bundleTypeBundleIds)
            ->whereHas('packageservice', function ($q) use ($serviceId) {
                $q->where('service_id', $serviceId);
            })
            ->select('package_bundles.*', 'package_bundles.discount_name as discountname', 'services.name as bundlename')
            ->get();

        $packagebundles = $bundleTypeBundles->merge($planTypeBundles);

        // Get package services with config_group_id from package_bundles
        $packageservices = PackageService::join('services', 'package_services.service_id', '=', 'services.id')
            ->leftJoin('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
            ->where('package_services.package_id', '=', $package->id)
            ->where('package_services.service_id', '=', $serviceId)
            ->select('package_services.*', 'services.name as servicename', 'package_bundles.config_group_id')
            ->get();

        // Calculate total payments for this plan (for consumption lock checks)
        $totalPlanPayments = PackageAdvances::where('package_id', $package->id)
            ->where('cash_flow', 'in')
            ->sum('cash_amount');

        // Calculate total already consumed value
        $totalConsumedValue = PackageService::where('package_id', $package->id)
            ->where('is_consumed', 1)
            ->sum('tax_including_price');

        // Get all package services in same config groups for ordering checks
        $configGroupIds = $packageservices->pluck('config_group_id')->filter()->unique()->values();
        $configGroupServices = [];
        if ($configGroupIds->isNotEmpty()) {
            $configGroupServices = PackageService::join('services', 'package_services.service_id', '=', 'services.id')
                ->leftJoin('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
                ->where('package_services.package_id', '=', $package->id)
                ->whereIn('package_bundles.config_group_id', $configGroupIds)
                ->select('package_services.id', 'package_services.is_consumed', 'package_services.consumption_order', 'package_services.tax_including_price', 'package_bundles.config_group_id', 'services.name as servicename')
                ->get()
                ->groupBy('config_group_id')
                ->toArray();
        }

        // Check if plan is fully paid (total payments >= total plan value)
        // Allow 1-rupee tolerance for rounding (e.g. 3 × 6996.67 = 20990.01 vs 20990 payment)
        $totalPlanValue = PackageService::where('package_id', $package->id)
            ->sum('tax_including_price');
        $isPlanFullyPaid = ($totalPlanPayments >= ($totalPlanValue - 1));

        return response()->json([
            'status' => true,
            'packagebundles' => $packagebundles,
            'packageservices' => $packageservices,
            'total_plan_payments' => (float) $totalPlanPayments,
            'total_consumed_value' => (float) $totalConsumedValue,
            'is_plan_fully_paid' => $isPlanFullyPaid,
            'config_group_services' => $configGroupServices,
        ]);
    }

    /*
     * Load Invoice information
     *
     * @oaran $request
     *
     * @return mixed
     */

    public function getpackageprice(Request $request): JsonResponse
    {
        // Validate input parameters
        $appointmentId = $request->appointment_id_create;
        $packageId = $request->package_id_create;
        $packageServiceId = $request->package_service_id;

        if (! $appointmentId || ! $packageId || ! $packageServiceId) {
            return response()->json([
                'status' => false,
                'message' => 'Missing required parameters',
            ]);
        }

        // Get only patient_id from appointment
        $appointmentinfo = Appointments::select('patient_id')->find($appointmentId);

        if (! $appointmentinfo) {
            return response()->json([
                'status' => false,
                'message' => 'Appointment not found',
            ]);
        }

        $patientId = $appointmentinfo->patient_id;

        // Calculate balance using single query with conditional sum
        $balanceData = PackageAdvances::where('patient_id', '=', $patientId)
            ->where('package_id', '=', $packageId)
            ->selectRaw("
                 SUM(CASE WHEN cash_flow = 'in' THEN cash_amount ELSE 0 END) as total_in,
                 SUM(CASE WHEN cash_flow = 'out' THEN cash_amount ELSE 0 END) as total_out
             ")
            ->first();

        $balance = ceil(($balanceData->total_in ?? 0) - ($balanceData->total_out ?? 0));

        // Get package service
        $package_service = PackageService::find($packageServiceId);

        if (! $package_service) {
            return response()->json([
                'status' => false,
                'message' => 'Package service not found',
            ]);
        }

        // Get package bundle
        $package_bundle = PackageBundles::find($package_service->package_bundle_id);

        if (! $package_bundle) {
            return response()->json([
                'status' => false,
                'message' => 'Package bundle not found',
            ]);
        }

        // Get bundle (only if type is 'multiple')
        $bundle = Bundles::where('id', '=', $package_bundle->bundle_id)
            ->where('type', '=', 'multiple')
            ->first();

        // Get service
        $service = Services::find($package_service->service_id);

        if (! $service) {
            return response()->json([
                'status' => false,
                'message' => 'Service not found',
            ]);
        }

        // Determine package access
        $package_access = 1;
        if ($bundle) {
            if ($balance < $bundle->price && $balance < $service->price) {
                $package_access = 0;
            }
        }

        // Calculate total package cost and remaining amount to pay for bundle logic
        $total_package_cost = $package_bundle->tax_including_price;

        // Calculate how much has been consumed from this specific bundle
        $consumed_from_bundle = PackageService::where('package_bundle_id', '=', $package_bundle->id)
            ->where('is_consumed', '=', 1)
            ->sum('tax_including_price');

        // Remaining to pay is the bundle price minus what's been consumed minus current balance
        $remaining_to_pay = max(0, $total_package_cost - $consumed_from_bundle - $balance);

        $cash = 0;

        // Helper function to calculate outstanding
        $calculateOutstanding = function ($bundle, $service, $balance, $cash, $remaining_to_pay, $fallbackAmount = 0) {
            if ($bundle) {
                if ($balance >= $service->price) {
                    return 0;
                }
                $naive_outstanding = (int) $service->price - (int) $balance - $cash;

                return min($naive_outstanding, max(0, $remaining_to_pay));
            }

            return (int) $fallbackAmount - $cash - (int) $balance;
        };

        if ($package_access == 1) {
            $price = $package_service->tax_including_price;
            $outstanding = $calculateOutstanding($bundle, $service, $balance, $cash, $remaining_to_pay, $package_service->tax_including_price);
            $remaining = 0;
            $settleamount = min($price - $cash, $balance);
        } else {
            if ($package_service->price > ($package_bundle->net_amount - $balance)) {
                $price = $package_service->price;
                $outstanding = $calculateOutstanding($bundle, $service, $balance, $cash, $remaining_to_pay);
                $settleamount = min((int) ($package_bundle->net_amount - $balance) - $cash, $balance);
            } else {
                $price = $package_service->price;
                $outstanding = $calculateOutstanding($bundle, $service, $balance, $cash, $remaining_to_pay);
                $settleamount = min($price - $cash, $balance);
            }
            $remaining = $package_service->tax_including_price;
        }

        // Ensure outstanding is not negative
        $outstanding = max(0, $outstanding);

        return response()->json([
            'status' => true,
            'amount' => $package_service->tax_exclusive_price,
            'tax_price' => $package_service->tax_price,
            'serviceprice' => $price,
            'outstanding' => $outstanding,
            'settleamount' => round($settleamount, 2),
            'balance' => round($balance, 2),
            'remaining' => $remaining,
            'package_service_id' => $request->package_id_create,
        ]);
    }

    /*
     * Get the package price against package id
     *
     * */
    public function getinvoicecalculation(Request $request): JsonResponse
    {
        if ($request->cash_create == 0 || $request->cash_create < 0) {
            return response()->json([
                'status' => true,
                'outstdanding' => $request->outstanding_for_zero,
                'settleamount' => $request->settleamount_for_zero,
            ]);
        }
        $outstdanding = $request->outstanding_for_zero - $request->cash_create;
        $balance = $request->balance_create;
        $settleamount = $request->price_create - $request->cash_create;

        return response()->json([
            'status' => true,
            'outstdanding' => round($outstdanding, 2),
            'settleamount' => round($settleamount, 2),

        ]);
    }

    /*
     * Get the calculation of service price according to exclusive and inclusive check
     *
     * */
    public function getcalculatedPriceExclusicecheck(Request $request): JsonResponse
    {
        $location_info = Locations::find($request->location_id);
        $orgTaxTreatment = Settings::getOrgTaxTreatment(Auth::user()->account_id);
        if ($orgTaxTreatment == Config::get('constants.tax_both')) {
            if ($request->is_exclusive == '1') {
                $amount_create = $request->price_orignal;
                $tax_create = ceil($request->price_orignal * ($location_info->tax_percentage / 100));
                $price = ceil($amount_create + (($amount_create * $location_info->tax_percentage) / 100));
            } else {
                $price = $request->price_orignal;
                $amount_create = ceil((100 * $price) / ($location_info->tax_percentage + 100));
                $tax_create = ceil($price - $amount_create);
            }
        } elseif ($orgTaxTreatment == Config::get('constants.tax_is_exclusive')) {
            $amount_create = $request->price_orignal;
            $tax_create = ceil($request->price_orignal * ($location_info->tax_percentage / 100));
            $price = ceil($amount_create + (($amount_create * $location_info->tax_percentage) / 100));
        } else {
            $price = $request->price_orignal;
            $amount_create = ceil((100 * $price) / ($location_info->tax_percentage + 100));
            $tax_create = ceil($price - $amount_create);
        }
        $outstdanding = $price;
        $settleamount = 0;

        return response()->json([
            'status' => true,
            'amount_create' => $amount_create,
            'tax_create' => $tax_create,
            'price' => $price,
            'outstdanding' => $outstdanding,
            'settleamount' => $settleamount,
        ]);
    }

}
