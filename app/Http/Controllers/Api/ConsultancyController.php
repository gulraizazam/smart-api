<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentType;
use App\Exceptions\AppointmentException;
use App\Exports\ExportConsultancies;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\UpdateAppointmentStatusRequest;
use App\Http\Requests\Consultancy\ScheduleConsultancyRequest;
use App\Http\Requests\Consultancy\StoreConsultancyRequest;
use App\Helpers\ActivityLogRenderer;
use App\Http\Requests\Consultancy\UpdateConsultancyRequest;
use App\Http\Resources\Consultancy\ConsultancyResource;
use App\Models\Activity;
use App\Models\Appointments;
use App\Models\SMSTemplates;
use App\Services\Appointment\ConsultancyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
                // Unified patient search — SPA sends `q`; the service
                // delegates to PatientSearchService::applyPatientFilter
                // (the canonical classifier shared with Plans / Patients /
                // Treatments / Vouchers / Invoices listings).
                'q',
                'patient_id', 'phone', 'location_id', 'doctor_id', 'service_id',
                'service_parent_id',
                'appointment_status_id', 'scheduled_date_from', 'scheduled_date_to',
                'created_date_from', 'created_date_to', 'scheduled',
                'created_by', 'updated_by', 'rescheduled_by',
            ]);

            // Sort shape mirrors the legacy datatable pattern:
            //   ?sort[field]=created_at&sort[sort]=desc
            // Validated server-side against a column whitelist in the
            // service layer; unknown / unsafe values fall back to default.
            $filters['sort'] = $this->resolveSort($request);

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

    /**
     * GET /api/consultancy/{id}/activities
     *
     * Per-consultation lifecycle timeline. Reads activities scoped to
     * `appointment_id = $id`, runs each through ActivityLogRenderer so
     * the SPA receives the same `description_html` shape as the global
     * activity-logs report. The SPA panel parses the leading tag
     * (LEAD / APT / RESC / EDIT / RCVD / DEL …) into a coloured badge
     * via the shared `DescriptionWithTag` component.
     *
     * Account scope is enforced through the parent appointment lookup
     * (404s on a wrong-account id) — same guard the show / update /
     * status endpoints use. Centre ACL via `ACL::getUserCentres()`.
     */
    public function activities(int $id): JsonResponse
    {
        try {
            if (! Gate::allows('consultations_manage') && ! Gate::allows('appointments_view')) {
                throw AppointmentException::unauthorized();
            }

            // Reuse the same account + centre ACL guard the rest of the
            // controller relies on. Throws notFound() on a wrong-tenant
            // or wrong-centre id, so the activities endpoint can never
            // leak a different consultation's history.
            $appointment = $this->consultancyService->getConsultancyById($id);

            $activities = Activity::with('user:id,name')
                ->where('appointment_id', $appointment->id)
                ->where('account_id', Auth::user()->account_id)
                ->orderByDesc('created_at')
                ->limit(200)
                ->get();

            $rows = $activities->map(function (Activity $a): array {
                $description = ActivityLogRenderer::renderCompact($a)
                    ?? $a->description
                    ?? ActivityLogRenderer::render($a);

                $timestamp = $a->updated_at ?? $a->created_at;
                // Stored under config('app.timezone') (no DB-level UTC
                // conversion), so it's already local wall-clock time.
                // Parsing as UTC then converting double-applied the +5h
                // offset — see ActivityLogService for the full note.
                $localTs = $timestamp
                    ? Carbon::parse((string) $timestamp)
                    : null;

                return [
                    'id' => (int) $a->id,
                    'type' => $a->activity_type ?? $a->action ?? 'unknown',
                    'description' => $this->stripHtml((string) $description),
                    'description_html' => (string) $description,
                    'created_at' => $timestamp,
                    'time_formatted' => $localTs?->format('M j, Y g:i A'),
                    'time_short' => $localTs?->format('m-d-Y H:i'),
                    'actor' => $a->user ? [
                        'id' => (int) $a->user->id,
                        'name' => (string) $a->user->name,
                    ] : null,
                ];
            })->values()->all();

            return $this->successResponse('Consultation activities retrieved.', [
                'rows' => $rows,
                'count' => count($rows),
            ]);
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error fetching consultancy activities');
        }
    }

    /**
     * Strip HTML tags from a rendered description so the SPA has a clean
     * plain-text fallback (used when the badge-aware renderer can't
     * parse the leading tag — e.g. legacy un-tagged stored descriptions).
     */
    private function stripHtml(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
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

    /**
     * Resolve a safe `[field, direction]` sort tuple from the request.
     *
     * Wire format follows the legacy datatable: `sort[field]=...` +
     * `sort[sort]=asc|desc`. We don't reuse `getSortBy()` here because
     * we want a strict enum-style whitelist (not just regex) — joining
     * arbitrary columns into the query creates ambiguity once the
     * Eloquent eager-loaded relations land.
     *
     * @return array{field: string, direction: string}
     */
    private function resolveSort(Request $request): array
    {
        $allowed = [
            'name' => 'appointments.name',
            'scheduled_date' => 'appointments.scheduled_date',
            'created_at' => 'appointments.created_at',
            'updated_at' => 'appointments.updated_at',
            'appointment_status_id' => 'appointments.appointment_status_id',
            'location_id' => 'appointments.location_id',
            'doctor_id' => 'appointments.doctor_id',
            'service_id' => 'appointments.service_id',
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
     * WhatsApp prefill payload for a consultancy: cleaned phone (PK
     * country-coded) and a templated message with `#token#` placeholders
     * substituted. The SPA uses this to open `whatsapp://send?...` /
     * `https://web.whatsapp.com/send?...` client-side — there is no
     * server-side "send" because the actual delivery is the user's
     * WhatsApp app.
     *
     * Mirrors the legacy AppointmentCommunicationController@getWhatsAppData
     * shape so the SMSTemplates slug + token list stays a single source
     * of truth across the two surfaces.
     */
    public function whatsappData(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('consultations_manage') && !Gate::allows('appointments_view')) {
                throw AppointmentException::unauthorized();
            }

            $appointment = Appointments::with([
                'patient', 'doctor', 'location', 'service', 'appointment_status',
            ])->find($id);

            if (!$appointment) {
                return $this->errorResponse('Consultancy not found.', 404);
            }

            $rawPhone = $appointment->patient?->phone;
            if (!$rawPhone) {
                return $this->errorResponse('Customer WhatsApp number not found.', 422);
            }

            // PK-defaulting normalisation, lifted verbatim from the
            // legacy controller so a number formatted "0301-1234567" or
            // "3011234567" comes out as "923011234567".
            $whatsapp = preg_replace('/[^0-9]/', '', (string) $rawPhone);
            if (str_starts_with($whatsapp, '0')) {
                $whatsapp = '92' . substr($whatsapp, 1);
            } elseif (strlen($whatsapp) === 10 && !str_starts_with($whatsapp, '92')) {
                $whatsapp = '92' . $whatsapp;
            }

            $slug = ($appointment->appointment_type_id === AppointmentType::Treatment->value)
                ? 'treatment_whatsapp'
                : 'consultancy_whatsapp';
            $template = SMSTemplates::getBySlug($slug, Auth::user()->account_id);
            if (!$template) {
                return $this->errorResponse(
                    'WhatsApp template not configured. Create an SMS template with slug "' . $slug . '".',
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
                'patient_name' => $appointment->patient?->name ?? 'N/A',
                'appointment_time' => $appointmentTime,
                'patient_id' => (string) ($appointment->patient?->id ?? 'N/A'),
                'appointment_id' => (string) $appointment->id,
                'doctor_name' => $appointment->doctor?->name ?? 'N/A',
                'location_name' => $appointment->location?->name ?? 'N/A',
                'centre_google_map' => $appointment->location?->google_map ?? 'N/A',
                'service_name' => $appointment->service?->name ?? 'N/A',
                'scheduled_date' => $appointment->scheduled_date
                    ? $appointment->scheduled_date->format('Y-m-d')
                    : 'N/A',
                'scheduled_time' => $appointment->scheduled_time
                    ? (string) $appointment->scheduled_time
                    : 'N/A',
                'status' => $appointment->appointment_status?->name ?? 'N/A',
            ];

            $message = $template->content;
            foreach ($tokens as $key => $value) {
                // Both `#token#` and `##token##` are accepted in the
                // legacy templates.
                $message = str_replace('##' . $key . '##', $value, $message);
                $message = str_replace('#' . $key . '#', $value, $message);
            }

            return $this->successResponse('WhatsApp data retrieved.', [
                'whatsapp' => $whatsapp,
                'message' => $message,
            ]);
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error preparing WhatsApp data');
        }
    }

    /**
     * Stream an XLSX export of the filtered consultancy list. Filter
     * keys mirror the SPA's index filters; we then re-key them to the
     * legacy `filter_*` shape that ExportConsultancies@query reads.
     * Always scoped to consultancy appointment_type so no caller can
     * accidentally export treatment rows from this endpoint.
     */
    public function export(Request $request): BinaryFileResponse
    {
        if (!Gate::allows('consultations_manage') && !Gate::allows('appointments_export_all')
            && !Gate::allows('appointments_export_today') && !Gate::allows('appointments_export_this_month')) {
            abort(403, 'You are not authorised to export consultancies.');
        }

        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0');

        // Re-key SPA filter shape onto the *exact* field names the
        // exporter reads. The legacy DataTables admin used a heterogenous
        // `filter_*` naming (some `_id`-suffixed, some not, some
        // `_center_` instead of `_location_`); the exporter still expects
        // those — so anything we forget to map is silently dropped.
        // Always force appointment_type=consultancy so this endpoint
        // can never bleed treatment rows.
        $consultancyTypeId = AppointmentType::Consultancy->value;

        $request->merge([
            'appointmenttype' => $consultancyTypeId,
            // Scheduled-date window
            'filter_date_from' => $request->input('scheduled_date_from'),
            'filter_date_to' => $request->input('scheduled_date_to'),
            // Created-date window — note the legacy `_id` suffix
            'filter_created_from_id' => $request->input('created_date_from'),
            'filter_created_to_id' => $request->input('created_date_to'),
            // Foreign keys — note `filter_center_id` (NOT `filter_location_id`)
            // and `filter_status_id` (NOT `filter_appointment_status_id`)
            'filter_doctor_id' => $request->input('doctor_id'),
            'filter_center_id' => $request->input('location_id'),
            'filter_service_id' => $request->input('service_id'),
            'filter_status_id' => $request->input('appointment_status_id'),
            'filter_patient_id' => $request->input('patient_id'),
            // User filters
            'filter_created_by_id' => $request->input('created_by'),
            'filter_updated_by_id' => $request->input('updated_by'),
            'filter_rescheduled_by_id' => $request->input('rescheduled_by'),
            // Phone — the exporter handles its own normalisation
            'filter_phone' => $request->input('phone'),
        ]);

        return Excel::download(new ExportConsultancies(10000, 0, $request), 'consultancies.xlsx');
    }
}
