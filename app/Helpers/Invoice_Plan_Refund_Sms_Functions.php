<?php

namespace App\Helpers;

use App\Models\Appointments;
use App\Models\Doctors;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\Services;
use App\Models\Settings;
use App\Models\SMSLogs;
use App\Models\SMSTemplates;
use App\Models\UserOperatorSettings;
use Carbon\Carbon;
use Composer\Package\Package;
use Auth;

class Invoice_Plan_Refund_Sms_Functions
{
    /*
     * Function That sand sms when plan received amount
     * @param: int $package_id
     * @param: int $packageAdavances
     * @return: string
     */
    static function PlanCashReceived_SMS($package_id, $packageAdavances)
    {
        // SEND SMS for Appointment Booked
        $SMSTemplate = SMSTemplates::getBySlug('plan-cash', Auth::User()->account_id);

        if (!$SMSTemplate) {
            // SMS Promotion is disabled
            return array(
                'status' => true,
                'sms_data' => 'Plan Cash Amount SMS is disabled',
                'error_msg' => '',
            );
        }

        $plan_information = Packages::find($package_id);

        $patient = Patients::find($plan_information->patient_id);

        $preparedText = Self::prepareSMSContent($package_id, $SMSTemplate->content, $packageAdavances, $plan_information, $patient);

        $setting = Settings::whereSlug('sys-current-sms-operator')->first();

        $UserOperatorSettings = UserOperatorSettings::getRecord(Auth::User()->account_id, $setting->data);

        if ($setting->data == 1) {

            $SMSObj = array(
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient->phone)),
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
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient->phone)),
                'text' => $preparedText,
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            );
            $response = JazzSMSAPI::SendSMS($SMSObj);
        }

//        $response = TelenorSMSAPI::SendSMS($SMSObj);

        $SMSLog = array_merge($SMSObj, $response);
        $SMSLog['package_id'] = $package_id;
        $SMSLog['created_by'] = Auth::user()->id;
        if ($setting->data == 2) {
            $SMSLog['mask'] = $SMSObj['from'];
        }
        SMSLogs::create($SMSLog);
        // SEND SMS for Appointment Booked End
        return $response;
    }

    /**
     * Prepare SMS Contnet for Plan cash received
     *
     * @param: int $package_id
     * @param: int $smsContent
     * @param: int $packageAdavances
     * @return: string
     */
    static public function prepareSMSContent($package_id, $smsContent, $packageAdavances, $plan_information, $patient)
    {
        if (!$package_id) {
            return $smsContent;
        } else {
            if ($plan_information) {
                $smsContent = str_replace('##patient_name##', $patient->name, $smsContent);
                $smsContent = str_replace('##cash_amount##', number_format($packageAdavances->cash_amount), $smsContent);

                $smsContent = str_replace('##created_at##', Carbon::parse($packageAdavances->created_at)->toFormattedDateString(), $smsContent);
                $smsContent = str_replace('##id##', $plan_information->id, $smsContent);
            }
            return $smsContent;
        }
    }

    /*
     * Function That sand sms when refund give to client
     * @param: int $package_id
     * @param: int $packageAdavances
     * @return: string
     */
    static function RefundCashReceived_SMS($packageAdavances)
    {
        // SEND SMS for Appointment Booked
        $SMSTemplate = SMSTemplates::getBySlug('refund-amount', Auth::User()->account_id);

        if (!$SMSTemplate) {
            // SMS Promotion is disabled
            return array(
                'status' => true,
                'sms_data' => 'Refund SMS is disabled',
                'error_msg' => '',
            );
        }

        $patient = Patients::find($packageAdavances->patient_id);

        $preparedText = Self::prepareSMSContent_refund($packageAdavances, $SMSTemplate->content, $patient);

        $setting = Settings::whereSlug('sys-current-sms-operator')->first();

        $UserOperatorSettings = UserOperatorSettings::getRecord(Auth::User()->account_id, $setting->data);

        if ($setting->data == 1) {

            $SMSObj = array(
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient->phone)),
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
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient->phone)),
                'text' => $preparedText,
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            );
            $response = JazzSMSAPI::SendSMS($SMSObj);
        }

//        $response = TelenorSMSAPI::SendSMS($SMSObj);

        $SMSLog = array_merge($SMSObj, $response);
        if ($packageAdavances->package_id) {
            $SMSLog['package_id'] = $packageAdavances->package_id;
            $SMSLog['is_refund'] = 'Yes';
        } else {
            $SMSLog['appointment_id'] = $packageAdavances->appointment_id;
            $SMSLog['is_refund'] = 'Yes';
        }
        $SMSLog['created_by'] = Auth::user()->id;
        if ($setting->data == 2) {
            $SMSLog['mask'] = $SMSObj['from'];
        }
        SMSLogs::create($SMSLog);
        // SEND SMS for Appointment Booked End
        return $response;
    }

    /**
     * Prepare SMS Contnet for refund
     *
     * @param: int $package_id
     * @param: int $smsContent
     * @param: int $packageAdavances
     * @return: string
     */
    static public function prepareSMSContent_refund($packageAdavances, $smsContent, $patient)
    {
        if (!$packageAdavances) {
            return $smsContent;
        } else {
            if ($packageAdavances) {
                $smsContent = str_replace('##patient_name##', $patient->name, $smsContent);
                $smsContent = str_replace('##cash_amount##', number_format($packageAdavances->cash_amount), $smsContent);

                $smsContent = str_replace('##created_at##', Carbon::parse($packageAdavances->created_at)->toFormattedDateString(), $smsContent);
            }
            return $smsContent;
        }
    }

    /*
     * Function That sand sms when Invoice RingUp
     * @param: int $package_id
     * @param: int $packageAdavances
     * @return: string
     */
    static function InvoiceCashReceived_SMS($invoice, $invoice_detail, $package_id = false)
    {
        // SEND SMS for Appointment Booked
        $SMSTemplate = SMSTemplates::getBySlug('invoice-ringup', Auth::User()->account_id);

        if (!$SMSTemplate) {
            // SMS Promotion is disabled
            return array(
                'status' => true,
                'sms_data' => 'Invoice Ringup SMS is disabled',
                'error_msg' => '',
            );
        }
        if ($package_id) {
            $information_type = Packages::find($package_id);
            $balance_patient_in = PackageAdvances::where([
                ['patient_id', '=', $information_type->patient_id],
                ['package_id', '=', $package_id],
                ['cash_flow', '=', 'in']
            ])->sum('cash_amount');
            $balance_patient_out = PackageAdvances::where([
                ['patient_id', '=', $information_type->patient_id],
                ['package_id', '=', $package_id],
                ['cash_flow', '=', 'out']
            ])->sum('cash_amount');
            $balance = $balance_patient_in - $balance_patient_out;
        } else {
            $information_type = Appointments::find($invoice->appointment_id);
            $balance = 0;
        }

        $patient = Patients::find($invoice->patient_id);

        $preparedText = Self::prepareSMSContent_invoice($information_type, $SMSTemplate->content, $invoice, $invoice_detail, $patient, $balance);

        $setting = Settings::whereSlug('sys-current-sms-operator')->first();

        $UserOperatorSettings = UserOperatorSettings::getRecord(Auth::User()->account_id, $setting->data);

        if ($setting->data == 1) {

            $SMSObj = array(
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient->phone)),
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
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient->phone)),
                'text' => $preparedText,
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            );
            $response = JazzSMSAPI::SendSMS($SMSObj);
        }

//        $response = TelenorSMSAPI::SendSMS($SMSObj);

        $SMSLog = array_merge($SMSObj, $response);
        if ($package_id) {
            $SMSLog['package_id'] = $package_id;
        } else {
            $SMSLog['appointment_id'] = $invoice->appointment_id;
        }
        $SMSLog['invoice_id'] = $invoice->id;
        $SMSLog['created_by'] = Auth::user()->id;
        if ($setting->data == 2) {
            $SMSLog['mask'] = $SMSObj['from'];
        }
        SMSLogs::create($SMSLog);
        // SEND SMS for Appointment Booked End
        return $response;
    }

    /**
     * Prepare SMS Contnet for Invoice
     *
     * @param: int $package_id
     * @param: int $smsContent
     * @param: int $packageAdavances
     * @return: string
     */
    static public function prepareSMSContent_invoice($information_type, $smsContent, $invoice, $invoice_detail, $patient, $balance)
    {
        if (!$information_type) {
            return $smsContent;
        } else {
            $service_info = Services::find($invoice_detail->service_id);
            if ($invoice) {
                $smsContent = str_replace('##patient_name##', $patient->name, $smsContent);
                $smsContent = str_replace('##service_name##', $service_info->name, $smsContent);
                if ($balance) {
                    $smsContent = str_replace('##remaining_balance##', number_format($balance), $smsContent);
                } else {
                    $smsContent = str_replace('##remaining_balance##', 0, $smsContent);
                }
                $smsContent = str_replace('##created_at##', Carbon::parse($invoice->created_at)->toFormattedDateString(), $smsContent);
            }
            return $smsContent;
        }
    }

}