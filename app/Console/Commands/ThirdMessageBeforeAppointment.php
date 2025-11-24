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

class ThirdMessageBeforeAppointment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointment:3rd-message-before-appointment';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send 3rd message 2 hours before appointment';

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
        $day = Carbon::now()->setTimezone('Asia/Karachi')->format('Y-m-d');
        $start_time = Carbon::parse(Carbon::now())->setTimezone('Asia/Karachi')->format('H:i').':00';
        $end_time = Carbon::parse(Carbon::now())->addMinutes(120)->setTimezone('Asia/Karachi')->format('H:i').':00';

        $currentTime = Carbon::now()->setTimezone('Asia/Karachi')->format('H:i').':00';
        $start = '09:00:00';
        $end = '19:00:00';

        if (
            strtotime($currentTime) < strtotime($start) ||
            strtotime($currentTime) > strtotime($end)
        ) {
            // whatever you have to do here
            return;
        }

        $where = [];

        $where[] = [
            'scheduled_date',
            '=',
            $day,
        ];
        $where[] = [
            'scheduled_time',
            '>=',
            $start_time,
        ];
        $where[] = [
            'scheduled_time',
            '<=',
            $end_time,
        ];
        $appointments = Appointments::join('users', 'users.id', '=', 'appointments.patient_id')->where($where)
            ->where(['appointments.appointment_status_allow_message' => 1])
            ->where('patient_id',49382)
            ->whereNull('coming_from')
            ->select('appointments.id as appointment_id', 'appointments.account_id', 'users.phone')
            ->get();

        $log_type = '3rd_sms';

        if ($appointments) {
            foreach ($appointments as $appointment) {

                $smsLog = SMSLogs::where([
                    'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($appointment->phone)),
                    'log_type' => $log_type,
                ])
                    ->where('appointment_id', '=', $appointment->appointment_id)
                    ->whereDate('created_at', '=', $day)
                    ->select('id')->first();

                if ($smsLog) {
                    continue;
                }

                $response = $this->sendSMS($appointment->appointment_id, $appointment->phone, $log_type, $appointment->account_id);
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
                $SMSTemplate = SMSTemplates::getBySlug('virtual-third-sms', $account_id); // 'third-sms' for virtual consultancy
            } else {
                $SMSTemplate = SMSTemplates::getBySlug('third-sms', $account_id); // 'third-sms' for Appointment SMS
            }
        } else {
            // SEND SMS for Appointment Booked
            $SMSTemplate = SMSTemplates::getBySlug('treatment-third-sms', $account_id); // 'third-sms' for Appointment SMS
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
