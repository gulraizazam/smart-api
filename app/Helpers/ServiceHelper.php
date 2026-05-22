<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Services;
use App\Models\TaxTreatmentType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

final class ServiceHelper
{
    public const CACHE_TTL = 3600;

    public const DEFAULT_TAX_TREATMENT_TYPE = 3;

    /**
     * Get parent services for dropdown (cached).
     *
     * @return array<int, array{id: int, name: string, color: string|null}>
     */
    public static function getParentServices(int $accountId): array
    {
        $cacheKey = "services_parent_list_{$accountId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, fn (): array => Services::parentsOnly()
            ->forAccount($accountId)
            ->isActive()
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'color'])
            ->toArray()
        );
    }

    /**
     * Get tax treatment types (cached) — excludes "Both" option (ID 1).
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function getTaxTreatmentTypes(): array
    {
        return Cache::remember('tax_treatment_types_filtered', self::CACHE_TTL, fn (): array => TaxTreatmentType::where('id', '!=', 1)
            ->get(['id', 'name'])
            ->toArray()
        );
    }

    /**
     * Get duration options in HH:MM format (5-minute intervals up to 04:55).
     *
     * @return array<int, string>
     */
    public static function getDurations(): array
    {
        return Cache::remember('service_durations', self::CACHE_TTL * 24, function (): array {
            $durations = [];

            for ($hour = 0; $hour <= 4; $hour++) {
                for ($min = 0; $min < 60; $min += 5) {
                    if ($hour === 0 && $min === 0) {
                        continue;
                    }
                    $durations[] = sprintf('%02d:%02d', $hour, $min);
                }
            }

            return $durations;
        });
    }

    /**
     * Clear service-related caches for the given account.
     */
    public static function clearCache(int $accountId): void
    {
        Cache::forget("services_parent_list_{$accountId}");
        Cache::forget("services_list_{$accountId}");
        Cache::forget("services_tree_{$accountId}");
        Cache::forget('tax_treatment_types');
        Cache::forget('tax_treatment_types_filtered');
    }

    /**
     * Get datatable permissions for the current user.
     *
     * @return array<string, bool>
     */
    public static function getPermissions(): array
    {
        return [
            'edit'      => Gate::allows('services.edit'),
            'delete'    => Gate::allows('services.destroy'),
            'active'    => Gate::allows('services.activate'),
            'inactive'  => Gate::allows('services.deactivate'),
            'create'    => Gate::allows('services.create'),
            'sort'      => Gate::allows('services.sort'),
            'export'    => Gate::allows('services.export'),
            'duplicate' => Gate::allows('services.duplicate'),
            'detail'    => Gate::allows('services.detail.view'),
        ];
    }

    /**
     * Check if current user can view inactive services.
     */
    public static function canViewInactive(): bool
    {
        return Gate::allows('services.list.view_inactive');
    }

    /**
     * Prepare service data for storage (set defaults, handle inheritance).
     *
     * @return array<string, mixed>
     */
    public static function prepareServiceData(array $data, int $accountId): array
    {
        $data['account_id'] = $accountId;
        $data['duration'] = $data['duration'] ?? '00:00';
        $data['price'] = $data['price'] ?? 0.0;
        $data['end_node'] = ! empty($data['end_node']) ? 1 : 0;
        $data['complimentory'] = ! empty($data['complimentory']) ? 1 : 0;
        $data['tax_treatment_type_id'] = (isset($data['tax_treatment_type_id']) && $data['tax_treatment_type_id'] != 1)
            ? $data['tax_treatment_type_id']
            : self::DEFAULT_TAX_TREATMENT_TYPE;

        // Coerce parent_id at the persistence seam. The legacy form
        // convention is "0 = make this a parent category" (and some flows
        // submit '' for the same intent), but `services.parent_id` is a
        // nullable self-ref FK — 0 / '' violate the constraint because
        // no `services.id = 0` row exists, and MariaDB rejects '' as an
        // integer. Normalise both to NULL so a parent service persists
        // cleanly and child rows still get their real parent id.
        if (array_key_exists('parent_id', $data)) {
            $parentId = $data['parent_id'];

            if ($parentId === null || $parentId === '' || $parentId === 0 || $parentId === '0') {
                $data['parent_id'] = null;
            } else {
                $data['parent_id'] = (int) $parentId;
            }
        }

        return $data;
    }

    /**
     * Get service color from parent.
     */
    public static function getParentColor(int $parentId): ?string
    {
        if ($parentId <= 0) {
            return null;
        }

        return Services::where('id', $parentId)->value('color');
    }
}
