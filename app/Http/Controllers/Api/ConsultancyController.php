<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\AppointmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\UpdateAppointmentStatusRequest;
use App\Http\Requests\Consultancy\ScheduleConsultancyRequest;
use App\Http\Requests\Consultancy\StoreConsultancyRequest;
use App\Http\Requests\Consultancy\UpdateConsultancyRequest;
use App\Http\Resources\Consultancy\ConsultancyResource;
use App\Services\Appointment\ConsultancyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConsultancyController extends Controller
{
    public function __construct(
        private readonly ConsultancyService $consultancyService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('consultations_manage')) {
                throw AppointmentException::unauthorized();
            }

            $filters = $request->only([
                'patient_id', 'phone', 'location_id', 'doctor_id',
                'appointment_status_id', 'scheduled_date_from', 'scheduled_date_to',
                'created_date_from', 'created_date_to', 'scheduled',
            ]);

            $query = $this->consultancyService->getConsultancyList($filters);

            if ($request->get('paginate') === 'false') {
                return $this->successResponse(
                    'Consultancies retrieved successfully.',
                    ConsultancyResource::collection($query->get()),
                );
            }

            $perPage = max(1, min((int) $request->get('per_page', 15), 100));
            $paginator = $query->paginate($perPage)->appends($request->query());

            return $this->paginatedResponse(
                'Consultancies retrieved successfully.',
                $paginator,
                ConsultancyResource::class,
            );
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error fetching consultancies');
        }
    }

    public function store(StoreConsultancyRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_consultancy')) {
                throw AppointmentException::unauthorized();
            }

            $consultancy = $this->consultancyService->createConsultancy($request->validated());

            return $this->successResponse(
                'Consultancy created successfully.',
                new ConsultancyResource($consultancy),
                201,
            );
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error creating consultancy');
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('consultations_manage') && !Gate::allows('appointments_view')) {
                throw AppointmentException::unauthorized();
            }

            $consultancy = $this->consultancyService->getConsultancyById($id);

            return $this->successResponse(
                'Consultancy retrieved successfully.',
                new ConsultancyResource($consultancy),
            );
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error fetching consultancy');
        }
    }

    public function update(UpdateConsultancyRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_consultancy')) {
                throw AppointmentException::unauthorized();
            }

            $consultancy = $this->consultancyService->updateConsultancy($id, $request->validated());

            return $this->successResponse(
                'Consultancy updated successfully.',
                new ConsultancyResource($consultancy),
            );
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error updating consultancy');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_consultancy')) {
                throw AppointmentException::unauthorized();
            }

            $this->consultancyService->deleteConsultancy($id);

            return $this->successResponse('Consultancy deleted successfully.');
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error deleting consultancy');
        }
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_manage') && !Gate::allows('appointments_status_update')) {
                throw AppointmentException::unauthorized();
            }

            $consultancy = $this->consultancyService->updateConsultancyStatus($id, $request->validated());

            return $this->successResponse(
                'Consultancy status updated successfully.',
                new ConsultancyResource($consultancy),
            );
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error updating consultancy status');
        }
    }

    public function schedule(ScheduleConsultancyRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_consultancy')) {
                throw AppointmentException::unauthorized();
            }

            $consultancy = $this->consultancyService->scheduleConsultancy($id, $request->toServiceData());

            return $this->successResponse(
                'Consultancy scheduled successfully.',
                new ConsultancyResource($consultancy),
            );
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error scheduling consultancy');
        }
    }

    public function scheduled(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('consultations_manage')) {
                throw AppointmentException::unauthorized();
            }

            $filters = $request->only([
                'location_id', 'doctor_id', 'appointment_status_id',
                'scheduled_date_from', 'scheduled_date_to',
            ]);

            $consultancies = $this->consultancyService->getScheduledConsultancies($filters);

            return $this->successResponse(
                'Scheduled consultancies retrieved successfully.',
                ConsultancyResource::collection($consultancies),
            );
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error fetching scheduled consultancies');
        }
    }

    public function nonScheduled(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('consultations_manage')) {
                throw AppointmentException::unauthorized();
            }

            $filters = $request->only(['location_id', 'doctor_id', 'appointment_status_id']);

            $consultancies = $this->consultancyService->getNonScheduledConsultancies($filters);

            return $this->successResponse(
                'Non-scheduled consultancies retrieved successfully.',
                ConsultancyResource::collection($consultancies),
            );
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error fetching non-scheduled consultancies');
        }
    }

    public function statistics(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('consultations_manage')) {
                throw AppointmentException::unauthorized();
            }

            $filters = $request->only([
                'location_id', 'doctor_id', 'appointment_status_id',
                'scheduled_date_from', 'scheduled_date_to',
            ]);

            $statistics = $this->consultancyService->getConsultancyStatistics($filters);

            return $this->successResponse(
                'Consultancy statistics retrieved successfully.',
                $statistics,
            );
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error fetching consultancy statistics');
        }
    }
}
