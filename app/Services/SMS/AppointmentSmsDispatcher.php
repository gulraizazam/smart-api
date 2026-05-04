<?php

declare(strict_types=1);

namespace App\Services\SMS;

use App\Enums\AppointmentType;
use App\Helpers\GeneralFunctions;
use App\Helpers\JazzSMSAPI;
use App\Helpers\TelenorSMSAPI;
use App\Models\Appointments;
use App\Models\Settings;
use App\Models\SMSLogs;
use App\Models\SMSTemplates;
use App\Models\UserOperatorSettings;
use Illuminate\Support\Facades\Log;

/**
 * One blessed entry point for sending appointment-related SMS
 * (booking confirmations, reminders).
 *
 * Replaces the duplicated SMS-send blocks that used to live in the
 * `appointment:deliver-on-appointment-book` and
 * `appointment:2nd-message-on-appointment-day` commands. The same
 * service is dispatchable from the cron commands today and from any
 * future SPA controller / job that needs to emit one of these SMS.
 *
 * The dispatcher owns:
 *   - template resolution per appointment_type + consultancy_type
 *   - operator routing (Telenor vs Jazz) via `sys-current-sms-operator`
 *   - per-account `UserOperatorSettings` lookup
 *   - SMSLogs row creation
 *
 * Returns a structured `DispatchResult` array — never throws for
 * "skip" conditions (missing template / missing operator settings),
 * only for unexpected infrastructure failures.
 */
final class AppointmentSmsDispatcher
{
    public const OPERATOR_TELENOR = 1;
    public const OPERATOR_JAZZ = 2;

    /**
     * Author id stamped on auto-generated SMSLog rows. Historical
     * convention — the SMSLogs table has a NOT NULL `created_by` FK
     * to users.id; system-emitted SMS logs have always used user 1.
     * If a service-account user is added later, swap this constant.
     */
    private const SYSTEM_USER_ID = 1;

    /**
     * Send the booking-confirmation SMS for a freshly-booked
     * appointment. Picks `on-appointment` / `virtual-on-appointment`
     * / `treatment-on-appointment` based on the appointment type.
     *
     * @return array{sent: bool, reason?: string, response?: array<string, mixed>}
     */
    public function dispatchBookingMessage(Appointments $appointment): array
    {
        $slug = $this->resolveTemplateSlug($appointment, [
            'consultancy_virtual' => 'virtual-on-appointment',
            'consultancy_in_person' => 'on-appointment',
            'treatment' => 'treatment-on-appointment',
        ]);

        return $this->send($appointment, $slug, 'sms');
    }

    /**
     * Send the day-before reminder SMS. Picks `second-sms` /
     * `virtual-second-sms` / `treatment-second-sms` based on type.
     *
     * @return array{sent: bool, reason?: string, response?: array<string, mixed>}
     */
    public function dispatchReminderMessage(Appointments $appointment): array
    {
        $slug = $this->resolveTemplateSlug($appointment, [
            'consultancy_virtual' => 'virtual-second-sms',
            'consultancy_in_person' => 'second-sms',
            'treatment' => 'treatment-second-sms',
        ]);

        return $this->send($appointment, $slug, '2nd_sms');
    }

    /**
     * @param  array{consultancy_virtual: string, consultancy_in_person: string, treatment: string}  $slugMap
     */
    private function resolveTemplateSlug(Appointments $appointment, array $slugMap): string
    {
        // `appointment_type_id` has no cast on the model; PDO can
        // return integer columns as strings on raw selects. Cast
        // before the strict comparison.
        $isConsultancy = (int) $appointment->appointment_type_id === AppointmentType::Consultancy->value;

        if (! $isConsultancy) {
            return $slugMap['treatment'];
        }

        return $appointment->consultancy_type === 'virtual'
            ? $slugMap['consultancy_virtual']
            : $slugMap['consultancy_in_person'];
    }

    /**
     * @return array{sent: bool, reason?: string, response?: array<string, mixed>}
     */
    private function send(Appointments $appointment, string $templateSlug, string $logType): array
    {
        $accountId = (int) $appointment->account_id;

        $template = SMSTemplates::getBySlug($templateSlug, $accountId);
        if (! $template) {
            Log::info('AppointmentSmsDispatcher: template missing', [
                'appointment_id' => $appointment->id,
                'account_id' => $accountId,
                'slug' => $templateSlug,
            ]);

            return ['sent' => false, 'reason' => 'template_missing'];
        }

        $operatorSetting = Settings::whereSlug('sys-current-sms-operator')->first();
        if (! $operatorSetting) {
            Log::warning('AppointmentSmsDispatcher: sys-current-sms-operator setting missing', [
                'appointment_id' => $appointment->id,
                'account_id' => $accountId,
            ]);

            return ['sent' => false, 'reason' => 'operator_setting_missing'];
        }

        $operator = (int) $operatorSetting->data;
        $credentials = UserOperatorSettings::getRecord($accountId, $operator);
        if (! $credentials) {
            Log::warning('AppointmentSmsDispatcher: operator credentials missing', [
                'appointment_id' => $appointment->id,
                'account_id' => $accountId,
                'operator' => $operator,
            ]);

            return ['sent' => false, 'reason' => 'credentials_missing'];
        }

        $patientPhone = $appointment->phone ?? optional($appointment->patient)->phone ?? null;
        if (! $patientPhone) {
            Log::warning('AppointmentSmsDispatcher: appointment has no phone', [
                'appointment_id' => $appointment->id,
            ]);

            return ['sent' => false, 'reason' => 'phone_missing'];
        }

        $preparedText = Appointments::prepareSMSContent($appointment->id, $template->content);
        $to = GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patientPhone));

        try {
            $smsObj = $this->buildSmsObj($operator, $credentials, $to, $preparedText);
            $response = $this->callApi($operator, $smsObj);
        } catch (\Throwable $e) {
            Log::error('AppointmentSmsDispatcher: provider call failed', [
                'appointment_id' => $appointment->id,
                'operator' => $operator,
                'error' => $e->getMessage(),
            ]);

            return ['sent' => false, 'reason' => 'provider_failure'];
        }

        $this->logSms($smsObj, $response, $appointment->id, $logType, $operator);

        return ['sent' => true, 'response' => $response];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSmsObj(int $operator, UserOperatorSettings $credentials, string $to, string $text): array
    {
        $base = [
            'username' => $credentials->username,
            'password' => $credentials->password,
            'to' => $to,
            'text' => $text,
            'test_mode' => $credentials->test_mode,
        ];

        return $operator === self::OPERATOR_TELENOR
            ? $base + ['mask' => $credentials->mask]
            : $base + ['from' => $credentials->mask];
    }

    /**
     * @param  array<string, mixed>  $smsObj
     * @return array<string, mixed>
     */
    private function callApi(int $operator, array $smsObj): array
    {
        return $operator === self::OPERATOR_TELENOR
            ? TelenorSMSAPI::SendSMS($smsObj)
            : JazzSMSAPI::SendSMS($smsObj);
    }

    /**
     * @param  array<string, mixed>  $smsObj
     * @param  array<string, mixed>  $response
     */
    private function logSms(array $smsObj, array $response, int $appointmentId, string $logType, int $operator): void
    {
        $logRow = array_merge($smsObj, $response, [
            'appointment_id' => $appointmentId,
            'created_by' => self::SYSTEM_USER_ID,
            'log_type' => $logType,
        ]);

        if ($operator === self::OPERATOR_JAZZ) {
            // Jazz uses `from` as the sender mask; mirror it into the
            // log's `mask` column so legacy reports that filter on
            // mask still work for Jazz-sent SMS.
            $logRow['mask'] = $smsObj['from'] ?? null;
        }

        SMSLogs::create($logRow);
    }
}
