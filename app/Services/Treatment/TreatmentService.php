<?php

declare(strict_types=1);

namespace App\Services\Treatment;

use App\Exceptions\TreatmentException;
use App\Helpers\ACL;
use App\Helpers\ActivityLogger;
use App\Helpers\DoctorDashboardHelper;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Http\Resources\Treatment\TreatmentDatatableResource;
use App\Jobs\IndexSingleAppointmentJob;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\Cities;
use App\Models\Doctors;
use App\Models\Invoices;
use App\Models\InvoiceStatuses;
use App\Models\Leads;
use App\Models\LeadsServices;
use App\Models\LeadStatuses;
use App\Models\Locations;
use App\Models\MachineTypeHasServices;
use App\Models\Patients;
use App\Models\Regions;
use App\Models\ResourceHasRota;
use App\Models\ResourceHasRotaDays;
use App\Models\Resources;
use App\Models\Services;
use App\Models\User;
use App\Services\Appointment\AppointmentService;
use App\Services\Phone\PhoneFormattingService;
use App\Services\PatientManagement\PatientSearchService;
use App\Services\Reports\Concerns\ParsesDateRange;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class TreatmentService
{
    use ParsesDateRange;

    private const CACHE_TTL = 3600;

    private const FILTER_KEY = 'treatments';

    // ──────────────────────────────────────────────────
    //  Datatable
    // ──────────────────────────────────────────────────

    /**
     * Build the full datatable payload (data + meta + filters + permissions).
     *
     * @return array{data: array, meta: array, active_filters: mixed, filter_values: array, permissions: array}
     */
    public function getDatatableData(array $requestAll, ?int $patientId = null): array
    {
        $filters = $this->processFilters($requestAll);
        $orderBy = $filters['order_by'];
        $order = $filters['order'];

        $treatmentTypeId = $this->getTreatmentTypeId();
        $baseConditions = $this->buildBaseConditions($filters, $patientId);
        $totalRecords = $this->getRecordsCount($baseConditions, $filters, $treatmentTypeId);

        [$perPage, $offset, $pages, $page] = getPaginationElement(
            request(),
            $totalRecords,
        );

        $appointments = $this->getAppointments(
            $baseConditions,
            $filters,
            $treatmentTypeId,
            $orderBy,
            $order,
            $perPage,
            $offset,
        );

        $lookupData = $this->getLookupData();
        $data = $this->transformAppointments($appointments, $lookupData);

        return [
            'data' => $data,
            'meta' => [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $perPage,
                'total' => $totalRecords,
                'sort' => $order,
            ],
            'active_filters' => Filters::all(Auth::id(), self::FILTER_KEY),
            'filter_values' => $this->getFilterValues(),
            'permissions' => $this->getPermissions(),
        ];
    }

    // ──────────────────────────────────────────────────
    //  Store (create treatment)
    // ──────────────────────────────────────────────────

    /**
     * @return array{success: bool, message: string, id: int}
     */
    public function store(array $validated): array
    {
        $user = Auth::user();
        $accountId = (int) $user->account_id;

        // Resolve or create the patient so the rest of the flow can
        // assume `patient_id` is set. The SPA's slim treatment-create
        // form (Service / Phone / Name / Time only) doesn't send
        // patient_id — we look up by phone. If no patient exists we
        // throw: per system policy, patients are created ONLY by the
        // consultation booking flow, never by treatment booking.
        if (empty($validated['patient_id']) && !empty($validated['phone'])) {
            $validated['patient_id'] = $this->resolveExistingPatientOrFail($validated, $accountId);
        }

        if (empty($validated['patient_id'])) {
            throw TreatmentException::invalidData('Patient is required.');
        }

        $service = Services::find($validated['service_id'])
            ?? throw TreatmentException::resourceNotFound('Service');

        $baseServiceId = $validated['base_service_id']
            ?? ($service->parent_id != 0 ? $service->parent_id : null);

        $resourceId = $validated['resource_id']
            ?? $this->findMachineForService(
                (int) $validated['service_id'],
                $baseServiceId ? (int) $baseServiceId : null,
                (int) $validated['location_id'],
            );

        if (! $resourceId) {
            throw TreatmentException::invalidData(
                'Machine not found. Please select a valid machine or ensure the location has an available machine for this service.',
            );
        }

        $location = Locations::find($validated['location_id'])
            ?? throw TreatmentException::resourceNotFound('Location');

        $this->validateDoctorService(
            (int) $validated['doctor_id'],
            (int) $validated['service_id'],
            $baseServiceId ? (int) $baseServiceId : null,
            (int) $validated['location_id'],
        );

        $appointmentData = $this->prepareAppointmentData(
            $validated,
            $service,
            $baseServiceId ? (int) $baseServiceId : null,
            (int) $resourceId,
            $location,
            $accountId,
        );

        $rotaValidation = $this->validateRotaAndAvailability(
            $validated,
            (int) $resourceId,
            $service,
            $appointmentData,
        );

        if (! $rotaValidation['valid']) {
            throw TreatmentException::invalidData($rotaValidation['message']);
        }

        $appointmentData = array_merge($appointmentData, $rotaValidation['data']);

        return DB::transaction(function () use ($validated, $appointmentData, $accountId, $service, $location): array {
            $lead = $this->handleLead($validated, $appointmentData, $accountId);
            $appointmentData['lead_id'] = $lead?->id;

            Patients::updateRecord((int) $appointmentData['patient_id'], false, $appointmentData, $appointmentData);

            $appointment = Appointments::create($appointmentData);

            if ($lead) {
                $this->handleLeadServices($lead, $validated, $appointment);
            }

            if (! empty($appointmentData['name'])) {
                Appointments::where('patient_id', $appointmentData['patient_id'])
                    ->update([
                        'name' => $appointmentData['name'],
                        'updated_at' => $appointmentData['updated_at'],
                    ]);
            }

            $appointment->update(['send_message' => 1]);

            $this->handleUnscheduledStatus($appointment, $accountId);

            ActivityLogger::saveAppointmentLogs('booked', 'Treatment', $appointment);

            $patient = Patients::find($appointment->patient_id);
            if ($patient) {
                ActivityLogger::logTreatmentBooked($appointment, $patient, $location, $service);
            }

            dispatch(new IndexSingleAppointmentJob([
                'account_id' => $accountId,
                'appointment_id' => $appointment->id,
            ]));

            return [
                'success' => true,
                'message' => 'Treatment has been created successfully.',
                'id' => $appointment->id,
            ];
        });
    }

    /**
     * Look up an existing patient by phone. Never creates.
     *
     * Patient creation is reserved for the consultation booking flow
     * (AppointmentService::createAppointment). Treatment booking
     * requires the patient to already exist; if no match is found,
     * we throw `patientNotRegistered` so the SPA can offer a
     * "book a consultation first" CTA.
     */
    private function resolveExistingPatientOrFail(array $data, int $accountId): int
    {
        // Use the canonical cleaner shared with the load/lead lookup
        // endpoint (`PhoneFormattingService::cleanNumber`) so the
        // search key here matches the one the SPA's phone-search ran
        // against. A divergent cleaner here would surface as
        // patient-not-registered for phones the SPA already proved
        // exist.
        $cleaned = PhoneFormattingService::cleanNumber($data['phone'] ?? null);
        if ($cleaned === '') {
            throw TreatmentException::invalidData('Phone is required to resolve the patient.');
        }

        // Match every equivalent stored form (canonical, raw leading zero,
        // 92/+92 country code). An exact match on the cleaned number alone
        // misses consultation-created rows that kept the leading zero,
        // surfacing as patient-not-registered for a phone the SPA's
        // load/lead search already proved exists. Must stay in lockstep
        // with Patients::getByPhone (the SPA-side lookup).
        $patient = User::where('account_id', $accountId)
            ->where('user_type_id', config('constants.patient_id'))
            ->whereIn('phone', PhoneFormattingService::matchVariants($cleaned))
            ->first();

        if (! $patient) {
            throw TreatmentException::patientNotRegistered();
        }

        return (int) $patient->id;
    }

    // ──────────────────────────────────────────────────
    //  Drag-Drop Reschedule
    // ──────────────────────────────────────────────────

    /**
     * @return array{success: bool, message: string, id: int}
     */
    public function dragDropReschedule(array $validated): array
    {
        $accountId = (int) Auth::user()->account_id;

        $appointment = Appointments::find($validated['id'])
            ?? throw TreatmentException::notFound();

        $paidStatusId = InvoiceStatuses::where('slug', 'paid')->value('id');
        if ($paidStatusId) {
            $hasPaidInvoice = Invoices::where('appointment_id', $appointment->id)
                ->where('invoice_status_id', $paidStatusId)
                ->exists();

            if ($hasPaidInvoice) {
                throw TreatmentException::invoiceExists();
            }
        }

        $doctorAvailable = Resources::checkDoctorAvailbility(request());
        if (! $doctorAvailable) {
            throw TreatmentException::doctorUnavailable('Doctor is not available for this time slot.');
        }

        $resourceDoctor = Resources::where('external_id', $validated['doctor_id'])->first()
            ?? throw TreatmentException::resourceNotFound('Doctor resource');

        $doctorRota = Resources::getResourceRotaHasDay($validated['start'], $resourceDoctor->id);
        if (empty($doctorRota['resource_has_rota_day_id'])) {
            throw TreatmentException::doctorUnavailable('Doctor rota is not available for this time slot.');
        }

        $data = $validated;
        $data['first_scheduled_count'] = $appointment->first_scheduled_count;
        $data['scheduled_at_count'] = $appointment->scheduled_at_count;
        $data['reschedule'] = 1;
        $data['resource_has_rota_day_id'] = $doctorRota['resource_has_rota_day_id'];
        $data['resource_id'] = ! empty($validated['resourceId'])
            ? $validated['resourceId']
            : $appointment->resource_id;

        // A drag can be a same-day time move or a cross-day move. Only a
        // DATE change counts as a reschedule for SMS purposes.
        $oldDragDate = $appointment->scheduled_date
            ? Carbon::parse($appointment->scheduled_date)->format('Y-m-d')
            : null;
        $newDragDate = ! empty($validated['start'])
            ? Carbon::parse($validated['start'])->format('Y-m-d')
            : $oldDragDate;
        $dragDateChanged = $oldDragDate !== $newDragDate;

        return DB::transaction(function () use ($data, $appointment, $accountId, $dragDateChanged): array {
            $record = Appointments::updateServiceRecord($data['id'], $data, $accountId);
            if (! $record) {
                throw TreatmentException::operationFailed('Failed to update appointment.');
            }

            // A reschedule resets the workflow status to the default
            // (Pending) UNLESS the treatment has already Arrived — that
            // reflects real progress and must survive a date change.
            // (Treatments have no Converted status; the shared helper
            // handles both kinds for parity with the consultation path.)
            $statusUpdate = AppointmentStatuses::resetStatusOnReschedule(
                (int) $appointment->base_appointment_status_id,
                $accountId,
            );
            if ($statusUpdate !== []) {
                // Re-notify only when the DATE moved — a same-day
                // time-only drag doesn't re-send. Don't clear an already-
                // pending flag on a time-only move either; the original
                // booking SMS must still go out.
                if ($dragDateChanged) {
                    $statusUpdate['send_message'] = 1;
                }
                $record->update($statusUpdate);
            }

            ActivityLogger::saveAppointmentLogs('rescheduled', 'Treatment', $record);

            dispatch(new IndexSingleAppointmentJob([
                'account_id' => $accountId,
                'appointment_id' => $appointment->id,
            ]));

            return [
                'success' => true,
                'message' => 'Treatment rescheduled successfully.',
                'id' => $appointment->id,
            ];
        });
    }

    // ──────────────────────────────────────────────────
    //  Check Patient Last Treatment
    // ──────────────────────────────────────────────────

    /**
     * @return array{last_treatment: ?array, can_edit_doctor?: bool}
     */
    public function checkPatientLastTreatment(array $validated): array
    {
        $patientId = (int) $validated['patient_id'];
        $serviceId = (int) $validated['service_id'];
        $locationId = (int) $validated['location_id'];
        $excludeAppointmentId = $validated['exclude_appointment_id'] ?? null;
        $startDateTime = $validated['start'] ?? null;

        $arrivedStatusId = $this->getArrivedStatusId();
        $accountId = (int) Auth::user()->account_id;

        // Account-scope the lookup so a guessed patient_id from another
        // tenant can't surface their treatment history. Cross-CENTRE
        // matches inside the account ARE allowed on purpose — the
        // warning is about patient continuity of care, not centre
        // membership; an operator at centre B should still see the
        // patient's prior arrived treatment performed at centre A.
        $query = Appointments::where('appointments.patient_id', $patientId)
            ->where('appointments.account_id', $accountId)
            ->where('appointments.appointment_type_id', $this->getTreatmentTypeId())
            ->where('appointments.appointment_status_id', $arrivedStatusId)
            ->where('appointments.service_id', $serviceId)
            ->join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
            ->select('appointments.*', 'invoices.created_at as invoice_created_at')
            ->orderBy('invoices.created_at', 'DESC')
            ->with(['doctor:id,name', 'service:id,name']);

        if ($excludeAppointmentId) {
            $query->where('appointments.id', '!=', $excludeAppointmentId);
        }

        $lastTreatment = $query->first();

        if (! $lastTreatment?->doctor_id) {
            return ['last_treatment' => null];
        }

        $doctor = User::find($lastTreatment->doctor_id);
        if (! $doctor?->active) {
            return ['last_treatment' => null];
        }

        // ⚠ DO NOT short-circuit on `checkDoctorAllocation` failure.
        // The legacy code returned null here, which silently hid the
        // warning whenever the previous doctor wasn't allocated to the
        // new centre — operators could re-route a patient to a brand
        // new doctor without any prompt, defeating the whole guard.
        //
        // The downstream UX already handles the unallocated case: the
        // doctor's rota check below returns false, the SPA renders the
        // "Schedule with previous doctor" radio in a disabled state
        // with a "(not available in this time slot)" note, and the
        // "Proceed with selected doctor" radio remains the only path
        // forward (gated on `can_edit_doctor`).
        $hasDoctorRota = $this->checkDoctorAllocation($lastTreatment->doctor_id, $locationId, $serviceId)
            && $this->checkDoctorRotaForDateTime($lastTreatment->doctor_id, $locationId, $startDateTime);

        return [
            'last_treatment' => [
                'id' => $lastTreatment->id,
                'doctor_id' => $lastTreatment->doctor_id,
                'doctor_name' => $lastTreatment->doctor->name ?? 'Unknown',
                'service_id' => $lastTreatment->service_id,
                'service_name' => $lastTreatment->service->name ?? 'Unknown',
                'scheduled_date' => $lastTreatment->scheduled_date,
                'scheduled_time' => $lastTreatment->scheduled_time,
                'has_doctor_rota' => $hasDoctorRota,
            ],
            'can_edit_doctor' => Gate::allows('can_edit_doctor'),
        ];
    }

    // ──────────────────────────────────────────────────
    //  Edit Data (for modal)
    // ──────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function getEditData(int $id): array
    {
        $accountId = (int) Auth::user()->account_id;

        $appointment = Appointments::with(['patient', 'doctor', 'service', 'location'])
            ->where('id', $id)
            ->where(fn ($q) => $q->where('account_id', $accountId)->orWhereNull('account_id'))
            ->first()
            ?? throw TreatmentException::notFound();

        $resourceRotaDay = $appointment->resource_has_rota_day_id
            ? ResourceHasRotaDays::find($appointment->resource_has_rota_day_id)
            : null;

        $machineRotaDay = $appointment->resource_has_rota_day_id_for_machine
            ? ResourceHasRotaDays::find($appointment->resource_has_rota_day_id_for_machine)
            : null;

        [$biggerTime, $smallerTime] = $this->calculateTimeBounds($resourceRotaDay, $machineRotaDay);

        $doctors = $this->getDoctorsWithRota($appointment->location_id, $accountId);

        $appointmentData = $appointment->toArray();
        if (isset($appointmentData['scheduled_date'])) {
            $appointmentData['scheduled_date'] = Carbon::parse($appointment->scheduled_date)->format('Y-m-d');
        }
        if (isset($appointmentData['first_scheduled_date'])) {
            $appointmentData['first_scheduled_date'] = Carbon::parse($appointment->first_scheduled_date)->format('Y-m-d');
        }

        $services = Services::whereNotNull('parent_id')
            ->where('parent_id', '!=', 0)
            ->whereNull('deleted_at')
            ->where('active', 1)
            ->orderBy('name')
            ->pluck('name', 'id');

        $isArrivedOrConverted = $this->isArrivedOrConverted($appointment);

        $user = Auth::user();
        $permissions = [
            'can_edit_doctor' => ! $isArrivedOrConverted || ($user?->can('can_edit_doctor') ?? false),
            'can_edit_service' => ! $isArrivedOrConverted || ($user?->can('can_edit_service') ?? false),
            'can_edit_schedule' => ! $isArrivedOrConverted || ($user?->can('can_edit_schedule') ?? false),
        ];

        return [
            'appointment' => $appointmentData,
            'services' => $services,
            'doctors' => $doctors,
            'resourceRotaDay' => $resourceRotaDay,
            'machineRotaDay' => $machineRotaDay,
            'biggerTime' => $biggerTime,
            'smallerTime' => $smallerTime,
            'permissions' => $permissions,
            'is_arrived_or_converted' => $isArrivedOrConverted,
        ];
    }

    // ──────────────────────────────────────────────────
    //  List / Scheduled / Non-Scheduled / Statistics
    //  (secondary API — /api/treatment/*)
    // ──────────────────────────────────────────────────

    public function getTreatmentList(array $filters): Builder
    {
        $service = app(AppointmentService::class);

        // Eager-count feedback rows so the SPA's row resource can emit
        // `has_feedback` without an N+1. Treatments-only; consultancy
        // list doesn't need this signal.
        return $service->getAppointmentsList($filters, $this->getTreatmentTypeId())
            ->withCount('feedback');
    }

    public function getScheduledTreatments(array $filters): mixed
    {
        $service = app(AppointmentService::class);
        $filters['appointment_type_id'] = $this->getTreatmentTypeId();

        return $service->getScheduledAppointments($filters);
    }

    public function getNonScheduledTreatments(array $filters): mixed
    {
        $service = app(AppointmentService::class);
        $filters['appointment_type_id'] = $this->getTreatmentTypeId();

        return $service->getNonScheduledAppointments($filters);
    }

    public function getTreatmentStatistics(array $filters = []): mixed
    {
        $service = app(AppointmentService::class);
        $filters['appointment_type_id'] = $this->getTreatmentTypeId();

        return $service->getAppointmentStatistics($filters);
    }

    // ──────────────────────────────────────────────────
    //  Resources & Services by Location
    // ──────────────────────────────────────────────────

    public function getAvailableResources(int $locationId, ?int $serviceId = null): mixed
    {
        $accountId = (int) Auth::user()->account_id;
        $cacheKey = "treatment_resources_{$accountId}_{$locationId}_{$serviceId}";

        return Cache::remember($cacheKey, self::CACHE_TTL / 2, function () use ($accountId, $locationId, $serviceId) {
            $query = Resources::where([
                'account_id' => $accountId,
                'location_id' => $locationId,
                'active' => 1,
            ]);

            if ($serviceId) {
                $query->whereHas('machineType.services', fn ($q) => $q->where('service_id', $serviceId));
            }

            return $query->with('machineType')->get();
        });
    }

    public function getServicesByLocation(int $locationId): mixed
    {
        $accountId = (int) Auth::user()->account_id;
        $cacheKey = "treatment_services_location_{$accountId}_{$locationId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => Services::whereHas('locations', fn ($q) => $q->where('location_id', $locationId))
            ->where('account_id', $accountId)
            ->where('active', 1)
            ->orderBy('name')
            ->get());
    }

    // ──────────────────────────────────────────────────
    //  Cache management
    // ──────────────────────────────────────────────────

    public function clearCache(): void
    {
        $accountId = (int) Auth::user()->account_id;
        Cache::forget("treatment_lookup_data_{$accountId}");
        Cache::forget("treatment_filter_values_{$accountId}_".md5(json_encode(ACL::getUserCentres())));
        Cache::forget('paid_invoice_status');
    }

    // ══════════════════════════════════════════════════
    //  PRIVATE — Datatable helpers
    // ══════════════════════════════════════════════════

    /**
     * @return array<string, mixed>
     */
    private function processFilters(array $requestAll): array
    {
        $userId = (int) Auth::id();
        $filters = getFilters($requestAll);

        if (isset($requestAll['sort'])) {
            [$orderBy, $order] = getSortBy(request(), 'appointments.created_at', 'DESC', 'appointments');
        } else {
            $orderBy = 'appointments.created_at';
            $order = 'desc';
        }

        Filters::put($userId, self::FILTER_KEY, 'order_by', $orderBy);
        Filters::put($userId, self::FILTER_KEY, 'order', $order);

        [$startDateTime, $endDateTime] = self::parseDateRangeForFilter(
            hasFilter($filters, 'created_at') ? $filters['created_at'] : null
        );
        if (hasFilter($filters, 'created_at')) {
            Filters::put($userId, self::FILTER_KEY, 'created_at', $filters['created_at']);
        }

        $simpleFilterKeys = [
            'phone', 'date_from', 'date_to', 'doctor_id', 'region_id', 'city_id',
            'location_id', 'service_id', 'created_by', 'converted_by', 'updated_by',
            'appointment_status_id', 'appointment_type_id', 'consultancy_type', 'name',
        ];

        foreach ($simpleFilterKeys as $key) {
            if (hasFilter($filters, $key)) {
                $value = match ($key) {
                    'patient_id' => PatientSearchService::patientSearch($filters[$key]),
                    'date_from' => $filters[$key].' 00:00:00',
                    'date_to' => $filters[$key].' 23:59:59',
                    default => $filters[$key],
                };
                Filters::put($userId, self::FILTER_KEY, $key, $value);
            }
        }

        if (hasFilter($filters, 'patient_id')) {
            Filters::put($userId, self::FILTER_KEY, 'patient_id', PatientSearchService::patientSearch($filters['patient_id']));
        }

        return array_merge($filters, [
            'order_by' => $orderBy,
            'order' => $order,
            'start_date_time' => $startDateTime,
            'end_date_time' => $endDateTime,
        ]);
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     */
    private function buildBaseConditions(array $filters, ?int $patientId = null): array
    {
        $where = [];

        if ($patientId) {
            $where[] = ['patient_id', '=', $patientId];
        } elseif (hasFilter($filters, 'patient_id')) {
            $where[] = ['patient_id', '=', PatientSearchService::patientSearch($filters['patient_id'])];
        }

        // Auto-filter by doctor_id for doctor roles
        $user = Auth::user();
        if ($user && $user->hasAnyRole(DoctorDashboardHelper::DOCTOR_ROLE_NAMES)) {
            $where[] = ['doctor_id', '=', $user->id];
        }

        $directFilterMap = [
            'phone' => ['users.phone', 'like'],
            'doctor_id' => ['doctor_id', '='],
            'region_id' => ['region_id', '='],
            'city_id' => ['city_id', '='],
            'created_by' => ['appointments.created_by', '='],
            'converted_by' => ['appointments.converted_by', '='],
            'updated_by' => ['appointments.updated_by', '='],
            'appointment_type_id' => ['appointments.appointment_type_id', '='],
            'consultancy_type' => ['appointments.consultancy_type', '='],
        ];

        foreach ($directFilterMap as $filterKey => [$column, $operator]) {
            if (hasFilter($filters, $filterKey)) {
                $value = $operator === 'like' ? '%'.$filters[$filterKey].'%' : $filters[$filterKey];
                $where[] = [$column, $operator, $value];
            }
        }

        if (hasFilter($filters, 'date_from')) {
            $where[] = ['appointments.scheduled_date', '>=', $filters['date_from'].' 00:00:00'];
        }
        if (hasFilter($filters, 'date_to')) {
            $where[] = ['appointments.scheduled_date', '<=', $filters['date_to'].' 23:59:59'];
        }
        if (! empty($filters['start_date_time'])) {
            $where[] = ['appointments.created_at', '>=', $filters['start_date_time']];
        }
        if (! empty($filters['end_date_time'])) {
            $where[] = ['appointments.created_at', '<=', $filters['end_date_time']];
        }

        return $where;
    }

    /**
     * @return array<int, int>
     */
    private function getStatusIdsForFilter(array $filters): array
    {
        if (! hasFilter($filters, 'appointment_status_id')) {
            return [];
        }

        $accountId = (int) Auth::user()->account_id;
        $selectedStatus = AppointmentStatuses::find($filters['appointment_status_id']);

        if ($selectedStatus?->is_arrived == 1) {
            $convertedStatus = AppointmentStatuses::where([
                'account_id' => $accountId,
                'is_converted' => 1,
            ])->first();

            if ($convertedStatus) {
                return [(int) $filters['appointment_status_id'], $convertedStatus->id];
            }
        }

        return [(int) $filters['appointment_status_id']];
    }

    /**
     * @return array<int, int>
     */
    private function getServiceIdsForFilter(array $filters): array
    {
        if (! hasFilter($filters, 'service_id')) {
            return [];
        }

        $serviceId = GeneralFunctions::getServiceId($filters['service_id']);
        $service = Services::find($serviceId);

        if (! $service) {
            return [];
        }

        if ($service->parent_id == 0) {
            return ($service->slug === 'all')
                ? Services::pluck('id')->toArray()
                : Services::where('parent_id', $service->id)->pluck('id')->toArray();
        }

        return [$service->id];
    }

    private function getRecordsCount(array $where, array $filters, int $treatmentTypeId): int
    {
        if (! Gate::allows('treatments.list.view')) {
            return 0;
        }

        $query = Appointments::query()
            ->join('users', fn ($join) => $join
                ->on('users.id', '=', 'appointments.patient_id')
                ->where('users.user_type_id', '=', config('constants.patient_id')))
            ->where('appointments.appointment_type_id', $treatmentTypeId)
            ->whereIn('appointments.city_id', ACL::getUserCities())
            ->whereIn('appointments.location_id', ACL::getUserCentres());

        $this->applyFiltersToQuery($query, $where, $filters);

        return $query->count();
    }

    private function getAppointments(
        array $where,
        array $filters,
        int $treatmentTypeId,
        string $orderBy,
        string $order,
        int $limit,
        int $offset,
    ): EloquentCollection {
        $invoiceStatus = $this->getPaidInvoiceStatus();

        $query = Appointments::query()
            ->join('users', fn ($join) => $join
                ->on('users.id', '=', 'appointments.patient_id')
                ->where('users.user_type_id', '=', config('constants.patient_id')))
            ->with([
                'patient:id,name,phone',
                'doctor:id,name',
                'city:id,name',
                'location:id,name',
                'service:id,name,parent_id',
                'appointment_type:id,name',
                'appointment_status:id,name,parent_id',
                'invoice' => fn ($q) => $q->where('invoice_status_id', $invoiceStatus?->id ?? 0),
            ])
            ->where('appointments.appointment_type_id', $treatmentTypeId)
            ->whereIn('appointments.location_id', ACL::getUserCentres());

        $this->applyFiltersToQuery($query, $where, $filters);

        if (hasFilter($filters, 'name')) {
            $query->where(fn ($q) => $q
                ->where('users.name', 'like', '%'.$filters['name'].'%')
                ->orWhere('appointments.name', 'like', '%'.$filters['name'].'%'));
        }

        if ($orderBy === 'name') {
            $orderBy = 'appointments.name';
        }

        return $query->select([
            'appointments.*',
            'users.phone',
            'appointments.name as patient_name',
            'appointments.id as app_id',
            'appointments.created_by as app_created_by',
            'appointments.updated_by as app_updated_by',
            'appointments.created_at as app_created_at',
        ])
            ->limit($limit)
            ->offset($offset)
            ->orderBy('appointments.created_at', 'DESC')
            ->get();
    }

    private function applyFiltersToQuery(mixed $query, array $where, array $filters): void
    {
        if ($where) {
            $query->where($where);
        }

        // Unified patient search — same engine the Plans / Patients /
        // picker / invoices surfaces use. SPA sends `q`; classifier
        // routes to id / phone_normalized / FT name.
        if (hasFilter($filters, 'q')) {
            PatientSearchService::applyPatientFilter(
                $query,
                (string) $filters['q'],
                'appointments.patient_id',
                Auth::user()?->account_id,
            );
        }

        $statusIds = $this->getStatusIdsForFilter($filters);
        if ($statusIds) {
            $query->whereIn('appointments.base_appointment_status_id', $statusIds);
        }

        $serviceIds = $this->getServiceIdsForFilter($filters);
        if ($serviceIds) {
            $query->whereIn('service_id', $serviceIds);
        }

        if (hasFilter($filters, 'location_id')) {
            $ids = explode(',', (string) $filters['location_id']);
            if (count($ids) > 1) {
                $query->whereIn('location_id', $ids);
            } else {
                $query->where('location_id', $ids[0]);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function transformAppointments(EloquentCollection $appointments, array $lookupData): array
    {
        $canViewContact = Gate::allows('treatments.list.view_contact');
        $regions = $lookupData['regions'];
        $users = $lookupData['users'];
        $appointmentStatuses = $lookupData['appointment_statuses'];
        $unscheduledStatus = $lookupData['unscheduled_status'];
        $cancelledStatus = $lookupData['cancelled_status'];

        return $appointments->map(function ($appointment) use (
            $canViewContact,
            $regions,
            $users,
            $appointmentStatuses,
            $unscheduledStatus,
            $cancelledStatus,
        ) {
            $resource = new TreatmentDatatableResource($appointment);
            $resource->setLookupData(
                $canViewContact,
                $regions,
                $users,
                $appointmentStatuses,
                $unscheduledStatus,
                $cancelledStatus,
            );

            return $resource->resolve(request());
        })->toArray();
    }

    // ══════════════════════════════════════════════════
    //  PRIVATE — Lookup / cache helpers
    // ══════════════════════════════════════════════════

    /**
     * @return array<string, mixed>
     */
    private function getLookupData(): array
    {
        $accountId = (int) Auth::user()->account_id;

        return Cache::remember("treatment_lookup_data_{$accountId}", self::CACHE_TTL, fn () => [
            'regions' => Regions::getAllRecordsDictionary($accountId),
            'users' => User::getAllRecords($accountId)->getDictionary(),
            'appointment_statuses' => AppointmentStatuses::getAllRecordsDictionary($accountId),
            'unscheduled_status' => AppointmentStatuses::getUnScheduledStatusOnly($accountId, ['id']),
            'cancelled_status' => AppointmentStatuses::getCancelledStatusOnly($accountId),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getFilterValues(): array
    {
        $accountId = (int) Auth::user()->account_id;
        $cacheKey = "treatment_filter_values_{$accountId}_".md5(json_encode(ACL::getUserCentres()));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($accountId): array {
            $appointmentStatuses = AppointmentStatuses::getAllParentRecords($accountId);

            return [
                'cities' => Cities::getActiveSortedFeatured(ACL::getUserCities()),
                'regions' => Regions::getActiveSorted(ACL::getUserRegions()),
                'users' => User::getAllRecords($accountId)->pluck('name', 'id'),
                'doctors' => Doctors::getActiveOnly(ACL::getUserCentres()),
                'locations' => Locations::getActiveSorted(ACL::getUserCentres()),
                'services' => GeneralFunctions::ServicesTreeList(),
                'appointment_statuses' => $appointmentStatuses?->pluck('name', 'id') ?? collect(),
                'appointment_types' => $this->getAppointmentTypes(),
                'consultancy_types' => config('constants.consultancy_type_array'),
            ];
        });
    }

    private function getAppointmentTypes(): mixed
    {
        $canConsultancy = Gate::allows('consultations.list.view');
        $canServices = Gate::allows('treatments.list.view');

        return match (true) {
            $canConsultancy && $canServices => AppointmentTypes::pluck('name', 'id'),
            $canConsultancy => AppointmentTypes::where('slug', 'consultancy')->pluck('name', 'id'),
            $canServices => AppointmentTypes::where('slug', 'treatment')->pluck('name', 'id'),
            default => collect(),
        };
    }

    /**
     * Datatable permission map — consumed by the legacy admin-v1 KTDatatable
     * (the SPA uses `usePermissions().has()` directly). Treatment-owned flags
     * now use the new dotted catalog; cross-module attachment flags
     * (image / measurement / medical_form / plans / patient_card) stay on
     * legacy `appointments_*` until those modules are audited.
     *
     * @return array<string, bool>
     */
    private function getPermissions(): array
    {
        $canEdit = Gate::allows('treatments.edit');

        return [
            'edit' => $canEdit,
            'consultancy' => Gate::allows('consultations.list.view'),
            'treatment' => Gate::allows('treatments.list.view'),
            'delete' => Gate::allows('treatments.delete'),
            // active / inactive / create-row toggles aren't surfaced in the
            // SPA — kept for legacy admin-v1 datatable but always false now.
            'active' => false,
            'inactive' => false,
            'create' => Gate::allows('treatments.create'),
            'log' => Gate::allows('appointments_log'),
            'status' => Gate::allows('treatments.update_status'),
            'schedule_edit' => Gate::allows('treatments.reschedule'),
            'invoice' => Gate::allows('treatments.invoice.create'),
            'invoice_display' => Gate::allows('treatments.invoice.view'),
            'image_manage' => Gate::allows('appointments_image_manage'),
            'measurement_manage' => Gate::allows('appointments_measurement_manage'),
            'medical_form_manage' => Gate::allows('appointments_medical_form_manage'),
            'plans_create' => $canEdit && Gate::allows('appointments_plans_create'),
            'patient_card' => Gate::allows('appointments_patient_card'),
            'contact' => Gate::allows('treatments.list.view_contact'),
            'add_feedback' => $canEdit && Gate::allows('feedbacks_create'),
            'can_edit_doctor' => $canEdit && Gate::allows('can_edit_doctor'),
        ];
    }

    private function getTreatmentTypeId(): int
    {
        return (int) config('constants.appointment_type_service');
    }

    private function getPaidInvoiceStatus(): ?InvoiceStatuses
    {
        return Cache::remember('paid_invoice_status', self::CACHE_TTL, fn () => InvoiceStatuses::where('slug', 'paid')->first()
        );
    }

    private function getArrivedStatusId(): int
    {
        $accountId = (int) Auth::user()->account_id;

        return (int) Cache::remember("arrived_status_id_{$accountId}", self::CACHE_TTL, fn () => AppointmentStatuses::where(['account_id' => $accountId, 'is_arrived' => 1])->value('id')
        );
    }

    // ══════════════════════════════════════════════════
    //  PRIVATE — Store helpers
    // ══════════════════════════════════════════════════

    private function findMachineForService(int $serviceId, ?int $baseServiceId, int $locationId): ?int
    {
        // Try child service first
        $machineTypeService = MachineTypeHasServices::where('service_id', $serviceId)->first();
        if ($machineTypeService) {
            $resource = Resources::where('location_id', $locationId)
                ->where('machine_type_id', $machineTypeService->machine_type_id)
                ->where('active', 1)
                ->first();

            if ($resource) {
                return $resource->id;
            }
        }

        // Then parent service
        if ($baseServiceId) {
            $machineTypeService = MachineTypeHasServices::where('service_id', $baseServiceId)->first();
            if ($machineTypeService) {
                $resource = Resources::where('location_id', $locationId)
                    ->where('machine_type_id', $machineTypeService->machine_type_id)
                    ->where('active', 1)
                    ->first();

                if ($resource) {
                    return $resource->id;
                }
            }
        }

        return null;
    }

    private function validateDoctorService(int $doctorId, int $serviceId, ?int $baseServiceId, int $locationId): bool
    {
        return DB::table('doctor_has_locations as dhl')
            ->join('services as s', 's.id', '=', 'dhl.service_id')
            ->where('dhl.location_id', $locationId)
            ->where('dhl.user_id', $doctorId)
            ->where('dhl.is_allocated', 1)
            ->where(function ($query) use ($serviceId, $baseServiceId): void {
                $query->where('dhl.service_id', $serviceId);
                if ($baseServiceId) {
                    $query->orWhere('dhl.service_id', $baseServiceId);
                }
                $query->orWhere('s.slug', 'all');
            })
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareAppointmentData(
        array $validated,
        Services $service,
        ?int $baseServiceId,
        int $resourceId,
        Locations $location,
        int $accountId,
    ): array {
        $user = Auth::user();

        $data = $validated;
        $data['account_id'] = $accountId;
        $data['created_by'] = $user->id;
        $data['consultancy_type'] = 'treatment';
        $data['appointment_type_id'] = $this->getTreatmentTypeId();
        $data['city_id'] = $location->city_id;
        $data['region_id'] = $location->region_id;
        $data['base_service_id'] = $baseServiceId;
        $data['resource_id'] = $resourceId;
        $data['user_type_id'] = 3;
        $data['created_at'] = Filters::getCurrentTimeStamp();
        $data['updated_at'] = Filters::getCurrentTimeStamp();

        $appointmentStatus = AppointmentStatuses::getADefaultStatusOnly($accountId);
        if ($appointmentStatus) {
            $data['appointment_status_id'] = $appointmentStatus->id;
            $data['base_appointment_status_id'] = $appointmentStatus->id;
            $data['appointment_status_allow_message'] = $appointmentStatus->allow_message;
        } else {
            $data['appointment_status_id'] = null;
            $data['base_appointment_status_id'] = null;
            $data['appointment_status_allow_message'] = 0;
        }

        return $data;
    }

    /**
     * @return array{valid: bool, data: array, message: string}
     */
    private function validateRotaAndAvailability(
        array $validated,
        int $resourceId,
        Services $service,
        array $appointmentData,
    ): array {
        $start = $validated['start'] ?? null;
        if (! $start) {
            return ['valid' => true, 'data' => [], 'message' => ''];
        }

        $serviceDuration = $service->duration ?? '00:30';
        $durationParts = explode(':', $serviceDuration);
        $end = (count($durationParts) >= 2)
            ? Carbon::parse($start)->addHours((int) $durationParts[0])->addMinutes((int) $durationParts[1])
            : Carbon::parse($start)->addMinutes(30);

        $startFormatted = Carbon::parse($start)->format('Y-m-d H:i:s');

        $doctorId = $validated['doctor_id'];
        if (! Resources::checkingDoctorAvailbility($doctorId, $startFormatted, $end)) {
            return [
                'valid' => false,
                'data' => [],
                'message' => 'Doctor is not available. Appointment cannot be scheduled.',
            ];
        }

        $data = [];

        $resourceDoctor = Resources::where('external_id', $doctorId)->first();
        if ($resourceDoctor) {
            $doctorRota = Resources::getResourceRotaHasDay($start, $resourceDoctor->id);
            if (! empty($doctorRota['resource_has_rota_day_id'])) {
                $data['resource_has_rota_day_id'] = $doctorRota['resource_has_rota_day_id'];
            }
        }

        $scheduledTime = $validated['scheduled_time']
            ?? Carbon::parse($start)->format('H:i:s');

        $data['scheduled_date'] = Carbon::parse($start)->format('Y-m-d');
        $data['first_scheduled_date'] = Carbon::parse($start)->format('Y-m-d');
        $data['first_scheduled_count'] = 1;
        $data['scheduled_time'] = Carbon::parse($scheduledTime)->format('H:i:s');
        $data['first_scheduled_time'] = Carbon::parse($scheduledTime)->format('H:i:s');

        return ['valid' => true, 'data' => $data, 'message' => ''];
    }

    private function handleLead(array $validated, array $appointmentData, int $accountId): ?Leads
    {
        $phone = $appointmentData['phone'];
        $lead = Leads::where('phone', $phone)->orderByDesc('id')->first();

        if ($lead) {
            return $lead;
        }

        $patient = Patients::find($appointmentData['patient_id']);

        $defaultBookedStatus = LeadStatuses::where([
            'account_id' => $accountId,
            'is_booked' => 1,
        ])->first();

        $leadData = $appointmentData;
        $leadData['lead_status_id'] = $defaultBookedStatus?->id ?? config('constants.lead_status_booked');
        $leadData['created_at'] = Filters::getCurrentTimeStamp();
        $leadData['updated_at'] = Filters::getCurrentTimeStamp();
        $leadData['location_id'] = $validated['location_id'];
        $leadData['gender'] = $patient?->gender;

        return Leads::updateOrCreate(
            ['phone' => $phone, 'account_id' => $accountId],
            $leadData,
        );
    }

    private function handleLeadServices(Leads $lead, array $validated, Appointments $appointment): void
    {
        $serviceId = $validated['service_id'];
        $baseServiceId = $validated['base_service_id'] ?? $appointment->base_service_id;

        LeadsServices::where('lead_id', $lead->id)->update(['status' => 0]);

        LeadsServices::updateOrCreate(
            [
                'lead_id' => $lead->id,
                'service_id' => $baseServiceId ?? $serviceId,
            ],
            [
                'child_service_id' => $serviceId,
                'treatment_id' => $appointment->id,
                'status' => 1,
            ],
        );
    }

    private function handleUnscheduledStatus(Appointments $appointment, int $accountId): void
    {
        if ($appointment->scheduled_date || $appointment->scheduled_time) {
            return;
        }

        $unscheduledStatus = AppointmentStatuses::getUnScheduledStatusOnly($accountId);
        $status = $unscheduledStatus ?: AppointmentStatuses::getADefaultStatusOnly($accountId);

        $appointment->update([
            'appointment_status_id' => $status?->id,
            'base_appointment_status_id' => $status?->id,
            'appointment_status_allow_message' => 0,
        ]);
    }

    // ══════════════════════════════════════════════════
    //  PRIVATE — Edit helpers
    // ══════════════════════════════════════════════════

    /**
     * @return array{0: mixed, 1: mixed}
     */
    private function calculateTimeBounds(?ResourceHasRotaDays $resourceRotaDay, ?ResourceHasRotaDays $machineRotaDay): array
    {
        if ($resourceRotaDay && $machineRotaDay) {
            return [
                ResourceHasRota::getBiggerTime($resourceRotaDay->start_time, $machineRotaDay->start_time),
                ResourceHasRota::getSmallerTime($resourceRotaDay->end_time, $machineRotaDay->end_time),
            ];
        }

        if ($resourceRotaDay) {
            return [$resourceRotaDay->start_time, $resourceRotaDay->end_time];
        }

        return [null, null];
    }

    private function getDoctorsWithRota(int $locationId, int $accountId): mixed
    {
        $doctors = Doctors::getActiveOnly($locationId, $accountId);

        if (! $doctors || $doctors->isEmpty()) {
            return collect();
        }

        $doctorExternalIds = array_keys($doctors->toArray());

        $resourcesWithRota = Resources::whereIn('external_id', $doctorExternalIds)
            ->whereHas('rotas', fn ($q) => $q->where('is_treatment', 1))
            ->pluck('external_id')
            ->toArray();

        return $doctors->filter(fn ($doctor, $key) => in_array($key, $resourcesWithRota, true));
    }

    private function checkDoctorAllocation(int $doctorId, int $locationId, int $serviceId): bool
    {
        $requestedService = Services::find($serviceId);

        return DB::table('doctor_has_locations as dhl')
            ->join('services as s', 's.id', '=', 'dhl.service_id')
            ->where('dhl.location_id', $locationId)
            ->where('dhl.user_id', $doctorId)
            ->where('dhl.is_allocated', 1)
            ->where(function ($query) use ($serviceId, $requestedService): void {
                $query->where('dhl.service_id', $serviceId);
                if ($requestedService?->parent_id) {
                    $query->orWhere('dhl.service_id', $requestedService->parent_id);
                }
                $query->orWhere('s.slug', 'all');
            })
            ->exists();
    }

    private function checkDoctorRotaForDateTime(int $doctorId, int $locationId, ?string $startDateTime): bool
    {
        if (! $startDateTime) {
            return false;
        }

        $startedTime = Carbon::parse($startDateTime)->format('Y-m-d H:i:s');

        return Resources::join('resource_has_rota', 'resources.id', '=', 'resource_has_rota.resource_id')
            ->join('resource_has_rota_days', 'resource_has_rota.id', '=', 'resource_has_rota_days.resource_has_rota_id')
            ->where('resources.external_id', $doctorId)
            ->where('resource_has_rota.location_id', $locationId)
            ->where('resource_has_rota_days.start_timestamp', '<=', $startedTime)
            ->where('resource_has_rota_days.end_timestamp', '>', $startedTime)
            ->exists();
    }

    private function isArrivedOrConverted(Appointments $appointment): bool
    {
        $accountId = (int) Auth::user()->account_id;

        $arrivedStatus = AppointmentStatuses::where([
            'account_id' => $accountId,
            'is_arrived' => 1,
        ])->first();

        $convertedStatus = AppointmentStatuses::where([
            'account_id' => $accountId,
            'is_converted' => 1,
        ])->first();

        $statusIds = array_filter([
            $arrivedStatus?->id,
            $convertedStatus?->id,
        ]);

        return in_array($appointment->appointment_status_id, $statusIds, false);
    }
}
