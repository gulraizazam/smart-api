<?php

namespace App\Services\Lead;

use App\Helpers\Filters;
use App\Models\AuditTrails;
use App\Models\LeadSources;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LeadSourceService
{
    protected static string $table = 'lead_sources';
    protected static string $filterKey = 'lead_sources';

    /**
     * Get datatable data with filters and pagination.
     */
    public function getDatatableData(array $requestFilters, int $accountId): array
    {
        $filters = getFilters($requestFilters);
        $applyFilter = checkFilters($filters, self::$filterKey);
        $where = $this->buildFilters($filters, $accountId, $applyFilter);

        $query = LeadSources::query();

        if (!empty($where)) {
            $query->where($where);
        }

        if (!Gate::allows('view_inactive_leadsources')) {
            $query->where('active', 1);
        }

        $total = (clone $query)->count();

        return [
            'query' => $query,
            'total' => $total,
            'active_filters' => Filters::all(Auth::id(), self::$filterKey),
        ];
    }

    /**
     * Get records for sort order page.
     */
    public function getSortableRecords(int $accountId): \Illuminate\Database\Eloquent\Collection
    {
        return LeadSources::where('account_id', $accountId)
            ->orderBy('sort_no')
            ->get();
    }

    /**
     * Save sort order.
     */
    public function saveSortOrder(array $itemIds): void
    {
        foreach ($itemIds as $position => $id) {
            LeadSources::where('id', $id)->update(['sort_no' => $position]);
        }
    }

    /**
     * Create a new lead source.
     */
    public function create(array $data, int $accountId): LeadSources
    {
        $data['account_id'] = $accountId;

        $record = LeadSources::create($data);
        $record->update(['sort_no' => $record->id]);

        AuditTrails::addEventLogger(self::$table, 'create', $data, LeadSources::$_fillable, $record);

        return $record;
    }

    /**
     * Find a lead source by ID within the account.
     */
    public function find(int $id): ?LeadSources
    {
        return LeadSources::getData($id);
    }

    /**
     * Update a lead source.
     */
    public function update(int $id, array $data, int $accountId): ?LeadSources
    {
        $record = LeadSources::where(['id' => $id, 'account_id' => $accountId])->first();

        if (!$record) {
            return null;
        }

        $oldData = $record->toArray();
        $data['account_id'] = $accountId;

        $record->update($data);

        AuditTrails::editEventLogger(self::$table, 'Edit', $data, LeadSources::$_fillable, $oldData, $id);

        return $record;
    }

    /**
     * Delete a lead source.
     */
    public function delete(int $id): array
    {
        $record = LeadSources::getData($id);

        if (!$record) {
            return ['status' => false, 'message' => 'Resource not found.'];
        }

        if (LeadSources::isChildExists($id, Auth::user()->account_id)) {
            return ['status' => false, 'message' => 'Child records exist, unable to delete resource.'];
        }

        $record->delete();
        AuditTrails::deleteEventLogger(self::$table, 'delete', LeadSources::$_fillable, $id);

        return ['status' => true, 'message' => 'Record has been deleted successfully.'];
    }

    /**
     * Bulk delete lead sources (from datatable).
     */
    public function bulkDelete(array $ids, int $accountId): void
    {
        $sources = LeadSources::getBulkData($ids);
        $sources?->each(function (LeadSources $source) use ($accountId) {
            if (!LeadSources::isChildExists($source->id, $accountId)) {
                $source->delete();
            }
        });
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(int $id, int $status): array
    {
        $record = LeadSources::getData($id);

        if (!$record) {
            return ['status' => false, 'message' => 'Resource not found.'];
        }

        $record->update(['active' => $status]);

        $action = $status ? 'active' : 'inactive';
        AuditTrails::{$action . 'EventLogger'}(self::$table, $action, LeadSources::$_fillable, $id);

        $message = $status
            ? 'Record has been activated successfully.'
            : 'Record has been inactivated successfully.';

        return ['status' => true, 'message' => $message];
    }

    /**
     * Build filter conditions with persistence.
     */
    protected function buildFilters(array $filters, int $accountId, bool $applyFilter): array
    {
        $where = [];
        $userId = Auth::id();

        // Account filter
        $where[] = ['account_id', '=', $accountId];
        Filters::put($userId, self::$filterKey, 'account_id', $accountId);

        // Name filter
        if (hasFilter($filters, 'name')) {
            $where[] = ['name', 'like', '%' . $filters['name'] . '%'];
            Filters::put($userId, self::$filterKey, 'lead_source_name', $filters['name']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::$filterKey, 'lead_source_name');
        } else {
            $stored = Filters::get($userId, self::$filterKey, 'lead_source_name');
            if ($stored) {
                $where[] = ['name', 'like', '%' . $stored . '%'];
            }
        }

        // Status filter
        if (hasFilter($filters, 'status')) {
            $where[] = ['active', '=', $filters['status']];
            Filters::put($userId, self::$filterKey, 'status', $filters['status']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::$filterKey, 'status');
        } else {
            $stored = Filters::get($userId, self::$filterKey, 'status');
            if ($stored !== null && in_array($stored, [0, 1, '0', '1'], true)) {
                $where[] = ['active', '=', $stored];
            }
        }

        return $where;
    }
}
