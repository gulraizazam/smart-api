<?php

namespace App\Services\Plan;

use App\Models\Packages;
use App\Models\PackageAdvances;
use App\Models\PackageService;
use App\Models\Locations;
use App\Models\User;
use App\Models\PaymentModes;
use App\Models\Settings;
use App\Models\Discounts;
use App\Models\Bundles;
use App\Models\BundleHasServices;
use App\Models\Services;
use App\Models\UserVouchers;
use App\Models\PackageVouchers;
use App\Models\PackageBundles;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\PlanInvoice;
use App\Models\Activity;
use App\Models\Leads;
use App\Models\Patients;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\UserHasLocations;
use App\Models\ServiceHasLocations;
use App\Models\DoctorHasLocations;
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\ActivityLogger;
use App\Helpers\Widgets\PlanAppointmentCalculation;
use App\Helpers\Invoice_Plan_Refund_Sms_Functions;
use App\Exceptions\PlanException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;

class PlanService
{
    protected int $cacheTtl = 3600; // 1 hour cache

    /**
     * Get optimized datatable data for plans (patient-specific)
     * Uses eager loading and aggregated queries to avoid N+1 problems
     */
    public function getDatatableData(array $filters, int $patientId): array
    {
        $userId = Auth::id();
        $accountId = Auth::user()->account_id;
        $filename = 'patient_packages';

        $whereConditions = $this->buildWhereConditions($filters, $filename, $userId, $accountId, $patientId);
        [$orderBy, $order] = $this->getOrderParams($filters);

        // Build optimized count query
        $totalRecords = $this->buildCountQuery($whereConditions, $accountId)->count();

        // Build result query with eager loading and aggregations
        $resultQuery = $this->buildOptimizedResultQuery($whereConditions, $accountId);

        return [
            'total' => $totalRecords,
            'query' => $resultQuery,
            'orderBy' => $orderBy,
            'order' => $order,
        ];
    }

    /**
     * Get optimized datatable data for global plans (admin packages page)
     * Uses eager loading and aggregated queries to avoid N+1 problems
     */
    public function getGlobalDatatableData(array $filters): array
    {
        $userId = Auth::id();
        $accountId = Auth::user()->account_id;
        $filename = 'packages';

        $whereConditions = $this->buildGlobalWhereConditions($filters, $filename, $userId, $accountId);
        [$orderBy, $order] = $this->getOrderParams($filters);

        // Build optimized count query
        $totalRecords = $this->buildCountQuery($whereConditions, $accountId)->count();

        // Build result query with eager loading and aggregations
        $resultQuery = $this->buildOptimizedResultQuery($whereConditions, $accountId);

        return [
            'total' => $totalRecords,
            'query' => $resultQuery,
            'orderBy' => $orderBy,
            'order' => $order,
        ];
    }

    /**
     * Build lightweight count query
     */
    protected function buildCountQuery(array $where, int $accountId): \Illuminate\Database\Eloquent\Builder
    {
        $query = Packages::query();

        if (!empty($where)) {
            $query->where($where);
        }

        $query->whereIn('location_id', ACL::getUserCentres());

        // Check permission for viewing inactive plans
        if (!\Illuminate\Support\Facades\Gate::allows('view_inactive_plans')) {
            $query->where('active', 1);
        }

        return $query;
    }

    /**
     * Build optimized result query with eager loading and aggregations.
     * Uses LEFT JOINs on pre-aggregated subqueries instead of per-row correlated subqueries.
     */
    protected function buildOptimizedResultQuery(array $where, int $accountId): \Illuminate\Database\Eloquent\Builder
    {
        $query = Packages::query()
            ->select([
                'packages.*',
                // total_price from pre-aggregated joins
                DB::raw('CASE 
                    WHEN packages.plan_type = "membership" THEN COALESCE(pb_agg.bundle_total, 0)
                    ELSE COALESCE(ps_agg.service_total, 0)
                END as total_price'),
                // Cash aggregates from single pre-aggregated join
                DB::raw('COALESCE(pa_agg.cash_receive, 0) as cash_receive'),
                DB::raw('COALESCE(pa_agg.settle_amount, 0) as settle_amount'),
                DB::raw('COALESCE(pa_agg.refund_amount_calculated, 0) as refund_amount_calculated'),
                // Session count from pre-aggregated join
                DB::raw('COALESCE(ps_agg.session_count, 0) as session_count'),
                // Latest activity timestamp from pre-aggregated joins
                DB::raw('GREATEST(
                    COALESCE(pa_agg.max_updated, "1970-01-01"),
                    COALESCE(pb_agg.max_updated, "1970-01-01"),
                    COALESCE(ps_agg.max_updated, "1970-01-01")
                ) as latest_advance_updated_at'),
            ])
            // Pre-aggregate package_advances per package_id (single pass)
            ->leftJoin(DB::raw('(
                SELECT package_id,
                    SUM(CASE WHEN cash_flow = "in" AND is_cancel = 0 THEN cash_amount ELSE 0 END) as cash_receive,
                    SUM(CASE WHEN cash_flow = "out" AND is_refund = 0 THEN cash_amount ELSE 0 END) as settle_amount,
                    SUM(CASE WHEN is_refund = 1 THEN cash_amount ELSE 0 END) as refund_amount_calculated,
                    MAX(updated_at) as max_updated
                FROM package_advances
                WHERE deleted_at IS NULL
                GROUP BY package_id
            ) as pa_agg'), 'pa_agg.package_id', '=', 'packages.id')
            // Pre-aggregate package_bundles per package_id (single pass)
            ->leftJoin(DB::raw('(
                SELECT package_id,
                    SUM(tax_including_price) as bundle_total,
                    MAX(updated_at) as max_updated
                FROM package_bundles
                GROUP BY package_id
            ) as pb_agg'), 'pb_agg.package_id', '=', 'packages.id')
            // Pre-aggregate package_services per package_id (single pass)
            ->leftJoin(DB::raw('(
                SELECT package_id,
                    SUM(tax_including_price) as service_total,
                    COUNT(*) as session_count,
                    MAX(updated_at) as max_updated
                FROM package_services
                GROUP BY package_id
            ) as ps_agg'), 'ps_agg.package_id', '=', 'packages.id')
            ->with([
                'user:id,name,account_id',
                'user.membership:id,patient_id,code,active,end_date,is_referral',
                'location:id,name,city_id',
                'location.city:id,name'
            ]);

        if (!empty($where)) {
            $query->where($where);
        }

        $query->whereIn('packages.location_id', ACL::getUserCentres());

        // Check permission for viewing inactive plans
        if (!\Illuminate\Support\Facades\Gate::allows('view_inactive_plans')) {
            $query->where('packages.active', 1);
        }

        return $query;
    }

    /**
     * Build where conditions from filters
     */
    protected function buildWhereConditions(array $filters, string $filename, int $userId, int $accountId, int $patientId): array
    {
        $where = [];
        $applyFilter = $this->shouldApplyFilter($filters);

        // Patient ID filter
        $where[] = ['packages.patient_id', '=', $patientId];
        Filters::put($userId, $filename, 'patient_id', $patientId);

        // Account ID filter
        $where[] = ['packages.account_id', '=', $accountId];
        Filters::put($userId, $filename, 'account_id', $accountId);

        // Package ID filter
        if ($this->hasFilter($filters, 'package_id')) {
            $where[] = ['packages.id', '=', $filters['package_id']];
            Filters::put($userId, $filename, 'package_id', $filters['package_id']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, $filename, 'package_id');
            } else {
                if ($packageId = Filters::get($userId, $filename, 'package_id')) {
                    $where[] = ['packages.id', '=', $packageId];
                }
            }
        }

        // Location filter
        if ($this->hasFilter($filters, 'location_id')) {
            $where[] = ['packages.location_id', '=', $filters['location_id']];
            Filters::put($userId, $filename, 'location_id', $filters['location_id']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, $filename, 'location_id');
            } else {
                if ($locationId = Filters::get($userId, $filename, 'location_id')) {
                    $where[] = ['packages.location_id', '=', $locationId];
                }
            }
        }

        // Status filter
        if ($this->hasFilter($filters, 'status')) {
            $where[] = ['packages.active', '=', $filters['status']];
            Filters::put($userId, $filename, 'status', $filters['status']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, $filename, 'status');
            } else {
                $status = Filters::get($userId, $filename, 'status');
                if ($status === 0 || $status === 1 || $status === '0' || $status === '1') {
                    $where[] = ['packages.active', '=', $status];
                }
            }
        }

        // Date range filter
        if ($this->hasFilter($filters, 'created_at')) {
            $dateRange = explode(' - ', $filters['created_at']);
            if (count($dateRange) === 2) {
                $startDateTime = Carbon::parse($dateRange[0])->startOfDay();
                $endDateTime = Carbon::parse($dateRange[1])->endOfDay();
                
                $where[] = ['packages.created_at', '>=', $startDateTime];
                $where[] = ['packages.created_at', '<=', $endDateTime];
                Filters::put($userId, $filename, 'created_at', $filters['created_at']);
            }
        } else {
            if ($applyFilter) {
                Filters::forget($userId, $filename, 'created_at');
            }
        }

        return $where;
    }

    /**
     * Build where conditions for global plans (admin packages page)
     */
    protected function buildGlobalWhereConditions(array $filters, string $filename, int $userId, int $accountId): array
    {
        $where = [];
        $applyFilter = $this->shouldApplyFilter($filters);

        // Account ID filter (always required)
        $where[] = ['packages.account_id', '=', $accountId];
        Filters::put($userId, $filename, 'account_id', $accountId);

        // Patient ID/Search filter
        if ($this->hasFilter($filters, 'patient_id')) {
            $patientId = $filters['patient_id'];
            // Check if it's a search string (e.g., "P-123")
            if (is_string($patientId) && strpos($patientId, 'P-') === 0) {
                $patientId = \App\Helpers\GeneralFunctions::patientSearch($patientId);
            }
            $where[] = ['packages.patient_id', '=', $patientId];
            Filters::put($userId, $filename, 'patient_id', $patientId);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, $filename, 'patient_id');
            } else {
                if ($patientId = Filters::get($userId, $filename, 'patient_id')) {
                    $where[] = ['packages.patient_id', '=', $patientId];
                }
            }
        }

        // Package ID filter
        if ($this->hasFilter($filters, 'package_id')) {
            $where[] = ['packages.id', '=', $filters['package_id']];
            Filters::put($userId, $filename, 'package_id', $filters['package_id']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, $filename, 'package_id');
            } else {
                if ($packageId = Filters::get($userId, $filename, 'package_id')) {
                    $where[] = ['packages.id', '=', $packageId];
                }
            }
        }

        // Location filter
        if ($this->hasFilter($filters, 'location_id')) {
            $where[] = ['packages.location_id', '=', $filters['location_id']];
            Filters::put($userId, $filename, 'location_id', $filters['location_id']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, $filename, 'location_id');
            } else {
                if ($locationId = Filters::get($userId, $filename, 'location_id')) {
                    $where[] = ['packages.location_id', '=', $locationId];
                }
            }
        }

        // Status filter
        if ($this->hasFilter($filters, 'status')) {
            $where[] = ['packages.active', '=', $filters['status']];
            Filters::put($userId, $filename, 'status', $filters['status']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, $filename, 'status');
            } else {
                $status = Filters::get($userId, $filename, 'status');
                if ($status === 0 || $status === 1 || $status === '0' || $status === '1') {
                    $where[] = ['packages.active', '=', $status];
                }
            }
        }

        // Date range filter
        if ($this->hasFilter($filters, 'created_at')) {
            $dateRange = explode(' - ', $filters['created_at']);
            if (count($dateRange) === 2) {
                $startDateTime = Carbon::parse($dateRange[0])->startOfDay();
                $endDateTime = Carbon::parse($dateRange[1])->endOfDay();
                
                $where[] = ['packages.created_at', '>=', $startDateTime];
                $where[] = ['packages.created_at', '<=', $endDateTime];
                Filters::put($userId, $filename, 'created_at', $filters['created_at']);
            }
        } else {
            if ($applyFilter) {
                Filters::forget($userId, $filename, 'created_at');
            }
        }

        return $where;
    }

    /**
     * Get order parameters
     */
    protected function getOrderParams(array $filters): array
    {
        $orderBy = 'latest_advance_updated_at';
        $order = 'DESC';

        if (isset($filters['sort']['field']) && isset($filters['sort']['sort'])) {
            $orderBy = $filters['sort']['field'];
            $order = strtoupper($filters['sort']['sort']);
        }

        // Validate order direction
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'DESC';
        }

        // Map sortable fields
        $allowedFields = ['id', 'package_id', 'created_at', 'updated_at', 'latest_advance_updated_at'];
        if (!in_array($orderBy, $allowedFields)) {
            $orderBy = 'latest_advance_updated_at';
        }

        return [$orderBy, $order];
    }

    /**
     * Format datatable records
     */
    public function formatDatatableRecords($packages): array
    {
        $records = [];

        foreach ($packages as $package) {
            $records[] = [
                'id' => $package->id,
                'patient_id' => $package->patient_id ?? 'N/A',
                'name' => $package->user->name ?? 'N/A',
                'package_id' => $package->name,
                'plan_name' => $package->plan_name ?? '',
                'location_id' => $this->formatLocation($package),
                'location_name' => $package->location->name ?? 'N/A',
                'city_name' => $package->location->city->name ?? 'N/A',
                'session_count' => $package->session_count ?? 0,
                'total' => number_format($package->total_price, 0),
                'total_raw' => $package->total_price,
                'cash_receive' => number_format($package->cash_receive ?? 0, 0),
                'cash_receive_raw' => $package->cash_receive ?? 0,
                'settle_amount' => number_format($package->settle_amount ?? 0, 0),
                'settle_amount_raw' => $package->settle_amount ?? 0,
                'refunded' => number_format($package->refund_amount_calculated ?? 0, 0),
                'balance' => number_format(($package->cash_receive ?? 0) - ($package->settle_amount ?? 0) - ($package->refund_amount_calculated ?? 0), 0),
                'active' => $package->active,
                'status' => $package->active == 1 ? 'Active' : 'Inactive',
                'date' => $package->created_at->format('Y-m-d'),
                'created_at' => $package->created_at->format('F j, Y h:i A'),
                'patient_name' => $package->user->name ?? 'N/A',
                'membership_info' => $this->formatMembershipInfo($package->user),
                'plan_type' => $package->plan_type ?? 'plan',
            ];
        }

        return $records;
    }

    /**
     * Format location display
     */
    protected function formatLocation($package): string
    {
        if (!$package->location || !$package->location->city) {
            return 'N/A';
        }

        return $package->location->city->name . ' - ' . $package->location->name;
    }

    /**
     * Format membership information
     */
    protected function formatMembershipInfo($user): string
    {
        if (!$user || !$user->membership) {
            return 'No Membership';
        }

        $membership = $user->membership;
        $endDate = Carbon::parse($membership->end_date);
        $isExpired = $endDate->isPast();
        $status = $isExpired ? 'Expired' : ($membership->active === 1 ? 'Active' : 'Inactive');

        if ($membership->is_referral == 1) {
            return "Ref: ({$membership->code}) - {$status}";
        }

        return "Gold - {$membership->code} - {$status}";
    }

    /**
     * Get cached lookup data for filters (patient-specific)
     */
    public function getLookupData(int $patientId): array
    {
        $cacheKey = "plan_lookup_data_patient_{$patientId}_" . Auth::id();

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($patientId) {
            $userCentres = ACL::getUserCentres();

            return [
                'locations' => Locations::whereIn('id', $userCentres)
                    ->where('active', 1)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray(),
                'packages' => Packages::where('patient_id', $patientId)
                    ->pluck('name', 'id')
                    ->toArray(),
                'statuses' => [
                    '1' => 'Active',
                    '0' => 'Inactive'
                ],
            ];
        });
    }

    /**
     * Get cached lookup data for global filters (admin packages page)
     */
    public function getGlobalLookupData(): array
    {
        $cacheKey = "plan_global_lookup_data_" . Auth::id();

        return Cache::remember($cacheKey, $this->cacheTtl, function () {
            $userCentres = ACL::getUserCentres();
            $accountId = Auth::user()->account_id;

            return [
                'locations' => Locations::whereIn('id', $userCentres)
                    ->where('active', 1)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray(),
                'statuses' => [
                    '1' => 'Active',
                    '0' => 'Inactive'
                ],
            ];
        });
    }

    /**
     * Clear lookup cache
     */
    public function clearLookupCache(int $patientId): void
    {
        $cacheKey = "plan_lookup_data_patient_{$patientId}_" . Auth::id();
        Cache::forget($cacheKey);
    }

    /**
     * Check if filter exists and has value
     */
    protected function hasFilter(array $filters, string $key): bool
    {
        return isset($filters[$key]) && $filters[$key] !== '' && $filters[$key] !== null;
    }

    /**
     * Check if filters should be applied
     */
    protected function shouldApplyFilter(array $filters): bool
    {
        if (!isset($filters['action'])) {
            return false;
        }

        $action = $filters['action'];

        if (is_array($action) && isset($action[0]) && $action[0] === 'filter_cancel') {
            return true;
        }

        return $action === 'filter';
    }

    /**
     * Handle bulk delete action
     */
    public function handleBulkDelete(array $ids): array
    {
        $accountId = Auth::user()->account_id;
        $deletedCount = 0;
        $skippedCount = 0;

        $packages = Packages::whereIn('id', $ids)
            ->where('account_id', $accountId)
            ->get();

        foreach ($packages as $package) {
            // Check if child records exist
            if ($this->hasChildRecords($package->id, $accountId)) {
                $skippedCount++;
                continue;
            }

            $package->delete();
            $deletedCount++;
        }

        return [
            'deleted' => $deletedCount,
            'skipped' => $skippedCount,
            'message' => $deletedCount > 0 
                ? "Successfully deleted {$deletedCount} record(s)." . ($skippedCount > 0 ? " {$skippedCount} record(s) skipped due to dependencies." : '')
                : "No records were deleted. {$skippedCount} record(s) have dependencies.",
        ];
    }

    /**
     * Get optimized data for create plan form
     * 
     * @param array $userCentres
     * @return array
     */
    public function getCreateFormData(array $userCentres): array
    {
        // Get locations with eager loading
        $locations = Locations::whereIn('id', $userCentres)
            ->where('active', 1)
            ->with('city:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'city_id'])
            ->mapWithKeys(function ($location) {
                return [$location->id => $location->city->name . '-' . $location->name];
            });

        // Get payment modes
        $paymentmodes = PaymentModes::where('type', 'application')
            ->pluck('name', 'id');

        // Get discount range setting
        $customdiscountrange = Settings::where('slug', 'sys-discounts')->first();
        $range = $customdiscountrange ? explode(':', $customdiscountrange->data) : [0, 100];

        // Get active discounts
        $discounts = Discounts::where('active', 1)
            ->get(['id', 'name']);

        // Generate random ID
        $random_id = md5(time() . rand(1, 9999) . rand(78599, 99999));

        return [
            'locations' => $locations,
            'random_id' => $random_id,
            'paymentmodes' => $paymentmodes,
            'range' => $range,
            'discount_type' => config('constants.amount_types'),
            'discounts' => $discounts,
        ];
    }

    /**
     * Get optimized data for create plan form with patient-specific data
     * 
     * @param array $userCentres
     * @param int $patientId
     * @return array
     */
    public function getCreateFormDataForPatient(array $userCentres, int $patientId): array
    {
        \Log::info('getCreateFormDataForPatient called', ['patient_id' => $patientId]);
        
        // Get base form data
        $data = $this->getCreateFormData($userCentres);
        
        // Add marker to verify this method was called
        $data['patient_specific_data_loaded'] = true;

        // Get patient name
        $patientUser = DB::table('users')->where('id', $patientId)->first(['name']);
        $data['patient_name'] = $patientUser ? $patientUser->name : 'Unknown';

        try {
            // Get last arrived consultation
            $lastConsultation = DB::table('appointments')
                ->where('patient_id', $patientId)
                ->where('appointment_type_id', 1) // Consultation
                ->whereIn('appointment_status_id', [2, 16]) // Arrived statuses
                ->orderBy('created_at', 'DESC')
                ->first(['id', 'location_id']);
            
            \Log::info('Last consultation query result', ['consultation' => $lastConsultation]);

            if ($lastConsultation) {
                $data['last_consultation_location_id'] = $lastConsultation->location_id;
                $data['last_consultation_id'] = $lastConsultation->id;
                
                // Get location name
                $location = Locations::with('city:id,name')
                    ->where('id', $lastConsultation->location_id)
                    ->first(['id', 'name', 'city_id']);
                
                if ($location && $location->city) {
                    $data['last_consultation_location_name'] = $location->city->name . '-' . $location->name;
                } else if ($location) {
                    $data['last_consultation_location_name'] = $location->name;
                } else {
                    $data['last_consultation_location_name'] = 'Unknown Location';
                }
                
                // Get appointments for this location with service and doctor details
                $appointments = DB::table('appointments')
                    ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
                    ->leftJoin('users as doctors', 'appointments.doctor_id', '=', 'doctors.id')
                    ->where('appointments.patient_id', $patientId)
                    ->where('appointments.location_id', $lastConsultation->location_id)
                    ->where('appointments.appointment_type_id', 1) // Consultation
                    ->whereIn('appointments.appointment_status_id', [2, 16]) // Arrived statuses
                    ->orderBy('appointments.created_at', 'DESC')
                    ->select([
                        'appointments.id',
                        'appointments.created_at',
                        'appointments.doctor_id',
                        'services.name as service_name',
                        'doctors.name as doctor_name'
                    ])
                    ->get();
                
                $appointmentArray = [];
                foreach ($appointments as $appointment) {
                    if ($appointment->created_at) {
                        $formattedDate = Carbon::parse($appointment->created_at)->format('F d,Y h:i A');
                        $serviceName = $appointment->service_name ?? 'Consultation';
                        
                        // Check if doctor name already has "Dr" prefix
                        $doctorName = '';
                        if ($appointment->doctor_name) {
                            $doctorName = $appointment->doctor_name;
                            // Only add "Dr" prefix if it doesn't already exist
                            if (!str_starts_with($doctorName, 'Dr ') && !str_starts_with($doctorName, 'Dr.')) {
                                $doctorName = 'Dr ' . $doctorName;
                            }
                        }
                        
                        // Format: "Service Name - Date Time - Dr Name"
                        $displayName = $serviceName . ' - ' . $formattedDate;
                        if ($doctorName) {
                            $displayName .= ' - ' . $doctorName;
                        }
                        
                        $appointmentArray[$appointment->id] = [
                            'id' => $appointment->id . '.A',
                            'name' => $displayName,
                            'doctor_id' => $appointment->doctor_id
                        ];
                    }
                }
                
                $data['appointmentArray'] = $appointmentArray;
                
                // Get patient membership info
                $patient = DB::table('users')
                    ->leftJoin('user_memberships', 'users.id', '=', 'user_memberships.user_id')
                    ->leftJoin('membership_types', 'user_memberships.membership_type_id', '=', 'membership_types.id')
                    ->where('users.id', $patientId)
                    ->select([
                        'user_memberships.id as membership_id',
                        'membership_types.name as membership_name',
                        'user_memberships.end_date',
                        'user_memberships.active'
                    ])
                    ->first();
                
                if ($patient && isset($patient->membership_id) && $patient->membership_id) {
                    $endDate = Carbon::parse($patient->end_date);
                    $isExpired = $endDate->isPast();
                    $status = $isExpired ? 'Expired' : ($patient->active == 1 ? 'Active' : 'Inactive');
                    $data['patient_membership'] = $patient->membership_name . ' (' . $status . ')';
                } else {
                    $data['patient_membership'] = 'No Membership';
                }
            }

            return $data;
        } catch (\Exception $e) {
            \Log::error('Error in getCreateFormDataForPatient: ' . $e->getMessage(), [
                'patient_id' => $patientId,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return data with marker even on error
            $data['patient_data_error'] = $e->getMessage();
            return $data;
        }
    }

    /**
     * Get appointment information for plan creation (optimized)
     * 
     * @param int $patientId
     * @param int $locationId
     * @return array
     */
    public function getAppointmentInfo(int $patientId, int $locationId): array
    {
        try {
            // Get appointment statuses (arrived and converted)
            $arrivedStatus = DB::table('appointment_statuses')
                ->where('is_arrived', 1)
                ->first();
            
            $convertedStatus = DB::table('appointment_statuses')
                ->where('is_converted', 1)
                ->first();
            
            $validStatusIds = array_filter([
                $arrivedStatus->id ?? null,
                $convertedStatus->id ?? null
            ]);

            // Get consultancy appointment type
            $appointmentType = DB::table('appointment_types')
                ->where('slug', 'consultancy')
                ->first();

            // Get all consultations for the patient at this location (ordered by latest first)
            $appointments = DB::table('appointments')
                ->join('services', 'appointments.service_id', '=', 'services.id')
                ->join('users', 'appointments.doctor_id', '=', 'users.id')
                ->where('appointments.patient_id', $patientId)
                ->where('appointments.appointment_type_id', $appointmentType->id ?? null)
                ->where('appointments.location_id', $locationId)
                ->whereIn('appointments.appointment_status_id', $validStatusIds)
                ->whereNull('appointments.deleted_at')
                ->orderBy('appointments.scheduled_date', 'desc')
                ->orderBy('appointments.scheduled_time', 'desc')
                ->select(
                    'appointments.id',
                    'appointments.scheduled_date',
                    'appointments.scheduled_time',
                    'appointments.doctor_id',
                    'services.name as service_name',
                    'users.name as doctor_name'
                )
                ->get(); // Get all consultations

            // Format appointments array
            $appointmentArray = [];
            foreach ($appointments as $appointment) {
                $appointmentDateTime = $appointment->scheduled_date . ' ' . $appointment->scheduled_time;
                $appointmentArray[$appointment->id] = [
                    'id' => $appointment->id . '.A',
                    'name' => $appointment->service_name . ' - ' . 
                             Carbon::parse($appointmentDateTime)->format('F j,Y h:i A') . ' - ' . 
                             $appointment->doctor_name,
                    'doctor_id' => $appointment->doctor_id
                ];
            }

            // Get membership information - always pick the latest assigned active membership
            $membership = DB::table('memberships')
                ->join('membership_types', 'memberships.membership_type_id', '=', 'membership_types.id')
                ->where('memberships.patient_id', $patientId)
                ->select(
                    'memberships.end_date',
                    'memberships.active',
                    'membership_types.name as type_name'
                )
                ->orderByRaw("CASE WHEN memberships.end_date >= ? AND memberships.active = 1 THEN 0 ELSE 1 END", [now()->format('Y-m-d')])
                ->orderBy('memberships.assigned_at', 'desc')
                ->first();

            $membershipTypeName = 'No membership';
            if ($membership) {
                $isExpired = $membership->end_date < now()->format('Y-m-d');
                $status = $isExpired ? ' - Expired' : ($membership->active == 1 ? ' - Active' : ' - Inactive');
                $typeName = str_replace(' Membership', '', $membership->type_name);
                $membershipTypeName = "{$typeName}{$status}";
            }

            // Get allocated doctors for the location
            $doctorIds = DB::table('doctor_has_locations')
                ->where('is_allocated', 1)
                ->where('location_id', $locationId)
                ->pluck('user_id')
                ->toArray();

            // Fetch active doctors
            $allDoctors = DB::table('users')
                ->whereIn('id', $doctorIds)
                ->pluck('name', 'id')
                ->toArray();

            // Determine selected doctor from appointments
            $selectedUserId = null;
            if (!empty($appointmentArray)) {
                $firstAppointment = reset($appointmentArray);
                $firstDoctorId = $firstAppointment['doctor_id'];
                if (array_key_exists($firstDoctorId, $allDoctors)) {
                    $selectedUserId = $firstDoctorId;
                }
            }

            // Check for recent treatments (last 30 days)
            $thirtyDaysAgo = now()->subDays(30)->format('Y-m-d');
            
            $recentTreatmentDoctorIds = DB::table('appointments')
                ->where('patient_id', $patientId)
                ->where('location_id', $locationId)
                ->where('appointment_status_id', 2)
                ->where('appointment_type_id', 2)
                ->where('scheduled_date', '>=', $thirtyDaysAgo)
                ->pluck('doctor_id')
                ->unique()
                ->toArray();

            // Determine which doctors to show
            $userIdsToShow = [];
            if (!empty($recentTreatmentDoctorIds)) {
                // Show selected doctor + recent treatment doctors
                $userIdsToShow = array_unique(array_merge(
                    $selectedUserId ? [$selectedUserId] : [],
                    $recentTreatmentDoctorIds
                ));
            } else {
                // Show only selected doctor
                $userIdsToShow = $selectedUserId ? [$selectedUserId] : [];
            }

            // Filter doctors to only those that should be shown
            $usersToShow = [];
            foreach ($userIdsToShow as $userId) {
                if (array_key_exists($userId, $allDoctors)) {
                    $usersToShow[$userId] = $allDoctors[$userId];
                }
            }

            // Get the latest consultation ID (first one since ordered by date desc)
            $latestConsultationId = $appointments->first()?->id;

            return [
                'appointments' => $appointmentArray,
                'membership' => $membershipTypeName,
                'users' => $usersToShow,
                'selected_doctor_id' => $selectedUserId,
                'latest_consultation_id' => $latestConsultationId
            ];

        } catch (\Exception $e) {
            \Log::error('Get Appointment Info Error: ' . $e->getMessage());
            return [
                'appointments' => [],
                'membership' => 'No membership',
                'users' => [],
                'selected_doctor_id' => null
            ];
        }
    }

    /**
     * Get services/bundles available for a specific location (optimized)
     * 
     * @param int $locationId
     * @param int $accountId
     * @return array
     */
    public function getServicesByLocation(int $locationId, int $accountId): array
    {
        try {
            // Get service IDs for this location with eager loading
            $serviceHasLocations = DB::table('service_has_locations')
                ->where('location_id', $locationId)
                ->pluck('service_id');

            if ($serviceHasLocations->isEmpty()) {
                \Log::info("No services found for location_id: {$locationId}");
                return [];
            }
            
            \Log::info("Services for location {$locationId}: " . $serviceHasLocations->implode(', '));

            // Scenario 1: Check if location has service_id = 13 (all services access)
            if ($serviceHasLocations->contains(13)) {
                \Log::info("Location {$locationId} has all services access (service_id=13)");
                
                // Return all active child services (parent_id > 0, excluding parent services where parent_id = 0)
                $services = DB::table('services')
                    ->where('parent_id', '>', 0)
                    ->where('active', 1)
                    ->where('account_id', $accountId)
                    ->whereNull('deleted_at')
                    ->select('id', 'name', 'parent_id', 'active')
                    ->get();
                
                \Log::info("All child services found: " . $services->count());
                
                return $services->toArray();
            }

            // Get details of assigned services to determine if they are parent or child
            $assignedServices = DB::table('services')
                ->whereIn('id', $serviceHasLocations)
                ->whereNull('deleted_at')
                ->select('id', 'name', 'parent_id', 'active')
                ->get();

            $resultServices = collect();

            foreach ($assignedServices as $service) {
                // Scenario 2: If service is a parent (parent_id = 0), get all its children
                if ($service->parent_id == 0) {
                    \Log::info("Service {$service->id} is a parent (parent_id=0), fetching children");
                    
                    $children = DB::table('services')
                        ->where('parent_id', $service->id)
                        ->where('active', 1)
                        ->where('account_id', $accountId)
                        ->whereNull('deleted_at')
                        ->select('id', 'name', 'parent_id', 'active')
                        ->get();
                    
                    \Log::info("Found {$children->count()} children for parent service {$service->id}");
                    $resultServices = $resultServices->merge($children);
                } else {
                    // Scenario 3: If service is a child (parent_id > 0), return only this child
                    \Log::info("Service {$service->id} is a child (parent_id={$service->parent_id}), adding to results");
                    $resultServices->push($service);
                }
            }

            // Remove duplicates by id and filter out any parent services (parent_id must be > 0)
            $resultServices = $resultServices
                ->filter(function($service) {
                    return $service->parent_id > 0;
                })
                ->unique('id')
                ->values();
            
            \Log::info("Total child services found for location {$locationId}: " . $resultServices->count());
            
            return $resultServices->toArray();

        } catch (\Exception $e) {
            \Log::error('Get Services By Location Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's default center if they only have one assigned
     * 
     * @return array
     */
    public function getUserDefaultCenter(): array
    {
        $centers = ACL::getUserCentres();
        
        if (count($centers) === 1) {
            return [
                'status' => true,
                'center' => $centers[0],
            ];
        }

        return [
            'status' => false,
            'center' => null,
        ];
    }

    /**
     * Check if package has child records
     */
    protected function hasChildRecords(int $packageId, int $accountId): bool
    {
        return DB::table('invoice_details')->where('package_id', $packageId)->exists()
            || DB::table('package_advances')->where('package_id', $packageId)->exists();
    }

    /**
     * Add service/bundle to package (optimized)
     * 
     * @param array $data
     * @return array
     * @throws PlanException
     */
    public function addServiceToPackage(array $data): array
    {
        DB::beginTransaction();
        
        try {
            \Log::info('=== addServiceToPackage CALLED ===', [
                'bundle_id_from_data' => $data['bundle_id'],
            ]);

            // For plan type 'plan', bundle_id in request is actually a service_id
            // Always read from Services table
            $service = Services::find($data['bundle_id']);
            if (!$service) {
                throw new PlanException('Service not found', 404);
            }

            \Log::info('addServiceToPackage: service found from Services table', [
                'service_id' => $service->id,
                'service_name' => $service->name,
            ]);
            
            $location = Locations::find($data['location_id']);
            if (!$location) {
                throw new PlanException('Location not found', 404);
            }

            $discount = null;
            if (!empty($data['discount_id'])) {
                $discount = Discounts::find($data['discount_id']);
            }

            // Get sold by user info
            $soldBy = $data['sold_by'] ?? null;
            $soldByName = '-';
            if ($soldBy) {
                $soldByUser = User::find($soldBy);
                $soldByName = $soldByUser ? $soldByUser->name : '-';
            }

            // Build package bundle data directly from the service
            $packageBundleData = $this->buildPackageBundleDataFromService(
                $service,
                $discount,
                $location,
                $data
            );

            // Handle voucher consumption if discount is a voucher
            if ($discount && $discount->discount_type == 'voucher') {
                $this->handleVoucherConsumption(
                    $discount,
                    $data['user_id'],
                    $data['random_id'],
                    $service,
                    $data['discount_price'] ?? 0,
                    $packageBundleData['id']
                );
            }

            // For plan type 'plan', one service = one PackageService record
            $serviceData = [
                'service_price' => $service->price,
                'calculated_price' => $data['net_amount'] ?? $service->price,
                'service_id' => $service->id,
                'name' => $service->name,
                'is_consumed' => 0,
            ];

            // Build service data with tax calculations
            $allDataServices = $this->buildServiceDataWithTaxFromService(
                [$serviceData],
                $service,
                $location,
                $data
            );

            // Calculate total
            $previousServicesTotal = PackageService::where('random_id', $data['random_id'])
                ->sum('tax_including_price');
            
            $total = $previousServicesTotal > 0 
                ? $previousServicesTotal 
                : number_format((float) $packageBundleData['tax_including_price'], 2, '.', '');

            // Get existing package data
            $packageServices = PackageService::where('random_id', $data['random_id'])->get();
            $packageBundle = PackageBundles::where('random_id', $data['random_id'])->get();

            // Prepare discount data
            $discountData = $this->prepareDiscountData($discount, $packageBundleData, $data);

            DB::commit();

            return [
                'bundlesData' => $packageBundleData,
                'packageServicesData' => $allDataServices,
                'packageServices' => $packageServices,
                'packageBundle' => $packageBundle,
                'random_id' => $data['random_id'],
                'service_name' => $packageBundleData['service_name'],
                'service_price' => $packageBundleData['service_price'],
                'discount_name' => $discountData['discount_name'],
                'discount_type' => $discountData['discount_type'],
                'discount_price' => $discountData['discount_price'],
                'net_amount' => $packageBundleData['net_amount'],
                'total' => $total,
                'sold_by' => $soldBy,
                'sold_by_name' => $soldByName,
            ];

        } catch (PlanException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Add Service To Package Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw new PlanException('Failed to add service to package: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Build package bundle data with tax calculations
     */
    protected function buildPackageBundleData(Bundles $bundle, ?Discounts $discount, Locations $location, array $data): array
    {
        $packageBundleData = [
            'qty' => '1',
            'bundle_id' => $bundle->id,
            'service_price' => $bundle->price,
            'service_name' => $bundle->name,
            'net_amount' => $data['net_amount'],
        ];

        // Add discount data if applicable
        if ($discount) {
            $discountPrice = $data['discount_price'] ?? 0;
            if ($discountPrice > $bundle->price) {
                $discountPrice = $bundle->price;
            }
            
            $packageBundleData['discount_name'] = $discount->name;
            $packageBundleData['discount_price'] = $discountPrice;
            $packageBundleData['discount_type'] = $data['discount_type'] ?? null;
            $packageBundleData['discount_id'] = $discount->id;
        }

        // Calculate tax
        $taxTreatmentType = $bundle->tax_treatment_type_id;
        $taxPercentage = $location->tax_percentage;
        $isExclusive = ($data['is_exclusive'] ?? '0') == '1';
        $netAmount = $data['net_amount'];

        $packageBundleData['is_exclusive'] = $isExclusive;
        $packageBundleData['tax_percenatage'] = $taxPercentage;

        $taxData = $this->calculateTax($taxTreatmentType, $netAmount, $taxPercentage, $isExclusive);
        $packageBundleData = array_merge($packageBundleData, $taxData);

        // Generate random ID for this service
        $randomNumber = rand(1000, 9999);
        $packageBundleData['id'] = str_pad($randomNumber, 4, '0', STR_PAD_LEFT);

        return $packageBundleData;
    }

    /**
     * Build package bundle data from Service model (for plan type 'plan')
     */
    protected function buildPackageBundleDataFromService(Services $service, ?Discounts $discount, Locations $location, array $data): array
    {
        $packageBundleData = [
            'qty' => '1',
            'bundle_id' => $service->id, // Store service_id in bundle_id column
            'service_price' => $service->price,
            'service_name' => $service->name,
            'net_amount' => $data['net_amount'],
        ];

        // Add discount data if applicable
        if ($discount) {
            $discountPrice = $data['discount_price'] ?? 0;
            if ($discountPrice > $service->price) {
                $discountPrice = $service->price;
            }
            
            $packageBundleData['discount_name'] = $discount->name;
            $packageBundleData['discount_price'] = $discountPrice;
            $packageBundleData['discount_type'] = $data['discount_type'] ?? null;
            $packageBundleData['discount_id'] = $discount->id;
        }

        // Calculate tax using service's tax_treatment_type_id
        $taxTreatmentType = $service->tax_treatment_type_id;
        $taxPercentage = $location->tax_percentage;
        $isExclusive = ($data['is_exclusive'] ?? '0') == '1';
        $netAmount = $data['net_amount'];

        $packageBundleData['is_exclusive'] = $isExclusive;
        $packageBundleData['tax_percenatage'] = $taxPercentage;

        $taxData = $this->calculateTax($taxTreatmentType, $netAmount, $taxPercentage, $isExclusive);
        $packageBundleData = array_merge($packageBundleData, $taxData);

        // Generate random ID for this service
        $randomNumber = rand(1000, 9999);
        $packageBundleData['id'] = str_pad($randomNumber, 4, '0', STR_PAD_LEFT);

        return $packageBundleData;
    }

    /**
     * Build service data with tax calculations from Service model (for plan type 'plan')
     */
    protected function buildServiceDataWithTaxFromService(array $calculatedServicePrices, Services $service, Locations $location, array $data): array
    {
        $allDataServices = [];
        
        foreach ($calculatedServicePrices as $detail) {
            $dataService = [
                'random_id' => $data['random_id'],
                'service_id' => $detail['service_id'],
                'name' => $detail['name'],
                'price' => $detail['calculated_price'],
                'orignal_price' => $detail['service_price'],
                'created_at' => Filters::getCurrentTimeStamp(),
                'updated_at' => Filters::getCurrentTimeStamp(),
                'is_consumed' => 0,
                'sold_by' => $data['sold_by'] ?? null,
            ];

            $isExclusive = ($data['is_exclusive'] ?? '0') == '1';
            $taxData = $this->calculateServiceTax(
                $service->tax_treatment_type_id,
                $detail['calculated_price'],
                $location->tax_percentage,
                $isExclusive
            );

            $allDataServices[] = array_merge($dataService, $taxData);
        }

        return $allDataServices;
    }

    /**
     * Calculate tax based on treatment type
     */
    protected function calculateTax(int $taxTreatmentType, float $netAmount, float $taxPercentage, bool $isExclusive): array
    {
        $taxData = [];

        switch ($taxTreatmentType) {
            case Config::get('constants.tax_both'):
                if ($isExclusive) {
                    $taxData['tax_exclusive_net_amount'] = $netAmount;
                    $taxData['tax_price'] = ceil($netAmount * ($taxPercentage / 100));
                    $taxData['tax_including_price'] = ceil($netAmount + ($netAmount * $taxPercentage / 100));
                } else {
                    $taxData['tax_including_price'] = $netAmount;
                    $taxData['tax_exclusive_net_amount'] = ceil((100 * $netAmount) / ($taxPercentage + 100));
                    $taxData['tax_price'] = ceil($netAmount - $taxData['tax_exclusive_net_amount']);
                }
                break;

            case Config::get('constants.tax_is_exclusive'):
                $taxData['tax_exclusive_net_amount'] = $netAmount;
                $taxData['tax_price'] = ceil($netAmount * ($taxPercentage / 100));
                $taxData['tax_including_price'] = ceil($netAmount + ($netAmount * $taxPercentage / 100));
                break;

            default:
                $taxData['tax_including_price'] = $netAmount;
                $taxData['tax_exclusive_net_amount'] = ceil((100 * $netAmount) / ($taxPercentage + 100));
                $taxData['tax_price'] = ceil($netAmount - $taxData['tax_exclusive_net_amount']);
                break;
        }

        return $taxData;
    }

    /**
     * Handle voucher consumption with proper locking
     */
    protected function handleVoucherConsumption(Discounts $discount, int $userId, string $randomId, $serviceOrBundle, float $discountPrice, string $serviceId): void
    {
        $userVoucher = UserVouchers::where('voucher_id', $discount->id)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if ($userVoucher) {
            $originalVoucherAmount = $userVoucher->amount;
            $amountLeft = $userVoucher->amount - $serviceOrBundle->price;
            
            if ($amountLeft < 0) {
                $amountLeft = 0;
            }

            $actualConsumedAmount = $originalVoucherAmount - $amountLeft;

            $userVoucher->amount = $amountLeft;
            $userVoucher->save();

            $amountForVoucher = ($amountLeft <= 0) ? $discountPrice : $serviceOrBundle->price;

            PackageVouchers::create([
                'package_random_id' => $randomId,
                'voucher_id' => $discount->id,
                'user_id' => $userId,
                'amount' => $amountForVoucher,
                'service_id' => $serviceId,
                'main_service_id' => $serviceOrBundle->id
            ]);

            if ($actualConsumedAmount > 0) {
                $patient = User::find($userId);
                ActivityLogger::logVoucherConsumed($actualConsumedAmount, $patient, $discount, $amountLeft);
            }
        }
    }

    /**
     * Build bundle services data (fixes N+1 query)
     */
    protected function buildBundleServicesData($allBundleServices, array $packageBundleData): array
    {
        $bundleServices = [];
        
        foreach ($allBundleServices as $bundleService) {
            $bundleServices[] = [
                'service_price' => $bundleService->calculated_price,
                'calculated_price' => $bundleService->calculated_price,
                'service_id' => $bundleService->service_id,
                'name' => $bundleService->service->name, // Already loaded via eager loading
                'is_consumed' => 0,
                'tax_exclusive_price' => ceil((100 * $packageBundleData['net_amount']) / ($packageBundleData['tax_percenatage'] + 100)),
                'tax_price' => $packageBundleData['tax_price'],
                'tax_including_price' => $packageBundleData['tax_including_price']
            ];
        }

        return $bundleServices;
    }

    /**
     * Build service data with tax calculations
     */
    protected function buildServiceDataWithTax(array $calculatedServicePrices, Bundles $bundle, Locations $location, array $data): array
    {
        $allDataServices = [];
        
        // Debug: Log sold_by value
        \Log::info('Building service data with sold_by', ['sold_by' => $data['sold_by'] ?? 'NOT SET']);
        
        foreach ($calculatedServicePrices as $detail) {
            $dataService = [
                'random_id' => $data['random_id'],
                'service_id' => $detail['service_id'],
                'name' => $detail['name'],
                'price' => $detail['calculated_price'],
                'orignal_price' => $detail['service_price'],
                'created_at' => Filters::getCurrentTimeStamp(),
                'updated_at' => Filters::getCurrentTimeStamp(),
                'is_consumed' => 0,
                'sold_by' => $data['sold_by'] ?? null,
            ];

            $isExclusive = ($data['is_exclusive'] ?? '0') == '1';
            $taxData = $this->calculateServiceTax(
                $bundle->tax_treatment_type_id,
                $detail['calculated_price'],
                $location->tax_percentage,
                $isExclusive
            );

            $allDataServices[] = array_merge($dataService, $taxData);
        }

        return $allDataServices;
    }

    /**
     * Calculate tax for individual service
     */
    protected function calculateServiceTax(int $taxTreatmentType, float $price, float $taxPercentage, bool $isExclusive): array
    {
        $taxData = ['tax_percenatage' => $taxPercentage];

        if ($taxTreatmentType == Config::get('constants.tax_both')) {
            if ($isExclusive) {
                $taxData['tax_exclusive_price'] = $price;
                $taxData['tax_price'] = ceil($price * ($taxPercentage / 100));
                $taxData['tax_including_price'] = ceil($price + ($price * $taxPercentage / 100));
                $taxData['is_exclusive'] = 1;
            } else {
                $taxData['tax_including_price'] = $price;
                $taxData['tax_exclusive_price'] = ceil((100 * $price) / ($taxPercentage + 100));
                $taxData['tax_price'] = ceil($price - $taxData['tax_exclusive_price']);
                $taxData['is_exclusive'] = 0;
            }
        } elseif ($taxTreatmentType == Config::get('constants.tax_is_exclusive')) {
            $taxData['tax_exclusive_price'] = $price;
            $taxData['tax_price'] = ceil($price * ($taxPercentage / 100));
            $taxData['tax_including_price'] = ceil($price + ($price * $taxPercentage / 100));
            $taxData['is_exclusive'] = 1;
        } else {
            $taxData['tax_including_price'] = $price;
            $taxData['tax_exclusive_price'] = ceil((100 * $price) / ($taxPercentage + 100));
            $taxData['tax_price'] = ceil($price - $taxData['tax_exclusive_price']);
            $taxData['is_exclusive'] = 0;
        }

        return $taxData;
    }

    /**
     * Prepare discount data for response
     */
    protected function prepareDiscountData(?Discounts $discount, array $packageBundleData, array $data): array
    {
        if (empty($data['discount_id']) || $data['discount_id'] == '0') {
            return [
                'discount_name' => '-',
                'discount_type' => '-',
                'discount_price' => '0.00'
            ];
        }

        return [
            'discount_name' => $packageBundleData['discount_name'] ?? '-',
            'discount_type' => $packageBundleData['discount_type'] ?? '-',
            'discount_price' => $packageBundleData['discount_price'] ?? '0.00'
        ];
    }

    /**
     * Save complete plan package (optimized)
     * 
     * @param array $data
     * @return array
     * @throws PlanException
     */
    public function savePlanPackage(array $data, $request = null): array
    {
        DB::beginTransaction();
        
        try {
            // Handle appointment creation/retrieval
            $appointmentId = $this->handleAppointment($data);
            
            if (!$appointmentId) {
                throw new PlanException('Appointment ID is required', 400);
            }

            // Create package record
            $package = $this->createPackageRecord($data, $appointmentId);

            // Store package bundles/memberships and services
            if (($data['plan_type'] ?? 'plan') === 'membership') {
                // Calculate if membership is fully paid (remaining <= 0)
                $total = floatval(str_replace(',', '', $data['total'] ?? 0));
                $cashAmount = floatval($data['cash_amount'] ?? 0);
                $remaining = $total - $cashAmount;
                $isFullyPaid = $remaining <= 0;
                
                // Check if this is student membership and handle documents
                $hasStudentDocuments = false;
                $isStudentMembership = false;
                
                // Decode package_memberships if it's a JSON string (from FormData)
                $packageMemberships = $data['package_memberships'];
                if (is_string($packageMemberships)) {
                    $packageMemberships = json_decode($packageMemberships, true);
                }
                
                \Log::info('Membership data check', [
                    'has_request' => $request !== null,
                    'has_package_memberships' => !empty($packageMemberships),
                    'membership_id_set' => isset($packageMemberships[0]['membershipId']),
                    'cash_amount' => $data['cash_amount'] ?? 'not set'
                ]);
                
                if ($request && isset($packageMemberships[0]['membershipId'])) {
                    $membershipTypeId = $packageMemberships[0]['membershipId'];
                    $studentVerificationService = app(\App\Services\Membership\StudentVerificationService::class);
                    
                    $isStudentCheck = $studentVerificationService->isStudentMembership($membershipTypeId);
                    \Log::info('Checking membership type', [
                        'membership_type_id' => $membershipTypeId,
                        'is_student' => $isStudentCheck
                    ]);
                    
                    if ($isStudentCheck) {
                        $isStudentMembership = true;
                        
                        // Use pre-stored document paths from controller (stored at request entry)
                        $storedDocumentPaths = $data['pre_stored_document_paths'] ?? [];
                        $hasStudentDocuments = !empty($storedDocumentPaths);
                        
                        \Log::info('Student membership detected', [
                            'is_fully_paid' => $isFullyPaid,
                            'documents_count' => count($storedDocumentPaths),
                            'has_valid_documents' => $hasStudentDocuments,
                            'stored_paths' => $storedDocumentPaths,
                            'should_consume' => $isFullyPaid && $hasStudentDocuments
                        ]);
                        
                        // Student Membership Logic:
                        // Case 1: Full payment + Documents = CONSUME membership
                        // Case 2: Partial payment + Documents = DON'T consume (save records only)
                        // Case 3: Full payment + NO documents = DON'T consume (save records only)
                        $shouldConsume = $isFullyPaid && $hasStudentDocuments;
                        
                        $this->storeMembershipData($package, $data, $shouldConsume);
                        
                        // Create student verification record with already-stored document paths
                        if ($hasStudentDocuments) {
                            $membershipCodeId = $packageMemberships[0]['membershipCodeId'] ?? null;
                            $studentVerificationService->createVerificationRecord([
                                'patient_id' => $data['patient_id'],
                                'membership_id' => $membershipCodeId,
                                'membership_type_id' => $membershipTypeId,
                                'package_id' => $package->id,
                                'document_paths' => $storedDocumentPaths,
                            ]);
                        }
                    } else {
                        // Non-student membership: consume if fully paid, reserve if partial payment
                        \Log::info('Non-student membership', [
                            'membership_type_id' => $membershipTypeId,
                            'is_fully_paid' => $isFullyPaid,
                            'action' => $isFullyPaid ? 'consume' : 'reserve'
                        ]);
                        $this->storeMembershipData($package, $data, $isFullyPaid);
                    }
                } else {
                    // No request object or membership info: consume normally if fully paid
                    $this->storeMembershipData($package, $data, $isFullyPaid);
                }
                
                // Generate and update plan_name from first two services
                $this->updatePlanName($package);
                
                // Handle payment logic
                if (!empty($data['cash_amount']) && $data['cash_amount'] != '0') {
                    \Log::info('Processing payment for membership', [
                        'is_student' => $isStudentMembership,
                        'is_fully_paid' => $isFullyPaid,
                        'has_documents' => $hasStudentDocuments,
                        'cash_amount' => $data['cash_amount']
                    ]);
                    
                    if ($isStudentMembership) {
                        // Student membership payment logic
                        if ($isFullyPaid && $hasStudentDocuments) {
                            // Case 1: Full payment + Documents = Process payment and consume
                            \Log::info('Case 1: Full payment + Documents - consuming');
                            $this->handlePackagePayment($package, $data, $appointmentId);
                        } else {
                            // Case 2 & 3: Partial payment OR Full payment without documents
                            // Only record payment (no consumption)
                            \Log::info('Case 2/3: Recording payment only (no consumption)');
                            $this->handlePartialMembershipPayment($package, $data, $appointmentId);
                        }
                    } else {
                        // Non-student membership: normal payment handling
                        if ($isFullyPaid) {
                            $this->handlePackagePayment($package, $data, $appointmentId);
                        } else {
                            $this->handlePartialMembershipPayment($package, $data, $appointmentId);
                        }
                    }
                }
            } else {
                // Store package bundles and services (optimized with bulk operations)
                $this->storePackageBundlesOptimized($package, $data);
                
                // Generate and update plan_name from first two services
                $this->updatePlanName($package);

                // Handle payment if cash amount provided
                if (!empty($data['cash_amount']) && $data['cash_amount'] != '0') {
                    $this->handlePackagePayment($package, $data, $appointmentId);
                }
            }

            DB::commit();

            return [
                'status' => true,
                'package_id' => $package->id
            ];

        } catch (PlanException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Save Plan Package Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw new PlanException('Failed to save package: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle appointment creation or retrieval
     */
    protected function handleAppointment(array $data): ?int
    {
        if (!isset($data['appointment_id']) || empty($data['appointment_id'])) {
            return null;
        }

        $tagAppoint = explode('.', $data['appointment_id']);
        
        // Check if appointment_id has the expected format (e.g., "369475.A")
        if (count($tagAppoint) >= 2 && $tagAppoint[1] == 'A') {
            return (int) $tagAppoint[0];
        } elseif (count($tagAppoint) >= 2) {
            // Has format like "369475.S" - create new appointment
            $planAppointmentCalculation = new PlanAppointmentCalculation();
            $appointmentId = $planAppointmentCalculation->storeAppointment(
                $data['patient_id'],
                $data['location_id'],
                (object) $data,
                $tagAppoint[0],
                false
            );
            $planAppointmentCalculation->saveinvoice($appointmentId);
            return $appointmentId;
        } else {
            // Plain appointment ID without suffix (e.g., "369475")
            return (int) $tagAppoint[0];
        }
    }

    /**
     * Create package record
     */
    protected function createPackageRecord(array $data, int $appointmentId): Packages
    {
        // Remove commas from total but preserve decimal point
        $totalPrice = str_replace(',', '', $data['total']);
        
        $packageData = [
            'random_id' => $data['random_id'],
            'patient_id' => $data['patient_id'],
            'location_id' => $data['location_id'],
            'total_price' => $totalPrice,
            'sessioncount' => '1',
            'account_id' => Auth::user()->account_id,
            'is_exclusive' => $data['is_exclusive'] ?? 0,
            'plan_type' => $data['plan_type'] ?? 'plan',
            'appointment_id' => $appointmentId,
            'created_at' => Filters::getCurrentTimeStamp(),
            'updated_at' => Filters::getCurrentTimeStamp(),
        ];

        $package = Packages::create($packageData);
        $package->update(['name' => sprintf('%05d', $package->id)]);

        return $package;
    }

    /**
     * Store membership data in package_bundles and package_services
     * Also updates the memberships table with patient and date info (only if fully paid)
     * 
     * @param Packages $package
     * @param array $data
     * @param bool $isFullyPaid - If true, set is_consumed=1 and update membership. If false, only save records.
     */
    protected function storeMembershipData(Packages $package, array $data, bool $isFullyPaid = true): void
    {
        if (empty($data['package_memberships'])) {
            return;
        }

        // Decode package_memberships if it's a JSON string (from FormData)
        $packageMemberships = $data['package_memberships'];
        if (is_string($packageMemberships)) {
            $packageMemberships = json_decode($packageMemberships, true);
        }

        if (empty($packageMemberships) || !is_array($packageMemberships)) {
            return;
        }

        $locationInfo = Locations::find($data['location_id']);

        foreach ($packageMemberships as $membership) {
            $packageBundleData = [
                'random_id' => $package->random_id,
                'is_allocate' => 1,
                'qty' => 1,
                'discount_name' => $membership['DiscountName'] ?? null,
                'discount_type' => $membership['Type'] ?? null,
                'discount_price' => $membership['DiscountValue'] ?? 0,
                'service_price' => str_replace(',', '', $membership['RegularPrice']),
                'net_amount' => str_replace(',', '', $membership['RegularPrice']),
                'discount_id' => null,
                'bundle_id' => null,
                'membership_type_id' => $membership['membershipId'] ?? null,
                'membership_code_id' => $membership['membershipCodeId'] ?? null,
                'package_id' => $package->id,
                'tax_exclusive_net_amount' => str_replace(',', '', $membership['Amount']),
                'tax_percenatage' => $locationInfo->tax_percentage ?? 0,
                'tax_price' => $membership['Tax'] ?? 0,
                'tax_including_price' => str_replace(',', '', $membership['Total']),
                'location_id' => $data['location_id'],
            ];

            $packageBundle = PackageBundles::create($packageBundleData);

            // Create package_services record to store sold_by, is_consumed, consumed_at
            // Only set is_consumed=1 if fully paid, otherwise leave as 0
            $soldBy = $membership['sold_by'] ?? $data['sold_by'] ?? null;
            $consumedAt = $isFullyPaid ? Filters::getCurrentTimeStamp() : null;
            $packageServiceData = [
                'random_id' => $package->random_id,
                'package_id' => $package->id,
                'package_bundle_id' => $packageBundle->id,
                'service_id' => null, // Memberships don't have a service_id
                'is_consumed' => $isFullyPaid ? 1 : 0,
                'consumed_at' => $consumedAt,
                'price' => str_replace(',', '', $membership['RegularPrice']),
                'orignal_price' => str_replace(',', '', $membership['RegularPrice']),
                'actual_price' => str_replace(',', '', $membership['RegularPrice']),
                'is_exclusive' => 0,
                'tax_exclusive_price' => str_replace(',', '', $membership['Amount']),
                'tax_percenatage' => $locationInfo->tax_percentage ?? 0,
                'tax_price' => $membership['Tax'] ?? 0,
                'tax_including_price' => str_replace(',', '', $membership['Total']),
                'sold_by' => $soldBy,
            ];
            PackageService::create($packageServiceData);

            // Update the memberships table
            $membershipCodeId = $membership['membershipCodeId'] ?? null;
            if ($membershipCodeId) {
                $membershipRecord = Membership::find($membershipCodeId);
                if ($membershipRecord) {
                    if ($isFullyPaid) {
                        // Fully consume: set patient_id, dates, and mark as active
                        $membershipType = MembershipType::find($membership['membershipId'] ?? $membershipRecord->membership_type_id);
                        $durationDays = $membershipType->period ?? 365; // Default 365 days (1 year) if not set
                        
                        $startDate = now()->toDateString();
                        $endDate = now()->addDays($durationDays)->toDateString();
                        
                        $membershipRecord->update([
                            'patient_id' => $data['patient_id'],
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'assigned_at' => now()->toDateString(),
                            'updated_by' => Auth::id(),
                        ]);
                        
                        \Log::info('Membership fully consumed', [
                            'membership_code_id' => $membershipCodeId,
                            'patient_id' => $data['patient_id']
                        ]);
                    } else {
                        // Reserve only: link to patient but don't set dates (not yet active)
                        // This prevents the code from being used by someone else
                        $membershipRecord->update([
                            'patient_id' => $data['patient_id'],
                            'updated_by' => Auth::id(),
                        ]);
                        
                        \Log::info('Membership code reserved (linked to patient, not yet active)', [
                            'membership_code_id' => $membershipCodeId,
                            'patient_id' => $data['patient_id']
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Store package bundles and services with optimized queries (fixes N+1)
     */
    protected function storePackageBundlesOptimized(Packages $package, array $data): void
    {
        if (empty($data['package_bundles'])) {
            return;
        }

        // Check if package_bundles contains simple IDs (from patient plan form) or structured data
        $firstBundle = reset($data['package_bundles']);
        $isSimpleIdFormat = !is_array($firstBundle);
        
        if ($isSimpleIdFormat) {
            // Simple ID format - just update existing package_bundles records to link to this package
            $packageBundleIds = $data['package_bundles'];
            PackageBundles::whereIn('id', $packageBundleIds)
                ->where('random_id', $data['random_id'])
                ->update([
                    'package_id' => $package->id,
                    'is_allocate' => 1
                ]);
            
            // Also update package_services
            PackageService::whereIn('package_bundle_id', $packageBundleIds)
                ->where('random_id', $data['random_id'])
                ->update([
                    'package_id' => $package->id
                ]);
            return;
        }

        // Fetch location ONCE (instead of per bundle)
        $locationInfo = Locations::find($data['location_id']);
        
        // Extract all bundle IDs (for plan type 'plan', these are actually service IDs)
        $bundleIds = array_column($data['package_bundles'], 'bundleId');
        
        $planType = $data['plan_type'] ?? 'plan';
        
        \Log::info('storePackageBundlesOptimized: structured data path', [
            'plan_type' => $planType,
            'bundleIds' => $bundleIds,
        ]);

        $allPackageServices = [];

        if ($planType === 'plan') {
            // PLAN TYPE 'plan': bundleId contains service_id, read from Services table
            $servicesData = Services::whereIn('id', $bundleIds)->get()->keyBy('id');

            foreach ($data['package_bundles'] as $packageBundle) {
                $serviceId = $packageBundle['bundleId'];
                $serviceData = $servicesData->get($serviceId);
                
                if (!$serviceData) {
                    \Log::warning('storePackageBundlesOptimized: Service not found', ['service_id' => $serviceId]);
                    continue;
                }

                $discountId = $packageBundle['DiscountId'] ?? null;
                if ($discountId == '0' || $discountId == '') {
                    $discountId = null;
                }
                
                $packageBundleData = [
                    'random_id' => $package->random_id,
                    'is_allocate' => 1,
                    'qty' => 1,
                    'discount_name' => $packageBundle['DiscountName'] ?? null,
                    'discount_type' => $packageBundle['Type'] ?? null,
                    'discount_price' => str_replace(',', '', $packageBundle['DiscountValue'] ?? 0),
                    'service_price' => str_replace(',', '', $packageBundle['RegularPrice']),
                    'net_amount' => str_replace(',', '', $packageBundle['RegularPrice']),
                    'discount_id' => $discountId,
                    'config_group_id' => !empty($packageBundle['config_group_id']) ? $packageBundle['config_group_id'] : null,
                    'bundle_id' => $serviceId, // Store service_id in bundle_id column
                    'package_id' => $package->id,
                    'tax_exclusive_net_amount' => str_replace(',', '', $packageBundle['Amount']),
                    'tax_percentage' => 1,
                    'tax_price' => str_replace(',', '', $packageBundle['Tax']),
                    'tax_including_price' => str_replace(',', '', $packageBundle['Total']),
                    'location_id' => $data['location_id'],
                ];

                $packageBundleRecord = PackageBundles::create($packageBundleData);

                // For plan type 'plan', create one PackageService directly from the service
                $totalPrice = floatval(str_replace(',', '', $packageBundle['Total']));
                $serviceTaxType = $serviceData->tax_treatment_type_id;
                $isExclusive = ($serviceTaxType == Config::get('constants.tax_is_exclusive'));
                
                $taxData = $this->calculateServiceTaxForPackage(
                    $serviceTaxType,
                    $totalPrice,
                    $locationInfo->tax_percentage,
                    $isExclusive
                );

                // Determine consumption_order from row_type:
                // 0 = normal, 1 = BUY (configurable), 2 = discounted GET, 3 = free GET
                $consumptionOrder = 0;
                $rowType = $packageBundle['row_type'] ?? '';
                if ($rowType === 'buy') {
                    $consumptionOrder = 1;
                } elseif ($rowType === 'get') {
                    $consumptionOrder = ($totalPrice == 0) ? 3 : 2;
                }

                $allPackageServices[] = array_merge([
                    'random_id' => $data['random_id'],
                    'package_id' => $package->id,
                    'package_bundle_id' => $packageBundleRecord->id,
                    'service_id' => $serviceData->id,
                    'price' => $totalPrice,
                    'orignal_price' => $serviceData->price,
                    'actual_price' => $serviceData->price,
                    'consumption_order' => $consumptionOrder,
                    'created_at' => Filters::getCurrentTimeStamp(),
                    'updated_at' => Filters::getCurrentTimeStamp(),
                    'sold_by' => $packageBundle['sold_by'] ?? null,
                ], $taxData);

                \Log::info('storePackageBundlesOptimized: plan service added', [
                    'service_id' => $serviceData->id,
                    'service_name' => $serviceData->name,
                    'package_bundle_id' => $packageBundleRecord->id,
                ]);
            }
        } else {
            // PLAN TYPE 'bundle': bundleId contains actual bundle_id, read from Bundles table
            $bundlesData = Bundles::whereIn('id', $bundleIds)->get()->keyBy('id');
            
            // Fetch all bundle services at once with eager loading (fixes N+1)
            $allBundleServices = BundleHasServices::with('service')
                ->whereIn('bundle_id', $bundleIds)
                ->get()
                ->groupBy('bundle_id');

            foreach ($data['package_bundles'] as $packageBundle) {
                $bundleId = $packageBundle['bundleId'];
                $serviceData = $bundlesData->get($bundleId);
                
                if (!$serviceData) {
                    continue;
                }

                $discountId = $packageBundle['DiscountId'] ?? null;
                if ($discountId == '0' || $discountId == '') {
                    $discountId = null;
                }
                
                $packageBundleData = [
                    'random_id' => $package->random_id,
                    'is_allocate' => 1,
                    'qty' => 1,
                    'discount_name' => $packageBundle['DiscountName'] ?? null,
                    'discount_type' => $packageBundle['Type'] ?? null,
                    'discount_price' => str_replace(',', '', $packageBundle['DiscountValue'] ?? 0),
                    'service_price' => str_replace(',', '', $packageBundle['RegularPrice']),
                    'net_amount' => str_replace(',', '', $packageBundle['RegularPrice']),
                    'discount_id' => $discountId,
                    'bundle_id' => $bundleId,
                    'package_id' => $package->id,
                    'tax_exclusive_net_amount' => str_replace(',', '', $packageBundle['Amount']),
                    'tax_percentage' => 1,
                    'tax_price' => str_replace(',', '', $packageBundle['Tax']),
                    'tax_including_price' => str_replace(',', '', $packageBundle['Total']),
                    'location_id' => $data['location_id'],
                ];

                $packageBundleRecord = PackageBundles::create($packageBundleData);

                // Get bundle services for this bundle (already loaded)
                $bundleServices = $allBundleServices->get($bundleId, collect());
                
                $calculableServices = [];
                foreach ($bundleServices as $bundleService) {
                    $calculableServices[] = [
                        'service_price' => $bundleService->calculated_price,
                        'calculated_price' => $bundleService->calculated_price,
                        'service_id' => $bundleService->service_id,
                    ];
                }

                $calculatedServicesPrices = Bundles::calculatePrices(
                    $calculableServices,
                    str_replace(',', '', $packageBundle['RegularPrice']),
                    str_replace(',', '', $packageBundle['Total'])
                );

                // Fetch all service IDs to get actual prices and tax treatment types
                $serviceIds = array_column($calculatedServicesPrices, 'service_id');
                $servicesInfo = Services::whereIn('id', $serviceIds)->get()->keyBy('id');

                // Prepare all services for bulk insert
                foreach ($calculatedServicesPrices as $calculatedServicePrice) {
                    $serviceInfo = $servicesInfo->get($calculatedServicePrice['service_id']);
                    
                    $dataService = [
                        'random_id' => $data['random_id'],
                        'package_id' => $package->id,
                        'package_bundle_id' => $packageBundleRecord->id,
                        'service_id' => $calculatedServicePrice['service_id'],
                        'price' => $calculatedServicePrice['calculated_price'],
                        'orignal_price' => $calculatedServicePrice['service_price'],
                        'actual_price' => $serviceInfo ? $serviceInfo->price : null,
                        'created_at' => Filters::getCurrentTimeStamp(),
                        'updated_at' => Filters::getCurrentTimeStamp(),
                        'sold_by' => $packageBundle['sold_by'] ?? null,
                    ];

                    $serviceTaxType = $serviceInfo ? $serviceInfo->tax_treatment_type_id : $serviceData->tax_treatment_type_id;
                    $isExclusive = ($serviceTaxType == Config::get('constants.tax_is_exclusive'));
                    
                    $taxData = $this->calculateServiceTaxForPackage(
                        $serviceTaxType,
                        $calculatedServicePrice['calculated_price'],
                        $locationInfo->tax_percentage,
                        $isExclusive
                    );

                    $allPackageServices[] = array_merge($dataService, $taxData);
                }
            }
        }

        // Bulk insert all package services (instead of individual inserts)
        if (!empty($allPackageServices)) {
            \Log::info('Inserting package services', [
                'count' => count($allPackageServices),
                'package_id' => $package->id,
                'first_service' => $allPackageServices[0] ?? null
            ]);
            
            $inserted = PackageService::insert($allPackageServices);
            
            \Log::info('Package services insertion result', [
                'success' => $inserted,
                'count' => count($allPackageServices)
            ]);
        } else {
            \Log::warning('No package services to insert', [
                'package_id' => $package->id,
                'bundle_count' => count($data['package_bundles'] ?? [])
            ]);
        }
    }

    /**
     * Calculate tax for package service
     */
    protected function calculateServiceTaxForPackage(int $taxTreatmentType, float $price, float $taxPercentage, bool $isExclusive): array
    {
        $taxData = ['tax_percenatage' => $taxPercentage];

        if ($taxTreatmentType == Config::get('constants.tax_both')) {
            if ($isExclusive) {
                $taxData['tax_exclusive_price'] = $price;
                $taxData['tax_price'] = ceil($price * ($taxPercentage / 100));
                $taxData['tax_including_price'] = ceil($price + ($price * $taxPercentage / 100));
                $taxData['is_exclusive'] = 1;
            } else {
                $taxData['tax_including_price'] = $price;
                $taxData['tax_exclusive_price'] = ceil((100 * $price) / ($taxPercentage + 100));
                $taxData['tax_price'] = ceil($price - $taxData['tax_exclusive_price']);
                $taxData['is_exclusive'] = 0;
            }
        } elseif ($taxTreatmentType == Config::get('constants.tax_is_exclusive')) {
            $taxData['tax_exclusive_price'] = $price;
            $taxData['tax_price'] = ceil($price * ($taxPercentage / 100));
            $taxData['tax_including_price'] = ceil($price + ($price * $taxPercentage / 100));
            $taxData['is_exclusive'] = 1;
        } else {
            $taxData['tax_including_price'] = $price;
            $taxData['tax_exclusive_price'] = ceil((100 * $price) / ($taxPercentage + 100));
            $taxData['tax_price'] = ceil($price - $taxData['tax_exclusive_price']);
            $taxData['is_exclusive'] = 0;
        }

        return $taxData;
    }

    /**
     * Generate and update plan_name from first two bundles
     * If one bundle: plan_name = bundle name
     * If two or more bundles: plan_name = first two bundle names comma separated
     * For plan type (not bundle): add '...' if more than 2 services
     */
    protected function updatePlanName(Packages $package): void
    {
        if ($package->plan_type === 'membership') {
            // For membership plans, get name from membership_types table
            $membershipNames = PackageBundles::where('package_bundles.package_id', $package->id)
                ->join('membership_types', 'package_bundles.membership_type_id', '=', 'membership_types.id')
                ->orderBy('package_bundles.id', 'asc')
                ->limit(2)
                ->pluck('membership_types.name')
                ->toArray();

            if (!empty($membershipNames)) {
                $planName = implode(', ', $membershipNames);
                Packages::where('id', $package->id)->update(['plan_name' => $planName]);
                $package->plan_name = $planName;
            }
            return;
        }

        // Get total count of bundles for this package
        $totalBundleCount = PackageBundles::where('package_id', $package->id)->count();
        
        // For plan type 'plan': bundle_id contains service_id, join with services table
        // For plan type 'bundle': bundle_id contains bundle_id, join with bundles table
        if ($package->plan_type === 'plan') {
            $names = PackageBundles::where('package_bundles.package_id', $package->id)
                ->join('services', 'package_bundles.bundle_id', '=', 'services.id')
                ->orderBy('package_bundles.id', 'asc')
                ->limit(2)
                ->pluck('services.name')
                ->toArray();
        } else {
            $names = PackageBundles::where('package_bundles.package_id', $package->id)
                ->join('bundles', 'package_bundles.bundle_id', '=', 'bundles.id')
                ->orderBy('package_bundles.id', 'asc')
                ->limit(2)
                ->pluck('bundles.name')
                ->toArray();
        }

        $planName = !empty($names) ? implode(', ', $names) : '-';
        
        if ($package->plan_type === 'plan' && $totalBundleCount > 2) {
            $planName .= '...';
        }

        Packages::where('id', $package->id)->update(['plan_name' => $planName]);
        $package->plan_name = $planName;
    }

    /**
     * Handle package payment and related activities
     */
    protected function handlePackagePayment(Packages $package, array $data, int $appointmentId): void
    {
        // Create package advance record
        $packageAdvanceData = [
            'cash_flow' => 'in',
            'cash_amount' => $data['cash_amount'],
            'account_id' => Auth::user()->account_id,
            'patient_id' => $data['patient_id'],
            'payment_mode_id' => $data['payment_mode_id'],
            'created_by' => Auth::user()->id,
            'updated_by' => Auth::user()->id,
            'package_id' => $package->id,
            'location_id' => $data['location_id'],
            'created_at' => Filters::getCurrentTimeStamp(),
            'updated_at' => Filters::getCurrentTimeStamp(),
        ];

        $packageAdvance = PackageAdvances::createRecord($packageAdvanceData, $package);

        // For membership plans, create two 'out' entries:
        // 1. Tax exclusive amount (is_setteled=1)
        // 2. Tax amount (is_tax=1)
        // Note: Using PackageAdvances::create() directly since createRecord() hardcodes cash_flow='in'
        if (($data['plan_type'] ?? '') === 'membership') {
            // Calculate tax exclusive and tax amounts from package_bundles
            $packageBundles = PackageBundles::where('package_id', $package->id)->get();
            $taxExclusiveTotal = $packageBundles->sum('tax_exclusive_net_amount');
            $taxTotal = $packageBundles->sum('tax_price');

            // Get 'Settle Amount' payment mode ID from database
            $settlePaymentMode = PaymentModes::where('name', 'Settle Amount')->first();
            $settlePaymentModeId = $settlePaymentMode ? $settlePaymentMode->id : null;

            // First 'out' entry: Tax exclusive amount
            PackageAdvances::create([
                'cash_flow' => 'out',
                'cash_amount' => $taxExclusiveTotal,
                'account_id' => Auth::user()->account_id,
                'patient_id' => $data['patient_id'],
                'payment_mode_id' => $settlePaymentModeId,
                'created_by' => Auth::user()->id,
                'updated_by' => Auth::user()->id,
                'package_id' => $package->id,
                'location_id' => $data['location_id'],
                'is_setteled' => 0,
                'is_tax' => 0,
                'created_at' => Filters::getCurrentTimeStamp(),
                'updated_at' => Filters::getCurrentTimeStamp(),
            ]);

            // Second 'out' entry: Tax amount
            if ($taxTotal > 0) {
                PackageAdvances::create([
                    'cash_flow' => 'out',
                    'cash_amount' => $taxTotal,
                    'account_id' => Auth::user()->account_id,
                    'patient_id' => $data['patient_id'],
                    'payment_mode_id' => $settlePaymentModeId,
                    'created_by' => Auth::user()->id,
                    'updated_by' => Auth::user()->id,
                    'package_id' => $package->id,
                    'location_id' => $data['location_id'],
                    'is_setteled' => 0,
                    'is_tax' => 1,
                    'created_at' => Filters::getCurrentTimeStamp(),
                    'updated_at' => Filters::getCurrentTimeStamp(),
                ]);
            }
        }

        // Create plan invoice
        $invoiceNumber = PlanInvoice::generateInvoiceNumber($data['patient_id'], $package->id);
        $planInvoiceData = [
            'invoice_number' => $invoiceNumber,
            'total_price' => $data['cash_amount'],
            'account_id' => Auth::user()->account_id,
            'patient_id' => $data['patient_id'],
            'created_by' => Auth::user()->id,
            'location_id' => $data['location_id'],
            'payment_mode_id' => $data['payment_mode_id'],
            'active' => 1,
            'package_id' => $package->id,
            'invoice_type' => 'exempt',
        ];
        PlanInvoice::create($planInvoiceData);

        // Log activity (fetch location with city in single query)
        $patient = User::find($data['patient_id']);
        $locationWithCity = Locations::with('city')->find($data['location_id']);
        $locationName = $locationWithCity 
            ? (($locationWithCity->city->name ?? '') . '-' . $locationWithCity->name) 
            : '';
        
        $creatorName = Auth::user()->name ?? 'System';
        $description = '<span class="highlight">' . $creatorName . '</span> received payment Rs. <span class="highlight-green">' . number_format($data['cash_amount']) . '</span> from <span class="highlight-orange">' . $patient->name . '</span> for <span class="highlight-purple">Plan Id: ' . $package->id . '</span> in <span class="highlight">' . $locationName . '</span> ';

        Activity::create([
            'action' => 'received',
            'activity_type' => 'payment_received',
            'description' => $description,
            'patient' => $patient->name,
            'patient_id' => $patient->id,
            'appointment_type' => 'Plan',
            'created_by' => Auth::user()->id,
            'account_id' => Auth::user()->account_id,
            'planId' => $package->id,
            'amount' => $data['cash_amount'],
            'location' => $locationName,
            'centre_id' => $data['location_id'],
            'created_at' => Filters::getCurrentTimeStamp(),
            'updated_at' => Filters::getCurrentTimeStamp(),
        ]);

        // Send SMS
        Invoice_Plan_Refund_Sms_Functions::PlanCashReceived_SMS($package->id, $packageAdvance);

        // Mark appointment as converted
        $this->markAppointmentAsConvertedOptimized($appointmentId, $package->id, $data['cash_amount']);
    }

    /**
     * Handle partial membership payment - only creates 'in' payment entry, no 'out' entries
     * Used when remaining amount > 0 (not fully paid)
     */
    protected function handlePartialMembershipPayment(Packages $package, array $data, int $appointmentId): void
    {
        \Log::info('handlePartialMembershipPayment called', [
            'package_id' => $package->id,
            'cash_amount' => $data['cash_amount'],
            'payment_mode_id' => $data['payment_mode_id'] ?? 'not set'
        ]);
        
        // Create package advance record (only 'in' entry)
        $packageAdvanceData = [
            'cash_flow' => 'in',
            'cash_amount' => $data['cash_amount'],
            'account_id' => Auth::user()->account_id,
            'patient_id' => $data['patient_id'],
            'payment_mode_id' => $data['payment_mode_id'],
            'created_by' => Auth::user()->id,
            'updated_by' => Auth::user()->id,
            'package_id' => $package->id,
            'location_id' => $data['location_id'],
            'created_at' => Filters::getCurrentTimeStamp(),
            'updated_at' => Filters::getCurrentTimeStamp(),
        ];

        $packageAdvance = PackageAdvances::createRecord($packageAdvanceData, $package);
        
        \Log::info('Package advance created', [
            'advance_id' => $packageAdvance->id ?? 'failed'
        ]);

        // No 'out' entries for partial payment - membership is not consumed yet

        // Create plan invoice
        $invoiceNumber = PlanInvoice::generateInvoiceNumber($data['patient_id'], $package->id);
        $planInvoiceData = [
            'invoice_number' => $invoiceNumber,
            'total_price' => $data['cash_amount'],
            'account_id' => Auth::user()->account_id,
            'patient_id' => $data['patient_id'],
            'created_by' => Auth::user()->id,
            'location_id' => $data['location_id'],
            'payment_mode_id' => $data['payment_mode_id'],
            'active' => 1,
            'package_id' => $package->id,
            'invoice_type' => 'exempt',
        ];
        PlanInvoice::create($planInvoiceData);

        // Log activity
        $patient = User::find($data['patient_id']);
        $locationWithCity = Locations::with('city')->find($data['location_id']);
        $locationName = $locationWithCity 
            ? $locationWithCity->name . ($locationWithCity->city ? ' ' . $locationWithCity->city->name : '')
            : '';

        $creatorName = Auth::user()->name ?? 'System';
        $description = '<span class="highlight">' . $creatorName . '</span> received partial payment Rs. <span class="highlight-green">' . number_format($data['cash_amount']) . '</span> from <span class="highlight-orange">' . $patient->name . '</span> for <span class="highlight-purple">Plan Id: ' . $package->id . '</span> in <span class="highlight">' . $locationName . '</span> ';

        Activity::create([
            'action' => 'received',
            'activity_type' => 'payment_received',
            'description' => $description,
            'patient' => $patient->name,
            'patient_id' => $patient->id,
            'appointment_type' => 'Plan',
            'created_by' => Auth::user()->id,
            'account_id' => Auth::user()->account_id,
            'planId' => $package->id,
            'amount' => $data['cash_amount'],
            'location' => $locationName,
            'centre_id' => $data['location_id'],
            'created_at' => Filters::getCurrentTimeStamp(),
            'updated_at' => Filters::getCurrentTimeStamp(),
        ]);

        // Send SMS
        Invoice_Plan_Refund_Sms_Functions::PlanCashReceived_SMS($package->id, $packageAdvance);

        // Mark appointment as converted
        $this->markAppointmentAsConvertedOptimized($appointmentId, $package->id, $data['cash_amount']);
    }

    /**
     * Mark appointment as converted (optimized with reduced queries)
     */
    protected function markAppointmentAsConvertedOptimized(int $appointmentId, int $packageId, float $paymentAmount): void
    {
        try {
            // Fetch appointment and package in single queries
            $appointment = Appointments::find($appointmentId);
            if (!$appointment) {
                return;
            }

            $package = Packages::find($packageId);
            if (!$package) {
                return;
            }

            $accountId = $appointment->account_id;

            // Cache appointment statuses (reduces repeated queries)
            $cacheKey = "appointment_statuses_{$accountId}";
            $statuses = Cache::remember($cacheKey, 3600, function() use ($accountId) {
                return [
                    'arrived' => AppointmentStatuses::where(['account_id' => $accountId, 'is_arrived' => 1])->first(),
                    'converted' => AppointmentStatuses::where(['account_id' => $accountId, 'is_converted' => 1])->first(),
                ];
            });

            if (!$statuses['arrived'] || !$statuses['converted']) {
                return;
            }

            // Find latest arrived consultation
            $latestArrivedConsultation = Appointments::where([
                    'patient_id' => $package->patient_id,
                    'appointment_type_id' => 1,
                    'base_appointment_status_id' => $statuses['arrived']->id
                ])
                ->whereNull('deleted_at')
                ->orderBy('scheduled_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if (!$latestArrivedConsultation) {
                return;
            }

            // Get consultation invoice
            $consultationInvoice = DB::table('invoices')
                ->where('appointment_id', $latestArrivedConsultation->id)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'asc')
                ->first();

            if (!$consultationInvoice) {
                return;
            }

            $invoiceDate = Carbon::parse($consultationInvoice->created_at)->format('Y-m-d');

            // Optimized query: Use JOIN instead of multiple whereIn queries
            $serviceAfterInvoice = DB::table('package_services')
                ->join('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
                ->join('packages', 'package_bundles.package_id', '=', 'packages.id')
                ->where('packages.patient_id', $package->patient_id)
                ->whereNull('packages.deleted_at')
                ->whereDate('package_services.created_at', '>=', $invoiceDate)
                ->exists();

            if (!$serviceAfterInvoice) {
                return;
            }

            // Check if this is the first payment
            $existingPaymentsCount = DB::table('package_advances')
                ->join('packages', 'package_advances.package_id', '=', 'packages.id')
                ->where('packages.patient_id', $package->patient_id)
                ->where('package_advances.cash_flow', 'in')
                ->where('package_advances.cash_amount', '>', 0)
                ->whereNull('package_advances.deleted_at')
                ->whereDate('package_advances.created_at', '>=', $invoiceDate)
                ->count();

            if ($existingPaymentsCount > 1) {
                return;
            }

            // Mark as converted
            $latestArrivedConsultation->update([
                'base_appointment_status_id' => $statuses['converted']->id,
                'appointment_status_id' => $statuses['converted']->id,
                'converted_at' => now()
            ]);

            // Fetch related data for logging (with eager loading)
            $patient = Patients::find($package->patient_id);
            $location = Locations::with('city')->find($latestArrivedConsultation->location_id);
            $service = Services::find($latestArrivedConsultation->service_id);

            // Log activities
            ActivityLogger::logAppointmentConverted($latestArrivedConsultation, $patient, $location, $service, $paymentAmount, $packageId);

            // Update lead status if exists
            if ($latestArrivedConsultation->lead_id) {
                $this->updateLeadStatusToConverted($latestArrivedConsultation, $accountId, $location, $service, $paymentAmount);
            }

            // Send Meta event
            $this->sendMetaConvertedEventOptimized($latestArrivedConsultation, $packageId, $paymentAmount);

        } catch (\Exception $e) {
            \Log::error('Mark Appointment As Converted Error: ' . $e->getMessage());
        }
    }

    /**
     * Update lead status to converted
     */
    protected function updateLeadStatusToConverted($appointment, int $accountId, $location, $service, float $paymentAmount): void
    {
        $lead = Leads::find($appointment->lead_id);
        if (!$lead) {
            return;
        }

        $convertedLeadStatus = Cache::remember("converted_lead_status_{$accountId}", 3600, function() use ($accountId) {
            return DB::table('lead_statuses')
                ->where(['account_id' => $accountId, 'is_converted' => 1])
                ->first();
        });

        if ($convertedLeadStatus) {
            $lead->update(['lead_status_id' => $convertedLeadStatus->id]);
            ActivityLogger::logLeadConverted($lead, $appointment, $location, $service, $paymentAmount);
        }
    }

    /**
     * Send Meta converted event (optimized)
     */
    protected function sendMetaConvertedEventOptimized($appointment, int $packageId, float $paymentAmount): void
    {
        if (!$appointment || !$appointment->lead_id) {
            return;
        }

        $lead = Leads::find($appointment->lead_id);
        if (!$lead) {
            return;
        }

        // Check if already sent
        $alreadySent = DB::table('appointments')
            ->where('lead_id', $lead->id)
            ->where('meta_purchase_sent', 1)
            ->exists();

        if ($alreadySent) {
            return;
        }

        try {
            $metaService = new \App\Services\MetaConversionApiService();
            $eventLeadId = $lead->meta_lead_id ?? 'apt_' . $appointment->id;
            
            $metaService->sendLeadStatus(
                $lead->phone,
                'converted',
                $eventLeadId,
                $lead->email,
                'PKR',
                $paymentAmount ?? 0
            );

            $appointment->update(['meta_purchase_sent' => 1]);

        } catch (\Exception $e) {
            \Log::error('Meta CAPI converted event failed: ' . $e->getMessage());
        }
    }

    /**
     * Get edit form data for package (optimized)
     * 
     * @param int $packageId
     * @return array
     * @throws PlanException
     */
    public function getEditFormData(int $packageId): array
    {
        try {
            // Fetch package with relationships
            $package = Packages::with('user', 'location')->find($packageId);
            
            if (!$package) {
                throw new PlanException('Package not found', 404);
            }

            // Calculate total price from bundles
            $totalPrice = PackageBundles::where('package_id', $packageId)->sum('tax_including_price');

            // Fetch package bundles with relationships (eager loading)
            // Include membershipType for membership plans
            // Include service and discount for configurable discounts (where bundle_id contains service_id)
            $packageBundles = PackageBundles::with(['bundle', 'service', 'discount', 'membershipType', 'packageservice.soldBy'])
                ->where('package_id', $packageId)
                ->get();

            // Fetch package services with relationships
            $packageServices = PackageService::with('service', 'soldBy')
                ->where('package_id', $packageId)
                ->get();

            // Fetch package advances with payment mode
            $packageAdvances = PackageAdvances::with('paymentmode')
                ->where([
                    ['package_id', '=', $packageId],
                    ['is_cancel', '=', '0'],
                    ['is_adjustment', '=', '0'],
                ])
                ->get();

            // Consolidated package advances summary (1 query instead of 4)
            $advancesSummary = DB::table('package_advances')
                ->where('package_id', $packageId)
                ->selectRaw("
                    SUM(CASE WHEN cash_flow = 'in' AND is_cancel = 0 AND is_setteled = 0 THEN cash_amount ELSE 0 END) as cash_in,
                    SUM(CASE WHEN cash_flow = 'out' THEN cash_amount ELSE 0 END) as cash_out,
                    SUM(CASE WHEN cash_flow = 'out' AND is_refund = 1 THEN cash_amount ELSE 0 END) as refunded,
                    SUM(CASE WHEN cash_flow = 'out' AND is_setteled = 1 THEN cash_amount ELSE 0 END) as setteled
                ")
                ->first();

            // Calculate grand total
            $grandTotal = $totalPrice - $advancesSummary->cash_in;
            $remainingAmount = number_format($grandTotal + $advancesSummary->refunded + $advancesSummary->setteled);

            // Get user locations (optimized - single query with join)
            $userLocations = Locations::whereIn('id', function($query) {
                    $query->select('location_id')
                          ->from('user_has_locations')
                          ->where('user_id', Auth::user()->id);
                })
                ->where('account_id', Auth::user()->account_id)
                ->where('slug', 'custom')
                ->get();

            // Get payment modes
            $paymentModes = PaymentModes::where('type', 'application')->pluck('name', 'id');

            // Get custom discount range (cached)
            $customDiscountRange = Cache::remember('sys_discounts', 3600, function() {
                return Settings::where('slug', 'sys-discounts')->first();
            });
            $range = $customDiscountRange ? explode(':', $customDiscountRange->data) : [];

            // Get services for location (reuse optimized method)
            $locationHasService = $this->getServicesByLocation($package->location_id, Auth::user()->account_id);

            // Get finance editing days (cached)
            $financeEditingDays = Cache::remember('sys_financeediting', 3600, function() {
                return Settings::where('slug', 'sys-financeediting')->first();
            });
            $endPreviousDate = Carbon::now()->subDays($financeEditingDays->data ?? 0)->toDateString();

            // Get appointment info (reuse optimized method)
            $appointmentInfo = $this->getAppointmentInfo($package->patient_id, $package->location_id);

            // Get membership info
            $membershipDisplay = $this->getMembershipDisplay($package->patient_id);

            // Get active discounts
            $discounts = Discounts::where('active', 1)->get(['id', 'name']);

            // Determine selected appointment ID for pre-selection
            $selectedAppointmentId = null;
            if ($package->appointment_id) {
                $selectedAppointmentId = $package->appointment_id . '.A';
            }

            // Get student verification documents if exists
            $studentDocuments = [];
            $studentVerification = \App\Models\StudentVerification::where('package_id', $packageId)->first();
            if ($studentVerification && !empty($studentVerification->document_paths)) {
                $studentDocuments = $studentVerification->document_paths;
            }
            
            // Check if membership is consumed/activated
            $isMembershipConsumed = PackageService::where('package_id', $packageId)
                ->where('is_consumed', 1)
                ->exists();

            return [
                'package' => $package,
                'locations' => $userLocations,
                'packagebundles' => $packageBundles,
                'packageservices' => $packageServices,
                'users' => $appointmentInfo['users'],
                'selectedUserId' => $appointmentInfo['selected_doctor_id'],
                'selectedAppointmentId' => $selectedAppointmentId,
                'packageadvances' => $packageAdvances,
                'paymentmodes' => $paymentModes,
                'grand_total' => $remainingAmount,
                'range' => $range,
                'locationhasservice' => $locationHasService,
                'total_price' => $totalPrice,
                'end_previous_date' => $endPreviousDate,
                'appointmentArray' => $appointmentInfo['appointments'],
                'discount_type' => config('constants.amount_types'),
                'discounts' => $discounts,
                'membership' => $membershipDisplay,
                'student_documents' => $studentDocuments,
                'is_membership_consumed' => $isMembershipConsumed,
            ];

        } catch (PlanException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Get Edit Form Data Error: ' . $e->getMessage());
            throw new PlanException('Failed to load edit form data: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get membership display string
     * Returns the latest active membership, or latest membership if no active one exists
     */
    protected function getMembershipDisplay(int $patientId): string
    {
        // First try to get the latest active membership
        $membership = Membership::with('membershiptype')
            ->where('patient_id', $patientId)
            ->where('active', 1)
            ->where('end_date', '>=', now()->format('Y-m-d'))
            ->orderBy('assigned_at', 'desc')
            ->first();
        
        // If no active membership, get the latest membership regardless of status
        if (!$membership) {
            $membership = Membership::with('membershiptype')
                ->where('patient_id', $patientId)
                ->orderBy('assigned_at', 'desc')
                ->first();
        }
        
        if (!$membership) {
            return 'No Membership';
        }

        // Determine status:
        // - If no start_date or end_date set, it's reserved/inactive (not yet consumed)
        // - If end_date is in the past, it's expired
        // - If active = 1 and end_date is in the future, it's active
        // - Otherwise, it's inactive
        $status = 'Inactive';
        if (empty($membership->start_date) || empty($membership->end_date)) {
            $status = 'Inactive';
        } elseif ($membership->end_date < now()->format('Y-m-d')) {
            $status = 'Expired';
        } elseif ($membership->active == 1) {
            $status = 'Active';
        }

        $expiryDateFormatted = $membership->end_date ? date('M d, Y', strtotime($membership->end_date)) : '';

        if ($membership->is_referral == 1) {
            return "Ref: ({$membership->code})-{$status}" . ($expiryDateFormatted ? " (Exp: {$expiryDateFormatted})" : "");
        }

        $membershipTypeName = $membership->membershipType 
            ? str_replace(' Membership', '', $membership->membershipType->name) 
            : 'Gold';
        
        return "{$membershipTypeName} - {$membership->code} - {$status}" . ($expiryDateFormatted ? " (Exp: {$expiryDateFormatted})" : "");
    }

    /**
     * Update existing plan package (optimized)
     * 
     * @param array $data
     * @return array
     * @throws PlanException
     */
    public function updatePlanPackage(array $data): array
    {
        DB::beginTransaction();
        
        try {
            // Fetch package once and reuse (instead of 3 separate queries)
            $package = Packages::where('random_id', $data['random_id'])->first();
            
            if (!$package) {
                throw new PlanException('Package not found', 404);
            }

            // Check if package is settled
            $isSettled = PackageAdvances::where([
                ['cash_flow', '=', 'out'],
                ['cash_amount', '>', 0],
                ['is_setteled', '=', '1'],
                ['package_id', '=', $package->id],
            ])->exists();

            if ($isSettled) {
                throw new PlanException('Plan is already settled. You cannot add further treatment in this plan.', 400);
            }

            // Handle appointment creation/update
            $appointmentId = $this->handleAppointmentForUpdate($data, $package);
            
            if (!$appointmentId) {
                throw new PlanException('Appointment ID is required', 400);
            }

            // Check if new services or payment
            $hasNewServices = isset($data['package_bundles']) && !empty($data['package_bundles']);
            $hasPayment = !empty($data['cash_amount']) && $data['cash_amount'] != '0';

            // Update package if needed
            if ($hasNewServices || $hasPayment) {
                $totalPrice = str_replace(',', '', $data['total']);
                
                $package->update([
                    'total_price' => $totalPrice,
                    'sessioncount' => '1',
                    'account_id' => Auth::user()->account_id,
                    'appointment_id' => $appointmentId,
                    'updated_at' => Filters::getCurrentTimeStamp(),
                ]);
            }

            // Additive insert: only add NEW services — never delete existing ones.
            if ($hasNewServices) {
                // Block adding if any config group has out-of-order consumption
                // (a higher consumption_order service is consumed while a lower one is not)
                $hasOutOfOrderConsumption = PackageService::where('package_services.package_id', $package->id)
                    ->join('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
                    ->whereNotNull('package_bundles.config_group_id')
                    ->where('package_services.is_consumed', '1')
                    ->whereExists(function ($query) use ($package) {
                        $query->select(\DB::raw(1))
                            ->from('package_services as ps2')
                            ->join('package_bundles as pb2', 'ps2.package_bundle_id', '=', 'pb2.id')
                            ->whereColumn('pb2.config_group_id', 'package_bundles.config_group_id')
                            ->where('ps2.package_id', $package->id)
                            ->where('ps2.is_consumed', '0')
                            ->whereColumn('ps2.consumption_order', '<', 'package_services.consumption_order');
                    })
                    ->exists();

                if ($hasOutOfOrderConsumption) {
                    throw new PlanException('Cannot add new services. A configurable discount group has out-of-order consumption. Please consume the BUY services first or create a new plan.', 400);
                }

                $this->storePackageBundlesOptimized($package, $data);
            }

            // Handle payment if provided
            if ($hasPayment) {
                $this->handlePackagePayment($package, $data, $appointmentId);
            }

            // Always update plan_name on every update
            $this->updatePlanName($package);

            DB::commit();

            return [
                'status' => true,
                'message' => 'updated successfully',
                'package_id' => $package->id
            ];

        } catch (PlanException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Update Plan Package Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw new PlanException('Failed to update package: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle appointment for update (create or retrieve)
     */
    protected function handleAppointmentForUpdate(array $data, Packages $package): ?int
    {
        if (!isset($data['appointment_id'])) {
            return null;
        }

        $tagAppoint = explode('.', $data['appointment_id']);
        
        if ($tagAppoint[1] == 'A') {
            return (int) $tagAppoint[0];
        } else {
            $planAppointmentCalculation = new PlanAppointmentCalculation();
            
            // Check if appointment exists
            $appointmentDecision = Appointments::find($package->appointment_id);
            
            if ($appointmentDecision) {
                $appointmentId = $planAppointmentCalculation->updateAppointment(
                    $data['patient_id'],
                    $data['location_id'],
                    (object) $data,
                    $tagAppoint[0],
                    $package
                );
            } else {
                $appointmentId = $planAppointmentCalculation->storeAppointment(
                    $data['patient_id'],
                    $data['location_id'],
                    (object) $data,
                    $tagAppoint[0],
                    false
                );
                $planAppointmentCalculation->saveinvoice($appointmentId);
            }
            
            return $appointmentId;
        }
    }

    /**
     * Get display data for package (optimized)
     * 
     * @param int $packageId
     * @return array
     * @throws PlanException
     */
    public function getDisplayData(int $packageId): array
    {
        try {
            // Fetch package with relationships
            $package = Packages::with('user', 'location')->find($packageId);
            
            if (!$package) {
                throw new PlanException('Package not found', 404);
            }

            // Fetch package bundles with relationships (eager loading)
            // Include membershipType for membership plans
            // Include service and discount for configurable discounts (where bundle_id contains service_id)
            $packageBundles = PackageBundles::with(['bundle', 'service', 'discount', 'membershipType', 'packageservice.soldBy'])
                ->where('package_id', $packageId)
                ->get();

            // Normalize bundle relationship based on source_type so frontend
            // can always use packagebundle.bundle.name regardless of source_type
            $packageBundles->each(function ($pb) {
                if ($pb->source_type === 'service' && $pb->service) {
                    $pb->setRelation('bundle', $pb->service);
                } elseif (!$pb->source_type && $pb->service && !$pb->membership_type_id) {
                    // Fallback for rows where source_type is NULL:
                    // Check if child package_services has exactly 1 row with service_id == bundle_id
                    // If so, bundle_id actually holds a service_id
                    $children = $pb->packageservice;
                    if ($children && $children->count() === 1 && $children->first()->service_id == $pb->bundle_id) {
                        $pb->setRelation('bundle', $pb->service);
                    }
                }
            });

            // Fetch package services with relationships
            $packageServices = PackageService::with('service', 'soldBy')
                ->where('package_id', $packageId)
                ->get();

            // Calculate services price
            // For membership plans, use PackageBundles sum (no child services)
            // For other plans, use PackageService sum
            if ($package->plan_type === 'membership') {
                $packageServicesPrice = PackageBundles::where('package_id', $packageId)
                    ->sum('tax_including_price');
            } else {
                $packageServicesPrice = PackageService::where('package_id', $packageId)
                    ->sum('price');
            }

            // Fetch package advances with payment mode
            $packageAdvances = PackageAdvances::with('paymentmode')
                ->where([
                    ['package_id', '=', $packageId],
                    ['is_cancel', '=', '0'],
                    ['is_adjustment', '=', '0'],
                ])
                ->get();

            // Process package advances
            $packageAdvances = $this->processPackageAdvances($packageAdvances);

            // Calculate cash amounts (consolidated query)
            $cashSummary = PackageAdvances::where('package_id', $packageId)
                ->selectRaw("
                    SUM(CASE WHEN cash_flow = 'in' THEN cash_amount ELSE 0 END) as cash_in,
                    SUM(CASE WHEN cash_flow = 'out' THEN cash_amount ELSE 0 END) as cash_out
                ")
                ->first();

            $cashAmount = $cashSummary->cash_in - $cashSummary->cash_out;
            $grandTotal = round($packageServicesPrice, 2);

            // Get services and discounts (cached)
            $services = Cache::remember('all_services', 3600, function() {
                return Services::getServices();
            });

            $discounts = Cache::remember('discounts_' . Auth::user()->account_id, 3600, function() {
                return Discounts::getDiscount(Auth::user()->account_id);
            });

            // Get payment modes
            $paymentModes = PaymentModes::pluck('name', 'id');

            // Get membership info
            $membershipDisplay = $this->getMembershipDisplayForPackage($package->patient_id);

            // Get student verification documents if exists
            $studentDocuments = [];
            $studentVerification = \App\Models\StudentVerification::where('package_id', $packageId)->first();
            
            \Log::info('Fetching student documents for display', [
                'package_id' => $packageId,
                'verification_found' => $studentVerification !== null,
                'document_paths' => $studentVerification ? $studentVerification->document_paths : null
            ]);
            
            if ($studentVerification && !empty($studentVerification->document_paths)) {
                $studentDocuments = $studentVerification->document_paths;
            }

            return [
                'package' => $package,
                'packagebundles' => $packageBundles,
                'packageservices' => $packageServices,
                'packageadvances' => $packageAdvances,
                'services' => $services,
                'discount' => $discounts,
                'paymentmodes' => $paymentModes,
                'grand_total' => $grandTotal,
                'membership' => $membershipDisplay,
                'student_documents' => $studentDocuments,
            ];

        } catch (PlanException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Get Display Data Error: ' . $e->getMessage());
            throw new PlanException('Failed to load display data: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Process package advances with appointment package calculations
     */
    protected function processPackageAdvances($packageAdvances): array
    {
        if ($packageAdvances->count() === 0) {
            return [];
        }

        $processedAdvances = [];
        
        foreach ($packageAdvances as $packageAdvance) {
            if ($packageAdvance->cash_flow == 'out' && $packageAdvance->is_tax == 0) {
                if (!is_null($packageAdvance->refund_note)) {
                    $packageAdvance->package_refund_price = number_format(
                        PackageAdvances::getAppointmentPackage(
                            $packageAdvance->appointment_id, 
                            $packageAdvance->patient_id, 
                            $packageAdvance->id
                        )
                    );
                } else {
                    $packageAdvance->package_refund_price = number_format(
                        PackageAdvances::getAppointmentPackage(
                            $packageAdvance->appointment_id, 
                            $packageAdvance->patient_id
                        )
                    );
                }
            } elseif ($packageAdvance->is_tax == 0) {
                $packageAdvance->package_refund_price = number_format($packageAdvance->cash_amount);
            } else {
                $packageAdvance->package_refund_price = '00.00';
            }
            
            $packageAdvance->created_at_formated = Carbon::parse($packageAdvance->created_at)
                ->format('F j,Y H:i A');

            $processedAdvances[] = $packageAdvance;
        }

        return $processedAdvances;
    }

    /**
     * Get membership display for package
     */
    protected function getMembershipDisplayForPackage(int $patientId): string
    {
        // Fetch the latest assigned membership (by created_at DESC)
        $checkMembership = Membership::with('membershiptype')
            ->where('patient_id', $patientId)
            ->orderBy('assigned_at', 'desc')
            ->first();

        if (!$checkMembership) {
            return 'No membership';
        }

        // Determine status:
        // - If no start_date or end_date set, it's reserved/inactive (not yet consumed)
        // - If end_date is in the past, it's expired
        // - If active = 1 and end_date is in the future, it's active
        // - Otherwise, it's inactive
        $status = ' - Inactive';
        if (empty($checkMembership->start_date) || empty($checkMembership->end_date)) {
            $status = ' - Inactive';
        } elseif ($checkMembership->end_date < now()->format('Y-m-d')) {
            $status = ' - Expired';
        } elseif ($checkMembership->active == 1) {
            $status = ' - Active';
        }

        return "{$checkMembership->membershipType->name}{$status}";
    }

    /**
     * Delete plan package (optimized)
     * 
     * @param int $packageId
     * @return array
     * @throws PlanException
     */
    public function deletePlan(int $packageId): array
    {
        try {
            // Fetch package
            $package = Packages::find($packageId);

            if (!$package) {
                throw new PlanException('Package not found', 404);
            }

            // Check if child records exist (optimized with exists() instead of count())
            $hasInvoiceDetails = DB::table('invoice_details')
                ->where('package_id', $packageId)
                ->where('deleted_at',null)
                ->exists();

            $hasPackageAdvances = DB::table('package_advances')
                ->where('package_id', $packageId)
                ->where('deleted_at',null)
                ->exists();

            if ($hasInvoiceDetails || $hasPackageAdvances) {
                // Build detailed error message
                $childRecords = [];
                if ($hasInvoiceDetails) {
                    $childRecords[] = 'Invoice Details';
                }
                if ($hasPackageAdvances) {
                    $childRecords[] = 'Package Advances (Payments)';
                }
                
                $message = 'Unable to delete package. Child records exist in: ' . implode(', ', $childRecords);
                
                throw new PlanException($message, 409);
            }

            // Delete the package (no child records)
            $package->delete();

            // Log audit trail
            \App\Models\AuditTrails::deleteEventLogger(
                'packages',
                'delete',
                ['name', 'sessioncount', 'total_price', 'is_exclusive', 'patient_id', 'active', 'location_id', 'appointment_id', 'is_refund', 'created_at', 'updated_at', 'deleted_at'],
                $packageId
            );

            return [
                'status' => true,
                'message' => 'Record has been deleted successfully.',
            ];

        } catch (PlanException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Delete Plan Error: ' . $e->getMessage());
            throw new PlanException('Failed to delete package: ' . $e->getMessage(), 500);
        }
    }
}
