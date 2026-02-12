<?php

namespace App\Services\Schedule;

use App\Models\BusinessClosure;
use App\Models\Locations;
use App\Models\AuditTrails;
use App\Helpers\ACL;
use App\Helpers\Filters;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BusinessClosureService
{
    protected static string $filterName = 'business_closures';
    protected static array $fillable = ['title', 'start_date', 'end_date'];

    /**
     * Get paginated business closures for datatable
     */
    public function getDatatableData(array $filters): array
    {
        $userId = Auth::id();
        $accountId = Auth::user()->account_id;
        $applyFilter = checkFilters($filters, self::$filterName);

        $whereConditions = $this->buildWhereConditions($filters, $applyFilter, $userId);
        
        // Handle sorting from filters array
        $orderBy = 'created_at';
        $order = 'DESC';
        if (isset($filters['sort']) && isset($filters['sort']['field'])) {
            $orderBy = $filters['sort']['field'];
            $order = $filters['sort']['sort'] ?? 'DESC';
        }

        $query = BusinessClosure::with(['locations', 'creator'])
            ->where('account_id', $accountId)
            ->when(!empty($whereConditions['where']), fn($q) => $q->where($whereConditions['where']));

        // Apply location filter through relationship
        if (!empty($whereConditions['location_id'])) {
            $locationId = $whereConditions['location_id'];
            // ID 30 is "All Centres" - a special location that means all locations
            $allCentresId = 30;
            $query->where(function ($q) use ($locationId, $allCentresId) {
                // Match closures that have this specific location
                $q->whereHas('locations', function ($subQ) use ($locationId) {
                    $subQ->where('locations.id', $locationId);
                })
                // OR closures that have "All Centres" (location_id 30) assigned
                ->orWhereHas('locations', function ($subQ) use ($allCentresId) {
                    $subQ->where('locations.id', $allCentresId);
                })
                // OR closures that have no locations assigned
                ->orWhereDoesntHave('locations');
            });
        }

        $totalRecords = $query->count();

        return [
            'total' => $totalRecords,
            'query' => $query,
            'orderBy' => $orderBy,
            'order' => $order,
        ];
    }

    /**
     * Build where conditions from filters
     */
    protected function buildWhereConditions(array $filters, bool $applyFilter, int $userId): array
    {
        $where = [];
        $locationId = null;

        if (hasFilter($filters, 'location_id')) {
            $locationId = $filters['location_id'];
            Filters::put($userId, self::$filterName, 'location_id', $filters['location_id']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, self::$filterName, 'location_id');
            }
        }

        if (hasFilter($filters, 'start_date')) {
            $where[] = ['start_date', '>=', $filters['start_date']];
            Filters::put($userId, self::$filterName, 'start_date', $filters['start_date']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, self::$filterName, 'start_date');
            }
        }

        if (hasFilter($filters, 'end_date')) {
            $where[] = ['end_date', '<=', $filters['end_date']];
            Filters::put($userId, self::$filterName, 'end_date', $filters['end_date']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, self::$filterName, 'end_date');
            }
        }

        return [
            'where' => $where,
            'location_id' => $locationId,
        ];
    }

    /**
     * Get filter values for datatable
     */
    public function getFilterValues(): array
    {
        $userCentres = ACL::getUserCentres();
        $locationsQuery = Locations::where([
            ['account_id', '=', Auth::user()->account_id],
            ['active', '=', '1'],
        ]);
        
        if ($userCentres && is_array($userCentres) && count($userCentres) > 0) {
            $locationsQuery->whereIn('id', $userCentres);
        }
        
        $locations = $locationsQuery->orderBy('name', 'asc')->get(['id', 'name']);
        $activeFilters = Filters::all(Auth::id(), self::$filterName);

        return [
            'filter_values' => [
                'locations' => $locations,
            ],
            'active_filters' => $activeFilters,
        ];
    }

    /**
     * Create a new business closure
     */
    public function create(array $data): BusinessClosure
    {
        $accountId = Auth::user()->account_id;
        $userId = Auth::id();

        DB::beginTransaction();
        try {
            $closure = BusinessClosure::create([
                'account_id' => $accountId,
                'title' => $data['title'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'created_by' => $userId,
            ]);

            if (!empty($data['location_ids']) && !in_array('all', $data['location_ids'])) {
                $closure->locations()->sync($data['location_ids']);
            }

            AuditTrails::addEventLogger('business_closures', 'create', $data, self::$fillable, $closure);

            DB::commit();
            return $closure;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing business closure
     */
    public function update(int $id, array $data): BusinessClosure
    {
        $accountId = Auth::user()->account_id;

        DB::beginTransaction();
        try {
            $closure = BusinessClosure::where('id', $id)
                ->where('account_id', $accountId)
                ->firstOrFail();

            $oldData = $closure->toArray();

            $closure->update([
                'title' => $data['title'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
            ]);

            if (!empty($data['location_ids']) && !in_array('all', $data['location_ids'])) {
                $closure->locations()->sync($data['location_ids']);
            } else {
                $closure->locations()->detach();
            }

            AuditTrails::editEventLogger('business_closures', 'edit', $data, self::$fillable, $oldData, $id);

            DB::commit();
            return $closure->fresh(['locations']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a business closure
     */
    public function delete(int $id): bool
    {
        $accountId = Auth::user()->account_id;

        $closure = BusinessClosure::where('id', $id)
            ->where('account_id', $accountId)
            ->firstOrFail();

        AuditTrails::deleteEventLogger('business_closures', 'delete', self::$fillable, $id);

        return $closure->delete();
    }

    /**
     * Get a single business closure by ID
     */
    public function getById(int $id): ?BusinessClosure
    {
        $accountId = Auth::user()->account_id;

        return BusinessClosure::with('locations')
            ->where('id', $id)
            ->where('account_id', $accountId)
            ->first();
    }

    /**
     * Bulk delete business closures
     */
    public function bulkDelete(array $ids): int
    {
        $accountId = Auth::user()->account_id;
        $deleted = 0;

        foreach ($ids as $id) {
            $closure = BusinessClosure::where('id', $id)
                ->where('account_id', $accountId)
                ->first();

            if ($closure) {
                $closure->delete();
                $deleted++;
            }
        }

        return $deleted;
    }
}
