<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedLegacy;
use App\Helpers\Filters;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class UserOperatorSettings extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'operator_id', 'operator_name', 'url', 'username', 'password',
        'mask', 'test_mode', 'string_1', 'string_2',
        'account_id', 'created_at', 'updated_at',
    ];

    protected static array $_fillable = [
        'operator_id', 'operator_name', 'url', 'username', 'password',
        'mask', 'test_mode', 'string_1', 'string_2',
    ];

    protected $table = 'user_operator_settings';

    protected static string $_table = 'user_operator_settings';

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'test_mode' => 'integer',
            'password' => EncryptedLegacy::class,
        ];
    }

    /**
     * @deprecated Use UserOperatorSettingsService::find() instead
     */
    public static function getRecord(int $accountId, int $id): ?static
    {
        return self::where('account_id', $accountId)
            ->where('id', $id)
            ->first();
    }

    /**
     * @deprecated Use UserOperatorSettingsService::getGlobalOperator() instead
     */
    public static function getGlobalOperators(): \Illuminate\Support\Collection
    {
        return GlobalOperatorSettings::get()->pluck('operator_name', 'id');
    }

    /**
     * @deprecated Use UserOperatorSettingsService::getGlobalOperator() instead
     */
    public static function getGlobalOperator(int $operatorId): ?GlobalOperatorSettings
    {
        return GlobalOperatorSettings::find($operatorId);
    }

    /**
     * @deprecated Use UserOperatorSettingsService::update() instead
     */
    public static function updateRecord(int $id, mixed $request, int $accountId): ?static
    {
        $oldData = (self::find($id))?->toArray() ?? [];

        $data = is_array($request) ? $request : $request->all();
        $data['account_id'] = $accountId;

        $record = self::where('id', $id)
            ->where('account_id', $accountId)
            ->first();

        if (!$record) {
            return null;
        }

        $record->update($data);

        AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $oldData, $id);

        return $record;
    }

    /**
     * @deprecated Use UserOperatorSettingsService::getDatatableData() instead
     */
    public static function getTotalRecords(mixed $request, int|false $accountId = false, bool $applyFilter = false): int
    {
        $where = self::operators_filters($request, $accountId, $applyFilter);

        return !empty($where) ? self::where($where)->count() : self::count();
    }

    /**
     * @deprecated Use UserOperatorSettingsService::getDatatableData() instead
     */
    public static function getRecords(mixed $request, int $iDisplayStart, int $iDisplayLength, int|false $accountId = false, bool $applyFilter = false): \Illuminate\Database\Eloquent\Collection
    {
        $where = self::operators_filters($request, $accountId, $applyFilter);
        [$orderBy, $order] = getSortBy($request);

        return self::when(!empty($where), fn ($q) => $q->where($where))
            ->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->when($orderBy !== 'name', fn ($q) => $q->orderBy($orderBy, $order))
            ->get();
    }

    /**
     * @deprecated Filter logic moved to UserOperatorSettingsService
     */
    public static function operators_filters(mixed $request, int|false $accountId, bool $applyFilter): array
    {
        $where = [];
        $filters = getFilters($request->all());

        if ($accountId) {
            $where[] = ['account_id', '=', $accountId];
            Filters::put(Auth::id(), 'operators', 'account_id', $accountId);
        } elseif ($applyFilter) {
            Filters::forget(Auth::id(), 'operators', 'account_id');
        } elseif ($stored = Filters::get(Auth::id(), 'operators', 'account_id')) {
            $where[] = ['account_id', '=', $stored];
        }

        if (hasFilter($filters, 'operator_name')) {
            $where[] = ['operator_name', 'like', '%' . $filters['operator_name'] . '%'];
            Filters::put(Auth::id(), 'operators', 'operator_name', $filters['operator_name']);
        } elseif ($applyFilter) {
            Filters::forget(Auth::id(), 'operators', 'operator_name');
        } elseif ($stored = Filters::get(Auth::id(), 'operators', 'operator_name')) {
            $where[] = ['operator_name', 'like', '%' . $stored . '%'];
        }

        return $where;
    }
}
