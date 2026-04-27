<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentType;
use App\Exceptions\AppointmentException;
use App\Exceptions\TreatmentException;
use App\Exports\ExportConsultancies;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\UpdateAppointmentStatusRequest;
use App\Http\Requests\Consultancy\ScheduleConsultancyRequest;
use App\Http\Requests\Treatment\AvailableResourcesRequest;
use App\Http\Requests\Treatment\CheckPatientLastTreatmentRequest;
use App\Http\Requests\Treatment\RescheduleTreatmentRequest;
use App\Http\Requests\Treatment\StoreTreatmentRequest;
use App\Http\Requests\Treatment\UpdateTreatmentRequest;
use App\Http\Resources\Treatment\TreatmentEditResource;
use App\Http\Resources\Treatment\TreatmentResource;
use App\Models\Appointments;
use App\Models\SMSTemplates;
use App\Services\Appointment\AppointmentService;
use App\Services\Appointment\TreatmentUpdateService;
use App\Services\Treatment\TreatmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        private readonly AppointmentService $appointmentService,
    ) {}

    // ──────────────────────────────────────────────────
    //  Datatable (POST /api/treatments/datatable)
    // ──────────────────────────────────────────────────

    public function datatable(Request $request, ?int $patientId = null): JsonResponse
    {
        try {
            $patientId = $patientId ?: $request->integer('patient_id') ?: null;

            $data = $this->treatmentService->getDatatableData($request->all(), $patientId);

            return response()->json($data);
        } catch (TreatmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode(), $e->getErrorData());
        } catch (\Exception $e) {
            return $this->handleException($e, 'TreatmentController');
        }
    }

    // ──────────────────────────────────────────────────
    //  Store (POST /api/treatments/store)
    // ──────────────────────────────────────────────────

    public function store(StoreTreatmentRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('treatments_services')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $result = $this->treatmentService->store($request->validated());

            return $this->successResponse($result['message'], ['id' => $result['id']]);
        } catch (TreatmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->handleException($e, 'TreatmentController');
        }
    }

    // ──────────────────────────────────────────────────
    //  Update (PUT /api/treatment/{id})
    // ──────────────────────────────────────────────────

    public function update(UpdateTreatmentRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('treatments_services')) {
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
            return $this->handleException($e, 'TreatmentController');
        }
    }

    // ──────────────────────────────────────────────────
    //  Drag-Drop Reschedule (POST /api/treatments/drag-drop-reschedule)
    // ──────────────────────────────────────────────────

    public function dragDropReschedule(RescheduleTreatmentRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('treatments_services')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $result = $this->treatmentService->dragDropReschedule($request->validated());

            return $this->successResponse($result['message'], ['id' => $result['id']]);
        } catch (TreatmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->handleException($e, 'TreatmentController');
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
            if (!Gate::allows('treatments_services')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $data = $this->treatmentService->getEditData($id);

            return $this->successResponse('Data found.', new TreatmentEditResource($data));
        } catch (TreatmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->handleException($e, 'TreatmentController');
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
            return $this->handleException($e, 'TreatmentController');
        }
    }

    // ──────────────────────────────────────────────────
    //  Secondary API — List (GET /api/treatment/)
    // ──────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('treatments_manage')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $filters = $request->only([
                'patient_id', 'phone', 'location_id', 'doctor_id', 'service_id',
                'appointment_status_id', 'scheduled_date_from', 'scheduled_date_to',
                'created_date_from', 'created_date_to', 'scheduled',
                'created_by', 'updated_by', 'rescheduled_by',
            ]);

            // Sort wire format mirrors consultancy: ?sort[field]=...&sort[sort]=...
            // Whitelist enforced server-side; unknown fields fall back to default.
            $filters['sort'] = $this->resolveSort($request);

            $query = $this->treatmentService->getTreatmentList($filters);

            if ($request->input('paginate') === 'false') {
                return response()->json([
                    'success' => true,
                    'message' => 'Treatments retrieved successfully.',
                    'data'    => TreatmentResource::collection($query->get()),
                ]);
            }

            $perPage = max(1, min((int) $request->get('per_page', 15), 100));
            $paginator = $query->paginate($perPage)->appends($request->query());

            // Hand-rolled pagination envelope so the SPA's Paginated<T>
            // shape (data + meta + links siblings) lines up with what
            // its consultations module already expects.
            return response()->json([
                'success' => true,
                'message' => 'Treatments retrieved successfully.',
                'data'    => TreatmentResource::collection($paginator->items()),
                'meta'    => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                    'from'         => $paginator->firstItem(),
                    'to'           => $paginator->lastItem(),
                ],
                'links'   => [
                    'first' => $paginator->url(1),
                    'last'  => $paginator->url($paginator->lastPage()),
                    'prev'  => $paginator->previousPageUrl(),
                    'next'  => $paginator->nextPageUrl(),
                ],
            ]);
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            Log::error('Error fetching treatments: ' . $e->getMessage());
            return $this->handleException($e, 'TreatmentController');
        }
    }

    // ──────────────────────────────────────────────────
    //  Show / Destroy / Status / Schedule / WhatsApp / Export
    //  (mirror of the consultancy controller's methods so the SPA
    //  can drive the same dialogs against treatments)
    // ──────────────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('treatments_manage') && !Gate::allows('appointments_view')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $treatment = $this->requireTreatment($id);

            return $this->successResponse(
                'Treatment retrieved successfully.',
                new TreatmentResource($treatment),
            );
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'TreatmentController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('treatments_services')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $this->requireTreatment($id);
            $this->appointmentService->deleteAppointment($id);

            return $this->successResponse('Treatment deleted successfully.');
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'TreatmentController');
        }
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('appointments_manage') && !Gate::allows('appointments_status_update')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $this->requireTreatment($id);
            $treatment = $this->appointmentService->updateAppointmentStatus($id, $request->validated());

            return $this->successResponse(
                'Treatment status updated successfully.',
                new TreatmentResource($treatment),
            );
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'TreatmentController');
        }
    }

    public function schedule(ScheduleConsultancyRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('treatments_services')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $this->requireTreatment($id);
            $treatment = $this->appointmentService->scheduleAppointment($id, $request->toServiceData());

            return $this->successResponse(
                'Treatment scheduled successfully.',
                new TreatmentResource($treatment),
            );
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'TreatmentController');
        }
    }

    public function whatsappData(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('treatments_manage') && !Gate::allows('appointments_view')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $appointment = Appointments::with([
                'patient', 'doctor', 'location', 'service', 'appointment_status',
            ])->find($id);

            if (!$appointment || $appointment->appointment_type_id !== AppointmentType::Treatment->value) {
                return $this->errorResponse('Treatment not found.', 404);
            }

            $rawPhone = $appointment->patient?->phone;
            if (!$rawPhone) {
                return $this->errorResponse('Customer WhatsApp number not found.', 422);
            }

            // Same PK normalisation as the consultancy endpoint.
            $whatsapp = preg_replace('/[^0-9]/', '', (string) $rawPhone);
            if (str_starts_with($whatsapp, '0')) {
                $whatsapp = '92' . substr($whatsapp, 1);
            } elseif (strlen($whatsapp) === 10 && !str_starts_with($whatsapp, '92')) {
                $whatsapp = '92' . $whatsapp;
            }

            $template = SMSTemplates::getBySlug('treatment_whatsapp', Auth::user()->account_id);
            if (!$template) {
                return $this->errorResponse(
                    'WhatsApp template not configured. Create an SMS template with slug "treatment_whatsapp".',
                    422,
                );
            }

            $appointmentTime = 'N/A';
            if ($appointment->scheduled_date && $appointment->scheduled_time) {
                try {
                    $appointmentTime = Carbon::parse($appointment->scheduled_time)->format('h:i A');
                } catch (\Throwable $e) {
                    $appointmentTime = (string) $appointment->scheduled_time;
                }
            }

            $tokens = [
                'patient_name'      => $appointment->patient?->name ?? 'N/A',
                'appointment_time'  => $appointmentTime,
                'patient_id'        => (string) ($appointment->patient?->id ?? 'N/A'),
                'appointment_id'    => (string) $appointment->id,
                'doctor_name'       => $appointment->doctor?->name ?? 'N/A',
                'location_name'     => $appointment->location?->name ?? 'N/A',
                'centre_google_map' => $appointment->location?->google_map ?? 'N/A',
                'service_name'      => $appointment->service?->name ?? 'N/A',
                'scheduled_date'    => $appointment->scheduled_date
                    ? $appointment->scheduled_date->format('Y-m-d')
                    : 'N/A',
                'scheduled_time'    => $appointment->scheduled_time
                    ? (string) $appointment->scheduled_time
                    : 'N/A',
                'status'            => $appointment->appointment_status?->name ?? 'N/A',
            ];

            $message = $template->content;
            foreach ($tokens as $key => $value) {
                $message = str_replace('##' . $key . '##', $value, $message);
                $message = str_replace('#' . $key . '#', $value, $message);
            }

            return $this->successResponse('WhatsApp data retrieved.', [
                'whatsapp' => $whatsapp,
                'message'  => $message,
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'TreatmentController');
        }
    }

    public function export(Request $request): BinaryFileResponse
    {
        if (!Gate::allows('treatments_manage') && !Gate::allows('appointments_export_all')
            && !Gate::allows('appointments_export_today') && !Gate::allows('appointments_export_this_month')) {
            abort(403, 'You are not authorised to export treatments.');
        }

        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0');

        // Force appointment_type=treatment so this endpoint can never
        // bleed consultancy rows. The legacy ExportConsultancies
        // exporter is type-agnostic — appointment_type filter selects
        // which rows it actually emits.
        $treatmentTypeId = AppointmentType::Treatment->value;

        $request->merge([
            'appointmenttype'              => $treatmentTypeId,
            'filter_date_from'             => $request->input('scheduled_date_from'),
            'filter_date_to'               => $request->input('scheduled_date_to'),
            'filter_created_from_id'       => $request->input('created_date_from'),
            'filter_created_to_id'         => $request->input('created_date_to'),
            'filter_doctor_id'             => $request->input('doctor_id'),
            'filter_center_id'             => $request->input('location_id'),
            'filter_service_id'            => $request->input('service_id'),
            'filter_status_id'             => $request->input('appointment_status_id'),
            'filter_patient_id'            => $request->input('patient_id'),
            'filter_created_by_id'         => $request->input('created_by'),
            'filter_updated_by_id'         => $request->input('updated_by'),
            'filter_rescheduled_by_id'     => $request->input('rescheduled_by'),
            'filter_phone'                 => $request->input('phone'),
        ]);

        return Excel::download(new ExportConsultancies(10000, 0, $request), 'treatments.xlsx');
    }

    /**
     * Resolve a safe sort tuple from the request — matches the
     * consultancy controller's resolveSort().
     *
     * @return array{field: string, direction: string}
     */
    private function resolveSort(Request $request): array
    {
        $allowed = [
            'name'                  => 'appointments.name',
            'scheduled_date'        => 'appointments.scheduled_date',
            'created_at'            => 'appointments.created_at',
            'updated_at'            => 'appointments.updated_at',
            'appointment_status_id' => 'appointments.appointment_status_id',
            'location_id'           => 'appointments.location_id',
            'doctor_id'             => 'appointments.doctor_id',
            'service_id'            => 'appointments.service_id',
        ];

        $field = $request->input('sort.field');
        $direction = strtolower((string) $request->input('sort.sort', 'desc'));

        if (!is_string($field) || !array_key_exists($field, $allowed)) {
            return ['field' => 'appointments.created_at', 'direction' => 'desc'];
        }

        if ($direction !== 'asc' && $direction !== 'desc') {
            $direction = 'desc';
        }

        return ['field' => $allowed[$field], 'direction' => $direction];
    }

    /**
     * Load a row by id and 404 if it isn't a treatment. Centralises
     * the type-guard so the various write actions can't accidentally
     * mutate a consultancy via a treatment endpoint.
     */
    private function requireTreatment(int $id): Appointments
    {
        $appointment = $this->appointmentService->getAppointmentById($id);

        if (!$appointment || $appointment->appointment_type_id !== AppointmentType::Treatment->value) {
            throw AppointmentException::notFound();
        }

        return $appointment;
    }

    // ──────────────────────────────────────────────────
    //  Scheduled / Non-Scheduled / Statistics
    // ──────────────────────────────────────────────────

    public function scheduled(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('treatments_manage')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $filters    = $request->only(['location_id', 'doctor_id', 'service_id', 'appointment_status_id', 'scheduled_date_from', 'scheduled_date_to']);
            $treatments = $this->treatmentService->getScheduledTreatments($filters);

            return $this->successResponse('Scheduled treatments retrieved successfully.', $treatments);
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            Log::error('Error fetching scheduled treatments: ' . $e->getMessage());
            return $this->handleException($e, 'TreatmentController');
        }
    }

    public function nonScheduled(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('treatments_manage')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $filters    = $request->only(['location_id', 'doctor_id', 'service_id', 'appointment_status_id']);
            $treatments = $this->treatmentService->getNonScheduledTreatments($filters);

            return $this->successResponse('Non-scheduled treatments retrieved successfully.', $treatments);
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            Log::error('Error fetching non-scheduled treatments: ' . $e->getMessage());
            return $this->handleException($e, 'TreatmentController');
        }
    }

    public function statistics(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('treatments_manage')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $filters    = $request->only(['location_id', 'doctor_id', 'service_id', 'appointment_status_id', 'scheduled_date_from', 'scheduled_date_to']);
            $statistics = $this->treatmentService->getTreatmentStatistics($filters);

            return $this->successResponse('Treatment statistics retrieved successfully.', $statistics);
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            Log::error('Error fetching treatment statistics: ' . $e->getMessage());
            return $this->handleException($e, 'TreatmentController');
        }
    }

    // ──────────────────────────────────────────────────
    //  Resources & Services
    // ──────────────────────────────────────────────────

    public function availableResources(AvailableResourcesRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('treatments_manage')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $resources = $this->treatmentService->getAvailableResources(
                $request->integer('location_id'),
                $request->integer('service_id') ?: null,
            );

            return $this->successResponse('Available resources retrieved successfully.', $resources);
        } catch (\Exception $e) {
            Log::error('Error fetching available resources: ' . $e->getMessage());
            return $this->handleException($e, 'TreatmentController');
        }
    }

    public function servicesByLocation(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('treatments_manage')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $request->validate(['location_id' => 'required|exists:locations,id']);

            $services = $this->treatmentService->getServicesByLocation(
                $request->integer('location_id'),
            );

            return $this->successResponse('Services retrieved successfully.', $services);
        } catch (\Exception $e) {
            Log::error('Error fetching services by location: ' . $e->getMessage());
            return $this->handleException($e, 'TreatmentController');
        }
    }

    // ──────────────────────────────────────────────────
    //  Standardized response helpers
    // ──────────────────────────────────────────────────

    protected function successResponse(string $message, mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    protected function errorResponse(string $message, int $code = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], $code);
    }
}
