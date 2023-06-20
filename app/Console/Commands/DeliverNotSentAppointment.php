<?php

namespace App\Console\Commands;

use App\Helpers\JazzSMSAPI;
use App\Helpers\TelenorSMSAPI;
use App\Models\Appointments;
use App\Models\Settings;
use App\Models\SMSLogs;
use App\Models\UserOperatorSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeliverNotSentAppointment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointment:deliver-not-sent-sms';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deliver not sent sms again';

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
        $start_time = Carbon::parse(Carbon::now())->subMinute(120)->setTimezone('Asia/Karachi')->format('Y-m-d H:i').':00';
        $end_time = Carbon::parse(Carbon::now())->addMinutes(5)->setTimezone('Asia/Karachi')->format('Y-m-d H:i').':59';

        $where = [];

        $where[] = [
            'status',
            '=',
            0,
        ];
        $where[] = [
            'created_at',
            '>=',
            $start_time,
        ];
        $where[] = [
            'created_at',
            '<=',
            $end_time,
        ];

        $sms_logs = SMSLogs::where($where)->select('id', 'to', 'text', 'appointment_id')->get();

        if ($sms_logs) {
            foreach ($sms_logs as $sms_log) {
                $response = $this->sendSMS($sms_log->id, $sms_log->to, $sms_log->text, $sms_log->appointment_id);
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
    private function sendSMS($smsId, $patient_phone, $preparedText, $appointmentId)
    {

        if ($appointmentId) {

            $appointment = Appointments::find($appointmentId);

            $setting = Settings::whereSlug('sys-current-sms-operator')->first();

            $UserOperatorSettings = UserOperatorSettings::getRecord($appointment->account_id, $setting->data);

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

            \Log::Info('AppointmentId: '.json_encode($appointmentId));

            return $response;
        }
    }
}
