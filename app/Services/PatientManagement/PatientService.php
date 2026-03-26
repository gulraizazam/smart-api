<?php

namespace App\Services\PatientManagement;

use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\AuditTrails;
use App\Models\Leads;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Patients;
use App\Models\UserVouchers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PatientService
{
    private const FILTER_KEY = 'patients';
    private const CACHE_TTL = 300; // 5 minutes
    private const MEMBERSHIP_TYPES_CACHE_KEY = 'active_membership_types';

    private const AUDIT_FILLABLE = [
        'name', 'email', 'phone', 'main_account', 'gender',
        'cnic', 'dob', 'address', 'referred_by', 'user_type_id',
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

        $baseQuery = $this->buildOptimizedQuery($request, $accountId, $applyFilter, $filters);

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

        $records['permissions'] = $this->getCachedPermissions($userId);

        return $records;
    }

    private function buildOptimizedQuery(Request $request, int $accountId, bool $applyFilter, array $filters)
    {
        $userId = Auth::user()->id;

        $query = Patients::query()
            ->where('user_type_id', Config::get('constants.patient_id'))
            ->where('account_id', $accountId);

        if (!Gate::allows('view_inactive_patients')) {
            $query->where('active', 1);
        }

        // ACL centre filter
        $userCentres = ACL::getUserCentres();
        if (!empty($userCentres)) {
            $query->whereExists(function ($sub) use ($userCentres) {
                $sub->select(DB::raw(1))
                    ->from('appointments')
                    ->whereColumn('appointments.patient_id', 'users.id')
                    ->whereIn('appointments.location_id', $userCentres);
            });
        }

        $this->applyOptimizedFilters($query, $filters, $applyFilter, $userId);

        if (isset($filters['membership'])) {
            Filters::put($userId, self::FILTER_KEY, 'memberships', $filters['membership']);
            $query->whereExists(function ($sub) use ($filters) {
                $sub->select(DB::raw(1))
                    ->from('memberships')
                    ->whereColumn('memberships.patient_id', 'users.id')
                    ->where('memberships.membership_type_id', $filters['membership']);
            });
        }

        return $query;
    }

    private function applyOptimizedFilters($query, array $filters, bool $applyFilter, int $userId): void
    {
        $this->applyFilter($query, $filters, $applyFilter, $userId, 'patient_id', function ($q, $value) {
            $q->where('id', 'like', '%' . GeneralFunctions::patientSearch($value) . '%');
        });

        $this->applyFilter($query, $filters, $applyFilter, $userId, 'name', function ($q, $value) {
            $q->where('name', 'like', "%{$value}%");
        });

        $this->applyFilter($query, $filters, $applyFilter, $userId, 'gender', function ($q, $value) {
            $q->where('gender', $value);
        });

        $this->applyFilter($query, $filters, $applyFilter, $userId, 'phone', function ($q, $value) {
            $q->where('phone', 'like', '%' . GeneralFunctions::cleanNumber($value) . '%');
        });

        $this->applyFilter($query, $filters, $applyFilter, $userId, 'status', function ($q, $value) {
            if ($value !== null && ($value == 0 || $value == 1)) {
                $q->where('active', $value);
            }
        });

        if (hasFilter($filters, 'created_at')) {
            $dateRange = explode(' - ', $filters['created_at']);
            $query->whereBetween('created_at', [
                date('Y-m-d 00:00:00', strtotime($dateRange[0])),
                date('Y-m-d 23:59:59', strtotime($dateRange[1])),
            ]);
            Filters::put($userId, self::FILTER_KEY, 'created_at', $filters['created_at']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'created_at');
        }
    }

    private function applyFilter($query, array $filters, bool $applyFilter, int $userId, string $key, callable $callback): void
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
        $memberships = Cache::remember(self::MEMBERSHIP_TYPES_CACHE_KEY, self::CACHE_TTL, function () {
            return MembershipType::where('active', 1)->pluck('id', 'name');
        });

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

    private function getCachedPermissions(int $userId): array
    {
        return Cache::remember("patient_permissions_{$userId}", 60, fn() => [
            'edit' => Gate::allows('patients_edit'),
            'delete' => Gate::allows('patients_destroy'),
            'active' => Gate::allows('patients_active'),
            'inactive' => Gate::allows('patients_inactive'),
            'manage' => Gate::allows('patients_manage'),
            'contact' => Gate::allows('contact'),
            'add_referrals' => Gate::allows('patients_add_referrals'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD Operations
    |--------------------------------------------------------------------------
    */

    public function getCreateData(): array
    {
        return ['gender' => config('constants.gender_array')];
    }

    public function create(array $data): array
    {
        $user = Auth::user();
        $data['phone'] = GeneralFunctions::cleanNumber($data['phone']);
        $data['created_by'] = $user->id;
        $data['updated_by'] = $user->id;
        $data['user_type_id'] = Config::get('constants.patient_id');
        $data['account_id'] = $user->account_id;

        $existingPatient = Patients::where([
            'phone' => $data['phone'],
            'user_type_id' => Config::get('constants.patient_id'),
            'account_id' => $user->account_id,
        ])->first();

        if ($existingPatient) {
            $patient = $this->updatePatientRecord($existingPatient->id, $data);
            Appointments::where('patient_id', $existingPatient->id)->update(['name' => $data['name']]);
        } else {
            $patient = $this->createPatientRecord($data);
        }

        return $patient
            ? ['status' => true, 'message' => 'Record has been created successfully.', 'patient' => $patient]
            : ['status' => false, 'message' => 'Something went wrong, please try again later.'];
    }

    public function getEditData(int $id): ?array
    {
        $patient = $this->findPatient($id);

        return $patient ? ['patient' => $patient, 'gender' => config('constants.gender_array')] : null;
    }

    public function update(int $id, array $data): array
    {
        $user = Auth::user();

        // Handle masked phone
        if (($data['phone'] ?? '') === '***********' && isset($data['old_phone'])) {
            $data['phone'] = $data['old_phone'];
        }

        $oldPhone = $data['old_phone'] ?? null;
        unset($data['old_phone']);

        $data['phone'] = GeneralFunctions::cleanNumber($data['phone']);

        $oldPatient = $this->findPatient($id);
        $oldValues = $oldPatient ? array_intersect_key(
            $oldPatient->toArray(),
            array_flip(['name', 'email', 'phone', 'gender', 'dob', 'address', 'cnic'])
        ) : [];

        $patient = $this->updatePatientRecord($id, $data);

        if (!$patient) {
            return ['status' => false, 'message' => 'Something went wrong, please try again later.'];
        }

        // Update related records
        Appointments::where('patient_id', $id)->update(['name' => $data['name']]);

        if ($oldPhone) {
            Leads::where('phone', $oldPhone)->update([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'gender' => $data['gender'] ?? null,
            ]);
        }

        // Log field changes
        $fieldLabels = [
            'name' => 'Name', 'email' => 'Email', 'phone' => 'Phone',
            'gender' => 'Gender', 'dob' => 'Date of Birth', 'address' => 'Address', 'cnic' => 'CNIC',
        ];

        $fieldChanges = [];
        foreach ($fieldLabels as $field => $label) {
            $oldVal = $oldValues[$field] ?? '';
            $newVal = $data[$field] ?? '';
            if (isset($data[$field]) && (string) $oldVal !== (string) $newVal) {
                $fieldChanges[$label] = ['old' => $oldVal ?: 'N/A', 'new' => $newVal ?: 'N/A'];
            }
        }

        if (!empty($fieldChanges)) {
            \App\Helpers\ActivityLogger::logPatientUpdated($patient, $fieldChanges);
        }

        return ['status' => true, 'message' => 'Record has been updated successfully.'];
    }

    public function delete(int $id): array
    {
        $accountId = Auth::user()->account_id;
        $patient = $this->findPatient($id);

        if (!$patient) {
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
            if (!$this->hasChildRecords($patient->id, $accountId)) {
                $patient->delete();
                $deletedCount++;
            } else {
                $childDetails = Patients::getChildRecordsDetails($patient->id, $accountId);
                $skippedPatients[] = "C-{$patient->id} ({$patient->name}): " . implode(', ', $childDetails);
            }
        }

        return match (true) {
            $deletedCount > 0 && empty($skippedPatients) => [
                'status' => true,
                'message' => "{$deletedCount} patient(s) deleted successfully!",
            ],
            $deletedCount > 0 => [
                'status' => true,
                'message' => "{$deletedCount} patient(s) deleted. Skipped " . count($skippedPatients) . " patient(s) with related records: " . implode('; ', $skippedPatients),
            ],
            default => [
                'status' => false,
                'message' => 'Cannot delete patient(s). Related records exist: ' . implode('; ', $skippedPatients),
            ],
        };
    }

    public function changeStatus(int $id, int $status): array
    {
        $patient = $this->findPatient($id);

        if (!$patient) {
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

        if (!$patient) {
            return null;
        }

        $membership = Membership::with('membershipType')
            ->where('patient_id', (int) $patient->id)
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
            'permissions' => [
                'edit' => Gate::allows('patients_edit'),
                'delete' => Gate::allows('patients_destroy'),
                'active' => Gate::allows('patients_active'),
                'inactive' => Gate::allows('patients_inactive'),
                'manage' => Gate::allows('patients_manage'),
                'contact' => Gate::allows('contact'),
                'add_referrals' => Gate::allows('patients_add_referrals'),
            ],
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

        if (!$membership) {
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
            \App\Helpers\ActivityLogger::logMembershipAssigned($patient, $membership, $membership->membershipType);
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

        if (!$membership) {
            return ['status' => false, 'message' => 'Invalid membership code or membership is inactive.'];
        }

        $patient = Patients::find($patientId);
        if (!$patient) {
            return ['status' => false, 'message' => 'Patient not found.'];
        }

        if ($membership->patient_id == $patientId) {
            return ['status' => false, 'message' => 'Membership is already assigned to this patient.'];
        }

        if (is_null($membership->patient_id)) {
            return ['status' => false, 'message' => 'This membership code is not assigned to any patient, so referral cannot be added.'];
        }

        if (!$membership->membershipType) {
            return ['status' => false, 'message' => 'Membership type not found.'];
        }

        if (strtolower(trim($membership->membershipType->name)) !== 'gold membership') {
            return ['status' => false, 'message' => 'Referrals can only be created for Gold Membership type. Current membership type: ' . $membership->membershipType->name];
        }

        if ($membership->patient_id != $patientId && Carbon::parse($membership->end_date)->isPast()) {
            return ['status' => false, 'message' => 'Membership is expired, referral cannot be added.'];
        }

        $existingReferrals = Membership::where('code', $membershipCode)->where('is_referral', 1)->count();
        if ($existingReferrals >= 2) {
            return ['status' => false, 'message' => 'Maximum of 2 referrals allowed per membership code. Limit reached.'];
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

    /*
    |--------------------------------------------------------------------------
    | Image & Search
    |--------------------------------------------------------------------------
    */

    public function storeImage(int $patientId, $file): array
    {
        $patient = $this->findPatient($patientId);

        if (!$patient) {
            return ['status' => false, 'message' => 'Resource not found.'];
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            return ['status' => false, 'message' => 'JPG, JPEG, PNG, GIF Only Allowed.'];
        }

        $fileName = time() . '-' . str_replace(' ', '-', $file->getClientOriginalName());
        $file->storeAs('public/patient_image', $fileName);

        DB::table('users')->where('id', $patient->id)->update(['image_src' => $fileName]);

        return [
            'status' => true,
            'message' => 'Picture saved successfully.',
            'image' => asset('storage/patient_image/' . $fileName),
        ];
    }

    public function searchPatients(string $search, int $accountId): array
    {
        $originalSearch = $search;
        $search = GeneralFunctions::patientSearch($search);
        $cleanedSearch = GeneralFunctions::clearnString($search);

        $baseQuery = Patients::where('user_type_id', Config::get('constants.patient_id'))
            ->where('active', 1)
            ->where('account_id', $accountId);

        if (is_numeric($cleanedSearch)) {
            $numericValue = (int) $cleanedSearch;

            // Exact ID match first
            $exactMatch = (clone $baseQuery)->where('id', $numericValue)->select('name', 'id', 'phone')->first();
            if ($exactMatch) {
                return [$exactMatch->toArray()];
            }

            // Partial ID or phone search
            $phone = GeneralFunctions::cleanNumber($originalSearch);
            return (clone $baseQuery)
                ->where(fn($q) => $q->where('id', 'LIKE', "%{$numericValue}%")->orWhere('phone', 'LIKE', "%{$phone}%"))
                ->select('name', 'id', 'phone')
                ->limit(20)
                ->get()
                ->toArray();
        }

        return (clone $baseQuery)
            ->where('name', 'LIKE', "%{$search}%")
            ->select('name', 'id', 'phone')
            ->limit(20)
            ->get()
            ->toArray();
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
            ->when($appointmentTypeId, fn($q) => $q->where('appointment_type_id', $appointmentTypeId)->whereNull('deleted_at'));

        $iTotalRecords = $countQuery->count();

        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $appointments = DB::table('appointments')
            ->select([
                'appointments.id', 'appointments.scheduled_date', 'appointments.consultancy_type',
                'appointments.appointment_type_id as type_id', 'appointments.created_at',
                'patients.name as patient_name', 'patients.phone as patient_phone',
                'doctors.name as doctor_name', 'cities.name as city_name',
                'locations.name as location_name', 'services.name as service_name',
                'appointment_statuses.name as status_name', 'appointment_types.name as type_name',
                'creators.name as created_by_name',
            ])
            ->leftJoin('users as patients', 'appointments.patient_id', '=', 'patients.id')
            ->leftJoin('users as doctors', 'appointments.doctor_id', '=', 'doctors.id')
            ->leftJoin('cities', 'appointments.city_id', '=', 'cities.id')
            ->leftJoin('locations', 'appointments.location_id', '=', 'locations.id')
            ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
            ->leftJoin('appointment_statuses', 'appointments.appointment_status_id', '=', 'appointment_statuses.id')
            ->leftJoin('appointment_types', 'appointments.appointment_type_id', '=', 'appointment_types.id')
            ->leftJoin('users as creators', 'appointments.created_by', '=', 'creators.id')
            ->where('appointments.patient_id', $patientId)
            ->where('appointments.account_id', $accountId)
            ->when($appointmentTypeId, fn($q) => $q->where('appointments.appointment_type_id', $appointmentTypeId)->whereNull('appointments.deleted_at'))
            ->orderByDesc('appointments.scheduled_date')
            ->offset($iDisplayStart)
            ->limit($iDisplayLength)
            ->get();

        $canViewContact = Gate::allows('contact');

        $data = $appointments->map(fn($apt) => [
            'id' => $apt->id,
            'name' => $apt->patient_name ?? '',
            'phone' => $canViewContact ? ($apt->patient_phone ?? '') : '***********',
            'scheduled_date' => $apt->scheduled_date ? Carbon::parse($apt->scheduled_date)->format('D M, d Y h:i A') : '',
            'doctor_id' => $apt->doctor_name ?? '',
            'city_id' => $apt->city_name ?? '',
            'location_id' => $apt->location_name ?? '',
            'service_id' => $apt->service_name ?? '',
            'appointment_status_id' => $apt->status_name ?? '',
            'appointment_type_id' => $apt->type_id ?? 0,
            'consultancy_type' => $apt->consultancy_type ?? '',
            'created_at' => $apt->created_at ? Carbon::parse($apt->created_at)->format('D M, d Y h:i A') : '',
            'created_by' => $apt->created_by_name ?? '',
        ]);

        return [
            'data' => $data,
            'meta' => compact('page', 'pages') + [
                'field' => $orderBy,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
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

            $consumedAmount = $totalAmount - $currentBalance;

            return [
                'id' => $voucher->id,
                'user_voucher_id' => $voucher->id,
                'name' => $voucher->voucher_name ?? '',
                'service' => '',
                'total_amount' => number_format($totalAmount, 2),
                'consumed_amount' => number_format($consumedAmount, 2),
                'balance' => number_format($currentBalance, 2),
                'amount' => $voucher->amount ?? 0,
                'startDate' => $voucher->start_date ?? '',
                'endDate' => $voucher->end_date ?? '',
                'created_at' => $voucher->created_at ? Carbon::parse($voucher->created_at)->format('D M, d Y h:i A') : '',
            ];
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
                'edit' => Gate::allows('vouchers_edit'),
                'delete' => Gate::allows('vouchers_destroy'),
            ],
            'filter_values' => ['patient' => null],
            'active_filters' => [],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Private Helpers
    |--------------------------------------------------------------------------
    */

    private function findPatient(int $id): ?Patients
    {
        return Patients::where('id', $id)
            ->where('account_id', Auth::user()->account_id)
            ->first();
    }

    private function createPatientRecord(array $data): ?Patients
    {
        $record = Patients::create($data);
        AuditTrails::addEventLogger('users', 'create', $data, self::AUDIT_FILLABLE, $record);
        return $record;
    }

    private function updatePatientRecord(int $id, array $data): ?Patients
    {
        $patient = Patients::find($id);
        if (!$patient) {
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
}
