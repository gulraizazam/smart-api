<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Patient\MedicalHistoryFillRequest;
use App\Http\Resources\Patient\PatientFormSubmissionResource;
use App\Services\Appointment\AppointmentMedicalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/medicalhistoryform/{id}
 *
 * {id} is the custom-form template id being filled (same semantics as the
 * web submit route /admin/appointmentsmedical/{form_id}/{appointment_id}).
 * The appointment_id + patient reference_id travel in the request body.
 *
 * ALLURA-REVIEW: the spec says {id} maps to the web
 * GET /admin/medicalhistoryform/{id} which there identifies the patient.
 * For a POST "fill" the required key is the form template id — that is
 * what the corresponding web submit uses. Confirm with product that the
 * API caller will pass the form_id as the URL segment.
 */
class MedicalHistoryController extends Controller
{
    public function __construct(
        private readonly AppointmentMedicalService $service,
    ) {}

    public function fill(MedicalHistoryFillRequest $request, int $id): JsonResponse
    {
        if (! Gate::allows('appointments_medical_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $appointmentId = (int) $request->validated('appointment_id');

            $result = DB::transaction(fn () => $this->service->submitForm(
                $request,
                $id,
                $appointmentId,
            ));

            if (! ($result['success'] ?? false)) {
                return $this->errorResponse(
                    $result['message'] ?? 'Invalid request',
                    (int) ($result['code'] ?? 422),
                );
            }

            return $this->successResponse(
                $result['message'] ?? 'Form submitted successfully.',
                new PatientFormSubmissionResource([
                    'form_id' => $id,
                    'reference_id' => (int) $request->validated('reference_id'),
                    'appointment_id' => $appointmentId,
                ]),
                201,
            );
        } catch (\Throwable $e) {
            Log::error('Medical history form fill error', [
                'message' => $e->getMessage(),
                'form_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('Something went wrong, please try again later.', 500);
        }
    }
}
