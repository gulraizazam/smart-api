<?php

namespace App\Console\Commands;

use Config;
use Carbon\Carbon;
use App\Models\SMSLogs;
use App\Models\Accounts;
use App\Models\Settings;
use App\Jobs\SecondSmsJob;
use App\Helpers\JazzSMSAPI;
use App\Models\Appointments;
use App\Models\SMSTemplates;
use App\Helpers\TelenorSMSAPI;
use Illuminate\Console\Command;
use App\Helpers\GeneralFunctions;
use Illuminate\Support\Facades\Log;

use App\Models\UserOperatorSettings;
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
    protected $description = 'Send reminder message to patients with appointments scheduled today';

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
        
        $day = Carbon::now()->format('Y-m-d');
        $tomorrow = Carbon::parse(Carbon::now())->addDay()->format('Y-m-d');

        $where = [];

        $where[] = [
            'scheduled_date',
            '=',
            $tomorrow,  // tomorrow
        ];
        $where[] = [
            'base_appointment_status_id',
            '=',
            1,
        ];
        $appointments = Appointments::join('users', 'users.id', '=', 'appointments.patient_id')->where($where)
            ->where(['appointments.appointment_status_allow_message' => 1])
            ->whereNull('coming_from')
             
            ->select('appointments.id as appointment_id', 'appointments.account_id', 'users.phone','appointments.appointment_type_id', 'appointments.consultancy_type')
            ->get();
           
            
        $log_type = '2nd_sms';
        
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
                $account = Accounts::first();
                /**
                 * Dispatch Second sms job
                 */
                // $job = (new SecondSmsJob([
                //     'account_id' => $account->id,
                //     'appointment_id' => $appointment->appointment_id,
                //     'phone' => $appointment->phone,
                //     'log_type' => $log_type,
                // ]))->delay(Carbon::now()->addSeconds(2));
                // dispatch($job);


               


            if ($appointment->appointment_type_id ==1) {
              
                // SEND SMS for Appointment Booked
                if ($appointment->consultancy_type == 'virtual') {
                  
                    $SMSTemplate = SMSTemplates::getBySlug('virtual-second-sms',$account->id); // 'second-sms' for virtual consultancy SMS
                } else {
                    
                    $SMSTemplate = SMSTemplates::getBySlug('second-sms', $account->id); // 'second-sms' for Appointment SMS
                }
            } else {
                
                // SEND SMS for Appointment Booked
                $SMSTemplate = SMSTemplates::getBySlug('treatment-second-sms',$account->id); // 'second-sms' for Appointment SMS
               
            }


            if (! $SMSTemplate) {
                // SMS template not found, skip this appointment
                continue;
            }
            $preparedText = Appointments::prepareSMSContent($appointment->appointment_id, $SMSTemplate->content);

            $setting = Settings::whereSlug('sys-current-sms-operator')->first();

            $UserOperatorSettings = UserOperatorSettings::getRecord($account->id, $setting->data);
           
            if ($setting->data == 1) {
                $SMSObj = [
                    'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                    'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                    'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($appointment->phone)),
                   
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
                    'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($appointment->phone)),
                    'text' => $preparedText,
                    'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
                ];
                
                $response = JazzSMSAPI::SendSMS($SMSObj);
            }
          
            $SMSLog = array_merge($SMSObj, $response);
            $SMSLog['appointment_id'] = $appointment->appointment_id;
            $SMSLog['created_by'] = 1;
            $SMSLog['log_type'] = $log_type;
            if ($setting->data == 2) {
                $SMSLog['mask'] = $SMSObj['from'];
            }
                SMSLogs::create($SMSLog);

          


            }
            return true;
            try {
                Log::info(json_encode($appointment));
            } catch (\Exception $e) {
                Log::info(json_encode('lOG-XCEPTION: '.$e));
            }

            Log::info('Second sms sent finally ');
        }else{
           
           echo "no Apt found";
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
