<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Patient\CustomFormFeedbackFillRequest;
use App\Http\Resources\Patient\PatientFormSubmissionResource;
use App\Services\CustomForm\CustomFormFeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/customformfeedbackspatient/fill
 *
 * Fills a custom form for a patient. Mirrors the shared
 * CustomFormFeedbackService::submitForm() used by the web flow
 * (Admin\CustomFormFeedbacksController::submit_form).
 */
class CustomFormFeedbackController extends Controller
{
    public function __construct(
        private readonly CustomFormFeedbackService $service,
    ) {}

    public function fill(CustomFormFeedbackFillRequest $request): JsonResponse
    {
        if (! Gate::allows('patients_customform_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $formId = (int) $request->validated('form_id');

            $result = DB::transaction(fn () => $this->service->submitForm(
                $request->all(),
                $formId,
            ));

            if (! ($result['success'] ?? false)) {
                return $this->errorResponse(
                    $result['message'] ?? 'Invalid request',
                    (int) ($result['code'] ?? 422),
                );
            }

            return $this->successResponse(
                'Form filled successfully.',
                new PatientFormSubmissionResource([
                    'form_id' => $formId,
                    'reference_id' => (int) $request->validated('reference_id'),
                ]),
                201,
            );
        } catch (\Throwable $e) {
            Log::error('Custom form feedback fill error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('Something went wrong, please try again later.', 500);
        }
    }
}
