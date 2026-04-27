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
use App\Http\Requests\Consultancy\UpdateConsultancyRequest;
use App\Http\Resources\Consultancy\ConsultancyResource;
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

        // Re-key SPA filter shape onto the field names the exporter
        // expects. Always force appointment_type=consultancy so this
        // endpoint can never bleed treatment rows.
        $consultancyTypeId = AppointmentType::Consultancy->value;

        $request->merge([
            'appointmenttype' => $consultancyTypeId,
            'filter_date_from' => $request->input('scheduled_date_from'),
            'filter_date_to' => $request->input('scheduled_date_to'),
            'filter_doctor_id' => $request->input('doctor_id'),
            'filter_location_id' => $request->input('location_id'),
            'filter_service_id' => $request->input('service_id'),
            'filter_appointment_status_id' => $request->input('appointment_status_id'),
            'filter_phone' => $request->input('phone'),
            'filter_created_from' => $request->input('created_date_from'),
            'filter_created_to' => $request->input('created_date_to'),
        ]);

        return Excel::download(new ExportConsultancies(10000, 0, $request), 'consultancies.xlsx');
    }
}
