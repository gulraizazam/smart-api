<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Appointments;

use App\Enums\AppointmentType;
use App\Helpers\ActivityLogger;
use App\Helpers\JazzSMSAPI;
use App\Helpers\TelenorSMSAPI;
use App\Http\Requests\Admin\StoreUpdateAppointmentCommentsRequest;
use App\Models\AppointmentComments;
use App\Models\Appointments;
use App\Models\Settings;
use App\Models\SMSLogs;
use App\Models\SMSTemplates;
use App\Models\UserOperatorSettings;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class AppointmentCommunicationController extends AppointmentBaseController
{
    /**
     * Load Appointment SMS History.
     */
    public function showSMSLogs(int $id): JsonResponse
    {
        // Dual-purpose endpoint — consultations + treatments both hit this.
        // Either module's `view_sms_logs` perm grants access;
        // `appointments_manage` kept as a legacy fallback.
        if (
            !\Illuminate\Support\Facades\Gate::allows('consultations.view_sms_logs')
            && !\Illuminate\Support\Facades\Gate::allows('treatments.view_sms_logs')
            && !\Illuminate\Support\Facades\Gate::allows('appointments_manage')
        ) {
            return $this->errorResponse('You are not authorized to view SMS logs.', 403);
        }

        $SMSLogs = SMSLogs::whereAppointmentId($id)->orderBy('created_at', 'desc')->get();

        return $this->successResponse('Record found', [
            'SMSLogs' => $SMSLogs,
            'sms_statuses' => config('constants.sms_array'),
        ]);
    }

    /**
     * Re-send Appointment SMS
     */
    public function sendLogSMS(Request $request): JsonResponse
    {
        $data = $request->all();
        $SMSLog = SMSLogs::find($request->id);
        if (! $SMSLog) {
            return $this->errorResponse('Resource not found', 200);
        }
        if ($SMSLog) {
            $response = $this->resendSMS($SMSLog->id, $SMSLog->to, $SMSLog->text, $SMSLog->appointment_id);

            if ($response['status']) {
                // Admin-initiated SMS resend → audit log row.
                // Silent-fail: audit problems never block the SMS flow.
                try {
                    $recipientName = $SMSLog->appointment?->patient?->name ?? 'Unknown';
                    $templateName = $SMSLog->template ?? 'Manual Resend';
                    ActivityLogger::logSmsSent(
                        recipientName: $recipientName,
                        recipientPhone: (string) $SMSLog->to,
                        templateName: (string) $templateName,
                        patient: $SMSLog->appointment?->patient,
                    );
                } catch (\Throwable $e) {
                    Log::warning('activities.sms_sent.write_failed', [
                        'event' => 'activities.sms_sent.write_failed',
                        'sms_log_id' => $SMSLog->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                return $this->successResponse('SMS sent successfully.');
            }
        }

        return $this->errorResponse('Failed to send SMS.', 200);
    }

    /**
     * Get WhatsApp data for appointment
     */
    public function getWhatsAppData(Request $request): JsonResponse
    {
        try {
            $appointmentId = $request->input('id');

            // Fetch appointment with patient details
            $appointment = Appointments::with([
                'patient',
                'doctor',
                'location',
                'service',
                'appointment_status',
            ])->find($appointmentId);

            if (! $appointment) {
                return response()->json([
                    'status' => false,
                    'message' => 'Appointment not found',
                ]);
            }

            // Check if patient has WhatsApp number
            $whatsappNumber = $appointment->patient->phone ?? null;

            if (! $whatsappNumber) {
                return response()->json([
                    'status' => false,
                    'message' => 'Customer WhatsApp number not found',
                ]);
            }

            // Clean the phone number (remove spaces, dashes, etc.)
            $whatsappNumber = preg_replace('/[^0-9]/', '', $whatsappNumber);

            // Ensure phone number has country code (Pakistan = 92)
            // If number starts with 0, replace with 92
            if (str_starts_with($whatsappNumber, '0')) {
                $whatsappNumber = '92'.substr($whatsappNumber, 1);
            }
            // If number doesn't start with 92 and is 10 digits, add 92
            elseif (strlen($whatsappNumber) === 10 && ! str_starts_with($whatsappNumber, '92')) {
                $whatsappNumber = '92'.$whatsappNumber;
            }

            // Determine template slug based on appointment type
            // appointment_type_id: 1 = Consultancy, 2 = Treatment
            $templateSlug = ($appointment->appointment_type_id === AppointmentType::Treatment->value) ? 'treatment_whatsapp' : 'consultancy_whatsapp';

            // Fetch SMS template
            $template = SMSTemplates::getBySlug($templateSlug, Auth::user()->account_id);

            if (! $template) {
                $templateType = ($appointment->appointment_type_id === AppointmentType::Treatment->value) ? 'Treatment' : 'Consultancy';

                return response()->json([
                    'status' => false,
                    'message' => 'WhatsApp template not found. Please create a template with slug "'.$templateSlug.'" for '.$templateType.' appointments',
                ]);
            }

            // Replace variables in template content
            $message = $template->content;

            // Format appointment time (only time, not date)
            $appointmentTime = 'N/A';
            if ($appointment->scheduled_date && $appointment->scheduled_time) {
                try {
                    $time = Carbon::parse($appointment->scheduled_time);
                    $appointmentTime = $time->format('h:i A');
                } catch (\Exception $e) {
                    $appointmentTime = $appointment->scheduled_time ?? 'N/A';
                }
            }

            // Replace single # variables
            $message = str_replace('#patient_name#', $appointment->patient->name ?? 'N/A', $message);
            $message = str_replace('#appointment_time#', $appointmentTime, $message);
            $message = str_replace('#patient_id#', (string) ($appointment->patient->id ?? 'N/A'), $message);
            $message = str_replace('#appointment_id#', (string) ($appointment->id ?? 'N/A'), $message);
            $message = str_replace('#doctor_name#', $appointment->doctor->name ?? 'N/A', $message);
            $message = str_replace('#location_name#', $appointment->location->name ?? 'N/A', $message);
            $message = str_replace('#centre_google_map#', $appointment->location->google_map ?? 'N/A', $message);
            $message = str_replace('#service_name#', $appointment->service->name ?? 'N/A', $message);
            $message = str_replace('#scheduled_date#', $appointment->scheduled_date ? $appointment->scheduled_date->format('Y-m-d') : 'N/A', $message);
            $message = str_replace('#scheduled_time#', $appointment->scheduled_time ? (string) $appointment->scheduled_time : 'N/A', $message);
            $message = str_replace('#status#', $appointment->appointment_status->name ?? 'N/A', $message);

            // Also support double ## format for backward compatibility
            $message = str_replace('##patient_name##', $appointment->patient->name ?? 'N/A', $message);
            $message = str_replace('##appointment_time##', $appointmentTime, $message);
            $message = str_replace('##patient_id##', (string) ($appointment->patient->id ?? 'N/A'), $message);
            $message = str_replace('##appointment_id##', (string) ($appointment->id ?? 'N/A'), $message);
            $message = str_replace('##doctor_name##', $appointment->doctor->name ?? 'N/A', $message);
            $message = str_replace('##location_name##', $appointment->location->name ?? 'N/A', $message);
            $message = str_replace('##centre_google_map##', $appointment->location->google_map ?? 'N/A', $message);
            $message = str_replace('##service_name##', $appointment->service->name ?? 'N/A', $message);
            $message = str_replace('##scheduled_date##', $appointment->scheduled_date ? $appointment->scheduled_date->format('Y-m-d') : 'N/A', $message);
            $message = str_replace('##scheduled_time##', $appointment->scheduled_time ? (string) $appointment->scheduled_time : 'N/A', $message);
            $message = str_replace('##status##', $appointment->appointment_status->name ?? 'N/A', $message);

            return response()->json([
                'status' => true,
                'data' => [
                    'whatsapp' => $whatsappNumber,
                    'message' => $message,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching WhatsApp data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created Appointment comment in storage.
     *
     * @return Response
     */
    public function comment_store(StoreUpdateAppointmentCommentsRequest $request): RedirectResponse
    {
        if (! Gate::allows('appointments_manage')) {
            return abort(401);
        }
        $data = $request->all();
        // Set Created by
        $data['created_by'] = Auth::user()->id;
        $appointment = AppointmentComments::create($data);
        flash('Comment has been added successfully.')->success()->important();

        return redirect()->back();
    }

    /**
     * Store a newly created Appointment comment via AJAX.
     *
     * @param  Request  $request
     * @return Response
     */
    public function AppointmentStoreComment(Request $req): JsonResponse
    {
        $appointmentComment = AppointmentComments::where('appointment_id', '=', $req->appointment_id)->get();
        $appointment = new AppointmentComments;
        $appointment->comment = $req->comment;
        $appointment->appointment_id = $req->appointment_id;
        $appointment->created_by = Auth::user()->id;
        $appointmentCommentDate = Carbon::parse($appointment->created_at)->format('D M d, Y g:i A');
        $appointment->save();
        $username = Auth::user()->name;
        $myarray = ['username' => $username, 'appointment' => $appointment, 'appointmentCommentDate' => $appointmentCommentDate, 'appointmentCommentSection' => $appointmentComment];

        return response()->json($myarray);
    }

    private function resendSMS($smsId, $patient_phone, $preparedText, $appointmentId): array
    {
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
            SMSLogs::find($smsId)?->update(['status' => 1]);
        }

        return $response;
    }
}
