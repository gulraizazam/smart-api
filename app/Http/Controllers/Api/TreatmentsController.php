<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\TreatmentException;
use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Treatment\TreatmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TreatmentsController extends Controller
{
    protected TreatmentService $treatmentService;

    public function __construct(TreatmentService $treatmentService)
    {
        $this->treatmentService = $treatmentService;
    }

    /**
     * Get treatment datatable data
     * Supports optional patient_id parameter for patient-specific filtering
     */
    public function datatable(Request $request, $patientId = null): JsonResponse
    {
        try {
            // if (!Gate::allows('treatments_manage')) {
            //     return ApiHelper::apiResponse(403, 'Unauthorized access', false);
            // }

            // Also check for patient_id in query string (for patient card context)
            if (!$patientId && $request->has('patient_id')) {
                $patientId = $request->input('patient_id');
            }

            // Pass patient_id to service if provided (patient card context)
            $data = $this->treatmentService->getDatatableData($request, $patientId);

            return ApiHelper::apiDataTable($data);
        } catch (TreatmentException $e) {
            return ApiHelper::apiResponse($e->getStatusCode(), $e->getMessage(), false, $e->getErrorData());
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Store a new treatment appointment
     */
    public function store(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_manage')) {
                return ApiHelper::apiResponse(403, 'You are not authorized to access this resource.', false);
            }

            $result = $this->treatmentService->store($request);

            return ApiHelper::apiResponse(200, $result['message'], $result['success'], [
                'id' => $result['id']
            ]);
        } catch (TreatmentException $e) {
            return ApiHelper::apiResponse(200, $e->getMessage(), false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Check patient's last treatment for continuity of care
     */
    public function checkPatientLastTreatment(Request $request): JsonResponse
    {
        try {
            $data = $this->treatmentService->checkPatientLastTreatment($request);

            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error checking patient treatment history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Drag and drop reschedule treatment
     */
    public function dragDropReschedule(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_manage')) {
                return ApiHelper::apiResponse(403, 'You are not authorized to access this resource.', false);
            }

            $result = $this->treatmentService->dragDropReschedule($request);

            return ApiHelper::apiResponse(200, $result['message'], $result['success'], [
                'id' => $result['id']
            ]);
        } catch (TreatmentException $e) {
            return ApiHelper::apiResponse(200, $e->getMessage(), false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Clear treatment caches
     */
    public function clearCache(): JsonResponse
    {
        try {
            $this->treatmentService->clearCache();

            return ApiHelper::apiResponse(200, 'Cache cleared successfully', true);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get treatment data for edit modal (optimized)
     */
    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_manage')) {
                return ApiHelper::apiResponse(403, 'You are not authorized to access this resource.', false);
            }

            $data = $this->treatmentService->getEditData($id);

            return ApiHelper::apiResponse(200, 'Data found.', true, $data);
        } catch (TreatmentException $e) {
            return ApiHelper::apiResponse(200, $e->getMessage(), false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
