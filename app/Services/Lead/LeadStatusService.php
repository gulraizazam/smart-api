<?php

namespace App\Services\Lead;

use App\Helpers\Filters;
use App\Models\AuditTrails;
use App\Models\LeadStatuses;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LeadStatusService
{
    protected static string $table = 'lead_statuses';
    protected static string $filterKey = 'lead_statuses';

    /**
     * Unique boolean flags — only one record per account can have each set to 1.
     */
    protected static array $uniqueFlags = [
        'is_junk', 'is_default', 'is_arrived', 'is_converted', 'is_booked',
    ];

    /**
     * Get datatable data with filters and pagination.
     */
    public function getDatatableData(array $requestFilters, int $accountId): array
    {
        $filters = getFilters($requestFilters);
        $applyFilter = checkFilters($filters, self::$filterKey);
        $where = $this->buildFilters($filters, $accountId, $applyFilter);

        $query = LeadStatuses::query();

        if (!empty($where)) {
            $query->where($where);
        }

        if (!Gate::allows('view_inactive_leadstatuses')) {
            $query->where('active', 1);
        }

        $total = (clone $query)->count();

        return [
            'query' => $query,
            'total' => $total,
            'allStatuses' => LeadStatuses::getAllRecordsDictionary($accountId),
            'parentStatuses' => LeadStatuses::getParentRecords(false, $accountId, [], true),
            'active_filters' => Filters::all(Auth::id(), self::$filterKey),
        ];
    }

    /**
     * Get records for sort order page.
     */
    public function getSortableRecords(int $accountId): \Illuminate\Database\Eloquent\Collection
    {
        return LeadStatuses::where('account_id', $accountId)
            ->orderBy('sort_no')
            ->get();
    }

    /**
     * Save sort order.
     */
    public function saveSortOrder(array $itemIds): void
    {
        foreach ($itemIds as $position => $id) {
            LeadStatuses::where('id', $id)->update(['sort_no' => $position]);
        }
    }

    /**
     * Create a new lead status.
     */
    public function create(array $data, int $accountId): LeadStatuses
    {
        $data['account_id'] = $accountId;
        $data['parent_id'] = $data['parent_id'] ?: 0;
        $data['is_comment'] ??= 0;

        $this->resetUniqueFlags($data, $accountId);

        $record = LeadStatuses::create($data);
        $record->update(['sort_no' => $record->id]);

        AuditTrails::addEventLogger(self::$table, 'Create', $data, LeadStatuses::$_fillable, $record);

        return $record;
    }

    /**
     * Find a lead status by ID within the account.
     */
    public function find(int $id): ?LeadStatuses
    {
        return LeadStatuses::getData($id);
    }

    /**
     * Update a lead status.
     */
    public function update(int $id, array $data, int $accountId): ?LeadStatuses
    {
        $record = LeadStatuses::where(['id' => $id, 'account_id' => $accountId])->first();

        if (!$record) {
            return null;
        }

        $oldData = $record->toArray();
        $data['account_id'] = $accountId;
        $data['parent_id'] ??= 0;
        $data['is_comment'] ??= 0;

        $this->resetUniqueFlags($data, $accountId);

        $record->update($data);

        AuditTrails::EditEventLogger(self::$table, 'Edit', $data, LeadStatuses::$_fillable, $oldData, $id);

        return $record;
    }

    /**
     * Delete a lead status.
     */
    public function delete(int $id): array
    {
        $record = LeadStatuses::getData($id);

        if (!$record) {
            return ['status' => false, 'message' => 'Resource not found.'];
        }

        if (LeadStatuses::isChildExists($id, Auth::user()->account_id)) {
            return ['status' => false, 'message' => 'Child records exist, unable to delete resource.'];
        }

        $record->delete();
        AuditTrails::deleteEventLogger(self::$table, 'delete', LeadStatuses::$_fillable, $id);

        return ['status' => true, 'message' => 'Record has been deleted successfully.'];
    }

    /**
     * Bulk delete lead statuses (from datatable).
     */
    public function bulkDelete(array $ids, int $accountId): void
    {
        $statuses = LeadStatuses::getBulkData($ids);
        $statuses?->each(function (LeadStatuses $status) use ($accountId) {
            if (!LeadStatuses::isChildExists($status->id, $accountId)) {
                $status->delete();
            }
        });
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(int $id, int $status): array
    {
        $record = LeadStatuses::getData($id);

        if (!$record) {
            return ['status' => false, 'message' => 'Resource not found.'];
        }

        $record->update(['active' => $status]);

        $action = $status ? 'active' : 'inactive';
        AuditTrails::{$action . 'EventLogger'}(self::$table, $action, LeadStatuses::$_fillable, $id);

        $message = $status
            ? 'Record has been activated successfully.'
            : 'Record has been inactivated successfully.';

        return ['status' => true, 'message' => $message];
    }

    /**
     * Get parent records for dropdowns.
     */
    public function getParentRecords(int $accountId, int|array $skipIds = [], string|false $prependText = false): \Illuminate\Support\Collection
    {
        return LeadStatuses::getParentRecords($prependText, $accountId, $skipIds, true);
    }

    /**
     * Reset unique boolean flags across the account when a new record claims one.
     */
    protected function resetUniqueFlags(array $data, int $accountId): void
    {
        foreach (self::$uniqueFlags as $flag) {
            if (in_array($data[$flag] ?? null, ['1', 1], true)) {
                LeadStatuses::where('account_id', $accountId)->update([$flag => 0]);
            }
        }
    }

    /**
     * Build filter conditions with persistence.
     */
    protected function buildFilters(array $filters, int $accountId, bool $applyFilter): array
    {
        $where = [];
        $userId = Auth::id();

        // Account filter (always scoped)
        $where[] = ['account_id', '=', $accountId];
        Filters::put($userId, self::$filterKey, 'account_id', $accountId);

        // Name filter
        if (hasFilter($filters, 'name')) {
            $where[] = ['name', 'like', '%' . $filters['name'] . '%'];
            Filters::put($userId, self::$filterKey, 'lead_status_name', $filters['name']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::$filterKey, 'lead_status_name');
        } else {
            $stored = Filters::get($userId, self::$filterKey, 'lead_status_name');
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
