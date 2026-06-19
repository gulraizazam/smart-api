<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Appointments;

use Carbon\Carbon;
use App\Enums\AppointmentType;
use App\Models\User;
use App\Models\SMSLogs;
use App\Models\Services;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\Invoices;
use App\Models\Resources;
use App\Models\Settings;
use App\Models\Doctors;
use App\Models\SMSTemplates;
use App\Models\Appointments;
use App\Models\InvoiceStatuses;
use App\Models\AppointmentStatuses;
use App\Models\UserOperatorSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use App\Helpers\Filters;
use App\Helpers\ActivityLogger;
use App\Helpers\JazzSMSAPI;
use App\Helpers\TelenorSMSAPI;
use App\Helpers\Widgets\AppointmentCheckesWidget;
use App\Jobs\IndexSingleAppointmentJob;
use App\Services\Phone\PhoneFormattingService;

class AppointmentScheduleController extends AppointmentBaseController
{
    public function checkAndSaveAppointments(Request $request): \Illuminate\Http\JsonResponse
    {
        $appointment_checkes = AppointmentCheckesWidget::AppointmentConsultancyCheckes($request);
        if ($appointment_checkes['status']) {
            $doctor_check_availability = Resources::checkDoctorAvailbility($request);
            if (
                $request->id &&
                $request->start &&
                $request->doctor_id &&
                $request->end
            ) {
                if ($doctor_check_availability) {
                    // Appointment Data
                    $data = $request->all();
                    $data['reschedule'] = 1;
                    $appointment = Appointments::findOrFail($request->id);

                    // Validate that the doctor has the service allocated at this location
                    \Log::info('checkAndSaveAppointments: Validating service allocation', [
                        'appointment_id' => $appointment->id,
                        'doctor_id' => $request->doctor_id,
                        'location_id' => $appointment->location_id,
                        'service_id' => $appointment->service_id,
                    ]);

                    // Check if doctor has the appointment's service allocated
                    $hasService = \DB::table('doctor_has_locations')
                        ->where('user_id', $request->doctor_id)
                        ->where('location_id', $appointment->location_id)
                        ->where('service_id', $appointment->service_id)
                        ->where('is_allocated', 1)
                        ->exists();

                    \Log::info('checkAndSaveAppointments: Validation result', [
                        'has_service' => $hasService,
                        'will_block' => !$hasService,
                    ]);

                    if (!$hasService) {
                        \Log::warning('checkAndSaveAppointments: Blocking update - doctor does not have service');
                        return $this->errorResponse('This doctor does not have the required service allocated for this location.', 200);
                    }
                    // Store old values for activity logging
                    $oldDate = $appointment->scheduled_date;
                    $oldTime = $appointment->scheduled_time;
                    $oldDoctorId = $appointment->doctor_id;

                    $data['first_scheduled_count'] = $appointment->first_scheduled_count;
                    $data['scheduled_at_count'] = $appointment->scheduled_at_count;

                    unset($data['resource_id']);
                    unset($data['resource_has_rota_day_id']);
                    unset($data['resource_has_rota_day_id_for_machine']);
                    $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
                    $invoice = Invoices::where([
                        ['appointment_id', '=', $appointment->id],
                        ['invoice_status_id', '=', $invoicestatus->id],
                    ])->get();
                    if (!empty($invoice)) {
                        return $this->errorResponse('Appointment has invoice.', 200);
                    }
                    $record = Appointments::updateRecord($request->id, $data, Auth::user()->account_id);
                    if ($record) {
                        /*
                         * Set Appointment Status 'pending' and set send message flag
                         */
                        $appointment_status = AppointmentStatuses::getADefaultStatusOnly(Auth::user()->account_id);
                        if ($appointment_status) {
                            $record->update([
                                'appointment_status_id' => $appointment_status->id,
                                'base_appointment_status_id' => $appointment_status->id,
                                'appointment_status_allow_message' => $appointment_status->allow_message,
                                'send_message' => 1, // Set flag 1 to send message on cron job
                            ]);
                        }
                        /**
                         * Dispatch Elastic Search Index
                         */
                        IndexSingleAppointmentJob::dispatch([
                                'account_id' => Auth::user()->account_id,
                                'appointment_id' => $appointment->id,
                            ]);

                        // Log activity for date, time, or doctor changes
                        $newDate = Carbon::parse($request->start)->format('Y-m-d');
                        $newTime = Carbon::parse($request->start)->format('H:i:s');
                        $newDoctorId = $request->doctor_id;

                        $fieldChanges = [];

                        // Check date change
                        if ($oldDate != $newDate) {
                            $fieldChanges['Date'] = [
                                'old' => Carbon::parse($oldDate)->format('M j, Y'),
                                'new' => Carbon::parse($newDate)->format('M j, Y')
                            ];
                        }

                        // Check time change
                        if ($oldTime != $newTime) {
                            $fieldChanges['Time'] = [
                                'old' => Carbon::parse($oldTime)->format('h:i A'),
                                'new' => Carbon::parse($newTime)->format('h:i A')
                            ];
                        }

                        // Check doctor change
                        if ($oldDoctorId != $newDoctorId) {
                            $oldDoctor = Doctors::find($oldDoctorId);
                            $newDoctor = Doctors::find($newDoctorId);
                            $fieldChanges['Doctor'] = [
                                'old' => $oldDoctor->name ?? 'Unknown',
                                'new' => $newDoctor->name ?? 'Unknown'
                            ];
                        }

                        // Log if any changes were made
                        if (!empty($fieldChanges)) {
                            $patient = Patients::find($record->patient_id);
                            $location = Locations::with('city')->find($record->location_id);
                            $service = Services::find($record->service_id);
                            ActivityLogger::logAppointmentUpdated($record, $patient, $fieldChanges, $location, $service);
                        }

                        return $this->successResponse('Appointment Updated Successfully');
                    }
                }

                return $this->errorResponse('Doctor is not available', 200);
            }

            return $this->errorResponse('Invalid paramters', 200);
        }

        return $this->errorResponse($appointment_checkes['message'], 200);
    }

    public function getScheduledAppointments(Request $request): \Illuminate\Http\JsonResponse
     {
         if ($request->location_id) {
            $appointments = Appointments::getScheduledAppointments($request, Config::get('constants.appointment_type_consultancy'), Auth::user()->account_id);
            $start = $request->start;
             $end = $request->end;
             if ($request->doctor_id) {
                 $doctor_rotas = Resources::getDoctorWithRotas($request->location_id, $request->doctor_id, $request->start, $request->end);
             }
             $location_id = $request->location_id;
             $doctor_id = $request->doctor_id;
             $machine_id = $request->machine_id;
             $minTime = Resources::getMinTimeWithDr($location_id, $doctor_id, $start, $end);
             if ($appointments) {
                 $data = [];
                 foreach ($appointments as $appointment) {
                     $dutation = explode(':', $appointment?->service?->duration ?? '');
                     $data[$appointment->id] = [
                         'id' => $appointment->id,
                         'service' => $appointment?->service?->name ?? '',
                         'patient' => ($appointment->name) ? $appointment->name : $appointment->patient->name,
                         'created_by' => $appointment->user?->name ?? '',
                         'phone' => Gate::allows('contact') ? PhoneFormattingService::prepareNumber4Call($appointment?->patient?->phone ?? '0300') : '***********',
                         'duration' => $appointment?->service?->duration ?? '00',
                         'editable' => true,
                         'overlap' => false,
                         'start' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d').' '.Carbon::parse($appointment->scheduled_time, null)->format('H:i'),
                         'end' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d').' '.Carbon::parse($appointment->scheduled_time, null)->addHours((int)($dutation[0] ?? 0))->addMinutes((int)($dutation[1] ?? 0))->format('H:i'),
                         'color' => $appointment?->service?->color ?? '#fff',
                         'resourceId' => $appointment->doctor_id,
                     ];
                 }
                 if ($request->doctor_id) {
                     return response()->json([
                         'status' => 1,
                         'events' => $data,
                         'min_time' => $minTime,
                         'rotas' => $doctor_rotas?->toArray() ?? '',
                         'start_time' => \Illuminate\Support\Carbon::parse($doctor_rotas->pluck('doctor_rotas')->flatten(1)->min('start_time'))->format('H:i:s'),
                         'end_time' => \Illuminate\Support\Carbon::parse($doctor_rotas->pluck('doctor_rotas')->flatten(1)->max('end_time'))->format('H:i:s'),

                     ]);
                 } else {
                     return response()->json([
                         'status' => 1,
                         'events' => $data,
                         'min_time' => $minTime,
                         'rotas' => $doctor_rotas?->toArray() ?? '',
                         'start_time' => '10:00',
                         'end_time' => '23:00',
                     ]);
                 }
             } else {
                 return response()->json([
                     'status' => 0,
                     'events' => null,
                 ]);
             }
         } else {
             return response()->json([
                 'status' => 0,
                 'events' => null,
             ]);
         }
     }

    public function getNonScheduledAppointments(Request $request): \Illuminate\Http\JsonResponse
    {
        if (
            $request->city_id &&
            $request->location_id &&
            $request->doctor_id
        ) {
            $appointments = Appointments::getNonScheduledAppointments($request, Config::get('constants.appointment_type_consultancy'), Auth::user()->account_id);
            if ($appointments) {
                $data = [];
                foreach ($appointments as $appointment) {
                    $data[$appointment->id] = [
                        'id' => $appointment->id,
                        'service' => $appointment->service->name,
                        'patient' => ($appointment->name) ? $appointment->name : $appointment->patient->name,
                        'created_by' => $appointment->user?->name ?? '',
                        'phone' => Gate::allows('contact') ? PhoneFormattingService::prepareNumber4Call($appointment->patient->phone) : '***********',
                        'duration' => $appointment->service->duration,
                        'editable' => true,
                        'overlap' => false,
                        'color' => $appointment->service->color,
                        'resourceId' => $appointment->doctor_id,
                    ];
                }

                return response()->json([
                    'status' => 1,
                    'events' => $data,
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'events' => null,
                ]);
            }
        } else {
            return response()->json([
                'status' => 0,
                'events' => null,
            ]);
        }
    }

    public function getScheduledServiceAppointments(Request $request): \Illuminate\Http\JsonResponse
    {

        $location_id = $request->location_id;
        $doctor_id = $request->doctor_id;
        $machine_id = $request->machine_id;
        $account_id = Auth::user()->account_id;
        $cancelled_appointment_status = AppointmentStatuses::getCancelledStatusOnly($account_id);
        $appointments = Appointments::getScheduledAppointments($request, Config::get('constants.appointment_type_service'), Auth::user()->account_id, true);
        $resources = Resources::getRoomsResourceRotaWithoutDays($request->location_id);
        $start = $request->start;
        $end = $request->end;
        $minTime = Resources::getMinTimeWithDrAndMachine($location_id, $doctor_id, $machine_id, $start, $end);
        if ($request->has('start') && $request->has('end')) {

            $doctor_rotas = Resources::getDoctorWithRotasWithSpecificDate($request->location_id, $request->doctor_id, $request->start, $request->end);
        } else {
            $doctor_rotas = collect();
        }

        if ($appointments) {
            $data = [];
            if ($request->doctor_id != '') {
                foreach ($appointments as $appointment) {
                    $dutation = explode(':', $appointment->service->duration);
                    $data[$appointment->id] = [
                        'id' => $appointment->id,
                        'service' => $appointment->service->name,
                        'patient' => ($appointment->name) ? $appointment->name : ($appointment->patient->name ?? ''),
                        'created_by' => $appointment->user?->name ?? '',
                        'phone' => PhoneFormattingService::prepareNumber4Call($appointment->patient->phone ?? ''),
                        'duration' => $appointment->service->duration,
                        'editable' => ($request->doctor_id == $appointment->doctor_id) ? true : false,
                        'overlap' => false,
                        'start' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d').' '.Carbon::parse($appointment->scheduled_time, null)->format('H:i'),
                        'end' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d').' '.Carbon::parse($appointment->scheduled_time, null)->addHours((int)($dutation[0] ?? 0))->addMinutes((int)($dutation[1] ?? 0))->format('H:i'),
                        'color' => $appointment->service->color, // Use exact service color
                        'resourceId' => $appointment->doctor_id, // Use doctor_id for resource calendar view
                    ];
                }
            } else {
                foreach ($appointments as $appointment) {
                    $dutation = explode(':', $appointment->service->duration);
                    $data[$appointment->id] = [
                        'id' => $appointment->id,
                        'service' => $appointment->service->name,
                        'patient' => ($appointment->name) ? $appointment->name : ($appointment->patient->name ?? ''),
                        'created_by' => $appointment->user?->name ?? '',
                        'phone' => PhoneFormattingService::prepareNumber4Call($appointment->patient->phone ?? ''),
                        'duration' => $appointment->service->duration,
                        'editable' => ($request->doctor_id == $appointment->doctor_id) ? true : false,
                        'overlap' => false,
                        'start' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d').' '.Carbon::parse($appointment->scheduled_time, null)->format('H:i'),
                        'end' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d').' '.Carbon::parse($appointment->scheduled_time, null)->addHours((int)($dutation[0] ?? 0))->addMinutes((int)($dutation[1] ?? 0))->format('H:i'),
                        'color' => $appointment->service->color,
                        'resourceId' => $appointment->doctor_id, // Use doctor_id for resource calendar view
                    ];
                }
            }

            $resource_ids = [];
            $resources = array_filter($resources);
            foreach ($resources as $resource) {
                $resource_ids[] = $resource['id'];
            }
            // Get business closures, time offs, and working days
            $closures = \App\Http\Controllers\Api\AppointmentsController::getBusinessClosures($account_id, $location_id, $start, $end);
            $workingDays = \App\Http\Controllers\Api\AppointmentsController::getBusinessWorkingDays($account_id);
            $workingDayExceptions = \App\Http\Controllers\Api\AppointmentsController::getWorkingDayExceptions($account_id);
            $timeOffs = [];
            if ($doctor_id) {
                $timeOffs = \App\Http\Controllers\Api\AppointmentsController::getDoctorTimeOffs($account_id, $location_id, $doctor_id, $start, $end);
            }

            if ($request->doctor_id) {
                return response()->json([
                    'status' => 1,
                    'events' => $data,
                    'rotas' => $doctor_rotas->toArray(),
                    'min_time' => $minTime,
                    'resource_ids' => $resource_ids,
                    'start_time' => \Illuminate\Support\Carbon::parse($doctor_rotas->pluck('doctor_rotas')->flatten(1)->min('start_time'))->format('H:i:s'),
                    'end_time' => \Illuminate\Support\Carbon::parse($doctor_rotas->pluck('doctor_rotas')->flatten(1)->max('end_time'))->format('H:i:s'),
                    'closures' => $closures,
                    'time_offs' => $timeOffs,
                    'working_days' => $workingDays,
                    'working_day_exceptions' => $workingDayExceptions,
                ]);
            } else {
                return response()->json([
                    'status' => 1,
                    'events' => $data,
                    'rotas' => $doctor_rotas->toArray() ?? '',
                    'min_time' => $minTime,
                    'resource_ids' => $resource_ids,
                    'start_time' => '10:00',
                    'end_time' => '22:00',
                    'closures' => $closures,
                    'time_offs' => $timeOffs,
                    'working_days' => $workingDays,
                    'working_day_exceptions' => $workingDayExceptions,
                ]);
            }

        } else {
            // Still return closures, time offs, and working days even when no appointments
            $closures = \App\Http\Controllers\Api\AppointmentsController::getBusinessClosures($account_id, $location_id, $start, $end);
            $workingDays = \App\Http\Controllers\Api\AppointmentsController::getBusinessWorkingDays($account_id);
            $workingDayExceptions = \App\Http\Controllers\Api\AppointmentsController::getWorkingDayExceptions($account_id);
            $timeOffs = [];
            if ($doctor_id) {
                $timeOffs = \App\Http\Controllers\Api\AppointmentsController::getDoctorTimeOffs($account_id, $location_id, $doctor_id, $start, $end);
            }

            return response()->json([
                'status' => 1,
                'events' => [],
                'rotas' => $doctor_rotas ? $doctor_rotas->toArray() : [],
                'closures' => $closures,
                'time_offs' => $timeOffs,
                'working_days' => $workingDays,
                'working_day_exceptions' => $workingDayExceptions,
            ]);
        }
    }

    public function getNonScheduledServiceAppointments(Request $request): \Illuminate\Http\JsonResponse
    {
        if (
            $request->city_id &&
            $request->location_id &&
            $request->doctor_id
        ) {
            $appointments = Appointments::getNonScheduledAppointments($request, Config::get('constants.appointment_type_service'), Auth::user()->account_id);
            if ($appointments) {
                $data = [];
                foreach ($appointments as $appointment) {
                    $data[$appointment->id] = [
                        'id' => $appointment->id,
                        'service' => $appointment->service->name,
                        'patient' => ($appointment->name) ? $appointment->name : $appointment->patient->name,
                        'created_by' => $appointment->user?->name ?? '',
                        'phone' => PhoneFormattingService::prepareNumber4Call($appointment->patient->phone),
                        'duration' => $appointment->service->duration,
                        'editable' => true,
                        'overlap' => false,
                        'color' => $appointment->service->color,
                        'resourceId' => $appointment->doctor_id,
                    ];
                }

                return response()->json([
                    'status' => 1,
                    'events' => $data,
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'events' => null,
                ]);
            }
        } else {
            return response()->json([
                'status' => 0,
                'events' => null,
            ]);
        }
    }

    public function loadRotaByDoctor(Request $request): \Illuminate\Http\JsonResponse
    {
        if (
            $request->doctor_id &&
            $request->appointment_id &&
            $request->scheduled_date &&
            $request->resourceRotaDayID
        ) {
            $appointment = Appointments::find($request->appointment_id);
            if ($request->resourceRotaDayID != $appointment->resource_has_rota_day_id) {
                /*
                    * Data is changed, avoid to provide rota
                    */
                return response()->json([
                    'status' => 0,
                    'resource_has_rota_day' => null,
                    'machine_has_rota_day' => null,
                    'selected' => null,
                ]);
            }
            /**
             * Location Information
             */
            $location_id = $request->location_id;
            $doctor = User::findOrFail($request->doctor_id);
            $resource = Resources::where([
                'external_id' => $doctor->id,
                'resource_type_id' => Config::get('constants.resource_doctor_type_id'),
                'account_id' => Auth::user()->account_id,
            ])->first();
            if ($resource) {
                if ($appointment->appointment_type_id == Config::get('constants.appointment_type_consultancy')) {
                    /*
                     * Consultancy: Grab Rota day info
                     */
                    $resource_has_rota_day = \App\Models\ResourceHasRotaDays::getSingleDayRotaWithResourceID($resource->id, $request->scheduled_date, Auth::user()->account_id, $location_id);
                    if (count($resource_has_rota_day)) {
                        if ($resource_has_rota_day['start_time'] && $resource_has_rota_day['end_time'] && $appointment->scheduled_time) {
                            $selected = (\App\Models\ResourceHasRota::checkTime(Carbon::parse($appointment->scheduled_time)->format('h:i A'), $resource_has_rota_day['start_time'], $resource_has_rota_day['end_time'], true)) ? Carbon::parse($appointment->scheduled_time)->format('h:i A') : '';
                            $resource_has_rota_day['start_time'] = Carbon::parse($resource_has_rota_day['start_time'])->format('h:ia');
                            $resource_has_rota_day['end_time'] = Carbon::parse($resource_has_rota_day['end_time'])->subMinutes($appointment->service->duration_in_minutes)->format('h:ia');

                            if ($resource_has_rota_day['start_off']) {
                                $resource_has_rota_day['start_off'] = Carbon::parse($resource_has_rota_day['start_off'])->subMinutes($appointment->service->duration_in_minutes)->addMinute('5')->format('h:ia');
                                $resource_has_rota_day['end_off'] = Carbon::parse($resource_has_rota_day['end_off'])->format('h:ia');
                            } else {
                                $resource_has_rota_day['start_off'] = null;
                                $resource_has_rota_day['end_off'] = null;
                            }
                        } else {
                            $selected = '';
                        }

                        return response()->json([
                            'status' => 1,
                            'resource_has_rota_day' => $resource_has_rota_day,
                            'machine_has_rota_day' => $resource_has_rota_day,
                            'selected' => ($selected) ? Carbon::parse($selected)->format('g:ia') : null,
                        ]);
                    }
                } else {
                    $resource_id = $request->machine_id;
                    if (($request->machineRotaDayID != $appointment->resource_has_rota_day_id_for_machine) || ! $resource_id) {
                        /*
                         * Data is changed, avoid to provide rota
                         */
                        return response()->json([
                            'status' => 0,
                            'resource_has_rota_day' => null,
                            'machine_has_rota_day' => null,
                            'selected' => null,
                        ]);
                    }
                    /*
                     * Treatment: Find overlapped doctor and machine area
                     */
                    $resource_has_rota_day = \App\Models\ResourceHasRotaDays::getSingleDayRotaWithResourceID($resource->id, $request->scheduled_date, Auth::user()->account_id, $location_id);
                    $machine_has_rota_day = \App\Models\ResourceHasRotaDays::getSingleDayRotaWithResourceID($resource_id, $request->scheduled_date, Auth::user()->account_id, $location_id);
                    if (count($resource_has_rota_day) && count($machine_has_rota_day)) {
                        if (
                            ($resource_has_rota_day['start_time'] && $resource_has_rota_day['end_time']) &&
                            ($machine_has_rota_day['start_time'] && $machine_has_rota_day['end_time']) &&
                            $appointment->scheduled_time
                        ) {
                            $biggerTime = \App\Models\ResourceHasRota::getBiggerTime($resource_has_rota_day['start_time'], $machine_has_rota_day['start_time']);
                            $smallerTime = \App\Models\ResourceHasRota::getSmallerTime($resource_has_rota_day['end_time'], $machine_has_rota_day['end_time']);
                            $selected = (\App\Models\ResourceHasRota::checkTime(Carbon::parse($appointment->scheduled_time)->format('h:i A'), $biggerTime, $smallerTime, true)) ? Carbon::parse($appointment->scheduled_time)->format('h:i A') : '';
                            $resource_has_rota_day['start_time'] = Carbon::parse($biggerTime)->format('h:ia');
                            $resource_has_rota_day['end_time'] = Carbon::parse($smallerTime)->subMinutes($appointment->service->duration_in_minutes)->format('h:ia');

                            if ($resource_has_rota_day['start_off']) {
                                $resource_has_rota_day['start_off'] = Carbon::parse($resource_has_rota_day['start_off'])->subMinutes($appointment->service->duration_in_minutes)->addMinute('5')->format('h:ia');
                                $resource_has_rota_day['end_off'] = Carbon::parse($resource_has_rota_day['end_off'])->format('h:ia');
                            } else {
                                $resource_has_rota_day['start_off'] = null;
                                $resource_has_rota_day['end_off'] = null;
                            }
                        } else {
                            $selected = '';
                        }

                        return response()->json([
                            'status' => 1,
                            'resource_has_rota_day' => $resource_has_rota_day,
                            'machine_has_rota_day' => $resource_has_rota_day,
                            'selected' => ($selected) ? Carbon::parse($selected)->format('g:ia') : null,
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'status' => 0,
            'resource_has_rota_day' => null,
            'machine_has_rota_day' => null,
            'selected' => null,
        ]);
    }

    /**
     * check appointment scheduling time. Is doctor and resource available and save that
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function serviceSchedule(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('appointments_manage')) {
            return $this->errorResponse('You are not authorized to perform this action.', 403);
        }

        $appointment_checkes = AppointmentCheckesWidget::AppointmentAppointmentCheckesfromcard($request);
        if ($appointment_checkes['status']) {
            // Only check doctor availability (machine rota check removed)
            $doctor_check_availability = Resources::checkDoctorAvailbility($request);
            if (
                $request->id &&
                $request->start &&
                $request->end
            ) {
                if ($doctor_check_availability) {
                    // Appointment Data
                    $data = $request->all();
                    $data['resource_id'] = $request->resourceId ?? null;
                    $appointment = Appointments::byAccount(auth()->user()->account_id)->findOrFail($request->id);
                    $data['first_scheduled_count'] = $appointment->first_scheduled_count;
                    $data['scheduled_at_count'] = $appointment->scheduled_at_count;
                    if ($appointment->appointment_type_id == Config::get('constants.appointment_type_service')) {
                        // Only get doctor rota (machine rota check removed)
                        $resource_dcotor = Resources::where('external_id', '=', $data['doctor_id'])->first();
                        if ($resource_dcotor) {
                            $response = Resources::getResourceRotaHasDay($data['start'], $resource_dcotor->id);
                            if (isset($response['resource_has_rota_day_id']) && $response['resource_has_rota_day_id']) {
                                $data['resource_has_rota_day_id'] = $response['resource_has_rota_day_id'];
                            }
                        }
                    }
                    $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
                    $invoice = Invoices::where([
                        ['appointment_id', '=', $appointment->id],
                        ['invoice_status_id', '=', $invoicestatus->id],
                    ])->get();
                    if (!empty($invoice)) {
                        return $this->errorResponse('Appointment has invoice.', 200);
                    }
                    $record = Appointments::updateServiceRecord($request->id, $data, Auth::user()->account_id);
                    if ($record) {
                        /*
                         * Set Appointment Status 'pending' and set send message flag
                         */
                        $appointment_status = AppointmentStatuses::getADefaultStatusOnly(Auth::user()->account_id);
                        if ($appointment_status) {
                            $record->update([
                                'appointment_status_id' => $appointment_status->id,
                                'base_appointment_status_id' => $appointment_status->id,
                                'appointment_status_allow_message' => $appointment_status->allow_message,
                                'send_message' => 1, // Set flag 1 to send message on cron job
                            ]);
                        }
                        IndexSingleAppointmentJob::dispatch([
                                'account_id' => Auth::user()->account_id,
                                'appointment_id' => $appointment->id,
                            ]);

                        return $this->successResponse('Appointment Updated Successfully.');
                    }

                    return $this->errorResponse('Failed to update appointment.', 200);
                } else {
                    return $this->errorResponse('Doctor is not available.', 200);
                }
            }

            return $this->errorResponse('Requested parameter not provided.', 200);
        }

        return $this->errorResponse($appointment_checkes['message'], 200);
    }

    public function getSchedule(Request $request): \Illuminate\Http\JsonResponse
    {

        $appointment = Appointments::select('id', 'scheduled_date', 'scheduled_time')->find($request->id);

        if ($appointment) {
            // Convert to array and format dates
            $appointmentData = [
                'id' => $appointment->id,
                'scheduled_date' => Carbon::parse($appointment->scheduled_date)->format('Y-m-d'),
                'scheduled_time' => Carbon::parse($appointment->scheduled_time)->format('h:i A'),
            ];

            return response()->json($appointmentData);
        }

        return response()->json(null);
    }

    public function updateSchedule(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('appointments_manage')) {
            return $this->errorResponse('You are not authorized to perform this action.', 403);
        }

        $data = [];
        $appointment = Appointments::byAccount(auth()->user()->account_id)->find($request->appointment_id);

        if (! $appointment) {
            return $this->errorResponse('Appointment not found', 200);
        }

        if ($appointment) {
            // Store old date/time for activity logging
            $oldDate = $appointment->scheduled_date;
            $oldTime = $appointment->scheduled_time;
            $isRescheduled = false;

            // Compare dates in same format (Y-m-d)
            $oldDateFormatted = Carbon::parse($appointment->scheduled_date)->format('Y-m-d');
            $newDateFormatted = Carbon::parse($request->scheduled_date)->format('Y-m-d');

            if ($oldDateFormatted != $newDateFormatted) {
                $data['converted_by'] = Auth::user()->id;
                $isRescheduled = true;
            }

            // Check if time changed
            $oldTimeFormatted = Carbon::parse($appointment->scheduled_time)->format('H:i:s');
            $newTimeFormatted = Carbon::parse($request->scheduled_time)->format('H:i:s');

            if ($oldTimeFormatted != $newTimeFormatted) {
                $isRescheduled = true;
            }

            if ($appointment->appointment_status_id == config('constants.appointment_status_arrived')
                || $appointment->appointment_status_id == config('constants.appointment_status_cancelled')) {
                return $this->errorResponse('Appointment has Invoice or has been canceled!', 200);
            }

            // Validate business closure, working days, and time offs
            $scheduleValidation = $this->validateScheduleDate($appointment, $request);
            if (!$scheduleValidation['status']) {
                return $this->errorResponse($scheduleValidation['message'], 200);
            }

            $rota = $this->checkRota($appointment, $request);

            if ($rota['status']) {
                $newScheduledDate = Carbon::parse($request->scheduled_date)->format('Y-m-d');
                $updateData = [
                    'scheduled_date' => $newScheduledDate,
                    'scheduled_time' => Carbon::parse($request->scheduled_time)->format('H:i:s'),
                    'converted_by' => $data['converted_by'] ?? $appointment->converted_by,
                    'appointment_status_id' => config('constants.appointment_status_pending'),
                    'base_appointment_status_id' => config('constants.appointment_status_pending'),
                    'updated_at' => Filters::getCurrentTimeStamp(),
                ];

                $currentScheduledDate = $appointment->scheduled_date
                    ? Carbon::parse($appointment->scheduled_date)->format('Y-m-d')
                    : null;
                if ($currentScheduledDate !== $newScheduledDate) {
                    $updateData['rescheduled_count'] = ((int) $appointment->rescheduled_count) + 1;
                }

                // Set send_message to 1 if consultation is rescheduled and status is pending
                if ($isRescheduled && $appointment->base_appointment_status_id == config('constants.appointment_status_pending', 1)) {
                    $updateData['send_message'] = 1;
                }

                $appointment->update($updateData);
                $screen = $appointment->appointment_type_id === AppointmentType::Consultancy->value ? 'Consultancy' : 'Treatment';
                ActivityLogger::saveAppointmentLogs('rescheduled', $screen, $appointment);
                $log_type = 'sms';
                $patient = Patients::findOrFail($appointment->patient_id);
                if ($appointment->isDirty('scheduled_date')) {
                    $this->SendRescheduleSms($request->appointment_id, $patient->phone, $log_type, $appointment->account_id);
                }

                // Log rescheduled activity
                if ($isRescheduled) {
                    $location = Locations::with('city')->find($appointment->location_id);
                    $service = Services::find($appointment->service_id);
                    ActivityLogger::logAppointmentRescheduled(
                        $appointment,
                        $patient,
                        $oldDate,
                        $oldTime,
                        Carbon::parse($request->scheduled_date)->format('Y-m-d'),
                        Carbon::parse($request->scheduled_time)->format('H:i:s'),
                        $location,
                        $service
                    );
                }

                return $this->successResponse('Record updated successfully!');
            }

            return $rota['status'] ? $this->successResponse($rota['message']) : $this->errorResponse($rota['message'], 200);
        }

        return $this->errorResponse('Appointment not found!', 200);
    }

    /**
     * Validate schedule date for business closures, working days, and time offs
     */
    private function validateScheduleDate($appointment, $request): array
    {
        $accountId = Auth::user()->account_id;
        $locationId = $request->location_id ?? $appointment->location_id;
        $doctorId = $request->doctor_id ?? $appointment->doctor_id;
        $date = Carbon::parse($request->scheduled_date)->format('Y-m-d');
        $time = Carbon::parse($request->scheduled_time)->format('H:i:s');

        // 1. Check for business closures
        $allCentresId = 30;
        $closure = \App\Models\BusinessClosure::where('account_id', $accountId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(function ($query) use ($locationId, $allCentresId) {
                $query->whereHas('locations', function ($subQ) use ($locationId) {
                    $subQ->where('locations.id', $locationId);
                })
                ->orWhereHas('locations', function ($subQ) use ($allCentresId) {
                    $subQ->where('locations.id', $allCentresId);
                })
                ->orWhereDoesntHave('locations');
            })
            ->first();

        if ($closure) {
            return [
                'status' => false,
                'message' => 'Cannot schedule appointment on ' . Carbon::parse($date)->format('d M, Y') . '. Business is closed: ' . ($closure->title ?? 'Business Closed')
            ];
        }

        // 2. Check for working days (with exceptions)
        $workingDays = \App\Http\Controllers\Api\AppointmentsController::getBusinessWorkingDays($accountId);
        $isWorkingDay = \App\Models\WorkingDayException::isWorkingDay($accountId, $date, $workingDays);

        if (!$isWorkingDay) {
            return [
                'status' => false,
                'message' => 'Cannot schedule appointment on ' . Carbon::parse($date)->format('l, d M Y') . '. Business is closed on this day.'
            ];
        }

        // 3. Check for doctor time offs
        if ($doctorId) {
            $resource = \App\Models\Resources::where([
                'external_id' => $doctorId,
                'resource_type_id' => Config::get('constants.resource_doctor_type_id'),
                'account_id' => $accountId,
            ])->first();

            if ($resource) {
                $timeOffs = \App\Models\ResourceTimeOff::where('resource_id', $resource->id)
                    ->where('account_id', $accountId)
                    ->where('location_id', $locationId)
                    ->where(function ($query) use ($date) {
                        $query->whereDate('start_date', $date)
                            ->orWhere(function ($q) use ($date) {
                                $q->where('is_repeat', 1)
                                    ->whereDate('start_date', '<=', $date)
                                    ->where(function ($rq) use ($date) {
                                        $rq->whereNull('repeat_until')
                                            ->orWhereDate('repeat_until', '>=', $date);
                                    });
                            });
                    })
                    ->get();

                foreach ($timeOffs as $timeOff) {
                    $timeOffStart = Carbon::parse($timeOff->start_time)->format('H:i:s');
                    $timeOffEnd = Carbon::parse($timeOff->end_time)->format('H:i:s');

                    if ($time >= $timeOffStart && $time < $timeOffEnd) {
                        return [
                            'status' => false,
                            'message' => 'Doctor has time off during this time slot (' . Carbon::parse($timeOff->start_time)->format('h:i A') . ' - ' . Carbon::parse($timeOff->end_time)->format('h:i A') . ').'
                        ];
                    }
                }
            }
        }

        return ['status' => true, 'message' => ''];
    }

    private function SendRescheduleSms($appointmentId, $patient_phone, $log_type, $account_id): array
    {
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
        if ($setting->data === '1') {
            $SMSObj = [
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'to' => PhoneFormattingService::prepareNumber(PhoneFormattingService::cleanNumber($patient_phone)),
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
                'to' => PhoneFormattingService::prepareNumber(PhoneFormattingService::cleanNumber($patient_phone)),
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
