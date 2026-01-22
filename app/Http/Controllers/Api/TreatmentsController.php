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
     */
    public function datatable(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_services')) {
                return ApiHelper::apiResponse(403, 'Unauthorized access', false);
            }

            $data = $this->treatmentService->getDatatableData($request);

            return ApiHelper::apiDataTable($data);
        } catch (TreatmentException $e) {
            return ApiHelper::apiResponse($e->getStatusCode(), $e->getMessage(), false, $e->getErrorData());
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
}
