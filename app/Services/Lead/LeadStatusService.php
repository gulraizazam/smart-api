<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\Helpers\Filters;
use App\Models\AuditTrails;
use App\Models\LeadStatuses;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LeadStatusService
{
    protected const TABLE = 'lead_statuses';
    protected const FILTER_KEY = 'lead_statuses';

    /**
     * Unique boolean flags — only one record per account can have each set to 1.
     */
    protected const UNIQUE_FLAGS = [
        'is_junk', 'is_default', 'is_arrived', 'is_converted', 'is_booked',
    ];

    public function getDatatableData(array $requestFilters, int $accountId): array
    {
        $filters = getFilters($requestFilters);
        $applyFilter = checkFilters($filters, self::FILTER_KEY);
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
            'active_filters' => Filters::all(Auth::id(), self::FILTER_KEY),
        ];
    }

    public function getSortableRecords(int $accountId): Collection
    {
        return LeadStatuses::where('account_id', $accountId)
            ->orderBy('sort_no')
            ->get();
    }

    public function saveSortOrder(array $itemIds): void
    {
        foreach ($itemIds as $position => $id) {
            LeadStatuses::where('id', $id)->update(['sort_no' => $position]);
        }
    }

    public function create(array $data, int $accountId): LeadStatuses
    {
        $data['account_id'] = $accountId;
        $data['parent_id'] = $data['parent_id'] ?: 0;
        $data['is_comment'] ??= 0;

        $this->resetUniqueFlags($data, $accountId);

        $record = LeadStatuses::create($data);
        $record->update(['sort_no' => $record->id]);

        AuditTrails::addEventLogger(self::TABLE, 'Create', $data, LeadStatuses::$_fillable, $record);

        return $record;
    }

    public function find(int $id): ?LeadStatuses
    {
        return LeadStatuses::getData($id);
    }

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

        AuditTrails::EditEventLogger(self::TABLE, 'Edit', $data, LeadStatuses::$_fillable, $oldData, $id);

        return $record;
    }

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
        AuditTrails::deleteEventLogger(self::TABLE, 'delete', LeadStatuses::$_fillable, $id);

        return ['status' => true, 'message' => 'Record has been deleted successfully.'];
    }

    public function bulkDelete(array $ids, int $accountId): void
    {
        $statuses = LeadStatuses::getBulkData($ids);
        $statuses?->each(function (LeadStatuses $status) use ($accountId): void {
            if (!LeadStatuses::isChildExists($status->id, $accountId)) {
                $status->delete();
            }
        });
    }

    public function toggleStatus(int $id, int $status): array
    {
        $record = LeadStatuses::getData($id);

        if (!$record) {
            return ['status' => false, 'message' => 'Resource not found.'];
        }

        $record->update(['active' => $status]);

        $action = $status ? 'active' : 'inactive';
        AuditTrails::{$action . 'EventLogger'}(self::TABLE, $action, LeadStatuses::$_fillable, $id);

        return [
            'status' => true,
            'message' => $status
                ? 'Record has been activated successfully.'
                : 'Record has been inactivated successfully.',
        ];
    }

    public function getParentRecords(
        int $accountId,
        int|array $skipIds = [],
        string|false $prependText = false,
    ): \Illuminate\Support\Collection {
        return LeadStatuses::getParentRecords($prependText, $accountId, $skipIds, true);
    }

    protected function resetUniqueFlags(array $data, int $accountId): void
    {
        foreach (self::UNIQUE_FLAGS as $flag) {
            if (in_array($data[$flag] ?? null, ['1', 1], true)) {
                LeadStatuses::where('account_id', $accountId)->update([$flag => 0]);
            }
        }
    }

    protected function buildFilters(array $filters, int $accountId, bool $applyFilter): array
    {
        $where = [];
        $userId = Auth::id();

        $where[] = ['account_id', '=', $accountId];
        Filters::put($userId, self::FILTER_KEY, 'account_id', $accountId);

        if (hasFilter($filters, 'name')) {
            $where[] = ['name', 'like', '%' . $filters['name'] . '%'];
            Filters::put($userId, self::FILTER_KEY, 'lead_status_name', $filters['name']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'lead_status_name');
        } else {
            $stored = Filters::get($userId, self::FILTER_KEY, 'lead_status_name');
            if ($stored) {
                $where[] = ['name', 'like', '%' . $stored . '%'];
            }
        }

        if (hasFilter($filters, 'status')) {
            $where[] = ['active', '=', $filters['status']];
            Filters::put($userId, self::FILTER_KEY, 'status', $filters['status']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'status');
        } else {
            $stored = Filters::get($userId, self::FILTER_KEY, 'status');
            if ($stored !== null && in_array($stored, [0, 1, '0', '1'], true)) {
                $where[] = ['active', '=', $stored];
            }
        }

        return $where;
    }
}
