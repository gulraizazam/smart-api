<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Bundles;
use App\Models\PackageBundles;
use App\Models\ServiceBundle;
use App\Models\Services;
use App\Models\TaxTreatmentType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class BundleHelper
{
    public const CACHE_TTL = 3600; // 1 hour
    public const DEFAULT_TAX_TREATMENT_TYPE = 2;

    /**
     * Get services list with caching.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getServices(): array
    {
        $accountId = Auth::user()->account_id;
        $cacheKey = "bundle_services_{$accountId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => Services::where('account_id', $accountId)
            ->where('active', 1)
            ->select('id', 'name', 'price', 'end_node')
            ->orderBy('name')
            ->get()
            ->toArray()
        );
    }

    /**
     * Get services as key-value pairs for dropdowns.
     *
     * @return array<int, string>
     */
    public static function getServicesForDropdown(): array
    {
        $accountId = Auth::user()->account_id;
        $cacheKey = "bundle_services_dropdown_{$accountId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => Services::where('account_id', $accountId)
            ->where('active', 1)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray()
        );
    }

    /**
     * Get tax treatment types with caching.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getTaxTreatmentTypes(): array
    {
        return Cache::remember('bundle_tax_treatment_types', self::CACHE_TTL, fn () => TaxTreatmentType::select('id', 'name')
            ->whereNot('id', 1)
            ->get()
            ->toArray()
        );
    }

    /**
     * Get status options.
     *
     * @return array<string, string>
     */
    public static function getStatusOptions(): array
    {
        return config('constants.status', [
            '1' => 'Active',
            '0' => 'Inactive',
        ]);
    }

    /**
     * Clear bundle-related caches.
     */
    public static function clearCache(): void
    {
        $accountId = Auth::user()->account_id;

        Cache::forget("bundle_services_{$accountId}");
        Cache::forget("bundle_services_dropdown_{$accountId}");
        Cache::forget('bundle_tax_treatment_types');
        Cache::forget("active_bundles_{$accountId}");
        Cache::forget("bundle_form_service_bundles_{$accountId}");
    }

    /**
     * Get active bundles with caching.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getActiveBundles(): array
    {
        $accountId = Auth::user()->account_id;
        $cacheKey = "active_bundles_{$accountId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($accountId): array {
            $date = Carbon::now();

            return Bundles::where('account_id', $accountId)
                ->where('active', 1)
                ->where('type', '!=', 'single')
                ->where(fn ($query) => $query->whereNull('start')->orWhere('start', '<=', $date))
                ->where(fn ($query) => $query->whereNull('end')->orWhere('end', '>=', $date))
                ->orderBy('sort_number', 'asc')
                ->get()
                ->toArray();
        });
    }

    /**
     * Calculate proportional prices for bundle services.
     *
     * @param  array<int, array{service_price: float, calculated_price: float}>  $services
     * @return array<int, array{service_price: float, calculated_price: float}>
     */
    public static function calculatePrices(array $services, float $servicesPrice, float $bundlePrice): array
    {
        if ($servicesPrice == 0) {
            return $services;
        }

        if ($servicesPrice == $bundlePrice) {
            foreach ($services as $key => $service) {
                $services[$key]['calculated_price'] = $services[$key]['service_price'];
            }
        } elseif ($servicesPrice > $bundlePrice) {
            $ratio = 1 - round($bundlePrice / $servicesPrice, 8);
            foreach ($services as $key => $service) {
                $services[$key]['calculated_price'] = round(
                    $services[$key]['service_price'] - ($services[$key]['service_price'] * $ratio),
                    2
                );
            }
        } else {
            $ratio = -1 * (1 - round($bundlePrice / $servicesPrice, 8));
            foreach ($services as $key => $service) {
                $services[$key]['calculated_price'] = round(
                    $services[$key]['service_price'] + ($services[$key]['service_price'] * $ratio),
                    2
                );
            }
        }

        return $services;
    }

    /**
     * Check if bundle has child records that prevent deletion.
     *
     * The block reason is `package_bundles` — once a bundle is sold on
     * a plan instance, refund/audit history depends on the bundle row
     * remaining intact. Appointments are NOT a guard here: the
     * `appointments` table tracks services (`service_id`), not bundles,
     * and the legacy `Appointments::where('bundle_id', ...)` query
     * referenced a column that no longer exists, throwing a
     * `QueryException` that masked the real reason behind a generic
     * 500. `$accountId` is kept in the signature for call-site
     * compatibility but is now unused.
     */
    public static function hasChildRecords(int $bundleId, int $accountId): bool
    {
        unset($accountId);
        return PackageBundles::where('bundle_id', $bundleId)->exists();
    }

    /**
     * Validate date range.
     */
    public static function isValidDateRange(?string $start, ?string $end): bool
    {
        if (empty($start) || empty($end)) {
            return true;
        }

        return strtotime($start) <= strtotime($end);
    }

    /**
     * Calculate total services price from service prices array.
     *
     * @param  array<int, int>     $serviceIds
     * @param  array<int, float>   $servicePrices
     */
    public static function calculateTotalServicesPrice(array $serviceIds, array $servicePrices): float
    {
        return array_sum(array_map(fn (mixed $price): float => (float) $price, $servicePrices));
    }

    /**
     * Get active service bundles for the package form dropdown.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getServiceBundles(): array
    {
        $accountId = Auth::user()->account_id;
        $cacheKey = "bundle_form_service_bundles_{$accountId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => ServiceBundle::where('account_id', $accountId)
            ->where('active', 1)
            ->with('service:id,name,price,parent_id', 'service.parent:id,name')
            ->orderBy('sort_number', 'asc')
            ->get()
            ->map(fn ($sb) => [
                'id'            => $sb->id,
                'name'          => $sb->sessions . 'x ' . ($sb->service?->name ?? 'Unknown'),
                'service_id'    => $sb->service_id,
                'service_name'  => $sb->service?->name ?? '',
                'service_price' => (float) ($sb->service?->price ?? 0),
                'sessions'      => $sb->sessions,
                'category'      => $sb->service?->parent?->name ?? 'Uncategorized',
            ])
            ->toArray()
        );
    }

    /**
     * Get filter values for datatable.
     *
     * @return array<string, mixed>
     */
    public static function getFilterValues(): array
    {
        return [
            'status'              => self::getStatusOptions(),
            'tax_treatment_types' => self::getTaxTreatmentTypes(),
            'services'            => self::getServices(),
            'service_bundles'     => self::getServiceBundles(),
        ];
    }

    /**
     * Get bundle permissions for datatable.
     *
     * @return array<string, bool>
     */
    public static function getPermissions(): array
    {
        return [
            'edit'     => Gate::allows('packages.edit'),
            'delete'   => Gate::allows('packages.destroy'),
            'active'   => Gate::allows('packages.activate'),
            'inactive' => Gate::allows('packages.deactivate'),
            'details'  => Gate::allows('packages.detail.view'),
            'create'   => Gate::allows('packages.create'),
            'sort'     => Gate::allows('packages.sort'),
        ];
    }
}
