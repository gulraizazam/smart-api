<?php

namespace App\Jobs;

use App\Helpers\Elastic\AppointmentsElastic;
use App\Helpers\GeneralFunctions;
use App\Helpers\JazzSMSAPI;
use App\Helpers\TelenorSMSAPI;
use App\Models\Accounts;
use App\Models\Appointments;
use App\Models\HeavyLifter;
use App\Models\Settings;
use App\Models\SMSLogs;
use App\Models\SMSTemplates;
use App\Models\UserOperatorSettings;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Auth;

class IndexSingleAppointmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Holds payload data
     *
     */
    protected $payload;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($payload)
    {
        $this->queue = 'medium';
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            // dd($this->payload);
            $appointment = Appointments::where([
                'account_id' => $this->payload['account_id'],
                'id' => $this->payload['appointment_id'],
            ])->first();
            $SMSTemplate = SMSTemplates::getBySlug('on-appointment', Auth::User()->account_id);

            if (!$SMSTemplate) {
                
                return array(
                    'status' => true,
                    'sms_data' => 'SMS Promotion is disabled',
                    'error_msg' => '',
                );
            }
    
            $preparedText = Appointments::prepareSMSContent($appointment->id, $SMSTemplate->content);
            
            $setting = Settings::whereSlug('sys-current-sms-operator')->first();
    
            $UserOperatorSettings = UserOperatorSettings::getRecord(Auth::User()->account_id, $setting->data);
    
            if ($setting->data == 1) {
    
                $SMSObj = array(
                    'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                    'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                    'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($this->payload['patient_phone'])),
                    'text' => $preparedText,
                    'mask' => $UserOperatorSettings->mask, // Setting ID 3 for Mask
                    'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
                );
                $response = TelenorSMSAPI::SendSMS($SMSObj);
            } else {
                $SMSObj = array(
                    'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                    'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                    'from' => $UserOperatorSettings->mask,
                    'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($this->payload['patient_phone'])),
                    'text' => $preparedText,
                    'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
                );
                $response = JazzSMSAPI::SendSMS($SMSObj);
            }
    

    
            $SMSLog = array_merge($SMSObj, $response);
            $SMSLog['appointment_id'] = $appointment->id;
            $SMSLog['created_by'] = Auth::user()->id;
            if ($setting->data == 2) {
                $SMSLog['mask'] = $SMSObj['from'];
            }
            SMSLogs::create($SMSLog);
        } catch (\Exception $exception) {

        }

        return true;
    }
}
