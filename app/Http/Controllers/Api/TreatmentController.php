<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AppointmentException;
use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Services\Appointment\TreatmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class TreatmentController extends Controller
{
    protected $treatmentService;

    public function __construct(TreatmentService $treatmentService)
    {
        $this->treatmentService = $treatmentService;
    }

    public function index(Request $request)
    {
        try {
            if (!Gate::allows('appointments_services')) {
                throw AppointmentException::unauthorized();
            }

            $filters = $request->only([
                'patient_id', 'phone', 'location_id', 'doctor_id', 'service_id',
                'appointment_status_id', 'scheduled_date_from', 'scheduled_date_to',
                'created_date_from', 'created_date_to', 'scheduled'
            ]);

            $query = $this->treatmentService->getTreatmentList($filters);

            if ($request->has('paginate') && $request->paginate == 'false') {
                $treatments = $query->get();
            } else {
                $perPage = $request->get('per_page', 15);
                $treatments = $query->paginate($perPage);
            }

            return ApiHelper::apiResponse(200, 'Treatments retrieved successfully.', $treatments);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error fetching treatments: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function store(StoreAppointmentRequest $request)
    {
        try {
            if (!Gate::allows('appointments_manage')) {
                throw AppointmentException::unauthorized();
            }

            $treatment = $this->treatmentService->createTreatment($request->validated());

            return ApiHelper::apiResponse(200, 'Treatment created successfully.', $treatment);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error creating treatment: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function update(UpdateAppointmentRequest $request, $id)
    {
        try {
            if (!Gate::allows('appointments_manage')) {
                throw AppointmentException::unauthorized();
            }

            $updateService = new \App\Services\Appointment\TreatmentUpdateService();
            $treatment = $updateService->updateTreatment($id, $request->validated());

            return ApiHelper::apiResponse(200, 'Treatment updated successfully.', true, $treatment);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error updating treatment: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function scheduled(Request $request)
    {
        try {
            if (!Gate::allows('appointments_services')) {
                throw AppointmentException::unauthorized();
            }

            $filters = $request->only([
                'location_id', 'doctor_id', 'service_id', 'appointment_status_id',
                'scheduled_date_from', 'scheduled_date_to'
            ]);

            $treatments = $this->treatmentService->getScheduledTreatments($filters);

            return ApiHelper::apiResponse(200, 'Scheduled treatments retrieved successfully.', $treatments);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error fetching scheduled treatments: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function nonScheduled(Request $request)
    {
        try {
            if (!Gate::allows('appointments_services')) {
                throw AppointmentException::unauthorized();
            }

            $filters = $request->only([
                'location_id', 'doctor_id', 'service_id', 'appointment_status_id'
            ]);

            $treatments = $this->treatmentService->getNonScheduledTreatments($filters);

            return ApiHelper::apiResponse(200, 'Non-scheduled treatments retrieved successfully.', $treatments);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error fetching non-scheduled treatments: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function statistics(Request $request)
    {
        try {
            if (!Gate::allows('appointments_services')) {
                throw AppointmentException::unauthorized();
            }

            $filters = $request->only([
                'location_id', 'doctor_id', 'service_id', 'appointment_status_id',
                'scheduled_date_from', 'scheduled_date_to'
            ]);

            $statistics = $this->treatmentService->getTreatmentStatistics($filters);

            return ApiHelper::apiResponse(200, 'Treatment statistics retrieved successfully.', $statistics);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error fetching treatment statistics: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function availableResources(Request $request)
    {
        try {
            if (!Gate::allows('appointments_services')) {
                throw AppointmentException::unauthorized();
            }

            $request->validate([
                'location_id' => 'required|exists:locations,id',
                'service_id' => 'nullable|exists:services,id'
            ]);

            $resources = $this->treatmentService->getAvailableResources(
                $request->location_id,
                $request->service_id
            );

            return ApiHelper::apiResponse(200, 'Available resources retrieved successfully.', $resources);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error fetching available resources: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function servicesByLocation(Request $request)
    {
        try {
            if (!Gate::allows('appointments_services')) {
                throw AppointmentException::unauthorized();
            }

            $request->validate([
                'location_id' => 'required|exists:locations,id'
            ]);

            $services = $this->treatmentService->getServicesByLocation($request->location_id);

            return ApiHelper::apiResponse(200, 'Services retrieved successfully.', $services);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error fetching services by location: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }
}
