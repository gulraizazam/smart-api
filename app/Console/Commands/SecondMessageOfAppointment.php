<?php

namespace App\Console\Commands;

use App\Helpers\GeneralFunctions;
use App\Helpers\TelenorSMSAPI;
use App\Jobs\SecondSmsJob;
use App\Models\Accounts;
use App\Models\Appointments;
use App\Models\UserOperatorSettings;
use App\Models\SMSLogs;
use App\Models\SMSTemplates;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Bus\DispatchesJobs;

class SecondMessageOfAppointment extends Command
{
    use DispatchesJobs;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointment:2nd-message-on-appointment-day';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send 2nd message one day before appointment at 8PM';

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
        $tomorrow = Carbon::parse(Carbon::now())->addDay()->setTimezone('Asia/Karachi')->format('Y-m-d');;

        $where = array();

        $where[] = array(
            'scheduled_date',
            '=',
            $tomorrow
        );
        $where[] = array(
            'base_appointment_status_id',
            '=',
            1
        );
        $appointments = Appointments::join('users', 'users.id', '=', 'appointments.patient_id')->where($where)
            ->where(['appointments.appointment_status_allow_message' => 1])
            ->whereNull('coming_from')
            ->where(['appointments.base_appointment_status' => 1])
            ->select('appointments.id as appointment_id', 'appointments.account_id', 'users.phone')
            ->get();
        $log_type = '2nd_sms';
        if ($appointments) {
            foreach ($appointments as $appointment) {
                $smsLog = SMSLogs::where(array(
                    'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($appointment->phone)),
                    'log_type' => $log_type,
                ))
                    ->where('appointment_id', '=', $appointment->appointment_id)
                    ->whereDate('created_at', '=', $day)
                    ->select('id')->first();

                if ($smsLog) {
                    continue;
                }
                $account = Accounts::first();
                /**
                 * Dispatch Second sms job
                 */
                $job = (new SecondSmsJob([
                    'account_id' => $account->id,
                    'appointment_id' => $appointment->appointment_id,
                    'phone' => $appointment->phone,
                    'log_type' => $log_type
                ]))->delay(Carbon::now()->addSeconds(2));
                dispatch($job);
            }

            try {
                Log::info(json_encode($appointment));
            } catch (\Exception $e) {
                Log::info(json_encode("lOG-XCEPTION: ". $e));
            }

            Log::info("Second sms sent finally ");
        }
    }

    /*
     * Send SMS on booking of Appointment
     *
     * @param: int $appointmentId
     * @param: string $patient_phone
     * @return: array|mixture
     */
//    private function sendSMS($appointmentId, $patient_phone, $log_type = 'sms', $account_id) {
//        // Get Appointment
//        $appointment = Appointments::find($appointmentId);
//        if($appointment->appointment_type_id == Config::get('constants.appointment_type_consultancy')) {
//            // SEND SMS for Appointment Booked
//            $SMSTemplate = SMSTemplates::getBySlug('second-sms', $account_id); // 'second-sms' for Appointment SMS
//        } else {
//            // SEND SMS for Appointment Booked
//            $SMSTemplate = SMSTemplates::getBySlug('treatment-second-sms', $account_id); // 'second-sms' for Appointment SMS
//        }
//
//        if(!$SMSTemplate) {
//            // SMS Promotion is disabled
//            return array(
//                'status' => true,
//                'sms_data' => 'SMS Promotion is disabled',
//                'error_msg' => '',
//            );
//        }
//
//        $preparedText = Appointments::prepareSMSContent($appointmentId, $SMSTemplate->content);
//
//        $UserOperatorSettings = UserOperatorSettings::getRecord($account_id);
//        $SMSObj = array(
//            'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
//            'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
//            'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient_phone)),
//            'text' => $preparedText,
//            'mask' => $UserOperatorSettings->mask, // Setting ID 3 for Mask
//            'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
//        );
//
//        $response = TelenorSMSAPI::SendSMS($SMSObj);
//
//        $SMSLog = array_merge($SMSObj, $response);
//        $SMSLog['appointment_id'] = $appointmentId;
//        $SMSLog['created_by'] = 1;
//        $SMSLog['log_type'] = $log_type;
//        SMSLogs::create($SMSLog);
//        // SEND SMS for Appointment Booked End
//
//        return $response;
//    }
}
