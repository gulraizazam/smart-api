<?php

namespace App\Models;

use App\Helpers\Filters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LeadStatuses extends BaseModal
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'parent_id', 'account_id', 'is_comment', 'is_default',
        'is_booked', 'is_arrived', 'is_converted', 'sort_no', 'active',
        'created_at', 'updated_at', 'is_junk',
    ];

    protected static array $_fillable = [
        'name', 'active', 'parent_id', 'is_comment', 'is_default',
        'is_booked', 'is_arrived', 'is_converted', 'is_junk',
    ];

    protected $table = 'lead_statuses';

    protected static string $_table = 'lead_statuses';

    /**
     * Unique boolean flags that must be reset across the account when set to 1.
     */
    protected static array $uniqueFlags = [
        'is_junk', 'is_default', 'is_arrived', 'is_converted', 'is_booked',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function leads(): HasMany
    {
        return $this->hasMany(Leads::class, 'lead_status_id');
    }

    // =========================================================================
    // Query Helpers
    // =========================================================================

    public static function getActiveSorted(
        array|int|false $skip_ids = false,
        array|int|false $include_ids = false,
        int|false $account_id = false
    ): \Illuminate\Support\Collection {
        $query = self::where('active', 1);

        if ($skip_ids) {
            $query->whereNotIn('id', (array) $skip_ids);
        }
        if ($include_ids) {
            $query->whereIn('id', (array) $include_ids);
        }

        return $query->orderBy('sort_no')->pluck('name', 'id');
    }

    public static function getActiveOnly(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('active', 1)->orderBy('sort_no')->get();
    }

    public static function getLeadStatuses(int|false $excludeIds = false): \Illuminate\Support\Collection
    {
        $query = self::where('account_id', Auth::user()->account_id)
            ->where('active', 1)
            ->where('parent_id', 0);

        if ($excludeIds) {
            $query->whereNotIn('id', (array) $excludeIds);
        }

        return $query->orderBy('sort_no')->pluck('name', 'id');
    }

    public static function getParentRecords(
        string|false $prepend_text,
        int $account_id,
        array|int $skip_ids = [],
        bool $active_only = false
    ): \Illuminate\Support\Collection {
        $where = ['account_id' => $account_id, 'parent_id' => 0];

        if ($active_only) {
            $where['active'] = 1;
        }

        $query = self::where($where);

        $skip_ids = (array) $skip_ids;
        if (!empty($skip_ids)) {
            $query->whereNotIn('id', $skip_ids);
        }

        $records = $query->pluck('name', 'id');

        if ($prepend_text) {
            $records->prepend($prepend_text, '');
        }

        return $records;
    }

    public static function getAllRecordsDictionary(int $account_id): array
    {
        return self::where('account_id', $account_id)->get()->getDictionary();
    }

    // =========================================================================
    // Datatable Methods
    // =========================================================================

    public static function getTotalRecords(Request $request, int|false $account_id = false, bool $apply_filter = false): int
    {
        return self::applyFiltersAndVisibility($request, $account_id, $apply_filter)
            ->count();
    }

    public static function getRecords(Request $request, int $offset, int $limit, int|false $account_id = false, bool $apply_filter = false): \Illuminate\Database\Eloquent\Collection
    {
        return self::applyFiltersAndVisibility($request, $account_id, $apply_filter)
            ->limit($limit)
            ->offset($offset)
            ->orderBy('sort_no')
            ->get();
    }

    // =========================================================================
    // CRUD Operations
    // =========================================================================

    public static function createRecord(Request $request, int $account_id): self
    {
        $data = $request->all();
        $data['account_id'] = $account_id;
        $data['parent_id'] = $data['parent_id'] ?: 0;
        $data['is_comment'] ??= 0;

        self::resetUniqueFlags($data, $account_id);

        $record = self::create($data);
        $record->update(['sort_no' => $record->id]);

        AuditTrails::addEventLogger(self::$_table, 'Create', $data, self::$_fillable, $record);

        return $record;
    }

    public static function updateRecord(int $id, Request $request, int $account_id): ?self
    {
        $record = self::where(['id' => $id, 'account_id' => $account_id])->first();

        if (!$record) {
            return null;
        }

        $old_data = $record->toArray();
        $data = $request->all();
        $data['account_id'] = $account_id;
        $data['parent_id'] ??= 0;
        $data['is_comment'] ??= 0;

        self::resetUniqueFlags($data, $account_id);

        $record->update($data);

        AuditTrails::EditEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $id);

        return $record;
    }

    public static function DeleteRecord(int $id): \Illuminate\Support\Collection
    {
        $lead_status = self::getData($id);

        if (!$lead_status) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }

        if (self::isChildExists($id, Auth::user()->account_id)) {
            return collect(['status' => false, 'message' => 'Child records exist, unable to delete resource']);
        }

        $lead_status->delete();
        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been deleted successfully.']);
    }

    public static function InactiveRecord(int $id): \Illuminate\Support\Collection
    {
        $lead_status = self::getData($id);

        if (!$lead_status) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }

        $lead_status->update(['active' => 0]);
        AuditTrails::InactiveEventLogger(self::$_table, 'Inactive', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been inactivated successfully.']);
    }

    public static function activeRecord(int $id): \Illuminate\Support\Collection
    {
        $lead_status = self::getData($id);

        if (!$lead_status) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }

        $lead_status->update(['active' => 1]);
        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been activated successfully.']);
    }

    public static function isChildExists(int $id, int $account_id): int
    {
        return self::where(['parent_id' => $id, 'account_id' => $account_id])->count();
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Reset unique boolean flags across the account when a new record claims one.
     * Replaces 5 identical if-blocks in create/update methods.
     */
    protected static function resetUniqueFlags(array $data, int $account_id): void
    {
        foreach (self::$uniqueFlags as $flag) {
            if (($data[$flag] ?? null) === '1' || ($data[$flag] ?? null) === 1) {
                self::where('account_id', $account_id)->update([$flag => 0]);
            }
        }
    }

    /**
     * Build filtered query with active/inactive visibility check.
     */
    protected static function applyFiltersAndVisibility(Request $request, int|false $account_id, bool $apply_filter): Builder
    {
        $where = self::buildFilters($request, $account_id, $apply_filter);

        $query = self::query();

        if (!empty($where)) {
            $query->where($where);
        }

        if (!Gate::allows('view_inactive_leadstatuses')) {
            $query->where('active', 1);
        }

        return $query;
    }

    /**
     * Build filter conditions.
     */
    protected static function buildFilters(Request $request, int|false $account_id, bool $apply_filter): array
    {
        $where = [];
        $filters = getFilters($request->all());
        $userId = Auth::id();
        $filterKey = 'lead_statuses';

        // Account filter
        if ($account_id) {
            $where[] = ['account_id', '=', $account_id];
            Filters::put($userId, $filterKey, 'account_id', $account_id);
        } elseif ($apply_filter) {
            Filters::forget($userId, $filterKey, 'account_id');
        } else {
            $stored = Filters::get($userId, $filterKey, 'account_id');
            if ($stored) {
                $where[] = ['account_id', '=', $stored];
            }
        }

        // Name filter
        if (hasFilter($filters, 'name')) {
            $where[] = ['name', 'like', '%' . $filters['name'] . '%'];
            Filters::put($userId, $filterKey, 'lead_status_name', $filters['name']);
        } elseif ($apply_filter) {
            Filters::forget($userId, $filterKey, 'lead_status_name');
        } else {
            $stored = Filters::get($userId, $filterKey, 'lead_status_name');
            if ($stored) {
                $where[] = ['name', 'like', '%' . $stored . '%'];
            }
        }

        // Status filter
        if (hasFilter($filters, 'status')) {
            $where[] = ['active', '=', $filters['status']];
            Filters::put($userId, $filterKey, 'status', $filters['status']);
        } elseif ($apply_filter) {
            Filters::forget($userId, $filterKey, 'status');
        } else {
            $stored = Filters::get($userId, $filterKey, 'status');
            if ($stored !== null && in_array($stored, [0, 1, '0', '1'], true)) {
                $where[] = ['active', '=', $stored];
            }
        }

        return $where;
    }
}
