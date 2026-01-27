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
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Exceptions\PlanException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
     * Build optimized result query with eager loading and aggregations
     */
    protected function buildOptimizedResultQuery(array $where, int $accountId): \Illuminate\Database\Eloquent\Builder
    {
        $query = Packages::query()
            ->select([
                'packages.*',
                // Aggregate cash_receive in single query
                DB::raw('(SELECT COALESCE(SUM(cash_amount), 0) 
                         FROM package_advances 
                         WHERE package_advances.package_id = packages.id 
                         AND package_advances.cash_flow = "in" 
                         AND package_advances.is_cancel = 0
                         AND package_advances.deleted_at IS NULL) as cash_receive'),
                // Aggregate settled amount
                DB::raw('(SELECT COALESCE(SUM(cash_amount), 0) 
                         FROM package_advances 
                         WHERE package_advances.package_id = packages.id 
                         AND package_advances.cash_flow = "out"
                         AND package_advances.is_refund = 0
                         AND package_advances.deleted_at IS NULL) as settle_amount'),
                // Aggregate refund amount
                DB::raw('(SELECT COALESCE(SUM(cash_amount), 0) 
                         FROM package_advances 
                         WHERE package_advances.package_id = packages.id 
                         AND package_advances.is_refund = 1
                         AND package_advances.deleted_at IS NULL) as refund_amount_calculated'),
                // Count package services
                DB::raw('(SELECT COUNT(*) 
                         FROM package_services 
                         WHERE package_services.package_id = packages.id) as session_count')
            ])
            ->with([
                'user:id,name,account_id',
                'user.membership:id,patient_id,code,active,end_date,is_referral',
                'location:id,name,city_id',
                'location.city:id,name'
            ]);

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
     * Build where conditions from filters
     */
    protected function buildWhereConditions(array $filters, string $filename, int $userId, int $accountId, int $patientId): array
    {
        $where = [];
        $applyFilter = $this->shouldApplyFilter($filters);

        // Patient ID filter
        $where[] = ['patient_id', '=', $patientId];
        Filters::put($userId, $filename, 'patient_id', $patientId);

        // Account ID filter
        $where[] = ['account_id', '=', $accountId];
        Filters::put($userId, $filename, 'account_id', $accountId);

        // Package ID filter
        if ($this->hasFilter($filters, 'package_id')) {
            $where[] = ['id', '=', $filters['package_id']];
            Filters::put($userId, $filename, 'package_id', $filters['package_id']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, $filename, 'package_id');
            } else {
                if ($packageId = Filters::get($userId, $filename, 'package_id')) {
                    $where[] = ['id', '=', $packageId];
                }
            }
        }

        // Location filter
        if ($this->hasFilter($filters, 'location_id')) {
            $where[] = ['location_id', '=', $filters['location_id']];
            Filters::put($userId, $filename, 'location_id', $filters['location_id']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, $filename, 'location_id');
            } else {
                if ($locationId = Filters::get($userId, $filename, 'location_id')) {
                    $where[] = ['location_id', '=', $locationId];
                }
            }
        }

        // Status filter
        if ($this->hasFilter($filters, 'status')) {
            $where[] = ['active', '=', $filters['status']];
            Filters::put($userId, $filename, 'status', $filters['status']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, $filename, 'status');
            } else {
                $status = Filters::get($userId, $filename, 'status');
                if ($status === 0 || $status === 1 || $status === '0' || $status === '1') {
                    $where[] = ['active', '=', $status];
                }
            }
        }

        // Date range filter
        if ($this->hasFilter($filters, 'created_at')) {
            $dateRange = explode(' - ', $filters['created_at']);
            if (count($dateRange) === 2) {
                $startDateTime = Carbon::parse($dateRange[0])->startOfDay();
                $endDateTime = Carbon::parse($dateRange[1])->endOfDay();
                
                $where[] = ['created_at', '>=', $startDateTime];
                $where[] = ['created_at', '<=', $endDateTime];
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
        $where[] = ['account_id', '=', $accountId];
        Filters::put($userId, $filename, 'account_id', $accountId);

        // Patient ID/Search filter
        if ($this->hasFilter($filters, 'patient_id')) {
            $patientId = $filters['patient_id'];
            // Check if it's a search string (e.g., "P-123")
            if (is_string($patientId) && strpos($patientId, 'P-') === 0) {
                $patientId = \App\Helpers\GeneralFunctions::patientSearch($patientId);
            }
            $where[] = ['patient_id', '=', $patientId];
            Filters::put($userId, $filename, 'patient_id', $patientId);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, $filename, 'patient_id');
            } else {
                if ($patientId = Filters::get($userId, $filename, 'patient_id')) {
                    $where[] = ['patient_id', '=', $patientId];
                }
            }
        }

        // Package ID filter
        if ($this->hasFilter($filters, 'package_id')) {
            $where[] = ['id', '=', $filters['package_id']];
            Filters::put($userId, $filename, 'package_id', $filters['package_id']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, $filename, 'package_id');
            } else {
                if ($packageId = Filters::get($userId, $filename, 'package_id')) {
                    $where[] = ['id', '=', $packageId];
                }
            }
        }

        // Location filter
        if ($this->hasFilter($filters, 'location_id')) {
            $where[] = ['location_id', '=', $filters['location_id']];
            Filters::put($userId, $filename, 'location_id', $filters['location_id']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, $filename, 'location_id');
            } else {
                if ($locationId = Filters::get($userId, $filename, 'location_id')) {
                    $where[] = ['location_id', '=', $locationId];
                }
            }
        }

        // Status filter
        if ($this->hasFilter($filters, 'status')) {
            $where[] = ['active', '=', $filters['status']];
            Filters::put($userId, $filename, 'status', $filters['status']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, $filename, 'status');
            } else {
                $status = Filters::get($userId, $filename, 'status');
                if ($status === 0 || $status === 1 || $status === '0' || $status === '1') {
                    $where[] = ['active', '=', $status];
                }
            }
        }

        // Date range filter
        if ($this->hasFilter($filters, 'created_at')) {
            $dateRange = explode(' - ', $filters['created_at']);
            if (count($dateRange) === 2) {
                $startDateTime = Carbon::parse($dateRange[0])->startOfDay();
                $endDateTime = Carbon::parse($dateRange[1])->endOfDay();
                
                $where[] = ['created_at', '>=', $startDateTime];
                $where[] = ['created_at', '<=', $endDateTime];
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
        $orderBy = 'updated_at';
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
        $allowedFields = ['id', 'package_id', 'created_at', 'updated_at'];
        if (!in_array($orderBy, $allowedFields)) {
            $orderBy = 'updated_at';
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
                'refund' => $package->is_refund == '1' ? 'Yes' : 'No',
                'refunded' => number_format($package->refund_amount_calculated ?? 0, 0),
                'active' => $package->active,
                'status' => $package->active == 1 ? 'Active' : 'Inactive',
                'date' => $package->created_at->format('Y-m-d'),
                'created_at' => $package->created_at->format('F j, Y h:i A'),
                'patient_name' => $package->user->name ?? 'N/A',
                'membership_info' => $this->formatMembershipInfo($package->user),
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
                
                // Return all active services where parent_id is not null (all child services)
                $services = DB::table('services')
                    ->whereNotNull('parent_id')
                    ->where('active', 1)
                    ->where('account_id', $accountId)
                    ->select('id', 'name', 'parent_id', 'active')
                    ->get();
                
                \Log::info("All child services found: " . $services->count());
                
                return $services->toArray();
            }

            // Get details of assigned services to determine if they are parent or child
            $assignedServices = DB::table('services')
                ->whereIn('id', $serviceHasLocations)
                ->select('id', 'name', 'parent_id', 'active')
                ->get();

            $resultServices = collect();

            foreach ($assignedServices as $service) {
                // Scenario 2: If service is a parent (parent_id is null), get all its children
                if ($service->parent_id === null) {
                    \Log::info("Service {$service->id} is a parent, fetching children");
                    
                    $children = DB::table('services')
                        ->where('parent_id', $service->id)
                        ->where('active', 1)
                        ->where('account_id', $accountId)
                        ->select('id', 'name', 'parent_id', 'active')
                        ->get();
                    
                    $resultServices = $resultServices->merge($children);
                } else {
                    // Scenario 3: If service is a child (has parent_id), return only this child
                    \Log::info("Service {$service->id} is a child, adding to results");
                    $resultServices->push($service);
                }
            }

            // Remove duplicates by id
            $resultServices = $resultServices->unique('id')->values();
            
            \Log::info("Total services found for location {$locationId}: " . $resultServices->count());
            
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
}
