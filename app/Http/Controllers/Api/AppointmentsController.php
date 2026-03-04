<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AppointmentException;
use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentStatusRequest;
use App\Http\Requests\Appointment\ScheduleAppointmentRequest;
use App\Services\Appointment\AppointmentService;
use App\Services\Appointment\ConsultancyService;
use App\Services\Appointment\TreatmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class AppointmentsController extends Controller
{
    protected $appointmentService;
    protected $consultancyService;
    protected $treatmentService;

    public function __construct(
        AppointmentService $appointmentService,
        ConsultancyService $consultancyService,
        TreatmentService $treatmentService
    ) {
        $this->appointmentService = $appointmentService;
        $this->consultancyService = $consultancyService;
        $this->treatmentService = $treatmentService;
    }

    public function index(Request $request)
    {
        try {
            if (!Gate::allows('appointments_manage') && !Gate::allows('appointments_view')) {
                throw AppointmentException::unauthorized();
            }

            $filters = $request->only([
                'patient_id', 'phone', 'location_id', 'doctor_id', 'service_id',
                'appointment_status_id', 'scheduled_date_from', 'scheduled_date_to',
                'created_date_from', 'created_date_to', 'scheduled'
            ]);

            $query = $this->appointmentService->getAppointmentsList($filters);

            if ($request->has('paginate') && $request->paginate == 'false') {
                $appointments = $query->get();
            } else {
                $perPage = $request->get('per_page', 15);
                $appointments = $query->paginate($perPage);
            }

            return ApiHelper::apiResponse(200, 'Appointments retrieved successfully.', true, $appointments);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error fetching appointments: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function store(StoreAppointmentRequest $request)
    
    {
        
        try {
            if (!Gate::allows('appointments_manage')) {
                throw AppointmentException::unauthorized();
            }

            $appointment = $this->appointmentService->createAppointment($request->validated());

            return ApiHelper::apiResponse(200, 'Appointment created successfully.', true, $appointment);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage(), false);
        } catch (\Exception $e) {
            Log::error('Error creating appointment: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function show($id)
    {
        try {
            if (!Gate::allows('appointments_manage') && !Gate::allows('appointments_view')) {
                throw AppointmentException::unauthorized();
            }

            $appointment = $this->appointmentService->getAppointmentById($id);

            return ApiHelper::apiResponse(200, 'Appointment retrieved successfully.', true, $appointment);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error fetching appointment: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function update(UpdateAppointmentRequest $request, $id)
    {
        try {
            if (!Gate::allows('appointments_manage')) {
                throw AppointmentException::unauthorized();
            }

            $appointment = $this->appointmentService->updateAppointment($id, $request->validated());

            return ApiHelper::apiResponse(200, 'Appointment updated successfully.', true, $appointment);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error updating appointment: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function destroy($id)
    {
        try {
            if (!Gate::allows('appointments_destroy')) {
                throw AppointmentException::unauthorized();
            }

            $this->appointmentService->deleteAppointment($id);

            return ApiHelper::apiResponse(200, 'Appointment deleted successfully.', true);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error deleting appointment: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, $id)
    {
        try {
            if (!Gate::allows('appointments_manage')) {
                throw AppointmentException::unauthorized();
            }

            $appointment = $this->appointmentService->updateAppointmentStatus($id, $request->validated());

            return ApiHelper::apiResponse(200, 'Appointment status updated successfully.', true, $appointment);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error updating appointment status: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function updateSchedule(Request $request)
    {
        return $this->schedule($request);
    }

    public function schedule(Request $request)
    {
        try {
            if (!Gate::allows('appointments_manage')) {
                throw AppointmentException::unauthorized();
            }

            // Calendar sends 'id' but service expects 'appointment_id'
            $appointmentId = $request->id ?? $request->appointment_id;
            
            if (!$appointmentId) {
                return ApiHelper::apiResponse(400, 'Appointment ID is required.', false);
            }

            $data = [
                'start' => $request->start,
                'doctor_id' => $request->doctor_id,
                'location_id' => $request->location_id,
                'resource_id' => $request->resource_id,
                'reschedule' => true,
            ];

            $appointment = $this->appointmentService->scheduleAppointment($appointmentId, $data);

            return ApiHelper::apiResponse(200, 'Appointment scheduled successfully.', true, $appointment);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage(), false);
        } catch (\Exception $e) {
            Log::error('Error scheduling appointment: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function scheduled(Request $request)
    {
        try {
            if (!Gate::allows('appointments_manage') && !Gate::allows('appointments_view')) {
                throw AppointmentException::unauthorized();
            }

            $account_id = \Illuminate\Support\Facades\Auth::user()->account_id;

            $filters = $request->only([
                'location_id', 'doctor_id', 'service_id', 'appointment_status_id',
                'scheduled_date_from', 'scheduled_date_to', 'appointment_type_id',
                'start', 'end'
            ]);

            // Map start/end to scheduled_date_from/to for calendar compatibility
            if (!empty($filters['start']) && empty($filters['scheduled_date_from'])) {
                $filters['scheduled_date_from'] = \Carbon\Carbon::parse($filters['start'])->format('Y-m-d');
            }
            if (!empty($filters['end']) && empty($filters['scheduled_date_to'])) {
                $filters['scheduled_date_to'] = \Carbon\Carbon::parse($filters['end'])->format('Y-m-d');
            }

            $appointments = $this->appointmentService->getScheduledAppointments($filters);

            // Format appointments for calendar
            $events = [];
            foreach ($appointments as $appointment) {
                $duration = explode(':', $appointment->service->duration ?? '00:00');
                $events[$appointment->id] = [
                    'id' => $appointment->id,
                    'service' => $appointment->service->name ?? '',
                    'patient' => $appointment->name ?: ($appointment->patient->name ?? ''),
                    'created_by' => $appointment->user->name ?? '',
                    'phone' => Gate::allows('contact') ? \App\Helpers\GeneralFunctions::prepareNumber4Call($appointment->patient->phone ?? '0300') : '***********',
                    'duration' => $appointment->service->duration ?? '00:00',
                    'editable' => true,
                    'overlap' => false,
                    'start' => \Carbon\Carbon::parse($appointment->scheduled_date)->format('Y-m-d') . ' ' . \Carbon\Carbon::parse($appointment->scheduled_time)->format('H:i'),
                    'end' => \Carbon\Carbon::parse($appointment->scheduled_date)->format('Y-m-d') . ' ' . \Carbon\Carbon::parse($appointment->scheduled_time)->addHours($duration[0] ?? 0)->addMinutes($duration[1] ?? 0)->format('H:i'),
                    'color' => $appointment->service->color ?? '#fff',
                    'resourceId' => $appointment->doctor_id,
                ];
            }

            // Get doctor rotas if doctor_id is provided
            $doctor_rotas = [];
            $start_time = '10:00';
            $end_time = '23:00';
            
            if (!empty($request->doctor_id)) {
                $doctor_rotas = \App\Models\Resources::getDoctorWithRotas(
                    $request->location_id,
                    $request->doctor_id,
                    $request->start,
                    $request->end
                );
                
                if ($doctor_rotas && $doctor_rotas->count() > 0) {
                    $rotas_flat = $doctor_rotas->pluck('doctor_rotas')->flatten(1);
                    $start_time = \Carbon\Carbon::parse($rotas_flat->min('start_time'))->format('H:i:s');
                    $end_time = \Carbon\Carbon::parse($rotas_flat->max('end_time'))->format('H:i:s');
                }
            }

            // Get business closures for the date range
            $closures = self::getBusinessClosures(
                $account_id,
                $request->location_id,
                $filters['scheduled_date_from'] ?? $request->start,
                $filters['scheduled_date_to'] ?? $request->end
            );

            // Get time offs for the doctor if selected
            $timeOffs = [];
            if (!empty($request->doctor_id)) {
                $timeOffs = self::getDoctorTimeOffs(
                    $account_id,
                    $request->location_id,
                    $request->doctor_id,
                    $filters['scheduled_date_from'] ?? $request->start,
                    $filters['scheduled_date_to'] ?? $request->end
                );
            }

            // Get business working days configuration
            $workingDays = self::getBusinessWorkingDays($account_id);

            // Get working day exceptions
            $workingDayExceptions = self::getWorkingDayExceptions($account_id);

            return response()->json([
                'status' => 1,
                'events' => $events,
                'rotas' => $doctor_rotas ? $doctor_rotas->toArray() : [],
                'start_time' => $start_time,
                'end_time' => $end_time,
                'closures' => $closures,
                'time_offs' => $timeOffs,
                'working_days' => $workingDays,
                'working_day_exceptions' => $workingDayExceptions,
            ]);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error fetching scheduled appointments: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function nonScheduled(Request $request)
    {
        try {
            if (!Gate::allows('appointments_manage') && !Gate::allows('appointments_view')) {
                throw AppointmentException::unauthorized();
            }

            $filters = $request->only([
                'location_id', 'doctor_id', 'service_id', 'appointment_status_id',
                'appointment_type_id'
            ]);

            $appointments = $this->appointmentService->getNonScheduledAppointments($filters);

            return ApiHelper::apiResponse(200, 'Non-scheduled appointments retrieved successfully.', true, $appointments);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error fetching non-scheduled appointments: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function statistics(Request $request)
    {
        try {
            if (!Gate::allows('appointments_manage') && !Gate::allows('appointments_view')) {
                throw AppointmentException::unauthorized();
            }

            $filters = $request->only([
                'location_id', 'doctor_id', 'service_id', 'appointment_status_id',
                'scheduled_date_from', 'scheduled_date_to', 'appointment_type_id'
            ]);

            $statistics = $this->appointmentService->getAppointmentStatistics($filters);

            return ApiHelper::apiResponse(200, 'Appointment statistics retrieved successfully.', true, $statistics);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error fetching appointment statistics: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get business closures for a location in a date range
     */
    public static function getBusinessClosures($accountId, $locationId, $startDate, $endDate): array
    {
        if (!$locationId || !$startDate || !$endDate) {
            return [];
        }

        if (!$accountId) {
            $accountId = \Illuminate\Support\Facades\Auth::user()->account_id;
        }
        $startDate = \Carbon\Carbon::parse($startDate)->format('Y-m-d');
        $endDate = \Carbon\Carbon::parse($endDate)->format('Y-m-d');

        // "All Centres" location ID - closures with this location apply to all locations
        $allCentresId = 30;

        $closures = \App\Models\BusinessClosure::where('account_id', $accountId)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereDate('start_date', '<=', $endDate)
                      ->whereDate('end_date', '>=', $startDate);
            })
            ->where(function ($query) use ($locationId, $allCentresId) {
                // Match closures that have this specific location
                $query->whereHas('locations', function ($subQ) use ($locationId) {
                    $subQ->where('locations.id', $locationId);
                })
                // OR closures that have "All Centres" (location_id 30) assigned
                ->orWhereHas('locations', function ($subQ) use ($allCentresId) {
                    $subQ->where('locations.id', $allCentresId);
                })
                // OR closures that have no locations assigned (applies to all)
                ->orWhereDoesntHave('locations');
            })
            ->get();

        $result = [];
        foreach ($closures as $closure) {
            $closureStart = \Carbon\Carbon::parse($closure->start_date);
            $closureEnd = \Carbon\Carbon::parse($closure->end_date);
            $rangeStart = \Carbon\Carbon::parse($startDate);
            $rangeEnd = \Carbon\Carbon::parse($endDate);

            $effectiveStart = $closureStart->greaterThan($rangeStart) ? $closureStart : $rangeStart;
            $effectiveEnd = $closureEnd->lessThan($rangeEnd) ? $closureEnd : $rangeEnd;

            $currentDate = $effectiveStart->copy();
            while ($currentDate->lessThanOrEqualTo($effectiveEnd)) {
                $result[] = [
                    'id' => $closure->id,
                    'title' => $closure->title ?? 'Business Closed',
                    'date' => $currentDate->format('Y-m-d'),
                ];
                $currentDate->addDay();
            }
        }

        return $result;
    }

    /**
     * Get time offs for a doctor in a date range
     */
    public static function getDoctorTimeOffs($accountId, $locationId, $doctorId, $startDate, $endDate): array
    {
        if (!$doctorId || !$startDate || !$endDate) {
            return [];
        }

        if (!$accountId) {
            $accountId = \Illuminate\Support\Facades\Auth::user()->account_id;
        }
        $startDate = \Carbon\Carbon::parse($startDate)->format('Y-m-d');
        $endDate = \Carbon\Carbon::parse($endDate)->format('Y-m-d');

        // Get resource ID for this doctor
        // external_id is the user_id (doctor_id) in the resources table
        $resource = \App\Models\Resources::where('external_id', $doctorId)
            ->where('account_id', $accountId)
            ->where('resource_type_id', \Config::get('constants.resource_doctor_type_id', 2))
            ->first();

        if (!$resource) {
            return [];
        }

        $timeOffs = \App\Models\ResourceTimeOff::where('account_id', $accountId)
            ->where('resource_id', $resource->id)
            ->where(function ($query) use ($locationId) {
                $query->where('location_id', $locationId)
                    ->orWhereNull('location_id');
            })
            ->where(function ($query) use ($startDate, $endDate) {
                // Use whereDate for proper date comparison ignoring time component
                $query->whereDate('start_date', '>=', $startDate)
                    ->whereDate('start_date', '<=', $endDate)
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('is_repeat', true)
                            ->whereDate('start_date', '<=', $endDate)
                            ->where(function ($q2) use ($startDate) {
                                $q2->whereNull('repeat_until')
                                    ->orWhereDate('repeat_until', '>=', $startDate);
                            });
                    });
            })
            ->get();

        $result = [];
        foreach ($timeOffs as $timeOff) {
            $result[] = [
                'id' => $timeOff->id,
                'resource_id' => $timeOff->resource_id,
                'type' => $timeOff->type,
                'type_label' => $timeOff->type_label,
                'date' => $timeOff->start_date->format('Y-m-d'),
                'start_time' => $timeOff->start_time,
                'end_time' => $timeOff->end_time,
                'is_full_day' => $timeOff->is_full_day,
                'is_repeat' => $timeOff->is_repeat,
                'repeat_until' => $timeOff->repeat_until ? $timeOff->repeat_until->format('Y-m-d') : null,
                'description' => $timeOff->description,
            ];
        }

        return $result;
    }

    /**
     * Get business working days configuration
     */
    public static function getBusinessWorkingDays($accountId = null): array
    {
        if (!$accountId) {
            $accountId = \Illuminate\Support\Facades\Auth::user()->account_id;
        }
        
        $setting = \App\Models\Settings::where('account_id', $accountId)
            ->where('slug', 'business_working_days')
            ->first();
        
        if ($setting && $setting->data) {
            return json_decode($setting->data, true);
        }
        
        // Default: Monday to Saturday are working days
        return [
            'monday' => true,
            'tuesday' => true,
            'wednesday' => true,
            'thursday' => true,
            'friday' => true,
            'saturday' => true,
            'sunday' => false,
        ];
    }

    /**
     * Get working day exceptions for calendar display
     */
    public static function getWorkingDayExceptions($accountId = null): array
    {
        if (!$accountId) {
            $accountId = \Illuminate\Support\Facades\Auth::user()->account_id;
        }

        $exceptions = \App\Models\WorkingDayException::where('account_id', $accountId)
            ->get()
            ->map(function ($exc) {
                return [
                    'date' => $exc->exception_date->format('Y-m-d'),
                    'is_working' => $exc->is_working,
                ];
            })
            ->toArray();

        return $exceptions;
    }
}
