<?php

namespace App\Helpers;

use App\Models\Services;
use App\Models\TaxTreatmentType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class ServiceHelper
{
    /**
     * Cache TTL in seconds (1 hour)
     */
    public const CACHE_TTL = 3600;

    /**
     * Get parent services for dropdown (cached)
     */
    public static function getParentServices(int $accountId): array
    {
        $cacheKey = "services_parent_list_{$accountId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($accountId) {
            return Services::where('parent_id', 0)
                ->where('account_id', $accountId)
                ->where('slug', '!=', 'all')
                ->where('active', 1)
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'color'])
                ->toArray();
        });
    }

    /**
     * Default tax treatment type ID (3 = Is Inclusive)
     */
    public const DEFAULT_TAX_TREATMENT_TYPE = 3;

    /**
     * Get tax treatment types (cached) - excludes "Both" option (ID 1)
     */
    public static function getTaxTreatmentTypes(): array
    {
        return Cache::remember('tax_treatment_types_filtered', self::CACHE_TTL, function () {
            return TaxTreatmentType::where('id', '!=', 1)->get(['id', 'name'])->toArray();
        });
    }

    /**
     * Get duration options
     */
    public static function getDurations(): array
    {
        return Cache::remember('service_durations', self::CACHE_TTL * 24, function () {
            $durations = [];
            for ($hour = 0; $hour <= 4; $hour++) {
                for ($min = 0; $min < 60; $min += 15) {
                    if ($hour == 0 && $min == 0) {
                        continue;
                    }
                    $durations[] = sprintf('%02d:%02d', $hour, $min);
                }
            }
            return $durations;
        });
    }

    /**
     * Clear service-related caches
     */
    public static function clearCache(int $accountId): void
    {
        Cache::forget("services_parent_list_{$accountId}");
        Cache::forget("services_list_{$accountId}");
        Cache::forget("services_tree_{$accountId}");
        Cache::forget("tax_treatment_types");
        Cache::forget("tax_treatment_types_filtered");
    }

    /**
     * Get permissions for datatable
     */
    public static function getPermissions(): array
    {
        return [
            'edit' => Gate::allows('services_edit'),
            'delete' => Gate::allows('services_destroy'),
            'active' => Gate::allows('services_active'),
            'inactive' => Gate::allows('services_inactive'),
            'create' => Gate::allows('services_create'),
            'sort' => Gate::allows('services_sort'),
            'duplicate' => Gate::allows('services_duplicate'),
            'detail' => Gate::allows('services_detail'),
        ];
    }

    /**
     * Check if user can view inactive services
     */
    public static function canViewInactive(): bool
    {
        return Gate::allows('view_inactive_services');
    }

    /**
     * Prepare service data for storage
     */
    public static function prepareServiceData(array $data, int $accountId): array
    {
        $data['account_id'] = $accountId;
        $data['duration'] = $data['duration'] ?? '00:00';
        $data['price'] = $data['price'] ?? 0.0;
        $data['end_node'] = isset($data['end_node']) && $data['end_node'] ? 1 : 0;
        $data['complimentory'] = isset($data['complimentory']) && $data['complimentory'] ? 1 : 0;
        // Default to Is Inclusive if not set or if Both (ID 1) was selected
        $data['tax_treatment_type_id'] = (isset($data['tax_treatment_type_id']) && $data['tax_treatment_type_id'] != 1) 
            ? $data['tax_treatment_type_id'] 
            : self::DEFAULT_TAX_TREATMENT_TYPE;

        return $data;
    }

    /**
     * Get service color from parent
     */
    public static function getParentColor(int $parentId): ?string
    {
        if ($parentId <= 0) {
            return null;
        }

        $parent = Services::find($parentId, ['color']);
        return $parent ? $parent->color : null;
    }
}
