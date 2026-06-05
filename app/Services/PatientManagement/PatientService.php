<?php

declare(strict_types=1);

namespace App\Services\PatientManagement;

use App\Helpers\ACL;
use App\Helpers\ActivityLogger;
use App\Helpers\DoctorDashboardHelper;
use App\Helpers\Filters;
use App\Helpers\PatientAccessScope;
use App\Models\Activity;
use App\Models\Appointments;
use App\Models\AuditTrails;
use App\Models\Documents;
use App\Models\InvoiceStatuses;
use App\Models\Leads;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\PackageVouchers;
use App\Models\PatientNote;
use App\Models\Patients;
use App\Models\UserVouchers;
use App\Services\ActivityLogService;
use App\Services\Phone\PhoneFormattingService;
use App\Services\Reports\Concerns\ParsesDateRange;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PatientService
{
    use ParsesDateRange;

    public function __construct(
        // Tier-1 list-enrichment metrics. Composes AtRiskPatientsMetric for
        // the at-risk badge so the dashboard's signal definition stays the
        // single source of truth — no duplicate rule logic on the patient
        // datatable.
        private readonly PatientLifecycleMetric $lifecycleMetric,
    ) {}

    private const FILTER_KEY = 'patients';

    private const CACHE_TTL = 300;

    private const MEMBERSHIP_TYPES_CACHE_KEY = 'active_membership_types';

    // Round 4 C3 — files now live under storage/app/patient_image/ (the 'local'
    // disk), NOT storage/app/public/patient_image/. The public/storage symlink
    // therefore no longer exposes them; reads must go through the authenticated
    // PatientFileController route.
    private const STORAGE_PATH = 'patient_image';

    private const STORAGE_DIR = 'patient_image';

    private const MAX_REFERRALS_PER_CODE = 2;

    private const GOLD_MEMBERSHIP_NAME = 'gold membership';

    private const AUDIT_FILLABLE = [
        'name', 'email', 'phone', 'main_account', 'gender',
        'cnic', 'dob', 'address', 'referred_by', 'user_type_id',
    ];

    private const FIELD_LABELS = [
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'gender' => 'Gender',
        'dob' => 'Date of Birth',
        'address' => 'Address',
        'cnic' => 'CNIC',
    ];

    /*
    |--------------------------------------------------------------------------
    | Datatable
    |--------------------------------------------------------------------------
    */

    public function getDatatableData(Request $request): array
    {
        $accountId = Auth::user()->account_id;
        $userId = Auth::user()->id;
        $filters = getFilters($request->all());
        $applyFilter = checkFilters($filters, self::FILTER_KEY);

        $records = ['data' => []];

        if (hasFilter($filters, 'delete')) {
            $deleteResult = $this->bulkDelete(explode(',', $filters['delete']), $accountId);
            $records['status'] = $deleteResult['status'];
            $records['message'] = $deleteResult['message'];
        }

        $baseQuery = $this->buildOptimizedQuery($accountId, $applyFilter, $filters, $userId);

        $iTotalRecords = (clone $baseQuery)->count();

        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $patients = (clone $baseQuery)
            ->with(['membership:id,patient_id,code,membership_type_id,end_date,active,is_referral'])
            ->select(['id', 'name', 'email', 'phone', 'gender', 'active', 'created_at', 'id as patient_id'])
            ->orderByDesc('created_at')
            ->offset($iDisplayStart)
            ->limit($iDisplayLength)
            ->get();

        // Tier-1 enrichment — at-risk + last visit + LTV + outstanding +
        // active-packages-count + next-appointment, all batched per page
        // (not per-patient) so the cost stays bounded against the 174k
        // total patient set. At-risk composes AtRiskPatientsMetric so the
        // dashboard's signal definition stays the canonical source.
        if ($patients->isNotEmpty()) {
            $pageIds = $patients->pluck('id')->all();
            $metrics = $this->lifecycleMetric->batch($pageIds);
            foreach ($patients as $p) {
                $m = $metrics[(int) $p->id] ?? null;
                if (! $m) {
                    continue;
                }
                $p->is_at_risk            = $m['is_at_risk'];
                $p->last_visit_at         = $m['last_visit_at'];
                $p->last_visit_days_ago   = $m['last_visit_days_ago'];
                $p->lifetime_value        = $m['lifetime_value'];
                $p->outstanding_balance   = $m['outstanding_balance'];
                $p->active_packages_count = $m['active_packages_count'];
                $p->next_appointment_at   = $m['next_appointment_at'];
            }
        }

        $records = $this->getFiltersDataCached($records, $userId);

        if ($patients->isNotEmpty()) {
            $records['data'] = $patients;
            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];
        }

        $records['permissions'] = $this->getPermissions();

        return $records;
    }

    private function buildOptimizedQuery(int $accountId, bool $applyFilter, array $filters, int $userId): Builder
    {
        $query = Patients::query()
            ->where('user_type_id', Config::get('constants.patient_id'))
            ->where('account_id', $accountId);

        if (! Gate::allows('patients.list.view_inactive')) {
            $query->where('active', 1);
        }

        $user = Auth::user();
        $userCentres = ACL::getUserCentres();

        // Doctor roles: only show patients from their own appointments
        if ($user && $user->hasAnyRole(DoctorDashboardHelper::DOCTOR_ROLE_NAMES)) {
            $query->whereExists(function ($sub) use ($user): void {
                $sub->select(DB::raw(1))
                    ->from('appointments')
                    ->whereColumn('appointments.patient_id', 'users.id')
                    ->where('appointments.doctor_id', $user->id);
            });
        } elseif (! empty($userCentres)) {
            $query->whereExists(function ($sub) use ($userCentres): void {
                $sub->select(DB::raw(1))
                    ->from('appointments')
                    ->whereColumn('appointments.patient_id', 'users.id')
                    ->whereIn('appointments.location_id', $userCentres);
            });
        }

        $this->applyOptimizedFilters($query, $filters, $applyFilter, $userId);

        if (isset($filters['membership'])) {
            Filters::put($userId, self::FILTER_KEY, 'memberships', $filters['membership']);
            $query->whereExists(function ($sub) use ($filters): void {
                $sub->select(DB::raw(1))
                    ->from('memberships')
                    ->whereColumn('memberships.patient_id', 'users.id')
                    ->where('memberships.membership_type_id', $filters['membership']);
            });
        }

        return $query;
    }

    private function applyOptimizedFilters(Builder $query, array $filters, bool $applyFilter, int $userId): void
    {
        // Unified search field — same engine the Plans datatable, picker
        // typeahead, and invoices typeahead use. The SPA sends a single
        // `q` value; PatientSearchService::applyPatientFilter classifies
        // and routes it (id / phone / FT name). The legacy `patient_id`
        // / `name` / `phone` keys below are kept for any caller that
        // still sends them.
        $this->applyFilter($query, $filters, $applyFilter, $userId, 'q', function ($builder, $value): void {
            PatientSearchService::applyPatientFilter($builder, (string) $value, 'id', Auth::user()->account_id);
        });

        $this->applyFilter($query, $filters, $applyFilter, $userId, 'patient_id', function ($q, $value): void {
            $q->where('id', 'like', '%'.PatientSearchService::patientSearch($value).'%');
        });

        $this->applyFilter($query, $filters, $applyFilter, $userId, 'name', function ($q, $value): void {
            $q->where('name', 'like', "%{$value}%");
        });

        $this->applyFilter($query, $filters, $applyFilter, $userId, 'gender', function ($q, $value): void {
            $q->where('gender', $value);
        });

        $this->applyFilter($query, $filters, $applyFilter, $userId, 'phone', function ($q, $value): void {
            $q->where('phone', 'like', '%'.PhoneFormattingService::cleanNumber($value).'%');
        });

        $this->applyFilter($query, $filters, $applyFilter, $userId, 'status', function ($q, $value): void {
            if ($value !== null && ($value == 0 || $value == 1)) {
                $q->where('active', $value);
            }
        });

        if (hasFilter($filters, 'created_at')) {
            $query->whereBetween('created_at', self::parseDateRangeWithTimeBounds($filters['created_at']));
            Filters::put($userId, self::FILTER_KEY, 'created_at', $filters['created_at']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'created_at');
        }
    }

    private function applyFilter(Builder $query, array $filters, bool $applyFilter, int $userId, string $key, callable $callback): void
    {
        if (hasFilter($filters, $key)) {
            $callback($query, $filters[$key]);
            Filters::put($userId, self::FILTER_KEY, $key, $filters[$key]);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, $key);
        } elseif ($storedValue = Filters::get($userId, self::FILTER_KEY, $key)) {
            $callback($query, $storedValue);
        }
    }

    private function getFiltersDataCached(array $records, int $userId): array
    {
        $memberships = Cache::remember(self::MEMBERSHIP_TYPES_CACHE_KEY, self::CACHE_TTL, fn () => MembershipType::where('active', 1)->pluck('id', 'name'));

        $records['filter_values'] = [
            'gender' => config('constants.gender_array'),
            'status' => config('constants.status'),
            'memberships' => $memberships,
        ];

        $filters = Filters::all($userId, self::FILTER_KEY);

        if (isset($filters['created_from'])) {
            $filters['created_from'] = date('Y-m-d', strtotime($filters['created_from']));
        }
        if (isset($filters['created_to'])) {
            $filters['created_to'] = date('Y-m-d', strtotime($filters['created_to']));
        }

        $records['active_filters'] = $filters;

        return $records;
    }

    public function getPermissions(): array
    {
        return [
            'edit' => Gate::allows('patients.edit'),
            'delete' => Gate::allows('patients.delete'),
            'active' => Gate::allows('patients.activate'),
            'inactive' => Gate::allows('patients.deactivate'),
            'manage' => Gate::allows('patients.card.view'),
            // Patient-module contact gate — replaces the cross-module
            // `contact` perm for surfaces inside the patients module so the
            // role-editor toggle `patients.list.view_contact` is the
            // authority here. The legacy `contact` perm still gates phone
            // visibility everywhere else (leads, treatments, appointments,
            // reports, exports) and will be re-pointed in those modules'
            // own audit passes.
            'contact' => Gate::allows('patients.list.view_contact'),
            'add_referrals' => Gate::allows('patients.referral.add'),
            // Inline action gates for the profile tab — the SPA hides the
            // matching buttons when these are false. Server-side
            // enforcement happens in PatientController and is the security
            // boundary; these flags are purely a UX hint.
            'assign_membership' => Gate::allows('patients.membership.assign'),
            'assign_voucher' => Gate::allows('patients.voucher.assign'),
            'view_voucher_history' => Gate::allows('patients.vouchers.view_history'),
            'notes_view' => Gate::allows('patients.notes.view'),
            'notes_create' => Gate::allows('patients.notes.create'),
            'notes_pin' => Gate::allows('patients.notes.pin'),
            'notes_manage' => Gate::allows('patients.notes.manage'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD Operations
    |--------------------------------------------------------------------------
    */

    // create() and getCreateData() removed: patients are never created
    // directly through this service. They appear as a side-effect of
    // booking flows (AppointmentService::create handles the User::create
    // with user_type_id=3 and the paired Lead row).

    public function getEditData(int $id): ?array
    {
        $patient = $this->findPatient($id);

        return $patient ? ['patient' => $patient, 'gender' => config('constants.gender_array')] : null;
    }

    public function update(int $id, array $data): array
    {
        if (($data['phone'] ?? '') === '***********' && isset($data['old_phone'])) {
            $data['phone'] = $data['old_phone'];
        }

        $oldPhone = $data['old_phone'] ?? null;
        unset($data['old_phone']);

        $data['phone'] = PhoneFormattingService::cleanNumber($data['phone']);

        $oldPatient = $this->findPatient($id);
        $oldValues = $oldPatient ? array_intersect_key(
            $oldPatient->toArray(),
            array_flip(array_keys(self::FIELD_LABELS))
        ) : [];

        $patient = $this->updatePatientRecord($id, $data);

        if (! $patient) {
            return ['status' => false, 'message' => 'Something went wrong, please try again later.'];
        }

        Appointments::where('patient_id', $id)->update(['name' => $data['name']]);

        if ($oldPhone) {
            Leads::where('phone', $oldPhone)->update([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'gender' => $data['gender'] ?? null,
            ]);
        }

        $fieldChanges = $this->detectFieldChanges($oldValues, $data);

        if (! empty($fieldChanges)) {
            ActivityLogger::logPatientUpdated($patient, $fieldChanges);
        }

        return ['status' => true, 'message' => 'Record has been updated successfully.'];
    }

    public function delete(int $id): array
    {
        $accountId = Auth::user()->account_id;
        $patient = $this->findPatient($id);

        if (! $patient) {
            return ['status' => false, 'message' => 'Resource not found.'];
        }

        if ($this->hasChildRecords($id, $accountId)) {
            $childList = implode(', ', Patients::getChildRecordsDetails($id, $accountId));

            return ['status' => false, 'message' => "Cannot delete patient. Related records exist: {$childList}"];
        }

        $patient->delete();
        AuditTrails::deleteEventLogger('users', 'delete', self::AUDIT_FILLABLE, $id);

        return ['status' => true, 'message' => 'Record has been deleted successfully.'];
    }

    public function bulkDelete(array $ids, int $accountId): array
    {
        $patients = Patients::whereIn('id', $ids)->where('account_id', $accountId)->get();

        $deletedCount = 0;
        $skippedPatients = [];

        foreach ($patients as $patient) {
            if (! $this->hasChildRecords($patient->id, $accountId)) {
                $patient->delete();
                $deletedCount++;
            } else {
                $childDetails = Patients::getChildRecordsDetails($patient->id, $accountId);
                $skippedPatients[] = "C-{$patient->id} ({$patient->name}): ".implode(', ', $childDetails);
            }
        }

        return match (true) {
            $deletedCount > 0 && empty($skippedPatients) => [
                'status' => true,
                'message' => "{$deletedCount} patient(s) deleted successfully!",
            ],
            $deletedCount > 0 => [
                'status' => true,
                'message' => "{$deletedCount} patient(s) deleted. Skipped ".count($skippedPatients).' patient(s) with related records: '.implode('; ', $skippedPatients),
            ],
            default => [
                'status' => false,
                'message' => 'Cannot delete patient(s). Related records exist: '.implode('; ', $skippedPatients),
            ],
        };
    }

    public function changeStatus(int $id, int $status): array
    {
        $patient = $this->findPatient($id);

        if (! $patient) {
            return ['status' => false, 'message' => 'Resource not found.'];
        }

        $patient->update(['active' => $status]);

        $action = $status ? 'active' : 'inactive';
        $method = $status ? 'activeEventLogger' : 'inactiveEventLogger';
        AuditTrails::$method('users', $action, self::AUDIT_FILLABLE, $id);

        return [
            'status' => true,
            'message' => $status ? 'Record has been activated successfully.' : 'Record has been inactivated successfully.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Patient Retrieval
    |--------------------------------------------------------------------------
    */

    public function getPatient(int $id): ?array
    {
        $patient = $this->findPatient($id);

        if (! $patient) {
            return null;
        }

        $membership = Membership::with('membershipType')
            ->where('patient_id', $patient->id)
            ->where('active', 1)
            ->first();

        return [
            'patient' => $patient,
            'membership' => $membership ? [
                'code' => $membership->code,
                'type' => $membership->membershipType?->name ?? 'Unknown',
                'start_date' => $membership->start_date,
                'end_date' => $membership->end_date,
                'is_active' => $membership->end_date >= now()->format('Y-m-d'),
            ] : null,
            'permissions' => $this->getPermissions(),
        ];
    }

    /**
     * Fetch the most recent appointment's location for a patient.
     *
     * Mirrors Admin\PatientsController::getLastAppointmentLocation — same
     * query, same scoping, exposed for API consumers.
     *
     * @return array{location_id:int,location_name:?string,location_slug:?string,last_appointment_at:?string}|null
     */
    public function lastAppointmentLocation(int $patientId, ?string $appointmentType = null): ?array
    {
        $appointmentTypeId = match ($appointmentType) {
            'consultancy' => Config::get('constants.consultancy_id'),
            'treatment' => Config::get('constants.treatment_id'),
            default => null,
        };

        $lastAppointment = Appointments::with('location:id,name,slug')
            ->where('patient_id', $patientId)
            ->where('account_id', Auth::user()->account_id)
            ->when($appointmentTypeId, fn ($q) => $q->where('appointment_type_id', $appointmentTypeId))
            ->orderByDesc('created_at')
            ->first();

        if (! $lastAppointment?->location_id) {
            return null;
        }

        $location = $lastAppointment->location;

        return [
            'location_id' => (int) $lastAppointment->location_id,
            'location_name' => $location?->getAttribute('name'),
            'location_slug' => $location?->getAttribute('slug'),
            'last_appointment_at' => $lastAppointment->created_at
                ? Carbon::parse($lastAppointment->created_at)->utc()->toIso8601String()
                : null,
        ];
    }

    /**
     * Resolve the centre to launch the "New consultation" calendar at
     * for a patient: the location of their most recent ARRIVED
     * consultation; failing that, the most recent consultation of any
     * status; null when the patient has no consultation history.
     *
     * @return array{location_id:int,location_name:?string,location_slug:?string,last_appointment_at:?string}|null
     */
    public function consultationLaunchLocation(int $patientId): ?array
    {
        return $this->launchLocationForType(
            $patientId,
            (int) Config::get('constants.appointment_type_consultancy'),
        );
    }

    /**
     * Treatment twin of consultationLaunchLocation(): the centre of the
     * patient's last ARRIVED treatment, falling back to their most recent
     * treatment of any status; null when they have no treatment history.
     * Drives the patient card's "New treatment" button.
     *
     * @return array{location_id:int,location_name:?string,location_slug:?string,last_appointment_at:?string}|null
     */
    public function treatmentLaunchLocation(int $patientId): ?array
    {
        return $this->launchLocationForType(
            $patientId,
            (int) Config::get('constants.appointment_type_service'),
        );
    }

    /**
     * Shared resolver for the "New {consultation,treatment}" launch
     * buttons: the location of the patient's most recent ARRIVED
     * appointment of the given type; failing that, the most recent of
     * any status; null when they have no history of that type.
     *
     * "Most recent" is by scheduled_date (the visit date operators
     * reason in), tie-broken by id. Account- and type-scoped the same
     * way as getPatientAppointmentsByType so it can't surface
     * cross-account rows. Uses the live `appointment_type_*` constants
     * rather than the empty legacy `consultancy_id` the deprecated
     * lastAppointmentLocation() relies on.
     *
     * @return array{location_id:int,location_name:?string,location_slug:?string,last_appointment_at:?string}|null
     */
    private function launchLocationForType(int $patientId, int $appointmentTypeId): ?array
    {
        $accountId = Auth::user()->account_id;

        $base = Appointments::with('location:id,name,slug')
            ->where('patient_id', $patientId)
            ->where('account_id', $accountId)
            ->where('appointment_type_id', $appointmentTypeId)
            ->whereNull('deleted_at');

        $arrivedStatusIds = DB::table('appointment_statuses')
            ->where('account_id', $accountId)
            ->where('is_arrived', 1)
            ->pluck('id')
            ->all();

        // Prefer the last ARRIVED appointment. When no arrived status
        // is configured the whereIn is skipped, which collapses this to
        // the same query as the fallback — harmless (idempotent).
        $appointment = (clone $base)
            ->when($arrivedStatusIds !== [], fn ($q) => $q->whereIn('appointment_status_id', $arrivedStatusIds))
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->first();

        // No arrived appointment → fall back to the most recent of any
        // status (product decision).
        if (! $appointment) {
            $appointment = (clone $base)
                ->orderByDesc('scheduled_date')
                ->orderByDesc('id')
                ->first();
        }

        if (! $appointment?->location_id) {
            return null;
        }

        $location = $appointment->location;

        return [
            'location_id' => (int) $appointment->location_id,
            'location_name' => $location?->getAttribute('name'),
            'location_slug' => $location?->getAttribute('slug'),
            'last_appointment_at' => $appointment->scheduled_date
                ? Carbon::parse($appointment->scheduled_date)->utc()->toIso8601String()
                : null,
        ];
    }

    public function getTabCounts(int $patientId): ?array
    {
        $patient = $this->findPatient($patientId);

        if (! $patient) {
            return null;
        }

        $accountId = Auth::user()->account_id;
        $userCentres = ACL::getUserCentres();

        return [
            'appointments' => $patient->appointments()
                ->where('account_id', $accountId)
                ->when(! empty($userCentres), fn ($q) => $q->whereIn('location_id', $userCentres))
                ->count(),
            'consultations' => $patient->appointments()
                ->where('account_id', $accountId)
                ->where('appointment_type_id', 1)
                ->when(! empty($userCentres), fn ($q) => $q->whereIn('location_id', $userCentres))
                ->count(),
            'treatments' => $patient->appointments()
                ->where('account_id', $accountId)
                ->where('appointment_type_id', 2)
                ->when(! empty($userCentres), fn ($q) => $q->whereIn('location_id', $userCentres))
                ->count(),
            'vouchers' => UserVouchers::where('user_id', $patientId)->count(),
            'documents' => $patient->documents()->count(),
            'plans' => $patient->packages()
                ->where('account_id', $accountId)
                ->when(! empty($userCentres), fn ($q) => $q->whereIn('location_id', $userCentres))
                ->count(),
            'invoices' => $patient->invoices()
                ->where('account_id', $accountId)
                ->when(! empty($userCentres), fn ($q) => $q->whereIn('location_id', $userCentres))
                ->count(),
            'refunds' => DB::table('package_advances')->where('patient_id', $patientId)->where('is_refund', 1)->count(),
            'activity_logs' => Activity::where('patient_id', $patientId)->whereNotNull('description')->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Membership & Voucher Operations
    |--------------------------------------------------------------------------
    */

    public function assignMembership(int $patientId, string $membershipCode): array
    {
        if (Membership::where('patient_id', $patientId)->exists()) {
            return ['status' => false, 'message' => 'A membership is already assigned to this patient.'];
        }

        $membership = Membership::with('membershipType')
            ->where('code', $membershipCode)
            ->where('active', 1)
            ->whereNull('patient_id')
            ->first();

        if (! $membership) {
            return ['status' => false, 'message' => 'Membership is inactive or already assigned to a patient.'];
        }

        $now = Carbon::now();
        $membership->update([
            'patient_id' => $patientId,
            'start_date' => $now->format('Y-m-d'),
            'end_date' => $now->copy()->addDays($membership->membershipType->period)->format('Y-m-d'),
            'assigned_at' => $now->format('Y-m-d'),
        ]);

        $patient = Patients::find($patientId);
        if ($patient) {
            ActivityLogger::logMembershipAssigned($patient, $membership, $membership->membershipType);
        }

        return ['status' => true, 'message' => 'Membership assigned successfully.'];
    }

    public function assignVoucher(int $patientId, int $voucherId, float $amount): array
    {
        UserVouchers::create([
            'user_id' => $patientId,
            'voucher_id' => $voucherId,
            'amount' => $amount,
            'total_amount' => $amount,
        ]);

        return ['status' => true, 'message' => 'Voucher assigned successfully.'];
    }

    public function addReferral(int $patientId, string $membershipCode): array
    {
        $membership = Membership::with('membershipType')
            ->where('code', $membershipCode)
            ->where('active', 1)
            ->first();

        if (! $membership) {
            return ['status' => false, 'message' => 'Invalid membership code or membership is inactive.'];
        }

        $patient = Patients::find($patientId);
        if (! $patient) {
            return ['status' => false, 'message' => 'Patient not found.'];
        }

        if ($membership->patient_id == $patientId) {
            return ['status' => false, 'message' => 'Membership is already assigned to this patient.'];
        }

        if ($membership->patient_id === null) {
            return ['status' => false, 'message' => 'This membership code is not assigned to any patient, so referral cannot be added.'];
        }

        if (! $membership->membershipType) {
            return ['status' => false, 'message' => 'Membership type not found.'];
        }

        if (strtolower(trim($membership->membershipType->name)) !== self::GOLD_MEMBERSHIP_NAME) {
            return ['status' => false, 'message' => 'Referrals can only be created for Gold Membership type. Current membership type: '.$membership->membershipType->name];
        }

        if ($membership->patient_id != $patientId && Carbon::parse($membership->end_date)->isPast()) {
            return ['status' => false, 'message' => 'Membership is expired, referral cannot be added.'];
        }

        $existingReferrals = Membership::where('code', $membershipCode)->where('is_referral', 1)->count();
        if ($existingReferrals >= self::MAX_REFERRALS_PER_CODE) {
            return ['status' => false, 'message' => 'Maximum of '.self::MAX_REFERRALS_PER_CODE.' referrals allowed per membership code. Limit reached.'];
        }

        $referral = Membership::create([
            'code' => $membership->code,
            'membership_type_id' => $membership->membership_type_id,
            'start_date' => $membership->start_date,
            'end_date' => $membership->end_date,
            'patient_id' => $patientId,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'active' => 1,
            'assigned_at' => Carbon::now()->format('Y-m-d'),
            'is_referral' => 1,
            'parent_membership_code' => $membership->code,
        ]);

        return $referral
            ? ['status' => true, 'message' => 'Referral added successfully.', 'referral' => $referral, 'patient' => $patient]
            : ['status' => false, 'message' => 'Failed to add referral. Please try again.'];
    }

    /**
     * Read-only referral usage for a parent membership code — powers the
     * SPA's live "X of 2 used" hint in the Add-referral dialog so the user
     * is warned (and submit disabled) before a guaranteed-to-fail POST.
     * Uses the SAME count + limit as addReferral() to stay consistent.
     */
    public function referralInfo(string $membershipCode): array
    {
        $count = Membership::where('code', $membershipCode)->where('is_referral', 1)->count();

        return [
            'code' => $membershipCode,
            'count' => $count,
            'max' => self::MAX_REFERRALS_PER_CODE,
            'limit_reached' => $count >= self::MAX_REFERRALS_PER_CODE,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Image & Search
    |--------------------------------------------------------------------------
    */

    public function storeImage(int $patientId, UploadedFile $file): array
    {
        $patient = $this->findPatient($patientId);

        if (! $patient) {
            return ['status' => false, 'message' => 'Resource not found.'];
        }

        $fileName = $this->storeUploadedFile($file);

        DB::table('users')->where('id', $patient->id)->update(['image_src' => $fileName]);

        return [
            'status' => true,
            'message' => 'Picture saved successfully.',
            // Round 4 C3 — point at the authenticated streaming route, not the
            // bare storage symlink which no longer serves these files. The
            // `_api` suffix on the route name is the SPA-facing alias (the
            // legacy /admin/* route stays live until Blade cutover).
            'image' => route('admin.files.patient_image_api', ['filename' => $fileName]),
        ];
    }

    public function searchPatients(string $search, int $accountId): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }

        // Single classifier shared with PlanService::applyUnifiedSearch and
        // Patients::getPatientSearchOptimized. `short_id` is treated as a
        // patient id here (this endpoint serves the invoices typeahead and
        // similar non-Plans pickers — there's no plan-id ambiguity).
        $shape = PatientSearchService::classifySearchInput($search);

        $baseQuery = Patients::where('user_type_id', Config::get('constants.patient_id'))
            ->where('active', 1)
            ->where('account_id', $accountId);

        // Restrict typeahead to patients with an appointment at a centre the
        // caller is assigned to. No-op for super-admin or "All Centres" users;
        // forces 1=0 when the caller has zero assignments. Applied to the base
        // query so every cloned branch (id, phone, name) inherits it.
        PatientAccessScope::applyTo($baseQuery);

        // Mirror the contact-permission redaction already implemented in
        // Patients::getPatientSearchOptimized — callers without the patients
        // contact gate must receive rows with the phone key entirely absent
        // (not masked, not blank) so downstream renderers don't show a column.
        $canViewContact = Gate::allows('patients.list.view_contact');

        if ($shape['type'] === 'patient_code' || $shape['type'] === 'short_id') {
            $row = (clone $baseQuery)
                ->where('id', (int) $shape['digits'])
                ->select('name', 'id', 'phone')
                ->first();

            if (! $row) {
                return [];
            }
            $arr = $row->toArray();
            if (! $canViewContact) {
                unset($arr['phone']);
            }
            return [$arr];
        }

        $candidateIds = $shape['type'] === 'phone'
            ? PatientSearchService::resolvePatientIdsByPhone($shape['digits'], $accountId)
            : PatientSearchService::resolvePatientIdsByText($search, $accountId);

        if ($candidateIds === []) {
            return [];
        }

        // Preserve resolver order: MATCH relevance for text, prefix-match for
        // phone. Without FIELD() MariaDB would re-order by primary key.
        $orderExpr = 'FIELD(id, '.implode(',', array_fill(0, count($candidateIds), '?')).')';

        $rows = (clone $baseQuery)
            ->whereIn('id', $candidateIds)
            ->select('name', 'id', 'phone')
            ->orderByRaw($orderExpr, $candidateIds)
            ->limit(20)
            ->get()
            ->toArray();

        if (! $canViewContact) {
            $rows = array_map(static function (array $row): array {
                unset($row['phone']);
                return $row;
            }, $rows);
        }

        return $rows;
    }

    /*
    |--------------------------------------------------------------------------
    | Patient Notes
    |--------------------------------------------------------------------------
    */

    public function getNotes(int $patientId): ?array
    {
        $patient = $this->findPatient($patientId);

        if (! $patient) {
            return null;
        }

        $notes = PatientNote::where('patient_id', $patientId)
            ->with('creator:id,name')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->get();

        return ['patient' => $patient, 'notes' => $notes];
    }

    public function addNote(int $patientId, string $noteText): ?array
    {
        $patient = $this->findPatient($patientId);

        if (! $patient) {
            return null;
        }

        $note = PatientNote::create([
            'patient_id' => $patientId,
            'created_by' => Auth::id(),
            'note' => $noteText,
            'is_pinned' => false,
        ]);

        $note->load('creator:id,name');

        Activity::create([
            'account_id' => Auth::user()->account_id,
            'patient_id' => $patientId,
            'patient' => $patient->name,
            'activity_type' => 'note_added',
            'action' => 'added',
            'description' => '<span class="highlight">'.e(Auth::user()->name).'</span> added a note for <span class="highlight-orange">'.e($patient->name).'</span>',
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['patient' => $patient, 'note' => $note];
    }

    public function updateNote(int $patientId, int $noteId, string $noteText, bool $canManage): array
    {
        $patient = $this->findPatient($patientId);
        if (! $patient) {
            return ['status' => false, 'message' => 'Patient not found.', 'code' => 404];
        }

        $note = PatientNote::where('id', $noteId)->where('patient_id', $patientId)->first();
        if (! $note) {
            return ['status' => false, 'message' => 'Note not found.', 'code' => 404];
        }

        if (! $canManage && $note->created_by !== Auth::id()) {
            return ['status' => false, 'message' => 'You can only edit notes you created.', 'code' => 403];
        }

        $note->update(['note' => $noteText]);
        $note->load('creator:id,name');

        return ['status' => true, 'message' => 'Note updated successfully.', 'note' => $note];
    }

    public function deleteNote(int $patientId, int $noteId, bool $canManage): array
    {
        $patient = $this->findPatient($patientId);
        if (! $patient) {
            return ['status' => false, 'message' => 'Patient not found.', 'code' => 404];
        }

        $note = PatientNote::where('id', $noteId)->where('patient_id', $patientId)->first();
        if (! $note) {
            return ['status' => false, 'message' => 'Note not found.', 'code' => 404];
        }

        if (! $canManage && $note->created_by !== Auth::id()) {
            return ['status' => false, 'message' => 'You can only delete notes you created.', 'code' => 403];
        }

        $note->delete();

        return ['status' => true, 'message' => 'Note deleted successfully.'];
    }

    public function togglePinNote(int $patientId, int $noteId): array
    {
        $patient = $this->findPatient($patientId);
        if (! $patient) {
            return ['status' => false, 'message' => 'Patient not found.', 'code' => 404];
        }

        $note = PatientNote::where('id', $noteId)->where('patient_id', $patientId)->first();
        if (! $note) {
            return ['status' => false, 'message' => 'Note not found.', 'code' => 404];
        }

        $note->update(['is_pinned' => ! $note->is_pinned]);

        return [
            'status' => true,
            'message' => $note->is_pinned ? 'Note pinned.' : 'Note unpinned.',
            'note' => $note,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    */

    public function uploadDocument(int $patientId, UploadedFile $file, string $documentType): array
    {
        $patient = $this->findPatient($patientId);
        if (! $patient) {
            return ['status' => false, 'message' => 'Patient not found.', 'code' => 404];
        }

        $originalName = $file->getClientOriginalName();
        $fileName = $this->storeUploadedFile($file);
        $path = self::STORAGE_DIR.'/'.$fileName;

        $document = Documents::create([
            'name' => $originalName,
            'document_type' => $documentType,
            'url' => $path,
            'user_id' => $patient->id,
        ]);

        return [
            'status' => true,
            'message' => 'Document uploaded successfully.',
            'document' => $document,
        ];
    }

    public function updateDocument(int $patientId, int $documentId, string $documentType, ?UploadedFile $file = null): array
    {
        $patient = $this->findPatient($patientId);
        if (! $patient) {
            return ['status' => false, 'message' => 'Patient not found.', 'code' => 404];
        }

        $document = Documents::where('id', $documentId)->where('user_id', $patient->id)->first();
        if (! $document) {
            return ['status' => false, 'message' => 'Document not found.', 'code' => 404];
        }

        $document->document_type = $documentType;

        if ($file) {
            $this->deleteOldFile($document->url);
            $fileName = $this->storeUploadedFile($file);
            $document->url = self::STORAGE_DIR.'/'.$fileName;
        }

        $document->save();

        return [
            'status' => true,
            'message' => 'Document updated successfully.',
            'document' => $document,
        ];
    }

    public function listDocuments(int $patientId): array
    {
        $patient = $this->findPatient($patientId);
        if (! $patient) {
            return ['status' => false, 'message' => 'Patient not found.', 'code' => 404];
        }

        // Scoped to the (account-checked) patient via user_id, exactly
        // like updateDocument's lookup. SoftDeletes already excludes
        // trashed rows. Newest first so the tab shows recent uploads on top.
        $documents = Documents::where('user_id', $patient->id)
            ->orderByDesc('id')
            ->get();

        return [
            'status' => true,
            'message' => 'Documents retrieved successfully.',
            'documents' => $documents,
        ];
    }

    public function deleteDocument(int $patientId, int $documentId): array
    {
        $patient = $this->findPatient($patientId);
        if (! $patient) {
            return ['status' => false, 'message' => 'Patient not found.', 'code' => 404];
        }

        $document = Documents::where('id', $documentId)->where('user_id', $patient->id)->first();
        if (! $document) {
            return ['status' => false, 'message' => 'Document not found.', 'code' => 404];
        }

        // Best-effort physical-file removal, then soft-delete the row
        // (Documents uses SoftDeletes). Mirrors updateDocument's
        // deleteOldFile() handling of the new/legacy storage paths.
        $this->deleteOldFile($document->url);
        $document->delete();

        return [
            'status' => true,
            'message' => 'Document deleted successfully.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Activity & Voucher History
    |--------------------------------------------------------------------------
    */

    public function getActivityHistory(int $patientId): ?array
    {
        $patient = $this->findPatient($patientId);
        if (! $patient) {
            return null;
        }

        return ActivityLogService::getActivityLogs(['patient_id' => $patientId]);
    }

    public function getVoucherHistory(int $patientId, int $userVoucherId): array
    {
        $userVoucher = UserVouchers::with(['user', 'voucher'])->find($userVoucherId);

        if (! $userVoucher) {
            return ['status' => false, 'message' => 'Voucher not found.', 'code' => 404];
        }

        if ($userVoucher->user_id != $patientId) {
            return ['status' => false, 'message' => 'Voucher does not belong to this patient.'];
        }

        // Pull every redemption tied to this patient + voucher type. The
        // `package_vouchers.amount` column has occasionally been left null on
        // older rows, so when it's missing we cross-reference the matching
        // package_bundles row (same key the legacy `view_content.blade.php`
        // uses) and read `discount_price` as the deducted amount.
        $rawHistory = PackageVouchers::where('user_id', $patientId)
            ->where('voucher_id', $userVoucher->voucher_id)
            ->orderByDesc('created_at')
            ->get();

        $randomIds = $rawHistory->whereNull('package_id')->pluck('package_random_id')->filter()->unique();
        $packageIdMap = $randomIds->isNotEmpty()
            ? Packages::whereIn('random_id', $randomIds)->pluck('id', 'random_id')
            : collect();

        $serviceIds = $rawHistory->pluck('main_service_id')->filter()->unique();
        // `main_service_id` is a `services.id` (see `consumeVoucherForBundle`).
        // The earlier lookup hit the `bundles` table by the same id, which
        // only worked when `services.id` and `bundles.id` happened to
        // coincide for matching display names — fragile and wrong for
        // most services. Hit `services` first; fall back to `bundles`
        // only when an older journal row stored a bundle id (the
        // bundle-subtype save path also writes through this journal).
        $serviceNameMap = $serviceIds->isNotEmpty()
            ? DB::table('services')->whereIn('id', $serviceIds)->pluck('name', 'id')
            : collect();
        $missingNames = $serviceIds->diff($serviceNameMap->keys());
        if ($missingNames->isNotEmpty()) {
            $bundleNames = DB::table('bundles')->whereIn('id', $missingNames)->pluck('name', 'id');
            $serviceNameMap = $serviceNameMap->merge($bundleNames);
        }

        // Fallback amount lookup: PackageBundles keyed by (random_id, bundle_id)
        // for the rows where `package_vouchers.amount` is null/0.
        $voucherName = $userVoucher->voucher?->name;
        $allRandomIds = $rawHistory->pluck('package_random_id')->filter()->unique();
        $bundleAmountByKey = ($voucherName && $allRandomIds->isNotEmpty() && $serviceIds->isNotEmpty())
            ? PackageBundles::whereIn('random_id', $allRandomIds)
                ->whereIn('bundle_id', $serviceIds)
                ->where('discount_name', $voucherName)
                ->get()
                ->mapWithKeys(fn ($b): array => [
                    $b->random_id.'|'.$b->bundle_id => (float) ($b->discount_price ?? 0),
                ])
            : collect();

        $usageHistory = $rawHistory->map(function ($item) use ($packageIdMap, $serviceNameMap, $bundleAmountByKey): array {
            $packageId = $item->package_id ?: ($packageIdMap[$item->package_random_id] ?? null);
            $amount = (float) ($item->amount ?? 0);

            if ($amount <= 0) {
                $key = $item->package_random_id.'|'.$item->main_service_id;
                $amount = (float) ($bundleAmountByKey[$key] ?? 0);
            }

            return [
                'kind' => 'used',
                'package_id' => $packageId,
                'service_name' => $serviceNameMap[$item->main_service_id] ?? 'N/A',
                'amount_deducted' => $amount,
                'applied_date' => $item->created_at?->format('M d, Y h:i A') ?? '-',
            ];
        })->values()->all();

        // Append the assignment itself as a synthetic "issued" entry so the
        // dialog always has at least one line of context, even before the
        // voucher has been redeemed against any package.
        $usageHistory[] = [
            'kind' => 'issued',
            'package_id' => null,
            'service_name' => 'Voucher issued',
            'amount_deducted' => (float) ($userVoucher->total_amount ?? 0),
            'applied_date' => $userVoucher->created_at?->format('M d, Y h:i A') ?? '-',
        ];

        $totalAmount = (float) ($userVoucher->total_amount ?? 0);
        $currentBalance = (float) ($userVoucher->amount ?? 0);

        return [
            'status' => true,
            'message' => 'Voucher history retrieved successfully.',
            'data' => [
                'voucher_name' => $userVoucher->voucher?->name ?? 'N/A',
                'total_amount' => $totalAmount,
                'consumed_amount' => $totalAmount - $currentBalance,
                'balance' => $currentBalance,
                'history' => $usageHistory,
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Patient Appointments / Consultations / Treatments Datatables
    |--------------------------------------------------------------------------
    */

    public function getPatientAppointments(int $patientId, Request $request): array
    {
        return $this->getPatientAppointmentsByType($patientId, $request);
    }

    public function getPatientConsultations(int $patientId, Request $request): array
    {
        return $this->getPatientAppointmentsByType($patientId, $request, 1);
    }

    public function getPatientTreatments(int $patientId, Request $request): array
    {
        return $this->getPatientAppointmentsByType($patientId, $request, 2);
    }

    private function getPatientAppointmentsByType(int $patientId, Request $request, ?int $appointmentTypeId = null): array
    {
        $accountId = Auth::user()->account_id;

        $countQuery = DB::table('appointments')
            ->where('patient_id', $patientId)
            ->where('account_id', $accountId)
            ->when($appointmentTypeId, fn ($q) => $q->where('appointment_type_id', $appointmentTypeId)->whereNull('deleted_at'));

        $iTotalRecords = $countQuery->count();

        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $paidStatusId = InvoiceStatuses::where('slug', 'paid')->value('id');

        $appointments = DB::table('appointments')
            ->select([
                'appointments.id', 'appointments.scheduled_date', 'appointments.consultancy_type',
                'appointments.appointment_type_id as type_id', 'appointments.created_at',
                'appointments.patient_id', 'appointments.doctor_id as doctor_id_raw',
                'appointments.location_id as location_id_raw',
                'patients.name as patient_name', 'patients.phone as patient_phone',
                'doctors.name as doctor_name', 'cities.name as city_name',
                'locations.name as location_name', 'services.name as service_name',
                'appointment_statuses.name as status_name', 'appointment_types.name as type_name',
                'creators.name as created_by_name',
                'invoices.id as invoice_id', 'invoices.invoice_status_id as invoice_status_id',
            ])
            ->leftJoin('users as patients', 'appointments.patient_id', '=', 'patients.id')
            ->leftJoin('users as doctors', 'appointments.doctor_id', '=', 'doctors.id')
            ->leftJoin('cities', 'appointments.city_id', '=', 'cities.id')
            ->leftJoin('locations', 'appointments.location_id', '=', 'locations.id')
            ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
            ->leftJoin('appointment_statuses', 'appointments.appointment_status_id', '=', 'appointment_statuses.id')
            ->leftJoin('appointment_types', 'appointments.appointment_type_id', '=', 'appointment_types.id')
            ->leftJoin('users as creators', 'appointments.created_by', '=', 'creators.id')
            ->leftJoin('invoices', function ($join) use ($paidStatusId) {
                $join->on('invoices.appointment_id', '=', 'appointments.id')
                    ->where('invoices.invoice_status_id', '=', $paidStatusId)
                    ->whereNull('invoices.deleted_at');
            })
            ->where('appointments.patient_id', $patientId)
            ->where('appointments.account_id', $accountId)
            ->when($appointmentTypeId, fn ($q) => $q->where('appointments.appointment_type_id', $appointmentTypeId)->whereNull('appointments.deleted_at'))
            ->orderByDesc('appointments.scheduled_date')
            ->offset($iDisplayStart)
            ->limit($iDisplayLength)
            ->get();

        return [
            'data' => $appointments,
            'meta' => compact('page', 'pages') + [
                'field' => $orderBy,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ],
            'permissions' => [
                'edit' => Gate::allows('appointments_edit'),
                'delete' => Gate::allows('appointments_destroy'),
                'status' => Gate::allows('appointments_appointment_status'),
                'invoice' => Gate::allows('consultancy_invoice') || Gate::allows('appointments_invoice'),
                'invoice_display' => Gate::allows('consultancy_invoice_display') || Gate::allows('appointments_invoice_display'),
                'log' => Gate::allows('appointments_log'),
                // Patient appointments table inside the patient card —
                // gate on the patients-module contact perm so revoking
                // `patients.list.view_contact` hides the patient phone
                // here too (not just on the datatable list page).
                'contact' => Gate::allows('patients.list.view_contact'),
            ],
            'filter_values' => [
                'patient' => null, 'cities' => [], 'locations' => [],
                'appointment_statuses' => [], 'appointment_types' => [],
                'doctors' => [], 'services' => [], 'users' => [], 'consultancy_types' => [],
            ],
            'active_filters' => [],
        ];
    }

    public function getPatientVouchers(int $patientId, Request $request): array
    {
        $iTotalRecords = DB::table('user_vouchers')->where('user_id', $patientId)->count();

        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $vouchers = DB::table('user_vouchers')
            ->select([
                'user_vouchers.id', 'user_vouchers.voucher_id', 'user_vouchers.amount',
                'user_vouchers.total_amount', 'user_vouchers.created_at',
                'discounts.name as voucher_name', 'discounts.start as start_date', 'discounts.end as end_date',
            ])
            ->leftJoin('discounts', 'user_vouchers.voucher_id', '=', 'discounts.id')
            ->where('user_vouchers.user_id', $patientId)
            ->orderByDesc('user_vouchers.created_at')
            ->offset($iDisplayStart)
            ->limit($iDisplayLength)
            ->get();

        $data = $vouchers->map(function ($voucher) use ($patientId) {
            $totalAmount = $voucher->total_amount ?? 0;
            $currentBalance = $voucher->amount;

            if ($currentBalance === null || ($currentBalance == 0 && $totalAmount > 0)) {
                $hasUsage = DB::table('package_vouchers')
                    ->where('user_id', $patientId)
                    ->where('voucher_id', $voucher->voucher_id)
                    ->exists();

                $currentBalance = $hasUsage ? ($currentBalance ?? 0) : $totalAmount;
            }

            $voucher->current_balance = $currentBalance;
            $voucher->total_amount = $totalAmount;

            return $voucher;
        });

        return [
            'data' => $data,
            'meta' => compact('page', 'pages') + [
                'field' => $orderBy,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ],
            'permissions' => [
                'edit'   => Gate::allows('vouchers.edit'),
                'delete' => Gate::allows('vouchers.destroy'),
            ],
            'filter_values' => ['patient' => null],
            'active_filters' => [],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function findPatient(int $id): ?Patients
    {
        return Patients::where('id', $id)
            ->where('account_id', Auth::user()->account_id)
            ->first();
    }

    private function updatePatientRecord(int $id, array $data): ?Patients
    {
        // Scope to the caller's account (mirrors findPatient) so update()
        // can't overwrite another clinic's patient via this re-fetch.
        $patient = $this->findPatient($id);
        if (! $patient) {
            return null;
        }

        $oldData = $patient->toArray();
        $patient->update($data);
        AuditTrails::EditEventLogger('users', 'edit', $patient, self::AUDIT_FILLABLE, $oldData, $id);

        return $patient;
    }

    private function hasChildRecords(int $id, int $accountId): bool
    {
        return Leads::where(['patient_id' => $id, 'account_id' => $accountId])->exists()
            || Appointments::where(['patient_id' => $id, 'account_id' => $accountId])->exists();
    }

    private function detectFieldChanges(array $oldValues, array $newData): array
    {
        $fieldChanges = [];

        foreach (self::FIELD_LABELS as $field => $label) {
            $oldVal = $oldValues[$field] ?? '';
            $newVal = $newData[$field] ?? '';
            if (isset($newData[$field]) && (string) $oldVal !== (string) $newVal) {
                $fieldChanges[$label] = ['old' => $oldVal ?: 'N/A', 'new' => $newVal ?: 'N/A'];
            }
        }

        return $fieldChanges;
    }

    private const ALLOWED_UPLOAD_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];

    private function storeUploadedFile(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?: ''));

        if (! in_array($ext, self::ALLOWED_UPLOAD_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('File type not allowed. Allowed: '.implode(', ', self::ALLOWED_UPLOAD_EXTENSIONS));
        }

        $fileName = time().'_'.uniqid().'.'.$ext;

        $storagePath = storage_path('app/'.self::STORAGE_PATH);
        if (! file_exists($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $file->move($storagePath, $fileName);

        return $fileName;
    }

    private function deleteOldFile(string $relativePath): void
    {
        // Round 4 C3 — files now live under storage/app/<dir>/, not under
        // storage/app/public/<dir>/. We try the new location first and fall
        // back to the legacy location for any rows that still reference the
        // old path (the migration moved physical files but pre-existing
        // documents.url values are unchanged in shape).
        foreach (['app/'.$relativePath, 'app/public/'.$relativePath] as $candidate) {
            $oldFilePath = storage_path($candidate);
            if (file_exists($oldFilePath)) {
                @unlink($oldFilePath);

                return;
            }
        }
    }
}
