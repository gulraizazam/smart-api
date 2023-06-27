<?php

namespace App\Jobs;

use App\Helpers\GeneralFunctions;
use App\Helpers\JazzSMSAPI;
use App\Helpers\TelenorSMSAPI;
use App\Models\Appointments;
use App\Models\Settings;
use App\Models\SMSLogs;
use App\Models\SMSTemplates;
use App\Models\UserOperatorSettings;
use Config;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SecondSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payload;

    /**
     * Create a new job instance.
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
            // Get Appointment
            $appointment = Appointments::find($this->payload['appointment_id']);

            if ($appointment->appointment_type_id == Config::get('constants.appointment_type_consultancy')) {
                // SEND SMS for Appointment Booked
                if ($appointment->consultancy_type == 'virtual') {
                    $SMSTemplate = SMSTemplates::getBySlug('virtual-second-sms', $this->payload['account_id']); // 'second-sms' for virtual consultancy SMS
                } else {
                    $SMSTemplate = SMSTemplates::getBySlug('second-sms', $this->payload['account_id']); // 'second-sms' for Appointment SMS
                }
            } else {
                // SEND SMS for Appointment Booked
                $SMSTemplate = SMSTemplates::getBySlug('treatment-second-sms', $this->payload['account_id']); // 'second-sms' for Appointment SMS
            }
            if (! $SMSTemplate) {
                // SMS Promotion is disabled
                return [
                    'status' => true,
                    'sms_data' => 'SMS Promotion is disabled',
                    'error_msg' => '',
                ];
            }
            $preparedText = Appointments::prepareSMSContent($this->payload['appointment_id'], $SMSTemplate->content);

            $setting = Settings::whereSlug('sys-current-sms-operator')->first();

            $UserOperatorSettings = UserOperatorSettings::getRecord($this->payload['account_id'], $setting->data);

            if ($setting->data == 1) {
                $SMSObj = [
                    'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                    'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                    'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($this->payload['phone'])),
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
                    'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($this->payload['phone'])),
                    'text' => $preparedText,
                    'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
                ];
                $response = JazzSMSAPI::SendSMS($SMSObj);
            }
            $SMSLog = array_merge($SMSObj, $response);
            $SMSLog['appointment_id'] = $this->payload['appointment_id'];
            $SMSLog['created_by'] = 1;
            $SMSLog['log_type'] = $this->payload['log_type'];
            if ($setting->data == 2) {
                $SMSLog['mask'] = $SMSObj['from'];
            }
            SMSLogs::create($SMSLog);

            return true;

        } catch (\Exception $exception) {
            $exception->getLine().'---'.$exception->getMessage().'----'.$exception->getFile();
        }
    }
}
