<?php

namespace App\Models;

use App\Helpers\Filters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LeadSources extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'account_id', 'sort_no', 'active', 'created_at', 'updated_at'];

    protected static array $_fillable = ['name', 'active'];

    protected $table = 'lead_sources';

    protected static string $_table = 'lead_sources';

    // =========================================================================
    // Relationships
    // =========================================================================

    public function leads(): HasMany
    {
        return $this->hasMany(Leads::class, 'lead_source_id');
    }

    // =========================================================================
    // Query Helpers
    // =========================================================================

    public static function getActiveSorted(): \Illuminate\Support\Collection
    {
        return self::where('account_id', Auth::user()->account_id)
            ->where('active', 1)
            ->orderBy('sort_no')
            ->pluck('name', 'id');
    }

    public static function getActiveOnly(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('active', 1)->orderBy('sort_no')->get();
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
        return self::applyFiltersAndVisibility($request, $account_id, $apply_filter, 'view_inactive_leadsources')
            ->count();
    }

    public static function getRecords(Request $request, int $offset, int $limit, int|false $account_id = false, bool $apply_filter = false): \Illuminate\Database\Eloquent\Collection
    {
        return self::applyFiltersAndVisibility($request, $account_id, $apply_filter, 'view_inactive_leadsources')
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

        $record = self::create($data);
        $record->update(['sort_no' => $record->id]);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

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

        $record->update($data);

        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $id);

        return $record;
    }

    public static function DeleteRecord(int $id): \Illuminate\Support\Collection
    {
        $lead_source = self::getData($id);

        if (!$lead_source) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }

        if (self::isChildExists($id, Auth::user()->account_id)) {
            return collect(['status' => false, 'message' => 'Child records exist, unable to delete resource']);
        }

        $lead_source->delete();
        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been deleted successfully.']);
    }

    public static function InactiveRecord(int $id): \Illuminate\Support\Collection
    {
        $lead_source = self::getData($id);

        if (!$lead_source) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }

        $lead_source->update(['active' => 0]);
        AuditTrails::inactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been inactivated successfully.']);
    }

    public static function activeRecord(int $id): \Illuminate\Support\Collection
    {
        $lead_source = self::getData($id);

        if (!$lead_source) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }

        $lead_source->update(['active' => 1]);
        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been activated successfully.']);
    }

    public static function isChildExists(int $id, int $account_id): bool
    {
        return false;
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Build filtered query with active/inactive visibility check.
     * Consolidates the duplicated filter + Gate check logic from getTotalRecords/getRecords.
     */
    protected static function applyFiltersAndVisibility(Request $request, int|false $account_id, bool $apply_filter, string $gateAbility): Builder
    {
        $where = self::buildFilters($request, $account_id, $apply_filter);

        $query = self::query();

        if (!empty($where)) {
            $query->where($where);
        }

        if (!Gate::allows($gateAbility)) {
            $query->where('active', 1);
        }

        return $query;
    }

    /**
     * Build filter conditions — consolidated from lead_sources_filters.
     */
    protected static function buildFilters(Request $request, int|false $account_id, bool $apply_filter): array
    {
        $where = [];
        $filters = getFilters($request->all());
        $userId = Auth::id();
        $filterKey = 'lead_sources';

        // Account filter
        $where = self::resolveFilter($where, $account_id, $filterKey, 'account_id', $userId, $apply_filter, 'account_id');

        // Name filter (like)
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

    /**
     * Resolve a simple equality filter with Filters persistence.
     */
    protected static function resolveFilter(array $where, mixed $value, string $filterKey, string $column, int $userId, bool $apply_filter, string $storedKey): array
    {
        if ($value) {
            $where[] = [$column, '=', $value];
            Filters::put($userId, $filterKey, $storedKey, $value);
        } elseif ($apply_filter) {
            Filters::forget($userId, $filterKey, $storedKey);
        } else {
            $stored = Filters::get($userId, $filterKey, $storedKey);
            if ($stored) {
                $where[] = [$column, '=', $stored];
            }
        }

        return $where;
    }
}
