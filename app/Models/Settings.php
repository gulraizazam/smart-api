<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Filters;
use App\Models\AuditTrails;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Settings extends BaseModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'data', 'account_id', 'slug', 'active', 'is_featured', 'sort_no', 'created_at', 'updated_at'];

    /** @var array Auditable fields for AuditTrails logging */
    public static array $_fillable = ['name', 'data', 'slug', 'active'];

    protected $table = 'settings';

    public static string $_table = 'settings';

    // -----------------------------------------------------------------
    // Active query methods (used by other modules)
    // -----------------------------------------------------------------

    /**
     * Find a setting by slug and account.
     */
    public static function getBySlug(string $slug, int $accountId): ?static
    {
        return self::where(['slug' => $slug, 'account_id' => $accountId])->first();
    }

    /**
     * Check if child records exist.
     */
    public static function isChildExists(int $id, int $accountId): bool
    {
        return false;
    }

    /**
     * Get all records as an ID-keyed dictionary array.
     */
    public static function getAllRecordsDictionary(int $accountId): array
    {
        return self::where(['account_id' => $accountId])->get()->getDictionary();
    }

    // -----------------------------------------------------------------
    // Deprecated methods — logic moved to SettingsService
    // -----------------------------------------------------------------

    /**
     * @deprecated Use SettingsService::getDatatableCount() instead.
     */
    public static function getTotalRecords(Request $request, int $account_id, bool $apply_filter): int
    {
        $where = self::settings_filters($request, $account_id, $apply_filter);

        return !empty($where) ? self::where($where)->count() : self::count();
    }

    /**
     * @deprecated Use SettingsService::getDatatableData() instead.
     */
    public static function getRecords(Request $request, int $iDisplayStart, int $iDisplayLength, int|false $account_id = false, bool $apply_filter = false): \Illuminate\Database\Eloquent\Collection
    {
        $where = self::settings_filters($request, $account_id, $apply_filter);
        [$orderBy, $order] = getSortBy($request);

        return self::when(!empty($where), fn ($q) => $q->where($where))
            ->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->orderBy($orderBy, $order)
            ->get();
    }

    /**
     * @deprecated Filter logic moved to SettingsService.
     */
    public static function settings_filters(mixed $request, int|false $account_id, bool $apply_filter): array
    {
        $where = [];
        $filters = getFilters($request->all());
        if ($account_id) {
            $where[] = ['account_id', '=', $account_id];
            Filters::put(Auth::user()->id, 'settings', 'account_id', $account_id);
        } elseif ($apply_filter) {
            Filters::forget(Auth::user()->id, 'settings', 'account_id');
        } elseif ($stored = Filters::get(Auth::user()->id, 'settings', 'account_id')) {
            $where[] = ['account_id', '=', $stored];
        }

        if (hasFilter($filters, 'name')) {
            $where[] = ['name', 'like', '%' . $filters['name'] . '%'];
            Filters::put(Auth::user()->id, 'settings', 'setting_name', $filters['name']);
        } elseif ($apply_filter) {
            Filters::forget(Auth::user()->id, 'settings', 'setting_name');
        } elseif ($stored = Filters::get(Auth::user()->id, 'settings', 'setting_name')) {
            $where[] = ['name', 'like', '%' . $stored . '%'];
        }

        if (hasFilter($filters, 'data')) {
            $where[] = ['data', 'like', '%' . $filters['data'] . '%'];
            Filters::put(Auth::user()->id, 'settings', 'setting_data', $filters['data']);
        } elseif ($apply_filter) {
            Filters::forget(Auth::user()->id, 'settings', 'setting_data');
        } elseif ($stored = Filters::get(Auth::user()->id, 'settings', 'setting_data')) {
            $where[] = ['data', 'like', '%' . $stored . '%'];
        }

        return $where;
    }

    /**
     * @deprecated Use SettingsService::create() instead.
     */
    public static function createRecord(mixed $request, int $account_id): static
    {
        $data = $request->all();
        $data['account_id'] = $account_id;
        $data['slug'] = 'custom';

        $record = self::create($data);
        $record->update(['sort_no' => $record->id]);

        return $record;
    }

    /**
     * @deprecated Use SettingsService::update() instead.
     */
    public static function updateRecord(int $id, mixed $request, int $account_id): ?static
    {
        $old_data = (self::find($id))?->toArray() ?? [];
        $data = $request->all();
        $data['account_id'] = $account_id;

        if (($old_data['slug'] ?? '') === 'sys-discounts') {
            $data['data'] = implode(':', [$request->min, $request->max]);
        }
        if (($old_data['slug'] ?? '') === 'sys-birthdaypromotion') {
            $data['data'] = implode(':', [$request->pre, $request->post]);
        }

        if (! isset($data['is_featured']) || $data['is_featured'] === '') {
            $data['is_featured'] = 0;
        }

        $record = self::where(['id' => $id, 'account_id' => $account_id])->first();
        if (! $record) {
            return null;
        }

        if (isset($data['min'])) {
            $data['min'] = ltrim($data['min'], '0');
        }
        if (isset($data['max'])) {
            $data['max'] = ltrim($data['max'], '0');
        }

        $record->update($data);

        AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $id);

        return $record;
    }
}
