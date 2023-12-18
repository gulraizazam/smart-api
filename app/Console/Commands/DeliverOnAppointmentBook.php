<?php

namespace App\Console\Commands;

use App\Helpers\GeneralFunctions;
use App\Helpers\JazzSMSAPI;
use App\Helpers\TelenorSMSAPI;
use App\Models\Appointments;
use App\Models\Settings;
use App\Models\SMSLogs;
use App\Models\SMSTemplates;
use App\Models\UserOperatorSettings;
use Carbon\Carbon;
use Config;
use Illuminate\Console\Command;

class DeliverOnAppointmentBook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointment:deliver-on-appointment-book';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send message on appointment booking';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $appointments = Appointments::join('users', 'users.id', '=', 'appointments.patient_id')
            ->where(['appointments.send_message' => 1])
            ->where(['appointments.base_appointment_status_id' => 1])
            ->whereBetween('appointments.updated_at',[Carbon::parse(Carbon::now())->startOfDay(),Carbon::parse(Carbon::now())->subMinutes(3)->toDateTimeString()])
            ->whereNull('coming_from')
            ->select('appointments.id as appointment_id', 'appointments.account_id', 'appointments.updated_at', 'users.phone')
            ->offset(0)
            ->limit(100)
            ->get();

        $log_type = 'sms';

        if ($appointments) {
            foreach ($appointments as $appointment) {

                try {
                    /* Prevent sms to the previous dates,  before these were sending */
                    if (Carbon::parse($appointment->updated_at)->format('Y-m-d') >= date('Y-m-d')) {

                        $this->sendSMS($appointment->appointment_id, $appointment->phone, $log_type, $appointment->account_id);

                        // Update Flags
                        Appointments::where(['id' => $appointment->appointment_id])->update(['send_message' => 0, 'msg_count' => 1]);
                    }
                } catch (\Exception $e) {
                    // Do nothing
                }

            }
        }
    }

    /*
     * Send SMS on booking of Appointment
     *
     * @param: int $appointmentId
     * @param: string $patient_phone
     * @return: array|mixture
     */
    private function sendSMS($appointmentId, $patient_phone, $log_type, $account_id)
    {
        // Get Appointment
        $appointment = Appointments::find($appointmentId);
        if ($appointment->appointment_type_id == Config::get('constants.appointment_type_consultancy')) {
            // SEND SMS for Appointment Booked
            if ($appointment->consultancy_type == 'virtual') {
                $SMSTemplate = SMSTemplates::getBySlug('virtual-on-appointment', $account_id); // 'on-appointment' for virtual consultancy SMS
            } else {
                $SMSTemplate = SMSTemplates::getBySlug('on-appointment', $account_id); // 'on-appointment' for Appointment SMS
            }
        } else {
            // SEND SMS for Appointment Booked
            $SMSTemplate = SMSTemplates::getBySlug('treatment-on-appointment', $account_id); // 'on-appointment' for Appointment SMS
        }

        if (! $SMSTemplate) {
            // SMS Promotion is disabled
            return [
                'status' => true,
                'sms_data' => 'SMS Promotion is disabled',
                'error_msg' => '',
            ];
        }

        $preparedText = Appointments::prepareSMSContent($appointmentId, $SMSTemplate->content);

        $setting = Settings::whereSlug('sys-current-sms-operator')->first();

        $UserOperatorSettings = UserOperatorSettings::getRecord($account_id, $setting->data);

        if ($setting->data == 1) {
            $SMSObj = [
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient_phone)),
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
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient_phone)),
                'text' => $preparedText,
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            ];
            $response = JazzSMSAPI::SendSMS($SMSObj);
        }
        $SMSLog = array_merge($SMSObj, $response);
        $SMSLog['appointment_id'] = $appointmentId;
        $SMSLog['created_by'] = 1;
        $SMSLog['log_type'] = $log_type;
        if ($setting->data == 2) {
            $SMSLog['mask'] = $SMSObj['from'];
        }
        SMSLogs::create($SMSLog);
        // SEND SMS for Appointment Booked End

        return $response;
    }
}
