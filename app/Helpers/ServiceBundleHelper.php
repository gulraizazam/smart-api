<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\PackageBundles;
use App\Models\ServiceBundle;
use App\Models\Services;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class ServiceBundleHelper
{
    public const CACHE_TTL = 3600;

    /**
     * Get active service bundles with caching.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getActiveServiceBundles(): array
    {
        $accountId = Auth::user()->account_id;
        $cacheKey = "active_service_bundles_{$accountId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => ServiceBundle::where('account_id', $accountId)
            ->where('active', 1)
            ->with('service:id,name,price,parent_id')
            ->orderBy('sort_number', 'asc')
            ->get()
            ->toArray()
        );
    }

    /**
     * Clear service bundle caches.
     */
    public static function clearCache(): void
    {
        $accountId = Auth::user()->account_id;

        Cache::forget("active_service_bundles_{$accountId}");
        Cache::forget("service_bundle_services_{$accountId}");
    }

    /**
     * Check if a service bundle has child records (used in plans) that prevent deletion.
     */
    public static function hasChildRecords(int $bundleId): bool
    {
        return PackageBundles::where('bundle_id', $bundleId)
            ->where('source_type', 'service_bundle')
            ->exists();
    }

    /**
     * Get active child services (end_node) for dropdowns, grouped by category.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getServicesForDropdown(): array
    {
        $accountId = Auth::user()->account_id;
        $cacheKey = "service_bundle_services_{$accountId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($accountId): array {
            return Services::where('account_id', $accountId)
                ->where('active', 1)
                ->where('end_node', 1)
                ->with('parent:id,name')
                ->select('id', 'name', 'price', 'parent_id')
                ->orderBy('name')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get permissions for datatable.
     *
     * @return array<string, bool>
     */
    public static function getPermissions(): array
    {
        return [
            'edit'     => Gate::allows('bundles.edit'),
            'delete'   => Gate::allows('bundles.destroy'),
            'active'   => Gate::allows('bundles.activate'),
            'inactive' => Gate::allows('bundles.deactivate'),
            'details'  => Gate::allows('bundles.detail.view'),
        ];
    }

    /**
     * Get filter values for datatable.
     *
     * @return array<string, mixed>
     */
    public static function getFilterValues(): array
    {
        return [
            'status'     => config('constants.status', ['1' => 'Active', '0' => 'Inactive']),
            'categories' => self::getCategories(),
            'services'   => self::getServicesForDropdown(),
        ];
    }

    /**
     * Get parent service categories for filter dropdown.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function getCategories(): array
    {
        $accountId = Auth::user()->account_id;

        return Services::where('account_id', $accountId)
            ->where('active', 1)
            ->parentsOnly()
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();
    }
}
