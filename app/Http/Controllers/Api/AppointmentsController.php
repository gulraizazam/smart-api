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

            return response()->json([
                'status' => 1,
                'events' => $events,
                'rotas' => $doctor_rotas ? $doctor_rotas->toArray() : [],
                'start_time' => $start_time,
                'end_time' => $end_time,
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
}
