<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AppointmentException;
use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Services\Appointment\ConsultancyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ConsultancyController extends Controller
{
    protected $consultancyService;

    public function __construct(ConsultancyService $consultancyService)
    {
        $this->consultancyService = $consultancyService;
    }

    public function index(Request $request)
    {
        try {
            if (!Gate::allows('appointments_consultancy')) {
                throw AppointmentException::unauthorized();
            }

            $filters = $request->only([
                'patient_id', 'phone', 'location_id', 'doctor_id',
                'appointment_status_id', 'scheduled_date_from', 'scheduled_date_to',
                'created_date_from', 'created_date_to', 'scheduled'
            ]);

            $query = $this->consultancyService->getConsultancyList($filters);

            if ($request->has('paginate') && $request->paginate == 'false') {
                $consultancies = $query->get();
            } else {
                $perPage = $request->get('per_page', 15);
                $consultancies = $query->paginate($perPage);
            }

            return ApiHelper::apiResponse(200, 'Consultancies retrieved successfully.', $consultancies);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error fetching consultancies: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function store(StoreAppointmentRequest $request)
    {
        try {
            if (!Gate::allows('appointments_manage')) {
                throw AppointmentException::unauthorized();
            }

            $consultancy = $this->consultancyService->createConsultancy($request->validated());

            return ApiHelper::apiResponse(200, 'Consultancy created successfully.', $consultancy);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error creating consultancy: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function update(UpdateAppointmentRequest $request, $id)
    {
        try {
            if (!Gate::allows('appointments_manage')) {
                throw AppointmentException::unauthorized();
            }

            $consultancy = $this->consultancyService->updateConsultancy($id, $request->validated());

            return ApiHelper::apiResponse(200, 'Consultancy updated successfully.', $consultancy);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error updating consultancy: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function scheduled(Request $request)
    {
        try {
            if (!Gate::allows('appointments_consultancy')) {
                throw AppointmentException::unauthorized();
            }

            $filters = $request->only([
                'location_id', 'doctor_id', 'appointment_status_id',
                'scheduled_date_from', 'scheduled_date_to'
            ]);

            $consultancies = $this->consultancyService->getScheduledConsultancies($filters);

            return ApiHelper::apiResponse(200, 'Scheduled consultancies retrieved successfully.', $consultancies);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error fetching scheduled consultancies: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function nonScheduled(Request $request)
    {
        try {
            if (!Gate::allows('appointments_consultancy')) {
                throw AppointmentException::unauthorized();
            }

            $filters = $request->only([
                'location_id', 'doctor_id', 'appointment_status_id'
            ]);

            $consultancies = $this->consultancyService->getNonScheduledConsultancies($filters);

            return ApiHelper::apiResponse(200, 'Non-scheduled consultancies retrieved successfully.', $consultancies);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error fetching non-scheduled consultancies: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function statistics(Request $request)
    {
        try {
            if (!Gate::allows('appointments_consultancy')) {
                throw AppointmentException::unauthorized();
            }

            $filters = $request->only([
                'location_id', 'doctor_id', 'appointment_status_id',
                'scheduled_date_from', 'scheduled_date_to'
            ]);

            $statistics = $this->consultancyService->getConsultancyStatistics($filters);

            return ApiHelper::apiResponse(200, 'Consultancy statistics retrieved successfully.', $statistics);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error fetching consultancy statistics: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function destroy($id)
    {
        try {
            if (!Gate::allows('appointments_manage')) {
                throw AppointmentException::unauthorized();
            }

            $this->consultancyService->deleteConsultancy($id);

            return ApiHelper::apiResponse(200, 'Consultancy deleted successfully.', true);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error deleting consultancy: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function schedule(Request $request, $id)
    {
        try {
            if (!Gate::allows('appointments_manage')) {
                throw AppointmentException::unauthorized();
            }

            $data = [
                'start' => $request->start,
                'doctor_id' => $request->doctor_id,
                'location_id' => $request->location_id,
                'reschedule' => true,
            ];

            $consultancy = $this->consultancyService->scheduleConsultancy($id, $data);

            return ApiHelper::apiResponse(200, 'Consultancy scheduled successfully.', $consultancy);
        } catch (AppointmentException $e) {
            return ApiHelper::apiResponse($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error scheduling consultancy: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }
}
