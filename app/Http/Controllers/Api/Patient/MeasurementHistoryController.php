<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Patient\MeasurementHistoryFillRequest;
use App\Http\Resources\Patient\PatientFormSubmissionResource;
use App\Services\Appointment\AppointmentMeasurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/measurementhistoryform/{id}
 *
 * {id} is the custom-form template id being filled (same semantics as the
 * web submit route /admin/appointmentsmeasurement/{form_id}/{appointment_id}).
 *
 * ALLURA-REVIEW: see MedicalHistoryController — same {id} semantics.
 */
class MeasurementHistoryController extends Controller
{
    public function __construct(
        private readonly AppointmentMeasurementService $service,
    ) {}

    public function fill(MeasurementHistoryFillRequest $request, int $id): JsonResponse
    {
        if (! Gate::allows('appointments_measurement_create')) {
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
            Log::error('Measurement history form fill error', [
                'message' => $e->getMessage(),
                'form_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('Something went wrong, please try again later.', 500);
        }
    }
}
