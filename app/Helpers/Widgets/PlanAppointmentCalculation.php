<?php

namespace App\Helpers\Widgets;

use App\Helpers\GeneralFunctions;
use App\Jobs\IndexSingleAppointmentJob;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\Discounts;
use App\Models\InvoiceDetails;
use App\Models\Invoices;
use App\Models\InvoiceStatuses;
use App\Models\Leads;
use App\Models\LeadStatuses;
use App\Services\Lead\LeadService;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\Patients;
use App\Models\PaymentModes;
use App\Models\ResourceHasRota;
use App\Models\ResourceHasRotaDays;
use App\Models\Resources;
use App\Models\Services;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use Config;
use Illuminate\Foundation\Bus\DispatchesJobs;

class PlanAppointmentCalculation
{
    use DispatchesJobs;

    /*
     * Need consultnacy and Doctors for tag in plan
     */
    public static function tagAppointments($request)
    {
        $appointment = [];
        $doctorids = [];

        if ($request->patient_id && $request->location_id) {

            $appointmentArray_appointment = [];

            $appointmentArray_doctor = [];

            // Get both arrived and converted appointment statuses
            $arrivedStatus = AppointmentStatuses::where('is_arrived', '=', '1')->first();
            $convertedStatus = AppointmentStatuses::where('is_converted', '=', '1')->first();
            
            // Build array of valid status IDs (arrived or converted)
            $validStatusIds = [];
            if ($arrivedStatus) {
                $validStatusIds[] = $arrivedStatus->id;
            }
            if ($convertedStatus) {
                $validStatusIds[] = $convertedStatus->id;
            }

            $appointment_type = AppointmentTypes::where('slug', '=', 'consultancy')->first();

            $appointment_type_treatment = AppointmentTypes::where('slug', '=', 'treatment')->first();

            // First we need to find appointment with arrived or converted status
            $appointment_info = Appointments::where([
                ['patient_id', '=', $request->patient_id],
                ['appointment_type_id', '=', $appointment_type?->id],
                ['location_id', '=', $request?->location_id],
            ])->whereIn('base_appointment_status_id', $validStatusIds)
            ->orderBy('created_at', 'desc')->get();

            // Making array for above data
            foreach ($appointment_info as $appointment) {
                $appointmentArray_appointment[$appointment?->id] = [
                    'id' => $appointment?->id.'.'.'A',
                    'name' => $appointment?->service->name.' - '.Carbon::parse($appointment?->created_at)->format('F j,Y h:i A').' - '.$appointment->doctor->name,
                    'doctor_id' => $appointment?->doctor_id
                ];
                $doctorids[] = $appointment?->doctor_id;
            }

            // Remember I create that funtion for getting doctors in creating rota here I take help of this function
            // $doctors = self::loadDoctorsByLocation($request?->location_id, $doctorids);

            // if ($doctors) {
            //     foreach ($doctors as $key => $doctor) {
            //         if ($doctor) {
            //             $appointmentArray_doctor[$key] = array(
            //                 'id' => $key . '.' . 'D',
            //                 'name' => $doctor,
            //             );
            //         }
            //     }
            // }
            $appointment = array_merge($appointmentArray_appointment, $appointmentArray_doctor);

            return $appointment;
        } else {
            return $appointment;
        }

    }

    /*
     * Now we need doctors which to which have rota for consutlancy
     */
    public static function loadDoctorsByLocation($location_id, $doctorids)
    {
        if ($location_id) {

            $doctors = $doctors_no_final = LocationsWidget::loadAppointmentDoctorByLocation($location_id, Auth::User()->account_id);
            foreach ($doctors_no_final as $key => $doctor) {

                $resource = Resources::where('external_id', '=', $key)->first();

                $doctor_rota = ResourceHasRota::join('resource_has_rota_days', 'resource_has_rota.id', '=', 'resource_has_rota_days.resource_has_rota_id')
                    ->where('resource_has_rota.resource_id', '=', $resource?->id)
                    ->where('resource_has_rota.is_consultancy', '=', '1')
                    ->where('resource_has_rota.location_id', '=', $location_id)
                    ->where('resource_has_rota.active', '=', '1')
                    ->where('resource_has_rota_days.date', '=', Carbon::now()->toDateString())
                    ->get();

                if (count($doctor_rota) == 0) {
                    unset($doctors[$key]);
                } else {
                    if (in_array($key, $doctorids)) {
                        unset($doctors[$key]);
                    }
                }
            }

            return $doctors;
        } else {
            return null;
        }
    }

    /*
     * Now we going to store auto created appointment
     */
    public function storeAppointment($patient_id, $location_id, $request, $doctor_id, $direct)
    {
        if ($direct) {
            // Here $request present the service id
            $service_info = self::getserviceparentinfo_direct($request);
        } else {
            $service_info = self::getserviceparentinfo($request);
        }

        $user_info = User::find($patient_id);
        $location_info = Locations::find($location_id);

        $appointmentData['lead_id'] = null;
        $appointmentData['patient_id'] = $user_info->id;
        $appointmentData['region_id'] = $location_info->region_id;
        $appointmentData['city_id'] = $location_info->city_id;
        $appointmentData['location_id'] = $location_info->id;
        $appointmentData['doctor_id'] = $doctor_id;
        $appointmentData['coming_from'] = 'plan';
        $appointmentData['start'] = Carbon::now()->toDateTimeString();
        $appointmentData['resource_id'] = $doctor_id;
        $appointmentData['appointment_type'] = 'consulting';
        $appointmentData['service_id'] = $service_info;
        $appointmentData['patient_id_1'] = null;
        $appointmentData['phone'] = $user_info->phone;
        $appointmentData['name'] = $user_info->name;
        $appointmentData['cnic'] = $user_info->cnic;
        $appointmentData['email'] = $user_info->email;
        $appointmentData['dob'] = $user_info->dob;

        // Store form data in a variable
        $appointmentData['account_id'] = Auth::User()->account_id;
        $appointmentData['phone'] = GeneralFunctions::cleanNumber($appointmentData['phone']);
        $appointmentData['created_by'] = Auth::user()->id;
        $appointmentData['updated_by'] = Auth::user()->id;
        $appointmentData['converted_by'] = Auth::user()->id;

        $response = Resources::getDoctorRotaHasDay($appointmentData['start'], $doctor_id);

        if (isset($response['resource_id']) && $response['resource_id']) {
            $appointmentData['resource_id'] = $response['resource_id'];
        }
        if (isset($response['resource_has_rota_day_id']) && $response['resource_has_rota_day_id']) {
            $appointmentData['resource_has_rota_day_id'] = $response['resource_has_rota_day_id'];
        }

        // Set default appointment status i.e. 'pending'
        $appointment_status = AppointmentStatuses::getADefaultStatusOnly(Auth::User()->account_id);

        if ($appointment_status) {
            $appointmentData['appointment_status_id'] = $appointment_status->id;
            $appointmentData['base_appointment_status_id'] = $appointment_status->id;
            $appointmentData['appointment_status_allow_message'] = $appointment_status->allow_message;
        } else {
            $appointmentData['appointment_status_id'] = null;
            $appointmentData['base_appointment_status_id'] = null;
            $appointmentData['appointment_status_allow_message'] = 0;
        }

        // Set Appointment Type
        $appointmentData['appointment_type_id'] = Config::get('constants.appointment_type_consultancy');

        /*
         * Check if Lead ID not provided then create a new lead
         * and assign this lead to current appointment.
         */
        if (! $appointmentData['lead_id']) {
            /*
             * If Patient is from database
             * - if appointment already exists then do not update info
             * - if appointment already exists then update info
             */
            if (isset($appointmentData['patient_id']) && $appointmentData['patient_id'] != '') {
                $patientData = $appointmentData;
                $patient = Patients::updateRecord($appointmentData['patient_id'], false, $appointmentData, $patientData);
            }

            if ($appointmentData['start']) {

                $date_of_appointment = Carbon::parse($appointmentData['start'])->format('Y-m-d');

                $doctor_checking = self::checkingDoctorAvailbility($appointmentData['doctor_id'], $appointmentData['location_id'], $date_of_appointment);

                $appointmentData['scheduled_date'] = Carbon::parse($doctor_checking[0]['start_timestamp'])->format('Y-m-d');
                $appointmentData['scheduled_time'] = Carbon::parse($doctor_checking[0]['start_timestamp'])->format('H:i:s');

                $appointmentData['first_scheduled_date'] = Carbon::parse($doctor_checking[0]['start_timestamp'])->format('Y-m-d');
                $appointmentData['first_scheduled_time'] = Carbon::parse($doctor_checking[0]['start_timestamp'])->format('H:i:s');

                $appointmentData['first_scheduled_count'] = 1;
            }

            $leadObj = $appointmentData;
            unset($leadObj['lead_id']); // Remove Lead ID index
            $leadObj['patient_id'] = $patient->id;
            // Convert Lead status to Converted
            $DefaultConvertedLeadStatus = LeadStatuses::where([
                'account_id' => Auth::User()->account_id,
                'is_converted' => 1,
            ])->first();
            if ($DefaultConvertedLeadStatus) {
                $default_converted_lead_status_id = $DefaultConvertedLeadStatus->id;
            } else {
                $default_converted_lead_status_id = Config::get('constants.lead_status_converted');
            }
            $leadObj['lead_status_id'] = $default_converted_lead_status_id;

            if (! empty($leadObj) && isset($leadObj['service_id'])) {
                $leadObj['base_service_id'] = $leadObj['service_id'];
            }
            $lead = app(LeadService::class)->createLeadRecord($leadObj, 'Appointment');
        }

        // Set Lead ID for Appointment
        $appointmentData['patient_id'] = $patient->id;
        $appointmentData['lead_id'] = $lead->id;
        /*
         * End Lead ID Process
         */
        $appointmentData['created_at'] = date('Y-m-d H:i:s');
        $appointmentData['updated_at'] = date('Y-m-d H:i:s');
        $appointmentData['updated_by'] = Auth::User()->id;

        $appointment = Appointments::create($appointmentData);

        /* Now We need to update name of all appointments that already in appointment table against patient
         */
        Appointments::where('patient_id', '=', $appointmentData['patient_id'])->update(['name' => $appointmentData['name']]);

        // Based on allow message by status and scheduled date, allow send sms
        if ($appointment->appointment_status_allow_message && $appointment->scheduled_date) {
            $appointment->update([
                'send_message' => 1,
            ]);
        }

        /*
         * Set Appointment Status if appointment scheduled date & time are not defined
         * case 1: If Scheduled Date is not set then status is 'un-scheduled'
         * case 2: If 'un-scheduled' is not set then set defautl status i.e. 'pending'
         */
        if (! $appointment->scheduled_date && ! $appointment->scheduled_time) {
            $appointment_status = AppointmentStatuses::getUnScheduledStatusOnly(Auth::User()->account_id);
            if ($appointment_status) {
                $appointment->update([
                    'appointment_status_id' => $appointment_status->id,
                    'base_appointment_status_id' => $appointment_status->id,
                    'appointment_status_allow_message' => 0,
                ]);
            } else {
                // Set default appointment status i.e. 'pending'
                $appointment_status = AppointmentStatuses::getADefaultStatusOnly(Auth::User()->account_id);
                if ($appointment_status) {
                    $appointment->update([
                        'appointment_status_id' => $appointment_status->id,
                        'base_appointment_status_id' => $appointment_status->id,
                        'appointment_status_allow_message' => 0,
                    ]);
                } else {
                    $appointment->update([
                        'appointment_status_id' => null,
                        'base_appointment_status_id' => null,
                        'appointment_status_allow_message' => 0,
                    ]);
                }
            }
        }

        $message = 'Record has been created successfully.';

        /**
         * Dispatch Elastic Search Index
         */
        $this->dispatch(
            new IndexSingleAppointmentJob([
                'account_id' => Auth::User()->account_id,
                'appointment_id' => $appointment->id,
            ])
        );

        return $appointment->id;

    }

    /*
     *  Now we need to find the parent service against child service
     */
    public static function getserviceparentinfo($request)
    {
        $searchServices = Services::where([
            'account_id' => Auth::User()->account_id,
            'active' => 1,
        ])->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id');

        $package_bundle_info = PackageBundles::where('random_id', '=', $request->random_id)->first();

        $service_id = self::findRoot($package_bundle_info?->packageservice()->first()->service_id, $searchServices);

        return $service_id;
    }

    /*
     *  Now we need to find the parent service against child service
     */
    public static function getserviceparentinfo_direct($request)
    {
        $searchServices = Services::where([
            'account_id' => Auth::User()->account_id,
            'active' => 1,
        ])->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id');

        $service_id = self::findRoot($request, $searchServices);

        return $service_id;
    }

    /*
     * We call function recursively so find the first parent
     */
    public static function findRoot($service_id, $data)
    {
        if ($data[$service_id]['parent_id'] == '0') {
            return $service_id;
        } else {
            return self::findRoot($data[$service_id]['parent_id'], $data);
        }
    }

    /*
     *  Check doctor is availabale or not
     */
    public static function checkingDoctorAvailbility($doctor_id, $location_id, $date_of_appointment)
    {
        $record = Resources::join('resource_has_rota', 'resources.id', '=', 'resource_has_rota.resource_id')
            ->join('resource_has_rota_days', 'resource_has_rota.id', '=', 'resource_has_rota_id')
            ->where('resources.external_id', '=', $doctor_id)
            ->where('resource_has_rota.location_id', '=', $location_id)
            ->where('resource_has_rota_days.date', '=', $date_of_appointment)
            ->select('resource_has_rota_days.*')
            ->get()->toArray();

        return $record;
    }

    /*
     * Now we are going to ringup the invoice and change the status
     */
    public function saveinvoice($appointment_id)
    {
        $payment = PaymentModes::first();

        $req['appointment_id'] = $appointment_id;
        $req['amount_create'] = 0;
        $req['tax_create'] = 0;
        $req['price'] = 0;
        $req['balance'] = 0;
        $req['cash'] = 0;
        $req['settle'] = 0;
        $req['outstand'] = 0;
        $req['payment_mode_id'] = $payment->id;
        $req['is_exclusive'] = 0;
        $req['discount_id'] = 0;
        $req['discount_type'] = 0;
        $req['discount_value'] = 0;
        $req['created_at'] = Carbon::now()->toDateString();
        $req['tax_treatment_type_id'] = 3;

        $paymentmode_settle = PaymentModes::where('payment_type', '=', Config::get('constants.payment_type_settle'))->first();
        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
        $appointmentinfo = Appointments::find($appointment_id);

        $data['total_price'] = $req['price'];
        $data['account_id'] = Auth::User()->account_id;
        $data['patient_id'] = $appointmentinfo->patient_id;
        $data['appointment_id'] = $req['appointment_id'];
        $data['invoice_status_id'] = $invoicestatus->id;
        $data['created_by'] = Auth::User()->id;
        $data['location_id'] = $appointmentinfo->location_id;
        $data['doctor_id'] = $appointmentinfo->doctor_id;
        $data['is_exclusive'] = $req['is_exclusive'];
        $data['created_at'] = $req['created_at'].' '.Carbon::now()->toTimeString();
        $data['updated_at'] = $req['created_at'].' '.Carbon::now()->toTimeString();

        $invoice = Invoices::CreateRecord($data);
        $data_detail['tax_exclusive_serviceprice'] = $req['amount_create'];
        $data_detail['tax_percenatage'] = $appointmentinfo->location->tax_percentage;
        $data_detail['tax_price'] = $req['tax_create'];
        $data_detail['tax_including_price'] = $req['price'];
        $data_detail['net_amount'] = $req['price'];
        $data_detail['is_exclusive'] = $req['is_exclusive'];

        $data_detail['qty'] = '1';
        $data_detail['service_price'] = $appointmentinfo->service->price;
        $data_detail['service_id'] = $appointmentinfo->service_id;
        $data_detail['invoice_id'] = $invoice->id;

        $data_detail['created_at'] = $req['created_at'].' '.Carbon::now()->toTimeString();
        $data_detail['updated_at'] = $req['created_at'].' '.Carbon::now()->toTimeString();

        $discount_info = Discounts::find($req['discount_id']);

        if ($discount_info) {

            $data_detail['discount_id'] = $req['discount_id'];
            $data_detail['discount_name'] = $discount_info->name;
            $data_detail['discount_type'] = $req['discount_type'];
            $data_detail['discount_price'] = $req['discount_value'];
        }

        $invoice_detail = InvoiceDetails::createRecord($data_detail, $invoice);
        $data_package['cash_flow'] = 'in';
        $data_package['cash_amount'] = $req['cash'];
        $data_package['patient_id'] = $appointmentinfo->patient_id;
        $data_package['payment_mode_id'] = $req['payment_mode_id'];
        $data_package['account_id'] = Auth::User()->account_id;
        $data_package['appointment_type_id'] = $appointmentinfo->appointment_type_id;
        $data_package['appointment_id'] = $req['appointment_id'];
        $data_package['invoice_id'] = $invoice->id;
        $data_package['location_id'] = $appointmentinfo->location_id;
        $data_package['created_by'] = Auth::User()->id;
        $data_package['updated_by'] = Auth::User()->id;

        $data_package['created_at'] = $req['created_at'].' '.Carbon::now()->toTimeString();
        $data_package['updated_at'] = $req['created_at'].' '.Carbon::now()->toTimeString();

        $package_advances = PackageAdvances::createRecord_forinvoice($data_package);
        $out_transcation = $req['cash'] + $req['settle'];

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
            $data_package['appointment_id'] = $req['appointment_id'];
            $data_package['invoice_id'] = $invoice->id;
            $data_package['location_id'] = $appointmentinfo->location_id;
            $data_package['created_by'] = Auth::User()->id;
            $data_package['updated_by'] = Auth::User()->id;
            $data_package['created_at'] = $req['created_at'].' '.Carbon::now()->toTimeString();
            $data_package['updated_at'] = $req['created_at'].' '.Carbon::now()->toTimeString();
            if ($invoice_detail->package_id != null) {
                $data_package['package_id'] = $invoice_detail->package_id;
            }
            $package_advances = PackageAdvances::createRecord_forinvoice($data_package);
            $count++;
        }

        $arrivedStatus = AppointmentStatuses::where('is_arrived', '=', 1)->select('id')->first();

        if (Appointments::where('id', '=', $req['appointment_id'])->where('appointment_type_id', '=', Config::get('constants.appointment_type_consultancy'))->exists()) {

            if (AppointmentStatuses::where('parent_id', '=', $arrivedStatus?->id)->exists()) {
                $appointmentStatus = AppointmentStatuses::where('parent_id', '=', $arrivedStatus->id)->where('active', '=', 1)->first();
                if ($appointmentStatus) {
                    Appointments::where('id', '=', $req['appointment_id'])->update(['base_appointment_status_id' => $arrivedStatus->id, 'appointment_status_id' => $appointmentStatus->id]);
                } else {
                    Appointments::where('id', '=', $req['appointment_id'])->update(['base_appointment_status_id' => $arrivedStatus->id, 'appointment_status_id' => $arrivedStatus->id]);
                }
            } else {
                Appointments::where('id', '=', $req['appointment_id'])->update([
                    'base_appointment_status_id' => $arrivedStatus?->id,
                    'appointment_status_id' => $arrivedStatus?->id,
                ]);
            }
        }

        // In case of auto change status we need to update by so that s why we did
        $appointment_data_status['converted_by'] = Auth::User()->id;
        $appointmentinfo->update($appointment_data_status);
        // End

        /**
         * Dispatch Elastic Search Index
         */
        $this->dispatch(
            new IndexSingleAppointmentJob([
                'account_id' => Auth::User()->account_id,
                'appointment_id' => $appointmentinfo->id,
            ])
        );

        return true;

    }

    public function updateAppointment($patient_id, $location_id, $request, $doctor_id, $package_info_tag)
    {

        $appointment = Appointments::findOrFail($package_info_tag->appointment_id);

        $appointmentData['city_id'] = $appointment->city_id;
        $appointmentData['location_id'] = $appointment->location_id;
        $appointmentData['doctor_id'] = $doctor_id;
        $appointmentData['phone'] = $appointment->user->phone;
        $appointmentData['name'] = $appointment->patient->name;
        $appointmentData['gender'] = $appointment->user->gender;
        $appointmentData['lead_id'] = $appointment->lead_id;
        $appointmentData['region_id'] = $appointment->region_id;
        $appointmentData['phone'] = GeneralFunctions::cleanNumber($appointmentData['phone']);
        $appointmentData['updated_by'] = Auth::user()->id;
        $appointmentData['updated_at'] = date('Y-m-d H:i:s');
        $appointmentData['start'] = Carbon::parse(Carbon::now())->toDateTimeString();

        if ($appointmentData['start']) {
            $date_of_appointment = Carbon::parse($appointmentData['start'])->format('Y-m-d');
            $doctor_checking = self::checkingDoctorAvailbility($appointmentData['doctor_id'], $appointmentData['location_id'], $date_of_appointment);
            $appointmentData['scheduled_date'] = Carbon::parse($doctor_checking[0]['start_timestamp'])->format('Y-m-d');
            $appointmentData['scheduled_time'] = Carbon::parse($doctor_checking[0]['start_timestamp'])->format('H:i:s');
        }

        /*
        * Grab Rota day info and update
        */
        $resource = Resources::where([
            'external_id' => $appointmentData['doctor_id'],
            'resource_type_id' => Config::get('constants.resource_doctor_type_id'),
            'account_id' => Auth::User()->account_id,
        ])->first();

        if ($resource) {
            $resource_has_rota_day = ResourceHasRotaDays::getSingleDayRotaWithResourceID($resource->id, $appointmentData['scheduled_date'], Auth::User()->account_id, $appointmentData['location_id']);
            if (count($resource_has_rota_day)) {
                $appointmentData['resource_id'] = $resource->id;
                $appointmentData['resource_has_rota_day_id'] = $resource_has_rota_day['id'];
            }
        }

        $appointment->update($appointmentData);

        if (count($appointment->getChanges()) > 1) {
            $scheduled_at_count = $appointment->scheduled_at_count;
            $appointment->update(['scheduled_at_count' => $scheduled_at_count + 1]);
        }

        /**
         * Dispatch Elastic Search Index
         */
        $this->dispatch(
            new IndexSingleAppointmentJob([
                'account_id' => Auth::User()->account_id,
                'appointment_id' => $appointment->id,
            ])
        );

        $message = 'Record has been updated successfully.';
        flash('Record has been updated successfully.')->success()->important();

        return $appointment->id;
    }
}
