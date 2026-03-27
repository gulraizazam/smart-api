<?php

declare(strict_types=1);

namespace App\Services\UserManagement;

use App\Helpers\Filters;
use App\Models\AuditTrails;
use App\Models\GlobalOperatorSettings;
use App\Models\UserOperatorSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UserOperatorSettingsService
{
    private const FILTER_KEY = 'operators';

    public function getDatatableCount(array $params): int
    {
        return $this->buildDatatableQuery($params)->count();
    }

    public function getDatatableData(array $params): array
    {
        $records = $this->buildDatatableQuery($params)
            ->orderBy($params['order_by'] ?? 'operator_name', $params['order'] ?? 'asc')
            ->offset($params['offset'] ?? 0)
            ->limit($params['limit'] ?? 30)
            ->get();

        return [
            'data' => $records,
            'total' => $records->count(),
        ];
    }

    private function buildDatatableQuery(array $params): \Illuminate\Database\Eloquent\Builder
    {
        $accountId = Auth::user()->account_id;
        $userId = (int) Auth::id();
        $applyFilter = $params['apply_filter'] ?? false;

        $where = $this->buildWhereConditions($params, $userId, $accountId, $applyFilter);

        return UserOperatorSettings::query()
            ->when(count($where) > 0, fn ($q) => $q->where($where));
    }

    public function find(int $id): ?UserOperatorSettings
    {
        return UserOperatorSettings::where('id', $id)
            ->where('account_id', Auth::user()->account_id)
            ->first();
    }

    public function update(int $id, array $data): ?UserOperatorSettings
    {
        $accountId = Auth::user()->account_id;

        $record = UserOperatorSettings::where('id', $id)
            ->where('account_id', $accountId)
            ->first();

        if (!$record) {
            return null;
        }

        $oldData = $record->toArray();

        $updateData = array_merge($data, ['account_id' => $accountId]);
        $record->update($updateData);

        AuditTrails::EditEventLogger(
            'user_operator_settings',
            'edit',
            $updateData,
            UserOperatorSettings::$_fillable ?? [],
            $oldData,
            $id,
        );

        return $record;
    }

    public function getGlobalOperator(int $operatorId): ?GlobalOperatorSettings
    {
        return GlobalOperatorSettings::find($operatorId);
    }

    public function getActiveFilters(): array
    {
        return Filters::all(Auth::id(), self::FILTER_KEY);
    }

    public function getPermissions(): array
    {
        return [
            'edit' => Gate::allows('user_operator_settings_edit'),
        ];
    }

    private function buildWhereConditions(array $params, int $userId, ?int $accountId, bool $applyFilter): array
    {
        $where = [];

        if ($accountId) {
            $where[] = ['account_id', '=', $accountId];
            Filters::put($userId, self::FILTER_KEY, 'account_id', $accountId);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'account_id');
        } elseif ($storedAccountId = Filters::get($userId, self::FILTER_KEY, 'account_id')) {
            $where[] = ['account_id', '=', $storedAccountId];
        }

        if (!empty($params['operator_name'])) {
            $where[] = ['operator_name', 'like', '%' . $params['operator_name'] . '%'];
            Filters::put($userId, self::FILTER_KEY, 'operator_name', $params['operator_name']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'operator_name');
        } elseif ($storedName = Filters::get($userId, self::FILTER_KEY, 'operator_name')) {
            $where[] = ['operator_name', 'like', '%' . $storedName . '%'];
        }

        return $where;
    }
}
