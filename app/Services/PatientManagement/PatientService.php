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

    /**
     * Get datatable data for patients listing
     * OPTIMIZED: Single query approach, cached filters, optimized eager loading
     */
    public function getDatatableData(Request $request): array
    {
        $accountId = Auth::user()->account_id;
        $userId = Auth::user()->id;
        $filters = getFilters($request->all());
        $applyFilter = checkFilters($filters, self::FILTER_KEY);

        $records = ['data' => []];

        // Handle bulk delete
        if (hasFilter($filters, 'delete')) {
            $deleteResult = $this->bulkDelete(explode(',', $filters['delete']), $accountId);
            $records['status'] = $deleteResult['status'];
            $records['message'] = $deleteResult['message'];
        }

        // Build base query once (reused for count and data)
        $baseQuery = $this->buildOptimizedQuery($request, $accountId, $applyFilter, $filters);

        // Get total count using optimized count query
        $iTotalRecords = $this->getOptimizedCount($baseQuery);

        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        // Get paginated data with eager loading
        $patients = $this->getOptimizedRecords($baseQuery, $iDisplayStart, $iDisplayLength);

        // Add cached filter data
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

        // Cache permissions check results
        $records['permissions'] = $this->getCachedPermissions($userId);

        return $records;
    }

    /**
     * Build optimized query with all conditions
     * OPTIMIZED: Single query builder instance reused for count and data
     */
    private function buildOptimizedQuery(Request $request, int $accountId, bool $applyFilter, array $filters)
    {
        $userId = Auth::user()->id;
        $canViewInactive = Gate::allows('view_inactive_patients');

        // Start with base query - select only needed columns for better performance
        $query = Patients::query();

        // Always filter by user_type and account (uses composite index)
        $query->where('user_type_id', Config::get('constants.patient_id'))
              ->where('account_id', $accountId);

        // Active filter (uses index)
        if (!$canViewInactive) {
            $query->where('active', 1);
        }

        // Filter by user's centre access - only show patients who have appointments at user's centres
        $userCentres = ACL::getUserCentres();
        if (!empty($userCentres)) {
            $query->whereExists(function ($subQuery) use ($userCentres) {
                $subQuery->select(DB::raw(1))
                    ->from('appointments')
                    ->whereColumn('appointments.patient_id', 'users.id')
                    ->whereIn('appointments.location_id', $userCentres);
            });
        }

        // Apply filters efficiently
        $this->applyOptimizedFilters($query, $filters, $applyFilter, $userId);

        // Membership filter with optimized subquery
        if (isset($filters['membership'])) {
            Filters::put($userId, self::FILTER_KEY, 'memberships', $filters['membership']);
            $query->whereExists(function ($subQuery) use ($filters) {
                $subQuery->select(DB::raw(1))
                    ->from('memberships')
                    ->whereColumn('memberships.patient_id', 'users.id')
                    ->where('memberships.membership_type_id', $filters['membership']);
            });
        }

        return $query;
    }

    /**
     * Get optimized count using COUNT(*) directly
     */
    private function getOptimizedCount($baseQuery): int
    {
        return (clone $baseQuery)->count();
    }

    /**
     * Get optimized records with selective eager loading
     */
    private function getOptimizedRecords($baseQuery, int $offset, int $limit)
    {
        return (clone $baseQuery)
            ->with(['membership:id,patient_id,code,membership_type_id,end_date,active,is_referral'])
            ->select(['id', 'name', 'email', 'phone', 'gender', 'active', 'created_at', 'id as patient_id'])
            ->orderBy('created_at', 'DESC')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * Apply filters efficiently without redundant checks
     */
    private function applyOptimizedFilters($query, array $filters, bool $applyFilter, int $userId): void
    {
        // Patient ID filter (use LIKE to match original behavior)
        $this->applyFilter($query, $filters, $applyFilter, $userId, 'patient_id', function($q, $value) {
            $searchValue = GeneralFunctions::patientSearch($value);
            $q->where('id', 'like', '%' . $searchValue . '%');
        });

        // Name filter
        $this->applyFilter($query, $filters, $applyFilter, $userId, 'name', function($q, $value) {
            $q->where('name', 'like', '%' . $value . '%');
        });


        // Gender filter (exact match, not LIKE)
        $this->applyFilter($query, $filters, $applyFilter, $userId, 'gender', function($q, $value) {
            $q->where('gender', $value); // Exact match is faster
        });

        // Phone filter
        $this->applyFilter($query, $filters, $applyFilter, $userId, 'phone', function($q, $value) {
            $q->where('phone', 'like', '%' . GeneralFunctions::cleanNumber($value) . '%');
        });

        // Status filter
        $this->applyFilter($query, $filters, $applyFilter, $userId, 'status', function($q, $value) {
            if ($value !== null && ($value == 0 || $value == 1)) {
                $q->where('active', $value);
            }
        });

        // Date range filter (uses index on created_at)
        if (hasFilter($filters, 'created_at')) {
            $dateRange = explode(' - ', $filters['created_at']);
            $startDateTime = date('Y-m-d 00:00:00', strtotime($dateRange[0]));
            $endDateTime = date('Y-m-d 23:59:59', strtotime($dateRange[1]));
            $query->whereBetween('created_at', [$startDateTime, $endDateTime]);
            Filters::put($userId, self::FILTER_KEY, 'created_at', $filters['created_at']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'created_at');
        }
    }

    /**
     * Generic filter application helper
     */
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

    /**
     * Get total records count - LEGACY (kept for backward compatibility)
     */
    private function getTotalRecords(Request $request, int $accountId, bool $applyFilter): int
    {
        $filters = getFilters($request->all());
        $where = $this->buildWhereConditions($request, $accountId, $applyFilter);

        $query = Patients::query();

        if (count($where)) {
            $query->where($where);
        }

        if (!Gate::allows('view_inactive_patients')) {
            $query->where('active', 1);
        }

        if (isset($filters['membership'])) {
            $query->whereHas('membership', function ($q) use ($filters) {
                $q->where('membership_type_id', $filters['membership']);
            });
        }

        return $query->count();
    }

    /**
     * Get patient records - LEGACY (kept for backward compatibility)
     */
    private function getRecords(Request $request, int $offset, int $limit, int $accountId, bool $applyFilter)
    {
        $filters = getFilters($request->all());
        $where = $this->buildWhereConditions($request, $accountId, $applyFilter);

        $query = Patients::with('membership');

        if (isset($filters['membership'])) {
            Filters::put(Auth::user()->id, self::FILTER_KEY, 'memberships', $filters['membership']);
            $query->whereHas('membership', function ($q) use ($filters) {
                $q->where('membership_type_id', $filters['membership']);
            });
        }

        if (count($where)) {
            $query->where($where);
        }

        if (!Gate::allows('view_inactive_patients')) {
            $query->where('active', 1);
        }

        return $query->select('*', 'id as patient_id')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    /**
     * Build where conditions for filtering
     */
    private function buildWhereConditions(Request $request, int $accountId, bool $applyFilter): array
    {
        $where = [];
        $filters = getFilters($request->all());
        $userId = Auth::user()->id;

        // User type filter (patients only)
        $where[] = ['user_type_id', '=', Config::get('constants.patient_id')];

        // Account filter
        $where[] = ['account_id', '=', $accountId];
        Filters::put($userId, self::FILTER_KEY, 'account_id', $accountId);

        // Patient ID filter
        if (hasFilter($filters, 'patient_id')) {
            $where[] = ['id', 'like', '%' . GeneralFunctions::patientSearch($filters['patient_id']) . '%'];
            Filters::put($userId, self::FILTER_KEY, 'patient_id', $filters['patient_id']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, self::FILTER_KEY, 'patient_id');
            } elseif (Filters::get($userId, self::FILTER_KEY, 'patient_id')) {
                $where[] = ['id', 'like', '%' . Filters::get($userId, self::FILTER_KEY, 'patient_id') . '%'];
            }
        }

        // Name filter
        if (hasFilter($filters, 'name')) {
            $where[] = ['name', 'like', '%' . $filters['name'] . '%'];
            Filters::put($userId, self::FILTER_KEY, 'name', $filters['name']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, self::FILTER_KEY, 'name');
            } elseif (Filters::get($userId, self::FILTER_KEY, 'name')) {
                $where[] = ['name', 'like', '%' . Filters::get($userId, self::FILTER_KEY, 'name') . '%'];
            }
        }

        // Email filter
        if (hasFilter($filters, 'email')) {
            $where[] = ['email', 'like', '%' . $filters['email'] . '%'];
            Filters::put($userId, self::FILTER_KEY, 'email', $filters['email']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, self::FILTER_KEY, 'email');
            } elseif (Filters::get($userId, self::FILTER_KEY, 'email')) {
                $where[] = ['email', 'like', '%' . Filters::get($userId, self::FILTER_KEY, 'email') . '%'];
            }
        }

        // Gender filter
        if (hasFilter($filters, 'gender')) {
            $where[] = ['gender', 'like', '%' . $filters['gender'] . '%'];
            Filters::put($userId, self::FILTER_KEY, 'gender', $filters['gender']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, self::FILTER_KEY, 'gender');
            } elseif (Filters::get($userId, self::FILTER_KEY, 'gender')) {
                $where[] = ['gender', 'like', '%' . Filters::get($userId, self::FILTER_KEY, 'gender') . '%'];
            }
        }

        // Phone filter
        if (hasFilter($filters, 'phone')) {
            $where[] = ['phone', 'like', '%' . GeneralFunctions::cleanNumber($filters['phone']) . '%'];
            Filters::put($userId, self::FILTER_KEY, 'phone', $filters['phone']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, self::FILTER_KEY, 'phone');
            } elseif (Filters::get($userId, self::FILTER_KEY, 'phone')) {
                $where[] = ['phone', 'like', '%' . GeneralFunctions::cleanNumber(Filters::get($userId, self::FILTER_KEY, 'phone')) . '%'];
            }
        }

        // Created at filter
        if (hasFilter($filters, 'created_at')) {
            $dateRange = explode(' - ', $filters['created_at']);
            $startDateTime = date('Y-m-d H:i:s', strtotime($dateRange[0]));
            $endDateTime = date('Y-m-d', strtotime($dateRange[1])) . ' 23:59:59';
            $where[] = ['created_at', '>=', $startDateTime];
            $where[] = ['created_at', '<=', $endDateTime];
            Filters::put($userId, self::FILTER_KEY, 'created_at', $filters['created_at']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, self::FILTER_KEY, 'created_at');
            } elseif (Filters::get($userId, self::FILTER_KEY, 'created_at')) {
                $where[] = ['created_at', '>=', Filters::get($userId, self::FILTER_KEY, 'created_at')];
            }
        }

        // Status filter
        if (hasFilter($filters, 'status')) {
            $where[] = ['active', '=', $filters['status']];
            Filters::put($userId, self::FILTER_KEY, 'status', $filters['status']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, self::FILTER_KEY, 'status');
            } elseif (Filters::get($userId, self::FILTER_KEY, 'status') !== null) {
                $status = Filters::get($userId, self::FILTER_KEY, 'status');
                if ($status == 0 || $status == 1) {
                    $where[] = ['active', '=', $status];
                }
            }
        }

        return $where;
    }

    /**
     * Get filter data for datatable (CACHED version)
     * OPTIMIZED: Caches membership types query
     */
    private function getFiltersDataCached(array $records, int $userId): array
    {
        // Cache membership types for 5 minutes (they rarely change)
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

    /**
     * Get filter data for datatable - LEGACY
     */
    private function getFiltersData(array $records): array
    {
        $records['filter_values'] = [
            'gender' => config('constants.gender_array'),
            'status' => config('constants.status'),
            'memberships' => MembershipType::where('active', 1)->pluck('id', 'name'),
        ];

        $filters = Filters::all(Auth::user()->id, self::FILTER_KEY);

        if (isset($filters['created_from'])) {
            $filters['created_from'] = date('Y-m-d', strtotime($filters['created_from']));
        }
        if (isset($filters['created_to'])) {
            $filters['created_to'] = date('Y-m-d', strtotime($filters['created_to']));
        }

        $records['active_filters'] = $filters;

        return $records;
    }

    /**
     * Get cached permissions for current user
     * OPTIMIZED: Caches permission checks per user session
     */
    private function getCachedPermissions(int $userId): array
    {
        $cacheKey = "patient_permissions_{$userId}";
        
        return Cache::remember($cacheKey, 60, function () { // Cache for 1 minute
            return [
                'edit' => Gate::allows('patients_edit'),
                'delete' => Gate::allows('patients_destroy'),
                'active' => Gate::allows('patients_active'),
                'inactive' => Gate::allows('patients_inactive'),
                'manage' => Gate::allows('patients_manage'),
                'contact' => Gate::allows('contact'),
                'add_referrals' => Gate::allows('patients_add_referrals'),
            ];
        });
    }

    /**
     * Get permissions for current user - LEGACY
     */
    private function getPermissions(): array
    {
        return [
            'edit' => Gate::allows('patients_edit'),
            'delete' => Gate::allows('patients_destroy'),
            'active' => Gate::allows('patients_active'),
            'inactive' => Gate::allows('patients_inactive'),
            'manage' => Gate::allows('patients_manage'),
            'contact' => Gate::allows('contact'),
            'add_referrals' => Gate::allows('patients_add_referrals'),
        ];
    }

    /**
     * Get create form data
     */
    public function getCreateData(): array
    {
        return [
            'gender' => config('constants.gender_array'),
        ];
    }

    /**
     * Create a new patient
     */
    public function create(array $data): array
    {
        $accountId = Auth::user()->account_id;
        $userId = Auth::user()->id;

        $data['phone'] = GeneralFunctions::cleanNumber($data['phone']);
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        $data['user_type_id'] = Config::get('constants.patient_id');
        $data['account_id'] = $accountId;

        // Check if patient already exists by phone
        $existingPatient = Patients::where([
            'phone' => $data['phone'],
            'user_type_id' => Config::get('constants.patient_id'),
            'account_id' => $accountId,
        ])->first();

        if ($existingPatient) {
            // Update existing patient
            $patient = $this->updatePatientRecord($existingPatient->id, $data);
            Appointments::where('patient_id', $existingPatient->id)->update(['name' => $data['name']]);
        } else {
            // Create new patient
            $patient = $this->createPatientRecord($data);
        }

        if ($patient) {
            return [
                'status' => true,
                'message' => 'Record has been created successfully.',
                'patient' => $patient,
            ];
        }

        return [
            'status' => false,
            'message' => 'Something went wrong, please try again later.',
        ];
    }

    /**
     * Get patient data for editing
     */
    public function getEditData(int $id): ?array
    {
        $patient = $this->findPatient($id);

        if (!$patient) {
            return null;
        }

        return [
            'patient' => $patient,
            'gender' => config('constants.gender_array'),
        ];
    }

    /**
     * Update a patient
     */
    public function update(int $id, array $data): array
    {
        $accountId = Auth::user()->account_id;
        $userId = Auth::user()->id;

        // Handle masked phone
        if (isset($data['phone']) && $data['phone'] === '***********' && isset($data['old_phone'])) {
            $data['phone'] = $data['old_phone'];
        }

        $oldPhone = $data['old_phone'] ?? null;
        unset($data['old_phone']);

        $data['phone'] = GeneralFunctions::cleanNumber($data['phone']);

        // Get old patient data for comparison
        $oldPatient = $this->findPatient($id);
        $oldValues = $oldPatient ? [
            'name' => $oldPatient->name,
            'email' => $oldPatient->email,
            'phone' => $oldPatient->phone,
            'gender' => $oldPatient->gender,
            'dob' => $oldPatient->dob,
            'address' => $oldPatient->address,
            'cnic' => $oldPatient->cnic,
        ] : [];

        $patient = $this->updatePatientRecord($id, $data);

        if ($patient) {
            // Update related records
            Appointments::where('patient_id', $id)->update(['name' => $data['name']]);
            
            if ($oldPhone) {
                Leads::where('phone', $oldPhone)->update([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'gender' => $data['gender'] ?? null,
                ]);
            }

            // Log patient info changes
            $fieldChanges = [];
            if (isset($data['name']) && $oldValues['name'] != $data['name']) {
                $fieldChanges['Name'] = ['old' => $oldValues['name'], 'new' => $data['name']];
            }
            if (isset($data['email']) && $oldValues['email'] != ($data['email'] ?? '')) {
                $fieldChanges['Email'] = ['old' => $oldValues['email'] ?? 'N/A', 'new' => $data['email'] ?? 'N/A'];
            }
            if (isset($data['phone']) && $oldValues['phone'] != $data['phone']) {
                $fieldChanges['Phone'] = ['old' => $oldValues['phone'], 'new' => $data['phone']];
            }
            if (isset($data['gender']) && $oldValues['gender'] != ($data['gender'] ?? '')) {
                $fieldChanges['Gender'] = ['old' => $oldValues['gender'] ?? 'N/A', 'new' => $data['gender'] ?? 'N/A'];
            }
            if (isset($data['dob']) && $oldValues['dob'] != ($data['dob'] ?? '')) {
                $fieldChanges['Date of Birth'] = ['old' => $oldValues['dob'] ?? 'N/A', 'new' => $data['dob'] ?? 'N/A'];
            }
            if (isset($data['address']) && $oldValues['address'] != ($data['address'] ?? '')) {
                $fieldChanges['Address'] = ['old' => $oldValues['address'] ?? 'N/A', 'new' => $data['address'] ?? 'N/A'];
            }
            if (isset($data['cnic']) && $oldValues['cnic'] != ($data['cnic'] ?? '')) {
                $fieldChanges['CNIC'] = ['old' => $oldValues['cnic'] ?? 'N/A', 'new' => $data['cnic'] ?? 'N/A'];
            }
            
            if (!empty($fieldChanges)) {
                \App\Helpers\ActivityLogger::logPatientUpdated($patient, $fieldChanges);
            }

            return [
                'status' => true,
                'message' => 'Record has been updated successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => 'Something went wrong, please try again later.',
        ];
    }

    /**
     * Delete a patient
     */
    public function delete(int $id): array
    {
        $accountId = Auth::user()->account_id;
        $patient = $this->findPatient($id);

        if (!$patient) {
            return [
                'status' => false,
                'message' => 'Resource not found.',
            ];
        }

        if ($this->hasChildRecords($id, $accountId)) {
            $childDetails = Patients::getChildRecordsDetails($id, $accountId);
            $childList = implode(', ', $childDetails);
            return [
                'status' => false,
                'message' => "Cannot delete patient. Related records exist: {$childList}",
            ];
        }

        $patient->delete();
        AuditTrails::deleteEventLogger('users', 'delete', $this->getFillable(), $id);

        return [
            'status' => true,
            'message' => 'Record has been deleted successfully.',
        ];
    }

    /**
     * Bulk delete patients
     */
    public function bulkDelete(array $ids, int $accountId): array
    {
        $patients = Patients::whereIn('id', $ids)
            ->where('account_id', $accountId)
            ->get();

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

        if ($deletedCount > 0 && count($skippedPatients) === 0) {
            return [
                'status' => true,
                'message' => "{$deletedCount} patient(s) deleted successfully!",
            ];
        }

        if ($deletedCount > 0 && count($skippedPatients) > 0) {
            return [
                'status' => true,
                'message' => "{$deletedCount} patient(s) deleted. Skipped " . count($skippedPatients) . " patient(s) with related records: " . implode('; ', $skippedPatients),
            ];
        }

        return [
            'status' => false,
            'message' => 'Cannot delete patient(s). Related records exist: ' . implode('; ', $skippedPatients),
        ];
    }

    /**
     * Change patient status
     */
    public function changeStatus(int $id, int $status): array
    {
        $patient = $this->findPatient($id);

        if (!$patient) {
            return [
                'status' => false,
                'message' => 'Resource not found.',
            ];
        }

        $patient->update(['active' => $status]);

        $action = $status ? 'active' : 'inactive';
        $method = $status ? 'activeEventLogger' : 'inactiveEventLogger';
        AuditTrails::$method('users', $action, $this->getFillable(), $id);

        $message = $status ? 'Record has been activated successfully.' : 'Record has been inactivated successfully.';

        return [
            'status' => true,
            'message' => $message,
        ];
    }

    /**
     * Get patient by ID
     */
    public function getPatient(int $id): ?array
    {
        $patient = $this->findPatient($id);

        if (!$patient) {
            return null;
        }

        // Get patient membership
        $membership = Membership::with('membershipType')
            ->where('patient_id', (int)$patient->id)
            ->where('active', 1)
            ->first();

        return [
            'patient' => $patient,
            'membership' => $membership ? [
                'code' => $membership->code,
                'type' => $membership->membershipType->name ?? 'Unknown',
                'start_date' => $membership->start_date,
                'end_date' => $membership->end_date,
                'is_active' => $membership->end_date >= now()->format('Y-m-d'),
            ] : null,
            'permissions' => $this->getPermissions(),
        ];
    }

    /**
     * Assign membership to patient
     */
    public function assignMembership(int $patientId, string $membershipCode): array
    {
        // Check if patient already has membership
        $existingMembership = Membership::where('patient_id', $patientId)->first();
        if ($existingMembership) {
            return [
                'status' => false,
                'message' => 'A membership is already assigned to this patient.',
            ];
        }

        // Find available membership
        $membership = Membership::with('membershipType')
            ->where('code', $membershipCode)
            ->where('active', 1)
            ->whereNull('patient_id')
            ->first();

        if (!$membership) {
            return [
                'status' => false,
                'message' => 'Membership is inactive or already assigned to a patient.',
            ];
        }

        // Assign membership
        $membership->update([
            'patient_id' => $patientId,
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date' => Carbon::now()->addDays($membership->membershipType->period)->format('Y-m-d'),
            'assigned_at' => Carbon::now()->format('Y-m-d'),
        ]);

        // Log activity
        $patient = Patients::find($patientId);
        if ($patient) {
            \App\Helpers\ActivityLogger::logMembershipAssigned($patient, $membership, $membership->membershipType);
        }

        return [
            'status' => true,
            'message' => 'Membership assigned successfully.',
        ];
    }

    /**
     * Assign voucher to patient
     */
    public function assignVoucher(int $patientId, int $voucherId, float $amount): array
    {
        UserVouchers::create([
            'user_id' => $patientId,
            'voucher_id' => $voucherId,
            'amount' => $amount,
            'total_amount' => $amount,
        ]);

        return [
            'status' => true,
            'message' => 'Voucher assigned successfully.',
        ];
    }

    /**
     * Add referral to patient
     */
    public function addReferral(int $patientId, string $membershipCode): array
    {
        // Find membership
        $membership = Membership::with('membershipType')
            ->where('code', $membershipCode)
            ->where('active', 1)
            ->first();

        if (!$membership) {
            return [
                'status' => false,
                'message' => 'Invalid membership code or membership is inactive.',
            ];
        }

        // Verify patient exists
        $patient = Patients::find($patientId);
        if (!$patient) {
            return [
                'status' => false,
                'message' => 'Patient not found.',
            ];
        }

        // Check if membership is already assigned to the same patient
        if ($membership->patient_id == $patientId) {
            return [
                'status' => false,
                'message' => 'Membership is already assigned to this patient.',
            ];
        }

        // Check if membership code is not assigned to any patient
        if (is_null($membership->patient_id)) {
            return [
                'status' => false,
                'message' => 'This membership code is not assigned to any patient, so referral cannot be added.',
            ];
        }

        // Check if membership type is Gold Membership
        if (!$membership->membershipType) {
            return [
                'status' => false,
                'message' => 'Membership type not found.',
            ];
        }

        $membershipTypeName = strtolower(trim($membership->membershipType->name));
        if ($membershipTypeName !== 'gold membership') {
            return [
                'status' => false,
                'message' => 'Referrals can only be created for Gold Membership type. Current membership type: ' . $membership->membershipType->name,
            ];
        }

        // Check if membership is expired
        if ($membership->patient_id != $patientId) {
            $endDate = Carbon::parse($membership->end_date);
            if ($endDate->isPast()) {
                return [
                    'status' => false,
                    'message' => 'Membership is expired, referral cannot be added.',
                ];
            }
        }

        // Check maximum referrals limit (2 per membership code)
        $existingReferrals = Membership::where('code', $membershipCode)
            ->where('is_referral', 1)
            ->count();

        if ($existingReferrals >= 2) {
            return [
                'status' => false,
                'message' => 'Maximum of 2 referrals allowed per membership code. Limit reached.',
            ];
        }

        // Create referral record
        $referral = Membership::create([
            'code' => $membership->code,
            'membership_type_id' => $membership->membership_type_id,
            'start_date' => $membership->start_date,
            'end_date' => $membership->end_date,
            'patient_id' => $patientId,
            'created_by' => Auth::user()->id,
            'updated_by' => Auth::user()->id,
            'active' => 1,
            'assigned_at' => Carbon::now()->format('Y-m-d'),
            'is_referral' => 1,
            'parent_membership_code' => $membership->code,
        ]);

        if ($referral) {
            return [
                'status' => true,
                'message' => 'Referral added successfully.',
                'referral' => $referral,
                'patient' => $patient,
            ];
        }

        return [
            'status' => false,
            'message' => 'Failed to add referral. Please try again.',
        ];
    }

    /**
     * Store patient image
     */
    public function storeImage(int $patientId, $file): array
    {
        $patient = $this->findPatient($patientId);

        if (!$patient) {
            return [
                'status' => false,
                'message' => 'Resource not found.',
            ];
        }

        $ext = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($ext, $allowedExtensions)) {
            return [
                'status' => false,
                'message' => 'JPG, JPEG, PNG, GIF Only Allowed.',
            ];
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

    /**
     * Search patients by ID, name, or phone (AJAX)
     */
    public function searchPatients(string $search, int $accountId): array
    {
        $originalSearch = $search;
        $search = GeneralFunctions::patientSearch($search);
        $cleanedSearch = GeneralFunctions::clearnString($search);

        // Check if search is numeric (could be ID or phone)
        if (is_numeric($cleanedSearch)) {
            $numericValue = (int) $cleanedSearch;
            
            // First, check for exact ID match
            $exactMatch = Patients::where('user_type_id', Config::get('constants.patient_id'))
                ->where('active', 1)
                ->where('account_id', $accountId)
                ->where('id', $numericValue)
                ->select('name', 'id', 'phone')
                ->first();
            
            // If exact ID match found, return only that record
            if ($exactMatch) {
                return [$exactMatch->toArray()];
            }
            
            // Otherwise, search by partial ID or phone
            $phone = GeneralFunctions::cleanNumber($originalSearch);
            return Patients::where('user_type_id', Config::get('constants.patient_id'))
                ->where('active', 1)
                ->where('account_id', $accountId)
                ->where(function ($q) use ($numericValue, $phone) {
                    $q->where('id', 'LIKE', "%{$numericValue}%")
                      ->orWhere('phone', 'LIKE', "%{$phone}%");
                })
                ->select('name', 'id', 'phone')
                ->limit(20)
                ->get()
                ->toArray();
        }

        // Search by name when not numeric
        return Patients::where('user_type_id', Config::get('constants.patient_id'))
            ->where('active', 1)
            ->where('account_id', $accountId)
            ->where('name', 'LIKE', "%{$search}%")
            ->select('name', 'id', 'phone')
            ->limit(20)
            ->get()
            ->toArray();
    }

    /**
     * Find patient by ID
     */
    private function findPatient(int $id): ?Patients
    {
        return Patients::where('id', $id)
            ->where('account_id', Auth::user()->account_id)
            ->first();
    }

    /**
     * Create patient record
     */
    private function createPatientRecord(array $data): ?Patients
    {
        $record = Patients::create($data);
        AuditTrails::addEventLogger('users', 'create', $data, $this->getFillable(), $record);
        return $record;
    }

    /**
     * Update patient record
     */
    private function updatePatientRecord(int $id, array $data): ?Patients
    {
        $patient = Patients::find($id);
        if (!$patient) {
            return null;
        }

        $oldData = $patient->toArray();
        $patient->update($data);
        AuditTrails::EditEventLogger('users', 'edit', $patient, $this->getFillable(), $oldData, $id);

        return $patient;
    }

    /**
     * Check if patient has child records
     */
    private function hasChildRecords(int $id, int $accountId): bool
    {
        return Leads::where(['patient_id' => $id, 'account_id' => $accountId])->exists()
            || Appointments::where(['patient_id' => $id, 'account_id' => $accountId])->exists();
    }

    /**
     * Get fillable fields for audit
     */
    private function getFillable(): array
    {
        return ['name', 'email', 'phone', 'main_account', 'gender', 'cnic', 'dob', 'address', 'referred_by', 'user_type_id'];
    }

    /**
     * Get patient appointments datatable data (OPTIMIZED)
     * Uses single query with JOINs instead of eager loading for better performance
     */
    public function getPatientAppointments(int $patientId, Request $request): array
    {
        $accountId = Auth::user()->account_id;
        $filters = getFilters($request->all());
        
        // Get count first with simple query
        $countQuery = DB::table('appointments')
            ->where('patient_id', $patientId)
            ->where('account_id', $accountId);
        
        $iTotalRecords = $countQuery->count();

        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        // Single optimized query with all JOINs - no eager loading
        $appointments = DB::table('appointments')
            ->select([
                'appointments.id',
                'appointments.scheduled_date',
                'appointments.consultancy_type',
                'appointments.appointment_type_id as type_id',
                'appointments.created_at',
                'patients.name as patient_name',
                'patients.phone as patient_phone',
                'doctors.name as doctor_name',
                'cities.name as city_name',
                'locations.name as location_name',
                'services.name as service_name',
                'appointment_statuses.name as status_name',
                'appointment_types.name as type_name',
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
            ->orderBy('appointments.scheduled_date', 'DESC')
            ->offset($iDisplayStart)
            ->limit($iDisplayLength)
            ->get();

        // Transform data
        $canViewContact = Gate::allows('contact');
        $data = $appointments->map(function ($apt) use ($canViewContact) {
            return [
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
            ];
        });

        return [
            'data' => $data,
            'meta' => [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ],
            'filter_values' => [
                'patient' => null,
                'cities' => [],
                'locations' => [],
                'appointment_statuses' => [],
                'appointment_types' => [],
                'doctors' => [],
                'services' => [],
                'users' => [],
                'consultancy_types' => [],
            ],
            'active_filters' => [],
        ];
    }

    /**
     * Get patient vouchers datatable data (OPTIMIZED)
     * Uses user_vouchers table joined with discounts table
     */
    public function getPatientVouchers(int $patientId, Request $request): array
    {
        // Get count first
        $iTotalRecords = DB::table('user_vouchers')
            ->where('user_id', $patientId)
            ->count();

        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        // Single optimized query with JOIN to discounts table
        $vouchers = DB::table('user_vouchers')
            ->select([
                'user_vouchers.id',
                'user_vouchers.voucher_id',
                'user_vouchers.amount',
                'user_vouchers.total_amount',
                'user_vouchers.created_at',
                'discounts.name as voucher_name',
                'discounts.start as start_date',
                'discounts.end as end_date',
            ])
            ->leftJoin('discounts', 'user_vouchers.voucher_id', '=', 'discounts.id')
            ->where('user_vouchers.user_id', $patientId)
            ->orderBy('user_vouchers.created_at', 'DESC')
            ->offset($iDisplayStart)
            ->limit($iDisplayLength)
            ->get();

        $data = $vouchers->map(function ($voucher) use ($patientId) {
            // Calculate consumed and balance amounts
            $totalAmount = $voucher->total_amount ?? 0;
            $currentBalance = $voucher->amount;
            
            // If amount is null or 0 but total_amount exists, check if voucher has been used
            if ($currentBalance === null || ($currentBalance == 0 && $totalAmount > 0)) {
                // Check if any package_vouchers exist for this user/voucher combination
                $hasUsage = DB::table('package_vouchers')
                    ->where('user_id', $patientId)
                    ->where('voucher_id', $voucher->voucher_id)
                    ->exists();
                
                // If no usage exists, balance = total_amount (voucher not used yet)
                if (!$hasUsage) {
                    $currentBalance = $totalAmount;
                } else {
                    $currentBalance = $currentBalance ?? 0;
                }
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
            'meta' => [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ],
            'permissions' => [
                'edit' => Gate::allows('vouchers_edit'),
                'delete' => Gate::allows('vouchers_destroy'),
            ],
            'filter_values' => [
                'patient' => null,
            ],
            'active_filters' => [],
        ];
    }
}
