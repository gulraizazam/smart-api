<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\Helpers\Filters;
use App\Models\AuditTrails;
use App\Models\LeadSources;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LeadSourceService
{
    protected const TABLE = 'lead_sources';
    protected const FILTER_KEY = 'lead_sources';

    public function getDatatableData(array $requestFilters, int $accountId): array
    {
        $filters = getFilters($requestFilters);
        $applyFilter = checkFilters($filters, self::FILTER_KEY);
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
            'active_filters' => Filters::all(Auth::id(), self::FILTER_KEY),
        ];
    }

    public function getSortableRecords(int $accountId): Collection
    {
        return LeadSources::where('account_id', $accountId)
            ->orderBy('sort_no')
            ->get();
    }

    public function saveSortOrder(array $itemIds): void
    {
        foreach ($itemIds as $position => $id) {
            LeadSources::where('id', $id)->where('account_id', auth()->user()->account_id)->update(['sort_no' => $position]);
        }
    }

    public function create(array $data, int $accountId): LeadSources
    {
        $data['account_id'] = $accountId;

        $record = LeadSources::create($data);
        $record->update(['sort_no' => $record->id]);

        AuditTrails::addEventLogger(self::TABLE, 'create', $data, LeadSources::$_fillable, $record);

        return $record;
    }

    public function find(int $id): ?LeadSources
    {
        return LeadSources::getData($id);
    }

    public function update(int $id, array $data, int $accountId): ?LeadSources
    {
        $record = LeadSources::where(['id' => $id, 'account_id' => $accountId])->first();

        if (!$record) {
            return null;
        }

        $oldData = $record->toArray();
        $data['account_id'] = $accountId;

        $record->update($data);

        AuditTrails::editEventLogger(self::TABLE, 'Edit', $data, LeadSources::$_fillable, $oldData, $id);

        return $record;
    }

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
        AuditTrails::deleteEventLogger(self::TABLE, 'delete', LeadSources::$_fillable, $id);

        return ['status' => true, 'message' => 'Record has been deleted successfully.'];
    }

    public function bulkDelete(array $ids, int $accountId): void
    {
        $sources = LeadSources::getBulkData($ids);
        $sources?->each(function (LeadSources $source) use ($accountId): void {
            if (!LeadSources::isChildExists($source->id, $accountId)) {
                $source->delete();
            }
        });
    }

    public function toggleStatus(int $id, int $status): array
    {
        $record = LeadSources::getData($id);

        if (!$record) {
            return ['status' => false, 'message' => 'Resource not found.'];
        }

        $record->update(['active' => $status]);

        $action = $status ? 'active' : 'inactive';
        AuditTrails::{$action . 'EventLogger'}(self::TABLE, $action, LeadSources::$_fillable, $id);

        return [
            'status' => true,
            'message' => $status
                ? 'Record has been activated successfully.'
                : 'Record has been inactivated successfully.',
        ];
    }

    protected function buildFilters(array $filters, int $accountId, bool $applyFilter): array
    {
        $where = [];
        $userId = Auth::id();

        $where[] = ['account_id', '=', $accountId];
        Filters::put($userId, self::FILTER_KEY, 'account_id', $accountId);

        if (hasFilter($filters, 'name')) {
            $where[] = ['name', 'like', '%' . $filters['name'] . '%'];
            Filters::put($userId, self::FILTER_KEY, 'lead_source_name', $filters['name']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'lead_source_name');
        } else {
            $stored = Filters::get($userId, self::FILTER_KEY, 'lead_source_name');
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
