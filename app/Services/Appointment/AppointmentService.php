<?php

declare(strict_types=1);

namespace App\Services\Appointment;

use App\Enums\AppointmentType;
use App\Exceptions\AppointmentException;
use App\Helpers\ActivityLogger;
use App\Helpers\AppointmentHelper;
use App\Helpers\GeneralFunctions;
use App\Helpers\Widgets\AppointmentCheckesWidget;
use App\Models\Activity;
use App\Models\Appointments;
use App\Models\AppointmentsDailyStats;
use App\Models\AppointmentStatuses;
use App\Models\AuditTrails;
use App\Models\InvoiceStatuses;
use App\Models\Invoices;
use App\Models\Leads;
use App\Models\LeadsServices;
use App\Models\LeadStatuses;
use App\Models\BusinessClosure;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\ResourceHasRotaDays;
use App\Models\Resources;
use App\Models\Services;
use App\Models\Settings;
use App\Models\User;
use App\Models\WorkingDayException;
use App\Services\Lead\BackfillLeadCategoryAction;
use App\Services\MetaConversionApiService;
use App\Services\PatientManagement\PatientSearchService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AppointmentService
{
    protected ?int $account_id = null;

    protected ?int $user_id = null;

    public function __construct(
        // Container-injected so tests can swap the action; runtime
        // ALWAYS resolves it (this is the only path that maintains the
        // single-active-row invariant on `leads_services` with proper
        // category awareness — the legacy inline code at the old lines
        // 482-521 has been removed in favour of this action).
        private readonly BackfillLeadCategoryAction $backfillCategory = new BackfillLeadCategoryAction(),
    ) {
        // Properties will be set lazily when needed via getAccountId() and getUserId()
    }

    protected function getAccountId(): int
    {
        if ($this->account_id === null) {
            if (! Auth::check()) {
                throw new \Exception('User must be authenticated to use AppointmentService');
            }
            $this->account_id = (int) Auth::user()->account_id;
        }

        return $this->account_id;
    }

    protected function getUserId(): int
    {
        if ($this->user_id === null) {
            if (! Auth::check()) {
                throw new \Exception('User must be authenticated to use AppointmentService');
            }
            $this->user_id = (int) Auth::id();
        }

        return $this->user_id;
    }

    /**
     * Look up the consultancy `appointment_type_id` for the current
     * account. Patient creation is allowed only when the appointment
     * being booked is of this type — no other appointment type may
     * spawn a patient row. Cached per account.
     */
    protected function getConsultancyTypeId(): ?int
    {
        $accountId = $this->getAccountId();

        return Cache::remember(
            "consultancy_type_id_{$accountId}",
            3600,
            fn () => \App\Models\AppointmentTypes::where([
                'account_id' => $accountId,
                'slug' => 'consultancy',
            ])->value('id'),
        );
    }

    /**
     * True only when both the supplied id and the resolved consultancy
     * type id are non-zero AND equal. Defensive against a missing seed
     * (the cache resolves to null) — without this, `(int) null === 0`
     * would let a request with `appointment_type_id = 0` silently pass
     * the gate.
     */
    protected function isConsultancyType(int $appointmentTypeId): bool
    {
        $consultancyId = $this->getConsultancyTypeId();

        return $consultancyId !== null
            && $appointmentTypeId > 0
            && $appointmentTypeId === (int) $consultancyId;
    }

    public function getAppointmentsList(array $filters, ?int $appointmentTypeId = null): Builder
    {
        $query = Appointments::with([
            'appointment_type',
            'appointment_status',
            'service',
            'location.city',
            'doctor',
            'patient',
            'lead',
            'user',
            'user_converted_by',
            'user_updated_by',
        ])->where('account_id', $this->getAccountId());

        // Centre-level ACL — every list endpoint that flows through
        // here (consultancy index, treatment index, scheduled /
        // non-scheduled / statistics) is scoped to the centres the
        // logged-in user has been assigned. The legacy datatable
        // services already do this directly; mirroring it here so the
        // REST API matches. Super-admin (user 1) and practitioners get
        // their full set via ACL::getUserCentres() — the helper
        // handles those branches centrally.
        $query->whereIn('appointments.location_id', \App\Helpers\ACL::getUserCentres());

        if ($appointmentTypeId) {
            $query->where('appointment_type_id', $appointmentTypeId);
        }

        $query = $this->applyFilters($query, $filters);

        return $query;
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        // Unified patient search — same engine the Plans / Patients /
        // Vouchers / picker / invoices surfaces use. SPA sends `q`;
        // PatientSearchService::applyPatientFilter classifies the value
        // (`C-<id>` / numeric id / phone / FT name) and routes to the
        // matching index. Routed against `appointments.patient_id` so
        // the filter applies before the heavier with()/where joins.
        if (! empty($filters['q'])) {
            PatientSearchService::applyPatientFilter(
                $query,
                (string) $filters['q'],
                'appointments.patient_id',
                $this->getAccountId(),
            );
        }

        if (! empty($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }

        if (! empty($filters['phone'])) {
            $phone = GeneralFunctions::cleanNumber($filters['phone']);
            $query->whereHas('patient', function ($q) use ($phone) {
                $q->where('phone', 'like', "%{$phone}%");
            });
        }

        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (! empty($filters['doctor_id'])) {
            $query->where('doctor_id', $filters['doctor_id']);
        }

        if (! empty($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        // `service_parent_id` is the SPA's "parent selected on the
        // services tree picker" filter — expands to the parent's
        // active children so the table shows every treatment booked
        // against any sub-service of the chosen category. Mutually
        // exclusive with `service_id` in the UI; if both arrive we
        // honour `service_id` (more specific) and ignore the parent.
        if (! empty($filters['service_parent_id']) && empty($filters['service_id'])) {
            $childIds = \App\Models\Services::query()
                ->where('parent_id', (int) $filters['service_parent_id'])
                ->where('active', 1)
                ->pluck('id')
                ->all();
            // Include the parent id itself too — some legacy rows
            // bind to the parent service directly.
            $childIds[] = (int) $filters['service_parent_id'];
            $query->whereIn('service_id', $childIds);
        }

        if (! empty($filters['appointment_status_id'])) {
            $query->where('appointment_status_id', $filters['appointment_status_id']);
        }

        // "Created / Updated / Rescheduled by" filters from the list page's
        // advanced shelf. `rescheduled_by` is the SPA label for the legacy
        // `converted_by` column (same source the ConsultancyResource /
        // TreatmentResource read for their `rescheduled_by` field), kept for
        // export-filter compatibility with the legacy admin.
        if (! empty($filters['created_by'])) {
            $query->where('appointments.created_by', $filters['created_by']);
        }

        if (! empty($filters['updated_by'])) {
            $query->where('appointments.updated_by', $filters['updated_by']);
        }

        if (! empty($filters['rescheduled_by'])) {
            $query->where('appointments.converted_by', $filters['rescheduled_by']);
        }

        if (! empty($filters['scheduled_date_from'])) {
            $query->where('scheduled_date', '>=', $filters['scheduled_date_from']);
        }

        if (! empty($filters['scheduled_date_to'])) {
            $query->where('scheduled_date', '<=', $filters['scheduled_date_to']);
        }

        if (! empty($filters['created_date_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_date_from']);
        }

        if (! empty($filters['created_date_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_date_to']);
        }

        if (isset($filters['scheduled']) && $filters['scheduled'] === true) {
            $query->whereNotNull('scheduled_date')
                ->whereNotNull('scheduled_time');
        } elseif (isset($filters['scheduled']) && $filters['scheduled'] === false) {
            $query->whereNull('scheduled_date')
                ->whereNull('scheduled_time');
        }

        // Sort. The controller has already validated the field against
        // an allow-list and resolved it to a safe `table.column` form,
        // so injecting it directly into orderBy is safe here.
        if (isset($filters['sort']) && is_array($filters['sort'])) {
            $field = (string) ($filters['sort']['field'] ?? 'appointments.created_at');
            $direction = (string) ($filters['sort']['direction'] ?? 'desc');
            $query->orderBy($field, $direction);
            // For scheduled_date sorts, break ties by time so two rows
            // on the same day land in a sensible order.
            if ($field === 'appointments.scheduled_date') {
                $query->orderBy('appointments.scheduled_time', $direction);
            }
        } else {
            $query->orderBy('appointments.created_at', 'desc');
        }

        return $query;
    }

    /**
     * Block scheduling on a date the business has marked closed (full-day
     * holiday or rolling closure window). The closure may be scoped to a
     * specific location, to the "All centres" sentinel location, or to no
     * locations at all (account-wide). Mirrors the legacy admin guards in
     * `AppointmentScheduleController::validateScheduleDate` and
     * `ConsultancyUpdateService::validateBusinessClosure`.
     */
    protected function validateBusinessClosure(int $accountId, int $locationId, string $date): void
    {
        $allCentresLocationId = (int) Config::get('constants.all_centres_location_id', 30);

        $closure = BusinessClosure::where('account_id', $accountId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(function ($query) use ($locationId, $allCentresLocationId) {
                $query->whereHas('locations', fn ($q) => $q->where('locations.id', $locationId))
                    ->orWhereHas('locations', fn ($q) => $q->where('locations.id', $allCentresLocationId))
                    ->orWhereDoesntHave('locations');
            })
            ->first();

        if ($closure) {
            $formattedDate = Carbon::parse($date)->format('d M, Y');
            throw AppointmentException::invalidData(
                "Cannot schedule appointment on {$formattedDate}. Business is closed: " . ($closure->title ?? 'Business Closed')
            );
        }
    }

    /**
     * Block scheduling on a non-working weekday (per the account's
     * `business_working_days` setting), respecting per-date overrides
     * stored in `working_day_exceptions`.
     */
    protected function validateWorkingDay(int $accountId, string $date): void
    {
        $workingDays = $this->getBusinessWorkingDays($accountId);
        $isWorkingDay = WorkingDayException::isWorkingDay($accountId, $date, $workingDays);

        if (! $isWorkingDay) {
            $formattedDate = Carbon::parse($date)->format('l, d M Y');
            throw AppointmentException::invalidData(
                "Cannot schedule appointment on {$formattedDate}. Business is closed on this day."
            );
        }
    }

    private function getBusinessWorkingDays(int $accountId): array
    {
        $setting = Settings::where('account_id', $accountId)
            ->where('slug', 'business_working_days')
            ->first();

        if ($setting?->data) {
            return json_decode($setting->data, true);
        }

        return [
            'monday' => true,
            'tuesday' => true,
            'wednesday' => true,
            'thursday' => true,
            'friday' => true,
            'saturday' => true,
            'sunday' => false,
        ];
    }

    /**
     * Convenience wrapper for the create / update / schedule paths.
     * Skips validation when no date or location is supplied — leaving
     * an appointment unscheduled is always allowed.
     */
    protected function validateScheduleAvailability(int $accountId, ?int $locationId, ?string $date): void
    {
        if (! $date || ! $locationId) {
            return;
        }
        $iso = Carbon::parse($date)->format('Y-m-d');
        $this->validateBusinessClosure($accountId, (int) $locationId, $iso);
        $this->validateWorkingDay($accountId, $iso);
    }

    public function createAppointment(array $data): Appointments
    {
        DB::beginTransaction();
        try {
            if (! isset($data['appointment_type_id'])) {
                throw AppointmentException::invalidData('Appointment type is required.');
            }

            if (! isset($data['appointment_status_id'])) {
                throw AppointmentException::invalidData('Appointment status is required.');
            }

            if (! isset($data['location_id'])) {
                throw AppointmentException::invalidData('Location is required.');
            }

            $this->validateAppointmentData($data);

            // Clean up empty lead_id and patient_id (sent as empty strings from form)
            if (isset($data['lead_id']) && (empty($data['lead_id']) || $data['lead_id'] === '' || $data['lead_id'] === '0')) {
                unset($data['lead_id']);
            }
            if (isset($data['patient_id']) && (empty($data['patient_id']) || $data['patient_id'] === '' || $data['patient_id'] === '0')) {
                unset($data['patient_id']);
            }

            // CRITICAL: Validate that lead_id/patient_id matches the phone number being submitted
            // This prevents linking a consultation to the wrong patient when user enters a new phone
            if (isset($data['lead_id']) && isset($data['phone'])) {
                $lead = Leads::find($data['lead_id']);
                if ($lead) {
                    $submittedPhone = GeneralFunctions::cleanNumber($data['phone']);
                    $leadPhone = GeneralFunctions::cleanNumber($lead->phone ?? '');

                    // If phone numbers don't match, this is a new patient - clear lead_id and patient_id
                    if ($submittedPhone !== $leadPhone) {
                        \Log::info('Phone mismatch detected - treating as new patient', [
                            'submitted_phone' => $submittedPhone,
                            'lead_phone' => $leadPhone,
                            'lead_id' => $data['lead_id'],
                        ]);
                        unset($data['lead_id']);
                        unset($data['patient_id']);
                    }
                }
            }

            // Same guard for an explicitly-provided patient_id: if the
            // submitted phone doesn't match that patient's phone, the caller
            // resolved a stale identity — drop it so we don't link the
            // appointment to the wrong patient.
            if (isset($data['patient_id']) && isset($data['phone'])) {
                $existingPatient = User::find($data['patient_id']);
                if ($existingPatient) {
                    $submittedPhone = GeneralFunctions::cleanNumber($data['phone']);
                    $patientPhone = GeneralFunctions::cleanNumber($existingPatient->phone ?? '');
                    if ($patientPhone !== '' && $submittedPhone !== $patientPhone) {
                        \Log::info('Phone mismatch vs patient_id - treating as new patient', [
                            'submitted_phone' => $submittedPhone,
                            'patient_phone' => $patientPhone,
                            'patient_id' => $data['patient_id'],
                        ]);
                        unset($data['patient_id']);
                    }
                }
            }

            // Server-side phone de-dup — the authoritative backstop. A phone
            // maps to exactly ONE patient within an account, so before we ever
            // mint a patient from a bare phone, look one up. The SPA resolves
            // the patient_id client-side, but that lookup is async (debounced +
            // network) and the Save button doesn't wait for it; a fast submit,
            // or any non-SPA caller (legacy crm2 / direct API), can arrive with
            // a phone and no patient_id. Without this guard each such create
            // spawned a DUPLICATE patient (e.g. a repeat visit at another
            // branch). Only runs when nothing is resolved yet — the exact case
            // that would otherwise fall through to new-patient creation below.
            if (! isset($data['patient_id']) && ! isset($data['lead_id']) && ! empty($data['phone'])) {
                $existingByPhone = Patients::getByPhone((string) $data['phone'], $this->getAccountId());
                if ($existingByPhone) {
                    $data['patient_id'] = $existingByPhone->id;
                }
            }

            // Decide whether to spawn a new patient. A new patient is created
            // ONLY when the caller supplied NO identity (neither lead_id NOR
            // patient_id) — either via the explicit "new patient" toggle or
            // by entering a phone with nothing resolved. Previously this
            // looked at lead_id alone, so a create that carried a real
            // patient_id (existing patient, but no matching lead row) still
            // fell through and minted a DUPLICATE patient — the new
            // consultation then landed on the duplicate while the patient's
            // earlier (e.g. arrived) consultations stayed on the original.
            $hasResolvedIdentity = isset($data['lead_id']) || isset($data['patient_id']);
            $shouldCreateNewPatient = ! $hasResolvedIdentity
                && (
                    (isset($data['new_patient']) && $data['new_patient'] == 1)
                    || (isset($data['phone']) && ! empty($data['phone']))
                );

            // Policy: patient creation is allowed ONLY for consultation
            // bookings. Any other appointment type that reaches this
            // point without a patient_id is a misuse — the SPA should
            // route the user through a consultation first.
            if ($shouldCreateNewPatient && ! $this->isConsultancyType((int) ($data['appointment_type_id'] ?? 0))) {
                throw AppointmentException::patientCreationNotAllowed();
            }

            if ($shouldCreateNewPatient) {
                // Step 1: Create patient/user record
                $patientData = [
                    'name' => $data['name'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'gender' => $data['gender'] ?? 0,
                    'referred_by' => $data['referred_by'] ?? null,
                    'account_id' => $this->getAccountId(),
                    'user_type_id' => config('constants.patient_id'),
                    'password' => \Hash::make(Str::random(16)),
                    'active' => 1,
                ];

                $patient = User::create($patientData);
                if (! $patient) {
                    throw AppointmentException::invalidData('Failed to create patient.');
                }

                // Step 2: Create lead with patient_id
                $accountId = Auth::user()->account_id ?? 1;
                $userId = Auth::id();

                $leadData = [
                    'patient_id' => $patient->id,
                    'name' => $data['name'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'referred_by' => $data['referred_by'] ?? null,
                    'account_id' => $accountId,
                    'created_by' => $userId,
                    'location_id' => $data['location_id'] ?? null,
                    'region_id' => null,
                    'city_id' => null,
                    'lead_status_id' => null,
                    'lead_source_id' => null,
                ];

                // Get location details for region and city
                if (isset($data['location_id'])) {
                    $location = Locations::find($data['location_id']);
                    if ($location) {
                        $leadData['region_id'] = $location->region_id;
                        $leadData['city_id'] = $location->city_id;
                    }
                }

                // Get 'Booked' lead status by FLAG, not name — a tenant
                // can rename the row but the `is_booked=1` flag is the
                // canonical contract (matches the existing-lead path
                // below + WrongConversionService + ConsultancyInvoice-
                // Service lookups).
                $bookedStatus = LeadStatuses::where([
                    'account_id' => $accountId,
                    'is_booked'  => 1,
                ])->first();

                if (! $bookedStatus) {
                    // Fallback to default status if no `is_booked=1` row
                    $bookedStatus = LeadStatuses::where([
                        'account_id' => $accountId,
                        'is_default' => 1,
                    ])->first();
                }

                if ($bookedStatus) {
                    $leadData['lead_status_id'] = $bookedStatus->id;
                }

                // Create lead record
                \Log::info('Creating lead with data:', $leadData);
                $lead = Leads::create($leadData);
                if (! $lead) {
                    throw AppointmentException::invalidData('Failed to create lead for new patient.');
                }

                // Create lead service entry if service_id is provided.
                // `account_id` removed — it's not in `LeadsServices::$fillable`
                // so mass-assignment silently drops it; tenant scope
                // is preserved via `leads.account_id` (every join from
                // leads_services back to leads carries the constraint).
                if (isset($data['service_id'])) {
                    LeadsServices::create([
                        'lead_id'    => $lead->id,
                        'service_id' => $data['service_id'],
                        'status'     => 1,
                    ]);
                }

                // Step 3: Set lead_id and patient_id for appointment
                $data['lead_id'] = $lead->id;
                $data['patient_id'] = $patient->id;
            }

            // Existing patient resolved by patient_id but with no lead_id —
            // the phone lookup only returns a lead when one matches the chosen
            // service, so this is the common case for a repeat patient. The
            // appointments table requires a lead_id, so resolve the patient's
            // most recent lead (or mint one for a consultation) instead of
            // letting the row fail or, worse, duplicating the patient.
            if (! $shouldCreateNewPatient && isset($data['patient_id']) && ! isset($data['lead_id'])) {
                $existingLead = Leads::where('patient_id', $data['patient_id'])
                    ->where('account_id', $this->getAccountId())
                    ->orderByDesc('id')
                    ->first();

                if ($existingLead) {
                    $data['lead_id'] = $existingLead->id;
                } elseif ($this->isConsultancyType((int) ($data['appointment_type_id'] ?? 0))) {
                    $patientRow = User::find($data['patient_id']);
                    if ($patientRow) {
                        $accountId = $this->getAccountId();
                        $leadData = [
                            'patient_id'  => $patientRow->id,
                            'name'        => $patientRow->name ?? ($data['name'] ?? null),
                            'phone'       => $patientRow->phone ?? ($data['phone'] ?? null),
                            'email'       => $patientRow->email ?? ($data['email'] ?? null),
                            'gender'      => $patientRow->gender ?? ($data['gender'] ?? null),
                            'account_id'  => $accountId,
                            'created_by'  => $this->getUserId(),
                            'location_id' => $data['location_id'] ?? null,
                        ];

                        if (isset($data['location_id'])) {
                            $loc = Locations::find($data['location_id']);
                            if ($loc) {
                                $leadData['region_id'] = $loc->region_id;
                                $leadData['city_id'] = $loc->city_id;
                            }
                        }

                        $bookedStatus = LeadStatuses::where(['account_id' => $accountId, 'is_booked' => 1])->first()
                            ?? LeadStatuses::where(['account_id' => $accountId, 'is_default' => 1])->first();
                        if ($bookedStatus) {
                            $leadData['lead_status_id'] = $bookedStatus->id;
                        }

                        $newLead = Leads::create($leadData);
                        if ($newLead) {
                            if (isset($data['service_id'])) {
                                LeadsServices::create([
                                    'lead_id'    => $newLead->id,
                                    'service_id' => $data['service_id'],
                                    'status'     => 1,
                                ]);
                            }
                            $data['lead_id'] = $newLead->id;
                        }
                    }
                }
            }

            $appointmentData = AppointmentHelper::prepareAppointmentData($data, $this->getAccountId(), $this->getUserId(), false);

            if (isset($data['lead_id'])) {
                $lead = Leads::find($data['lead_id']);
                if (! $lead) {
                    throw AppointmentException::leadNotFound();
                }

                // Set patient_id from lead if not already set
                if (! isset($appointmentData['patient_id']) || ! $appointmentData['patient_id']) {
                    $appointmentData['patient_id'] = $lead->patient_id;
                }

                // Set name from lead if not already set
                if (! isset($appointmentData['name']) || ! $appointmentData['name']) {
                    $appointmentData['name'] = $lead->name;
                }

                // If lead doesn't have patient_id, we need to create a patient.
                // Same policy gate as the new-patient branch above:
                // only consultation bookings are allowed to spawn
                // patient rows.
                if (! $lead->patient_id) {
                    if (! $this->isConsultancyType((int) ($data['appointment_type_id'] ?? 0))) {
                        throw AppointmentException::patientCreationNotAllowed();
                    }

                    $patientData = [
                        'name' => $lead->name ?? $data['name'] ?? null,
                        'phone' => $lead->phone ?? $data['phone'] ?? null,
                        'email' => $lead->email ?? $data['email'] ?? null,
                        'gender' => $lead->gender ?? $data['gender'] ?? 0,
                        'referred_by' => $lead->referred_by ?? $data['referred_by'] ?? null,
                        'account_id' => $this->getAccountId(),
                        'user_type_id' => config('constants.patient_id'),
                        'password' => \Hash::make(Str::random(16)),
                        'active' => 1,
                    ];

                    $patient = User::create($patientData);
                    if (! $patient) {
                        throw AppointmentException::invalidData('Failed to create patient for lead.');
                    }

                    // Update lead with patient_id
                    $lead->update(['patient_id' => $patient->id]);

                    // Set patient_id in appointment data
                    $appointmentData['patient_id'] = $patient->id;
                }
            }

            if (isset($data['patient_id'])) {
                $patient = User::find($data['patient_id']);
                if (! $patient) {
                    throw AppointmentException::patientNotFound();
                }
            }

            // Validate doctor has service allocated at location
            if (isset($appointmentData['doctor_id']) && isset($appointmentData['service_id']) && isset($appointmentData['location_id'])) {
                // Check if doctor has "all services" assigned at this location
                $hasAllServices = \DB::table('doctor_has_locations')
                    ->join('services', 'services.id', '=', 'doctor_has_locations.service_id')
                    ->where('doctor_has_locations.user_id', $appointmentData['doctor_id'])
                    ->where('doctor_has_locations.location_id', $appointmentData['location_id'])
                    ->where('services.slug', 'all')
                    ->where('doctor_has_locations.is_allocated', 1)
                    ->exists();

                if (! $hasAllServices) {
                    // If not all services, check for specific service
                    $hasService = \DB::table('doctor_has_locations')
                        ->where('user_id', $appointmentData['doctor_id'])
                        ->where('location_id', $appointmentData['location_id'])
                        ->where('service_id', $appointmentData['service_id'])
                        ->where('is_allocated', 1)
                        ->exists();

                    if (! $hasService) {
                        // Check if the service is a child and its parent is assigned to the doctor
                        $service = Services::find($appointmentData['service_id']);

                        if ($service && $service->parent_id) {
                            // Service has a parent, check if parent is assigned to doctor
                            $hasParentService = \DB::table('doctor_has_locations')
                                ->where('user_id', $appointmentData['doctor_id'])
                                ->where('location_id', $appointmentData['location_id'])
                                ->where('service_id', $service->parent_id)
                                ->where('is_allocated', 1)
                                ->exists();

                            if (! $hasParentService) {
                                throw AppointmentException::invalidData('This doctor does not have the required service or its parent service allocated for this location.');
                            }
                        } else {
                            throw AppointmentException::invalidData('This doctor does not have the required service allocated for this location.');
                        }
                    }
                }
            }

            // Schedule conflict check disabled to allow multiple bookings on the same slot
            // if (isset($appointmentData['scheduled_date']) && isset($appointmentData['scheduled_time'])) {
            //     $hasConflict = AppointmentHelper::validateScheduleConflict(
            //         $appointmentData['location_id'],
            //         $appointmentData['doctor_id'] ?? null,
            //         $appointmentData['resource_id'] ?? null,
            //         $appointmentData['scheduled_date'],
            //         $appointmentData['scheduled_time']
            //     );

            //     if ($hasConflict) {
            //         throw AppointmentException::scheduleConflict();
            //     }
            // }

            // Reject scheduling onto a business-closed day (full-day
            // closure window or non-working weekday). Skipped when the
            // appointment is being created without a date — operators
            // routinely create unscheduled rows and slot them in later.
            $this->validateScheduleAvailability(
                $this->getAccountId(),
                isset($appointmentData['location_id']) ? (int) $appointmentData['location_id'] : null,
                $appointmentData['scheduled_date'] ?? null,
            );

            $appointment = Appointments::create($appointmentData);

            if (! $appointment) {
                throw AppointmentException::creationFailed();
            }

            // Always set send_message to 1 for new appointments to trigger SMS via cron job
            $appointment->update(['send_message' => 1]);

            AuditTrails::addEventLogger(
                Appointments::$_table,
                'create',
                $appointmentData,
                Appointments::$_fillable,
                $appointment
            );

            // Handle lead status update and activity logging if lead_id is present
            if (isset($data['lead_id'])) {
                $lead = Leads::find($data['lead_id']);

                if ($lead) {
                    // Resolve the Booked status once (used both for the
                    // lead row update and for the leads_services pivot
                    // refresh inside `BackfillLeadCategoryAction`).
                    // Prefer flag (`is_booked=1`) over name match —
                    // matches the WrongConversionService / Consultancy-
                    // InvoiceService lookup pattern; the legacy `name =
                    // 'Booked'` query is brittle (a tenant rename
                    // silently breaks the cascade).
                    $bookedStatus = LeadStatuses::where([
                        'account_id' => $this->getAccountId(),
                        'is_booked'  => 1,
                    ])->first();

                    if ($bookedStatus && $lead->lead_status_id != $bookedStatus->id) {
                        $lead->update([
                            'lead_status_id' => $bookedStatus->id,
                            'updated_by'     => $this->getUserId(),
                            'updated_at'     => Carbon::now(),
                        ]);
                    }

                    // Single source of truth for the leads_services
                    // pivot: `BackfillLeadCategoryAction` handles
                    // category-aware demote-and-create with correct
                    // history preservation. Replaces the legacy inline
                    // demote-everything dance which used raw service_id
                    // equality (no parent_id grouping).
                    //
                    // `consultancy_id` is the FK on leads_services that
                    // points at the originating CONSULTATION — not at
                    // any appointment. Only pass the appointment id
                    // when this booking IS a consultation; otherwise
                    // (treatment booking with no consultation), leave
                    // it null to avoid polluting the column with a
                    // treatment-appointment id.
                    if (! empty($appointment->service_id)) {
                        $service = Services::find($appointment->service_id);
                        if ($service) {
                            $isConsultation = (int) $appointment->appointment_type_id === AppointmentType::Consultancy->value;
                            $this->backfillCategory->execute(
                                $lead,
                                $service,
                                $isConsultation ? $appointment->id : null,
                                $bookedStatus?->id,
                            );
                        }
                    }

                    // Send Meta CAPI event for booked status — gated on
                    // per-appointment `meta_booked_sent` so reschedules
                    // of the same row don't re-fire (Meta would over-
                    // count Booked → bias Lookalike Audiences + bid
                    // optimisation). Same shape as the existing
                    // `meta_purchase_sent` guard for Converted.
                    if (! $appointment->meta_booked_sent) {
                        \Log::info('Sending Meta CAPI booked event', [
                            'lead_id' => $lead->id,
                            'phone' => $lead->phone,
                            'meta_lead_id' => $lead->meta_lead_id,
                            'email' => $lead->email,
                        ]);
                        try {
                            $metaService = new MetaConversionApiService;
                            $metaService->sendLeadStatus(
                                $lead->phone,
                                'booked',
                                $lead->meta_lead_id,
                                $lead->email
                            );
                            $appointment->update(['meta_booked_sent' => 1]);
                            \Log::info('Meta CAPI booked event sent successfully', [
                                'lead_id' => $lead->id,
                            ]);
                        } catch (\Exception $e) {
                            // Round 4 Crypto-H3 — getTraceAsString() inlines
                            // argument values (lead phone numbers, emails) into
                            // the log line. Use file/line instead so PII does
                            // not land in storage/logs/laravel.log.
                            \Log::error('Meta CAPI booked event failed: '.$e->getMessage(), [
                                'lead_id' => $lead->id,
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                            ]);
                        }
                    }

                    // Get related data for activity logging
                    $location = Locations::with('city')->find($appointment->location_id);
                    $service = Services::find($appointment->service_id);

                    // Log lead booked activity
                    ActivityLogger::logLeadBooked($lead, $appointment, $location, $service);
                }
            }

            AppointmentHelper::clearAppointmentCache($this->getAccountId());

            DB::commit();

            $appointment->load([
                'appointment_type',
                'appointment_status',
                'service',
                'location',
                'doctor',
                'patient',
            ]);

            return $appointment;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateAppointment(int $id, array $data): Appointments
    {
        DB::beginTransaction();
        try {
            $appointment = Appointments::where([
                'id' => $id,
                'account_id' => $this->getAccountId(),
            ])->first();

            if (! $appointment) {
                throw AppointmentException::notFound();
            }

            $appointmentData = AppointmentHelper::prepareAppointmentData($data, $this->getAccountId(), $this->getUserId(), true);

            if (isset($data['reschedule']) && $data['reschedule'] == 1) {
                $appointmentData['converted_by'] = $this->getUserId();
            }

            // Validate doctor has service allocated at location (when doctor or service is being changed)
            $doctorId = $appointmentData['doctor_id'] ?? $appointment->doctor_id;
            $serviceId = $appointmentData['service_id'] ?? $appointment->service_id;
            $locationId = $appointmentData['location_id'] ?? $appointment->location_id;

            if ($doctorId && $serviceId && $locationId) {
                // Check if doctor has "all services" assigned at this location
                $hasAllServices = \DB::table('doctor_has_locations')
                    ->join('services', 'services.id', '=', 'doctor_has_locations.service_id')
                    ->where('doctor_has_locations.user_id', $doctorId)
                    ->where('doctor_has_locations.location_id', $locationId)
                    ->where('services.slug', 'all')
                    ->where('doctor_has_locations.is_allocated', 1)
                    ->exists();

                if (! $hasAllServices) {
                    // If not all services, check for specific service
                    $hasService = \DB::table('doctor_has_locations')
                        ->where('user_id', $doctorId)
                        ->where('location_id', $locationId)
                        ->where('service_id', $serviceId)
                        ->where('is_allocated', 1)
                        ->exists();

                    if (! $hasService) {
                        // Check if the service is a child and its parent is assigned to the doctor
                        $service = Services::find($serviceId);

                        if ($service && $service->parent_id) {
                            // Service has a parent, check if parent is assigned to doctor
                            $hasParentService = \DB::table('doctor_has_locations')
                                ->where('user_id', $doctorId)
                                ->where('location_id', $locationId)
                                ->where('service_id', $service->parent_id)
                                ->where('is_allocated', 1)
                                ->exists();

                            if (! $hasParentService) {
                                throw AppointmentException::invalidData('This doctor does not have the required service or its parent service allocated for this location.');
                            }
                        } else {
                            throw AppointmentException::invalidData('This doctor does not have the required service allocated for this location.');
                        }
                    }
                }
            }

            // Schedule conflict check disabled to allow multiple bookings on the same slot
            // if (isset($appointmentData['scheduled_date']) && isset($appointmentData['scheduled_time'])) {
            //     $hasConflict = AppointmentHelper::validateScheduleConflict(
            //         $appointmentData['location_id'] ?? $appointment->location_id,
            //         $appointmentData['doctor_id'] ?? $appointment->doctor_id,
            //         $appointmentData['resource_id'] ?? $appointment->resource_id,
            //         $appointmentData['scheduled_date'],
            //         $appointmentData['scheduled_time'],
            //         $id
            //     );

            //     if ($hasConflict) {
            //         throw AppointmentException::scheduleConflict();
            //     }
            // }

            // Reject rescheduling onto a closed day. Only fires when
            // `scheduled_date` is actually being set on this update — a
            // patient-detail edit that doesn't touch the date won't
            // re-validate against today's closure list.
            $this->validateScheduleAvailability(
                $this->getAccountId(),
                isset($appointmentData['location_id'])
                    ? (int) $appointmentData['location_id']
                    : (int) $appointment->location_id,
                $appointmentData['scheduled_date'] ?? null,
            );

            // Rota guard — same gate scheduleAppointment uses. Re-validates
            // when the doctor or the scheduled_date changes on update so
            // rescheduling into a no-rota slot fails loudly instead of
            // landing with `resource_has_rota_day_id = NULL`. Scoped to
            // consultancy because treatments allow off-rota slots.
            $rotaDoctorId = $appointmentData['doctor_id'] ?? $appointment->doctor_id;
            $rotaDate = $appointmentData['scheduled_date'] ?? null;
            $rotaLocationId = $appointmentData['location_id'] ?? $appointment->location_id;

            if (
                $rotaDoctorId
                && $rotaDate
                && $appointment->appointment_type_id == AppointmentType::Consultancy->value
            ) {
                $resource = Resources::where([
                    'external_id' => $rotaDoctorId,
                    'resource_type_id' => Config::get('constants.resource_doctor_type_id'),
                    'account_id' => $this->getAccountId(),
                ])->first();

                $rotaDay = $resource
                    ? ResourceHasRotaDays::getSingleDayRotaWithResourceID(
                        $resource->id,
                        $rotaDate,
                        $this->getAccountId(),
                        $rotaLocationId
                    )
                    : null;

                if (empty($rotaDay)) {
                    $dateLabel = Carbon::parse($rotaDate)->format('Y-m-d');
                    throw AppointmentException::invalidData(
                        "Doctor rota is not defined for {$dateLabel} at the selected centre."
                    );
                }

                if ($resource) {
                    $appointmentData['resource_id'] = $resource->id;
                    $appointmentData['resource_has_rota_day_id'] = $rotaDay['id'];
                }
            }

            // Time-of-day validation for BOTH consultancy and treatment. The
            // edit form used to skip this, so a date/time edit could land the
            // appointment outside the doctor's shift (e.g. after 9 PM for an
            // 11 AM–9 PM shift). Reuse the same check create + reschedule run.
            // Permissive when the doctor has no rota that day (the widget
            // returns OK), so treatments keep their off-rota flexibility.
            if ($rotaDoctorId && $rotaDate) {
                $this->validateRotaAvailability([
                    'scheduled_date' => $rotaDate,
                    'scheduled_time' => $appointmentData['scheduled_time'] ?? $appointment->scheduled_time,
                    'doctor_id' => $rotaDoctorId,
                    'location_id' => $rotaLocationId,
                    'city_id' => $appointment->city_id ?? '',
                ]);
            }

            $oldData = $appointment->toArray();

            if (isset($appointmentData['scheduled_date'])) {
                $newScheduledDate = Carbon::parse($appointmentData['scheduled_date'])->format('Y-m-d');
                $currentScheduledDate = $appointment->scheduled_date
                    ? Carbon::parse($appointment->scheduled_date)->format('Y-m-d')
                    : null;
                if ($currentScheduledDate !== $newScheduledDate) {
                    $appointmentData['rescheduled_count'] = ((int) $appointment->rescheduled_count) + 1;

                    // Date moved → re-notify the patient with the booking
                    // SMS, following the same cron + active-template rules
                    // as creation. Guarded to Booked/pending so an
                    // Arrived/Converted row never re-fires. A time-only
                    // edit doesn't enter this block, so it never re-sends.
                    if ((int) $appointment->base_appointment_status_id === (int) Config::get('constants.appointment_status_pending', 1)) {
                        $appointmentData['send_message'] = 1;
                    }
                }
            }

            $appointment->update($appointmentData);

            AuditTrails::editEventLogger(
                Appointments::$_table,
                'update',
                $appointmentData,
                Appointments::$_fillable,
                $oldData,
                $id
            );

            AppointmentHelper::clearAppointmentCache($this->getAccountId());

            DB::commit();

            return $appointment->fresh([
                'appointment_type',
                'appointment_status',
                'service',
                'location',
                'doctor',
                'patient',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteAppointment(int $id): bool
    {
        DB::beginTransaction();
        try {
            $appointment = Appointments::where([
                'id' => $id,
                'account_id' => $this->getAccountId(),
            ])->first();

            if (! $appointment) {
                throw AppointmentException::notFound();
            }

            if (AppointmentHelper::isChildExists($id, $this->getAccountId())) {
                throw AppointmentException::cannotDelete();
            }

            $patient = Patients::find($appointment->patient_id);
            $location = Locations::with('city')->find($appointment->location_id);
            $service = Services::find($appointment->service_id);

            ActivityLogger::logAppointmentDeleted($appointment, $patient, $location, $service);

            AppointmentsDailyStats::where('appointment_id', $id)->delete();

            $appointment->update([
                'deleted_by' => $this->getUserId(),
                'arrived_at' => null,
                'converted_at' => null,
            ]);

            $appointment->delete();

            AuditTrails::deleteEventLogger(
                Appointments::$_table,
                'delete',
                Appointments::$_fillable,
                $id,
                '0'
            );

            AppointmentHelper::clearAppointmentCache($this->getAccountId());

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateAppointmentStatus(int $id, array $data): Appointments
    {
        DB::beginTransaction();
        try {
            $appointment = Appointments::where([
                'id' => $id,
                'account_id' => $this->getAccountId(),
            ])->first();

            if (! $appointment) {
                throw AppointmentException::notFound();
            }

            $status = AppointmentStatuses::find($data['appointment_status_id']);
            if (! $status) {
                throw AppointmentException::invalidStatus();
            }

            // ───────── Manual-status guards ─────────
            // Mirror the legacy admin path's enforcement
            // (`AppointmentStatusController::storeAppointmentStatuses`
            // lines 144-153) so the SPA can't bypass the funnel.
            //
            // 1) Auto-only flag rejection — Arrived comes from the
            //    invoice path, Converted comes from the package
            //    payment path, Un-Scheduled is derived from missing
            //    scheduled_date+time. None can be set manually.
            if ($status->is_arrived ?? false) {
                throw AppointmentException::invalidStatus(
                    'Cannot manually set status to Arrived — this happens automatically when the consultation invoice is paid.'
                );
            }
            if ($status->is_converted ?? false) {
                throw AppointmentException::invalidStatus(
                    'Cannot manually set status to Converted — this happens automatically on the first package payment.'
                );
            }
            if ($status->is_unscheduled ?? false) {
                throw AppointmentException::invalidStatus(
                    'Un-Scheduled is derived from a missing scheduled date/time and cannot be set manually.'
                );
            }

            // 2) Paid-invoice lock — once an invoice is paid against
            //    this appointment the financial record is closed; status
            //    changes would silently invalidate revenue/conversion
            //    reports. Reuses the legacy slug='paid' lookup.
            $paidInvoiceStatusId = InvoiceStatuses::where('slug', '=', 'paid')->value('id');
            if ($paidInvoiceStatusId) {
                $hasPaidInvoice = Invoices::where('invoice_status_id', $paidInvoiceStatusId)
                    ->where('appointment_id', $id)
                    ->exists();
                if ($hasPaidInvoice) {
                    throw AppointmentException::invalidStatus(
                        'Invoice is paid — status can no longer be changed for this appointment.'
                    );
                }
            }

            // 3) Cancellation reason required when picking a cancelled
            //    status. Schema has `cancellation_reason_id` nullable,
            //    so enforcement lives here rather than in the request
            //    DTO.
            if (($status->is_cancelled ?? false) && empty($data['cancellation_reason_id'])) {
                throw AppointmentException::invalidStatus('A cancellation reason is required when cancelling.');
            }

            $updateData = [
                'appointment_status_id' => $data['appointment_status_id'],
                'base_appointment_status_id' => $status->base_appointment_status_id ?? $data['appointment_status_id'],
                'updated_by' => $this->getUserId(),
                'updated_at' => Carbon::now(),
            ];

            if (isset($data['reason'])) {
                $updateData['reason'] = $data['reason'];
            }

            if (isset($data['cancellation_reason_id'])) {
                $updateData['cancellation_reason_id'] = $data['cancellation_reason_id'];
            }

            // No `is_converted` write here — guard above rejects it.

            $oldData = $appointment->toArray();
            $appointment->update($updateData);

            // Activity feed entry — mirrors the legacy controller
            // (`AppointmentStatusController.php:272-279`) so the
            // patient timeline shows status changes regardless of
            // whether they came from the legacy admin Blade or the
            // SPA. AuditTrails is the low-level diff log; Activity is
            // the user-visible feed.
            $oldStatus = AppointmentStatuses::find($oldData['base_appointment_status_id'] ?? null);
            if ($oldStatus && $oldData['base_appointment_status_id'] !== $updateData['base_appointment_status_id']) {
                $patient = $appointment->patient_id ? Patients::find($appointment->patient_id) : null;
                $location = $appointment->location_id ? Locations::with('city')->find($appointment->location_id) : null;
                $service = $appointment->service_id ? Services::find($appointment->service_id) : null;
                if ($patient) {
                    ActivityLogger::logAppointmentStatusChange($appointment, $patient, $oldStatus, $status, $location, $service);
                }
            }

            AuditTrails::editEventLogger(
                Appointments::$_table,
                'status_update',
                $updateData,
                Appointments::$_fillable,
                $oldData,
                $id
            );

            AppointmentHelper::clearAppointmentCache($this->getAccountId());

            DB::commit();

            return $appointment->fresh(['appointment_status', 'appointment_status_base']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getScheduledAppointments(array $filters): mixed
    {
        $query = Appointments::with([
            'appointment_type',
            'appointment_status',
            'service',
            'location',
            'doctor',
            'patient',
            'resource',
        ])->whereNotNull('scheduled_date')
            ->where('appointment_type_id', 1)
            ->whereNotNull('scheduled_time')
            // Centre-level ACL — see getAppointmentsList for the
            // rationale; same scoping applied to the un-paginated
            // scheduled / non-scheduled / statistics list endpoints.
            ->where('account_id', $this->getAccountId())
            ->whereIn('appointments.location_id', \App\Helpers\ACL::getUserCentres());

        $cancelledStatus = AppointmentHelper::getCancelledStatus($this->getAccountId());
        if ($cancelledStatus) {
            $query->where(function ($q) use ($cancelledStatus) {
                $q->where('appointment_status_id', '!=', $cancelledStatus->id)
                    ->orWhereNull('appointment_status_id');
            });
        }

        $query = $this->applyFilters($query, $filters);

        return $query->get();
    }

    public function getNonScheduledAppointments(array $filters): mixed
    {
        $query = Appointments::with([
            'appointment_type',
            'appointment_status',
            'service',
            'location',
            'doctor',
            'patient',
        ])->where('account_id', $this->getAccountId())
            ->whereNull('scheduled_date')
            ->whereNull('scheduled_time')
            // Same centre ACL as the list / scheduled endpoints.
            ->whereIn('appointments.location_id', \App\Helpers\ACL::getUserCentres());

        $cancelledStatus = AppointmentHelper::getCancelledStatus($this->getAccountId());
        if ($cancelledStatus) {
            $query->where(function ($q) use ($cancelledStatus) {
                $q->where('appointment_status_id', '!=', $cancelledStatus->id)
                    ->orWhereNull('appointment_status_id');
            });
        }

        $query = $this->applyFilters($query, $filters);

        return $query->get();
    }

    public function scheduleAppointment(int $id, array $data): Appointments
    {
        DB::beginTransaction();
        try {
            $accountId = $this->getAccountId();

            // Find appointment by ID, allowing for NULL account_id or matching account_id
            $appointment = Appointments::where('id', $id)
                ->where(function ($query) use ($accountId) {
                    $query->where('account_id', $accountId)
                        ->orWhereNull('account_id');
                })
                ->first();

            if (! $appointment) {
                throw AppointmentException::notFound();
            }

            // If appointment has NULL account_id, set it to current user's account
            if ($appointment->account_id === null) {
                $appointment->account_id = $accountId;
            }

            $scheduleData = AppointmentHelper::formatScheduleData(
                $data['start'],
                $appointment->first_scheduled_count,
                $appointment->scheduled_at_count
            );

            // Reject rescheduling onto a business-closed day before any
            // other validation — fail-fast keeps the message specific
            // ("Business is closed: …") rather than masked by a downstream
            // service / rota error.
            $this->validateScheduleAvailability(
                $accountId,
                (int) ($data['location_id'] ?? $appointment->location_id),
                $scheduleData['scheduled_date'] ?? null,
            );

            // Validate doctor has service allocated at location. Consultancy
            // only — treatments routinely run services the doctor isn't
            // explicitly allocated, and the edit path (updateAppointment) never
            // enforced this either. Without the guard, treatment reschedule
            // threw "service not assigned" and MASKED the real rota/time error
            // that treatment edit (correctly) surfaces.
            if ($appointment->appointment_type_id == AppointmentType::Consultancy->value) {
                $doctorId = $data['doctor_id'] ?? $appointment->doctor_id;
                $locationId = $data['location_id'] ?? $appointment->location_id;

                // Check if doctor has "all services" assigned at this location
                $hasAllServices = \DB::table('doctor_has_locations')
                    ->join('services', 'services.id', '=', 'doctor_has_locations.service_id')
                    ->where('doctor_has_locations.user_id', $doctorId)
                    ->where('doctor_has_locations.location_id', $locationId)
                    ->where('services.slug', 'all')
                    ->where('doctor_has_locations.is_allocated', 1)
                    ->exists();

                if (! $hasAllServices) {
                    // If not all services, check for specific service
                    $hasService = \DB::table('doctor_has_locations')
                        ->where('user_id', $doctorId)
                        ->where('location_id', $locationId)
                        ->where('service_id', $appointment->service_id)
                        ->where('is_allocated', 1)
                        ->exists();

                    if (! $hasService) {
                        throw AppointmentException::invalidData('This doctor does not have the required service allocated for this location.');
                    }
                }
            }

            // Schedule conflict check disabled to allow multiple bookings on the same slot
            // $hasConflict = AppointmentHelper::validateScheduleConflict(
            //     $data['location_id'] ?? $appointment->location_id,
            //     $data['doctor_id'] ?? $appointment->doctor_id,
            //     $data['resource_id'] ?? $appointment->resource_id,
            //     $scheduleData['scheduled_date'],
            //     $scheduleData['scheduled_time'],
            //     $id
            // );

            // if ($hasConflict) {
            //     throw AppointmentException::scheduleConflict();
            // }

            $updateData = array_merge($scheduleData, [
                'updated_by' => $this->getUserId(),
                'updated_at' => Carbon::now(),
            ]);

            if (isset($data['doctor_id'])) {
                $updateData['doctor_id'] = $data['doctor_id'];
            }

            if (isset($data['resource_id'])) {
                $updateData['resource_id'] = $data['resource_id'];
            }

            // Resolve resource_id and resource_has_rota_day_id from the doctor's rota for the scheduled date
            $resolvedDoctorId = $updateData['doctor_id'] ?? $appointment->doctor_id;
            $resolvedDate = $scheduleData['scheduled_date'] ?? null;
            $resolvedLocationId = $data['location_id'] ?? $appointment->location_id;

            if ($resolvedDoctorId && $resolvedDate) {
                $resource = Resources::where([
                    'external_id' => $resolvedDoctorId,
                    'resource_type_id' => Config::get('constants.resource_doctor_type_id'),
                    'account_id' => $accountId,
                ])->first();

                $rotaDay = $resource
                    ? ResourceHasRotaDays::getSingleDayRotaWithResourceID(
                        $resource->id,
                        $resolvedDate,
                        $accountId,
                        $resolvedLocationId
                    )
                    : null;

                // Block scheduling when the doctor has no rota on the
                // chosen date for the chosen centre. Pre-fix, an empty
                // rota silently fell through and the appointment landed
                // with `resource_has_rota_day_id = NULL`, which broke
                // downstream calendar/utilization queries. Scoped to
                // consultancy because treatments allow off-rota slots.
                if (
                    $appointment->appointment_type_id == AppointmentType::Consultancy->value
                    && empty($rotaDay)
                ) {
                    $dateLabel = Carbon::parse($resolvedDate)->format('Y-m-d');
                    throw AppointmentException::invalidData(
                        "Doctor rota is not defined for {$dateLabel} at the selected centre."
                    );
                }

                if ($resource) {
                    $updateData['resource_id'] = $resource->id;
                    if (! empty($rotaDay)) {
                        $updateData['resource_has_rota_day_id'] = $rotaDay['id'];
                    }
                }

                // Time-of-day validation. A rota existing for the date is NOT
                // enough — the chosen TIME must fall within the doctor's shift
                // (and outside breaks / time-off). The reschedule path used to
                // skip this, so an appointment could be moved past the doctor's
                // end time (e.g. after 9 PM for an 11 AM–9 PM shift). Reuse the
                // exact check the create path runs.
                //
                // Applies to BOTH consultancy and treatment. The check is
                // permissive when the doctor has no rota that day (the widget
                // returns OK), so treatments keep their off-rota flexibility —
                // but when a shift IS defined, the time must fall within it.
                $this->validateRotaAvailability([
                    'scheduled_date' => $scheduleData['scheduled_date'] ?? null,
                    'scheduled_time' => $scheduleData['scheduled_time'] ?? null,
                    'start' => $data['start'] ?? null,
                    'doctor_id' => $resolvedDoctorId,
                    'location_id' => $resolvedLocationId,
                    'city_id' => $appointment->city_id ?? '',
                ]);
            }

            if (isset($data['reschedule']) && $data['reschedule']) {
                $updateData['converted_by'] = $this->getUserId();
            }

            // Capture pre-update snapshot for the activity log — we need
            // the OLD date/time/doctor to render the "rescheduled from X
            // to Y" line. Only emit a log when something actually moved.
            // `scheduled_date` is cast to `date` (Carbon) on the model
            // and `scheduled_time` is a raw string — normalise both to
            // a canonical format before comparing so a Carbon vs string
            // mismatch doesn't fire a false-positive "rescheduled" log.
            $oldScheduledDateRaw = $appointment->scheduled_date;
            $oldScheduledTimeRaw = $appointment->scheduled_time;
            $oldDoctorId = $appointment->doctor_id;
            $oldDateNorm = $oldScheduledDateRaw ? Carbon::parse($oldScheduledDateRaw)->format('Y-m-d') : null;
            $oldTimeNorm = $oldScheduledTimeRaw ? Carbon::parse($oldScheduledTimeRaw)->format('H:i:s') : null;

            // Reschedule SMS: a DATE change (not a time-only move) on a
            // still-Booked appointment re-arms the booking-confirmation
            // SMS so the patient is re-notified. The same cron
            // (appointment:deliver-on-appointment-book) + active-template
            // pipeline then delivers it exactly as it did for the original
            // booking. A time-only move and any change on an
            // Arrived/Converted row never re-notify.
            $rescheduleNewDate = isset($updateData['scheduled_date'])
                ? Carbon::parse($updateData['scheduled_date'])->format('Y-m-d')
                : $oldDateNorm;
            if (
                $oldDateNorm !== $rescheduleNewDate
                && (int) $appointment->base_appointment_status_id === (int) Config::get('constants.appointment_status_pending', 1)
            ) {
                $updateData['send_message'] = 1;
            }

            // A reschedule resets the workflow status: anything that isn't
            // Arrived or Converted goes back to the default (Pending) so the
            // moved appointment starts fresh on the new date. Arrived /
            // Converted are preserved (real progress). Mirrors the treatment
            // drag-drop path; the helper is the single source of this rule.
            $updateData = array_merge(
                $updateData,
                AppointmentStatuses::resetStatusOnReschedule(
                    (int) $appointment->base_appointment_status_id,
                    $accountId,
                ),
            );

            $appointment->update($updateData);

            $newScheduledDate = $updateData['scheduled_date'] ?? $oldDateNorm;
            $newScheduledTime = $updateData['scheduled_time'] ?? $oldTimeNorm;
            $newDoctorId = $updateData['doctor_id'] ?? $oldDoctorId;
            $newDateNorm = $newScheduledDate ? Carbon::parse($newScheduledDate)->format('Y-m-d') : null;
            $newTimeNorm = $newScheduledTime ? Carbon::parse($newScheduledTime)->format('H:i:s') : null;

            $dateChanged = $oldDateNorm !== $newDateNorm;
            $timeChanged = $oldTimeNorm !== $newTimeNorm;
            $doctorChanged = (int) $oldDoctorId !== (int) $newDoctorId;

            if ($dateChanged || $timeChanged || $doctorChanged) {
                $patient = Patients::find($appointment->patient_id);
                $location = Locations::with('city')->find($appointment->location_id);
                $service = Services::find($appointment->service_id);

                if ($patient && ($dateChanged || $timeChanged)) {
                    ActivityLogger::logAppointmentRescheduled(
                        $appointment,
                        $patient,
                        $oldDateNorm ?? $newDateNorm,
                        $oldTimeNorm ?? $newTimeNorm,
                        $newDateNorm,
                        $newTimeNorm,
                        $location,
                        $service,
                    );
                }

                if ($doctorChanged && $patient) {
                    $oldDoctor = User::find($oldDoctorId);
                    $newDoctor = User::find($newDoctorId);
                    ActivityLogger::logAppointmentUpdated(
                        $appointment,
                        $patient,
                        [
                            'Doctor' => [
                                'old' => $oldDoctor?->name ?? 'Unknown',
                                'new' => $newDoctor?->name ?? 'Unknown',
                            ],
                        ],
                        $location,
                        $service,
                    );
                }

                $screen = $appointment->appointment_type_id === AppointmentType::Consultancy->value
                    ? 'Consultancy'
                    : 'Treatment';
                ActivityLogger::saveAppointmentLogs('rescheduled', $screen, $appointment);
            }

            AppointmentHelper::clearAppointmentCache($this->getAccountId());

            DB::commit();

            return $appointment->fresh(['doctor', 'resource', 'location']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getAppointmentById(int $id): ?Appointments
    {
        $appointment = Appointments::with([
            'appointment_type',
            'appointment_status',
            'appointment_status_base',
            'service',
            'location.city',
            'doctor',
            'patient',
            'lead',
            'user',
            'user_converted_by',
            'user_updated_by',
            'cancellation_reason',
            'appointment_comments',
            'sms_logs',
            'packageadvance',
            'packages',
            'hasInvoices',
        ])->where([
            'id' => $id,
            'account_id' => $this->getAccountId(),
        ])
            // Centre ACL: a guessed id outside the user's assigned
            // centres surfaces as a clean 404 rather than an
            // information-leaky 200 with cross-centre data. Hardens
            // every show / update / delete / status / schedule path
            // that flows through this helper.
            ->whereIn('appointments.location_id', \App\Helpers\ACL::getUserCentres())
            ->first();

        if (! $appointment) {
            throw AppointmentException::notFound();
        }

        return $appointment;
    }

    protected function validateAppointmentData(array $data): void
    {
        if (isset($data['location_id'])) {
            $location = Locations::find($data['location_id']);
            if (! $location) {
                throw AppointmentException::invalidLocation();
            }
        }

        if (isset($data['doctor_id'])) {
            $doctor = User::find($data['doctor_id']);
            if (! $doctor) {
                throw AppointmentException::invalidDoctor();
            }
        }

        if (isset($data['service_id'])) {
            $service = Services::find($data['service_id']);
            if (! $service) {
                throw AppointmentException::invalidService();
            }
        }

        // Validate rota for consultancy appointments
        if (isset($data['appointment_type_id']) && $data['appointment_type_id'] == AppointmentType::Consultancy->value) {
            $this->validateRotaAvailability($data);
        }
    }

    protected function validateRotaAvailability(array $data): void
    {
        \Log::info('validateRotaAvailability called', [
            'has_scheduled_date' => isset($data['scheduled_date']),
            'has_scheduled_time' => isset($data['scheduled_time']),
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'scheduled_time' => $data['scheduled_time'] ?? null,
            'start' => $data['start'] ?? null,
        ]);

        $object = new \stdClass;

        // If we have scheduled_time but no scheduled_date, extract date from start
        if (! isset($data['scheduled_date']) && isset($data['start'])) {
            $data['scheduled_date'] = Carbon::parse($data['start'])->format('Y-m-d');
            \Log::info('Extracted scheduled_date from start', ['scheduled_date' => $data['scheduled_date']]);
        }

        // Build start datetime from scheduled_date and scheduled_time
        if (isset($data['scheduled_date']) && isset($data['scheduled_time'])) {
            $object->start = $data['scheduled_date'].'T'.Carbon::parse($data['scheduled_time'])->format('H:i:s');
            \Log::info('Using scheduled_date and scheduled_time', ['object_start' => $object->start]);
        } elseif (isset($data['start'])) {
            $object->start = $data['start'];
            \Log::info('Using start parameter', ['object_start' => $object->start]);
        } else {
            \Log::info('No time to validate, returning');

            return; // No time to validate
        }

        $object->city_id = $data['city_id'] ?? '';
        $object->doctor_id = $data['doctor_id'] ?? null;
        $object->location_id = $data['location_id'] ?? null;
        $object->appointment_type = 'consulting';

        $rota = AppointmentCheckesWidget::AppointmentConsultancyCheckes($object);

        if (! $rota['status']) {
            throw AppointmentException::invalidData($rota['message'] ?? 'Doctor rota is not available for the selected time.');
        }
    }

    public function getAppointmentStatistics(array $filters = []): array
    {
        // Centre-ACL hash bakes the user's accessible centre set into
        // the cache key so two users on the same account with
        // different centre allocations don't share each other's tile
        // counts.
        $userCentres = \App\Helpers\ACL::getUserCentres();
        $cacheKey = "appointment_stats_{$this->getAccountId()}_"
            . md5(json_encode($filters))
            . '_' . md5(json_encode($userCentres));

        return Cache::remember($cacheKey, 300, function () use ($filters, $userCentres) {
            $query = Appointments::where('account_id', $this->getAccountId())
                ->whereIn('location_id', $userCentres);
            $query = $this->applyFilters($query, $filters);

            return [
                'total' => $query->count(),
                'scheduled' => (clone $query)->whereNotNull('scheduled_date')->count(),
                'non_scheduled' => (clone $query)->whereNull('scheduled_date')->count(),
                'today' => (clone $query)->whereDate('scheduled_date', Carbon::today())->count(),
                'this_week' => (clone $query)->whereBetween('scheduled_date', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek(),
                ])->count(),
                'this_month' => (clone $query)->whereBetween('scheduled_date', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth(),
                ])->count(),
            ];
        });
    }
}
