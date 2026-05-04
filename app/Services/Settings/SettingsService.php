<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Helpers\Filters;
use App\Models\AuditTrails;
use App\Models\Settings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class SettingsService
{
    private const FILTER_KEY = 'settings';

    /**
     * Get datatable record count with filters applied.
     */
    public function getDatatableCount(array $params): int
    {
        return $this->buildFilteredQuery($params)->count();
    }

    /**
     * Get datatable records with filters, sorting and pagination.
     *
     * @return array{data: \Illuminate\Database\Eloquent\Collection, total: int}
     */
    public function getDatatableData(array $params): array
    {
        $records = $this->buildFilteredQuery($params)
            ->orderBy($params['order_by'] ?? 'name', $params['order'] ?? 'asc')
            ->offset($params['offset'] ?? 0)
            ->limit($params['limit'] ?? 30)
            ->get();

        return [
            'data' => $records,
            'total' => $records->count(),
        ];
    }

    /**
     * Find a setting by ID scoped to the current account.
     */
    public function find(int $id): ?Settings
    {
        return Settings::where('id', $id)
            ->where('account_id', Auth::user()->account_id)
            ->first();
    }

    /**
     * Create a new custom setting.
     */
    public function create(array $data): Settings
    {
        $data['account_id'] = Auth::user()->account_id;
        $data['slug'] = 'custom';

        return Settings::create($data);
    }

    /**
     * Update a setting with slug-specific data transformations.
     */
    public function update(int $id, array $data): ?Settings
    {
        $accountId = Auth::user()->account_id;

        $record = Settings::where('id', $id)
            ->where('account_id', $accountId)
            ->first();

        if (! $record) {
            return null;
        }

        $oldData = $record->toArray();

        $data['account_id'] = $accountId;
        $data['data'] = $this->resolveDataValue($record->slug, $data);

        // Strip leading zeros from min/max
        if (isset($data['min'])) {
            $data['min'] = ltrim((string) $data['min'], '0') ?: '0';
        }
        if (isset($data['max'])) {
            $data['max'] = ltrim((string) $data['max'], '0') ?: '0';
        }

        $record->update($data);

        AuditTrails::EditEventLogger(
            Settings::$_table,
            'edit',
            $data,
            Settings::$_fillable,
            $oldData,
            $id,
        );

        return $record;
    }

    /**
     * Soft-delete a setting if no child records exist.
     *
     * @return array{success: bool, message: string}
     */
    public function delete(int $id): array
    {
        $record = $this->find($id);

        if (! $record) {
            return ['success' => false, 'message' => 'Resource not found.'];
        }

        if (Settings::isChildExists($id, Auth::user()->account_id)) {
            return ['success' => false, 'message' => 'Child records exist, unable to delete resource.'];
        }

        $record->delete();

        return ['success' => true, 'message' => 'Record has been deleted successfully.'];
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleActive(int $id, bool $active): ?Settings
    {
        $record = $this->find($id);

        if (! $record) {
            return null;
        }

        $record->update(['active' => $active ? 1 : 0]);

        return $record;
    }

    /**
     * Validate discount min/max constraint.
     */
    public function validateDiscountRange(int $id, float $min, float $max): bool
    {
        $record = Settings::find($id);

        return ! ($record?->slug === 'sys-discounts' && $min > $max);
    }

    /**
     * Format a setting's data field for display in datatable.
     */
    public function formatForDisplay(Settings $setting): string
    {
        if (empty($setting->data)) {
            return $setting->data ?? '';
        }

        return match ($setting->slug) {
            'sys-discounts' => $this->formatMinMax($setting->data, 'Min: %s%%, Max: %s%%'),
            'sys-birthdaypromotion' => $this->formatMinMax($setting->data, 'Pre Days: %s, Post Days: %s'),
            'sys-list-mode' => config('constants.listing_array')[$setting->data] ?? $setting->data,
            'sys-back-date-appointment' => $setting->data == 0 ? 'Disabled' : 'Enabled',
            'sys-current-sms-operator' => config("constants.operator_array.{$setting->data}") ?? $setting->data,
            'sys-consultancy-invoice-medical-operator' => config("constants.invoice_consultancy_medical_form.{$setting->data}") ?? $setting->data,
            'sys-virtual-consultancy' => config("constants.consultancy_type.{$setting->data}") ?? $setting->data,
            'sys-tax-treatment' => config("constants.tax_treatment_array.{$setting->data}") ?? $setting->data,
            default => $setting->data,
        };
    }

    /**
     * Resolve field type and extra metadata for editing a setting.
     *
     * @return array{field_type: string, extra: array}
     */
    public function resolveFieldMeta(Settings $setting): array
    {
        return match ($setting->slug) {
            'sys-discounts' => [
                'field_type' => 'minmax',
                'extra' => $this->parseColonPair($setting->data, 'min', 'max'),
            ],
            'sys-birthdaypromotion' => [
                'field_type' => 'prepost',
                'extra' => $this->parseColonPair($setting->data, 'pre', 'post'),
            ],
            'sys-list-mode' => [
                'field_type' => 'select',
                'extra' => ['list' => config('constants.listing_array')],
            ],
            'sys-back-date-appointment' => [
                'field_type' => 'select',
                'extra' => ['list' => (object) ['Disabled', 'Enabled']],
            ],
            'sys-current-sms-operator' => [
                'field_type' => 'select',
                'extra' => ['list' => config('constants.operator_array')],
            ],
            'sys-consultancy-invoice-medical-operator' => [
                'field_type' => 'select',
                'extra' => ['list' => config('constants.invoice_consultancy_medical_form')],
            ],
            'sys-virtual-consultancy' => [
                'field_type' => 'select',
                'extra' => ['list' => config('constants.consultancy_type')],
            ],
            'sys-tax-treatment' => [
                'field_type' => 'select',
                'extra' => ['list' => config('constants.tax_treatment_array')],
            ],
            default => [
                'field_type' => 'text',
                'extra' => [],
            ],
        };
    }

    /**
     * Get user's active filters for the settings module.
     */
    public function getActiveFilters(): array
    {
        return Filters::all(Auth::id(), self::FILTER_KEY);
    }

    /**
     * Get current user's permissions for settings.
     */
    public function getPermissions(): array
    {
        return [
            'edit' => Gate::allows('settings_edit'),
        ];
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function buildFilteredQuery(array $params): \Illuminate\Database\Eloquent\Builder
    {
        $accountId = Auth::user()->account_id;
        $userId = (int) Auth::id();
        $applyFilter = $params['apply_filter'] ?? false;

        $where = $this->buildWhereConditions($params, $userId, $accountId, $applyFilter);

        return Settings::query()
            ->when(!empty($where), fn ($q) => $q->where($where));
    }

    private function buildWhereConditions(array $params, int $userId, ?int $accountId, bool $applyFilter): array
    {
        $where = [];

        // Account scope
        if ($accountId) {
            $where[] = ['account_id', '=', $accountId];
            Filters::put($userId, self::FILTER_KEY, 'account_id', $accountId);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'account_id');
        } elseif ($stored = Filters::get($userId, self::FILTER_KEY, 'account_id')) {
            $where[] = ['account_id', '=', $stored];
        }

        // Name filter
        if (! empty($params['name'])) {
            $where[] = ['name', 'like', '%' . $params['name'] . '%'];
            Filters::put($userId, self::FILTER_KEY, 'setting_name', $params['name']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'setting_name');
        } elseif ($stored = Filters::get($userId, self::FILTER_KEY, 'setting_name')) {
            $where[] = ['name', 'like', '%' . $stored . '%'];
        }

        // Data filter
        if (! empty($params['data'])) {
            $where[] = ['data', 'like', '%' . $params['data'] . '%'];
            Filters::put($userId, self::FILTER_KEY, 'setting_data', $params['data']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'setting_data');
        } elseif ($stored = Filters::get($userId, self::FILTER_KEY, 'setting_data')) {
            $where[] = ['data', 'like', '%' . $stored . '%'];
        }

        return $where;
    }

    /**
     * Resolve the `data` column value based on slug-specific rules.
     */
    private function resolveDataValue(string $slug, array $input): string
    {
        return match ($slug) {
            'sys-discounts' => implode(':', [$input['min'] ?? '0', $input['max'] ?? '0']),
            'sys-birthdaypromotion' => implode(':', [$input['pre'] ?? '0', $input['post'] ?? '0']),
            default => (string) ($input['data'] ?? ''),
        };
    }

    private function formatMinMax(string $data, string $format): string
    {
        $parts = explode(':', $data);

        return sprintf($format, $parts[0] ?? '0', $parts[1] ?? '0');
    }

    private function parseColonPair(string $data, string $keyA, string $keyB): array
    {
        $parts = explode(':', $data);

        return [
            $keyA => $parts[0] ?? '0',
            $keyB => $parts[1] ?? '0',
        ];
    }
}
