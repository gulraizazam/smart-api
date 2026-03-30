<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\AppointmentException;
use App\Exceptions\TreatmentException;
use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Treatment\AvailableResourcesRequest;
use App\Http\Requests\Treatment\CheckPatientLastTreatmentRequest;
use App\Http\Requests\Treatment\RescheduleTreatmentRequest;
use App\Http\Requests\Treatment\StoreTreatmentRequest;
use App\Http\Requests\Treatment\UpdateTreatmentRequest;
use App\Http\Resources\Treatment\TreatmentEditResource;
use App\Http\Resources\Treatment\TreatmentResource;
use App\Services\Appointment\TreatmentUpdateService;
use App\Services\Treatment\TreatmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Unified Treatment API Controller.
 *
 * Handles all treatment-related endpoints for both /api/treatments/* and /api/treatment/* routes.
 * Keeps controller thin: request → service → response.
 */
final class TreatmentController extends Controller
{
    public function __construct(
        private readonly TreatmentService $treatmentService,
        private readonly TreatmentUpdateService $updateService,
    ) {}

    // ──────────────────────────────────────────────────
    //  Datatable (POST /api/treatments/datatable)
    // ──────────────────────────────────────────────────

    public function datatable(Request $request, ?int $patientId = null): JsonResponse
    {
        try {
            $patientId = $patientId ?: $request->integer('patient_id') ?: null;

            $data = $this->treatmentService->getDatatableData($request->all(), $patientId);

            return ApiHelper::apiDataTable($data);
        } catch (TreatmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode(), $e->getErrorData());
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    // ──────────────────────────────────────────────────
    //  Store (POST /api/treatments/store)
    // ──────────────────────────────────────────────────

    public function store(StoreTreatmentRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $result = $this->treatmentService->store($request->validated());

            return $this->successResponse($result['message'], ['id' => $result['id']]);
        } catch (TreatmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    // ──────────────────────────────────────────────────
    //  Update (PUT /api/treatment/{id})
    // ──────────────────────────────────────────────────

    public function update(UpdateTreatmentRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $treatment = $this->updateService->updateTreatment($id, $request->validated());

            return $this->successResponse(
                'Treatment updated successfully.',
                new TreatmentResource($treatment),
            );
        } catch (TreatmentException|AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            Log::error('Error updating treatment: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    // ──────────────────────────────────────────────────
    //  Drag-Drop Reschedule (POST /api/treatments/drag-drop-reschedule)
    // ──────────────────────────────────────────────────

    public function dragDropReschedule(RescheduleTreatmentRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $result = $this->treatmentService->dragDropReschedule($request->validated());

            return $this->successResponse($result['message'], ['id' => $result['id']]);
        } catch (TreatmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    // ──────────────────────────────────────────────────
    //  Check Patient Last Treatment (GET /api/treatments/check-patient-last-treatment)
    // ──────────────────────────────────────────────────

    public function checkPatientLastTreatment(CheckPatientLastTreatmentRequest $request): JsonResponse
    {
        try {
            $data = $this->treatmentService->checkPatientLastTreatment($request->validated());

            return $this->successResponse('Patient treatment history retrieved.', $data);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error checking patient treatment history.',
                500,
            );
        }
    }

    // ──────────────────────────────────────────────────
    //  Edit Data (GET /api/treatments/{id}/edit)
    // ──────────────────────────────────────────────────

    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $data = $this->treatmentService->getEditData($id);

            return $this->successResponse('Data found.', new TreatmentEditResource($data));
        } catch (TreatmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    // ──────────────────────────────────────────────────
    //  Clear Cache (POST /api/treatments/clear-cache)
    // ──────────────────────────────────────────────────

    public function clearCache(): JsonResponse
    {
        try {
            $this->treatmentService->clearCache();

            return $this->successResponse('Cache cleared successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    // ──────────────────────────────────────────────────
    //  Secondary API — List (GET /api/treatment/)
    // ──────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_services')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $filters = $request->only([
                'patient_id', 'phone', 'location_id', 'doctor_id', 'service_id',
                'appointment_status_id', 'scheduled_date_from', 'scheduled_date_to',
                'created_date_from', 'created_date_to', 'scheduled',
            ]);

            $query = $this->treatmentService->getTreatmentList($filters);

            $treatments = ($request->input('paginate') === 'false')
                ? $query->get()
                : $query->paginate($request->integer('per_page', 15));

            return $this->successResponse('Treatments retrieved successfully.', $treatments);
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            Log::error('Error fetching treatments: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    // ──────────────────────────────────────────────────
    //  Scheduled / Non-Scheduled / Statistics
    // ──────────────────────────────────────────────────

    public function scheduled(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_services')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $filters    = $request->only(['location_id', 'doctor_id', 'service_id', 'appointment_status_id', 'scheduled_date_from', 'scheduled_date_to']);
            $treatments = $this->treatmentService->getScheduledTreatments($filters);

            return $this->successResponse('Scheduled treatments retrieved successfully.', $treatments);
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            Log::error('Error fetching scheduled treatments: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function nonScheduled(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_services')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $filters    = $request->only(['location_id', 'doctor_id', 'service_id', 'appointment_status_id']);
            $treatments = $this->treatmentService->getNonScheduledTreatments($filters);

            return $this->successResponse('Non-scheduled treatments retrieved successfully.', $treatments);
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            Log::error('Error fetching non-scheduled treatments: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function statistics(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_services')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $filters    = $request->only(['location_id', 'doctor_id', 'service_id', 'appointment_status_id', 'scheduled_date_from', 'scheduled_date_to']);
            $statistics = $this->treatmentService->getTreatmentStatistics($filters);

            return $this->successResponse('Treatment statistics retrieved successfully.', $statistics);
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            Log::error('Error fetching treatment statistics: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    // ──────────────────────────────────────────────────
    //  Resources & Services
    // ──────────────────────────────────────────────────

    public function availableResources(AvailableResourcesRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_services')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $resources = $this->treatmentService->getAvailableResources(
                $request->integer('location_id'),
                $request->integer('service_id') ?: null,
            );

            return $this->successResponse('Available resources retrieved successfully.', $resources);
        } catch (\Exception $e) {
            Log::error('Error fetching available resources: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    public function servicesByLocation(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_services')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $request->validate(['location_id' => 'required|exists:locations,id']);

            $services = $this->treatmentService->getServicesByLocation(
                $request->integer('location_id'),
            );

            return $this->successResponse('Services retrieved successfully.', $services);
        } catch (\Exception $e) {
            Log::error('Error fetching services by location: ' . $e->getMessage());
            return ApiHelper::apiException($e);
        }
    }

    // ──────────────────────────────────────────────────
    //  Standardized response helpers
    // ──────────────────────────────────────────────────

    private function successResponse(string $message, mixed $data = null): JsonResponse
    {
        return response()->json([
            'status'  => true,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    private function errorResponse(string $message, int $code = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], $code);
    }
}
