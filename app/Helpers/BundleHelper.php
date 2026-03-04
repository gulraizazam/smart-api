<?php

namespace App\Helpers;

use App\Models\Bundles;
use App\Models\Services;
use App\Models\TaxTreatmentType;
use App\Models\PackageBundles;
use App\Models\Appointments;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class BundleHelper
{
    const CACHE_TTL = 3600; // 1 hour
    const DEFAULT_TAX_TREATMENT_TYPE = 1;

    /**
     * Get services list with caching
     */
    public static function getServices(): array
    {
        $accountId = Auth::user()->account_id;
        $cacheKey = "bundle_services_{$accountId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($accountId) {
            return Services::where('account_id', $accountId)
                ->where('active', 1)
                ->select('id', 'name', 'price', 'end_node')
                ->orderBy('name')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get services as key-value pairs for dropdowns
     */
    public static function getServicesForDropdown(): array
    {
        $accountId = Auth::user()->account_id;
        $cacheKey = "bundle_services_dropdown_{$accountId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($accountId) {
            return Services::where('account_id', $accountId)
                ->where('active', 1)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        });
    }

    /**
     * Get tax treatment types with caching
     */
    public static function getTaxTreatmentTypes(): array
    {
        return Cache::remember('bundle_tax_treatment_types', self::CACHE_TTL, function () {
            return TaxTreatmentType::select('id', 'name')->get()->toArray();
        });
    }

    /**
     * Get status options
     */
    public static function getStatusOptions(): array
    {
        return config('constants.status', [
            '1' => 'Active',
            '0' => 'Inactive'
        ]);
    }

    /**
     * Clear bundle-related caches
     */
    public static function clearCache(): void
    {
        $accountId = Auth::user()->account_id;
        
        Cache::forget("bundle_services_{$accountId}");
        Cache::forget("bundle_services_dropdown_{$accountId}");
        Cache::forget('bundle_tax_treatment_types');
        Cache::forget("active_bundles_{$accountId}");
    }

    /**
     * Get active bundles with caching
     */
    public static function getActiveBundles(): array
    {
        $accountId = Auth::user()->account_id;
        $cacheKey = "active_bundles_{$accountId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($accountId) {
            $date = Carbon::now();
            
            return Bundles::where('account_id', $accountId)
                ->where('active', 1)
                ->where('type', '!=', 'single')
                ->where(function ($query) use ($date) {
                    $query->whereNull('start')
                        ->orWhere('start', '<=', $date);
                })
                ->where(function ($query) use ($date) {
                    $query->whereNull('end')
                        ->orWhere('end', '>=', $date);
                })
                ->orderBy('sort_number', 'asc')
                ->get()
                ->toArray();
        });
    }

    /**
     * Calculate proportional prices for bundle services
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
            $ratio = 1 - round(($bundlePrice / $servicesPrice), 8);
            foreach ($services as $key => $service) {
                $services[$key]['calculated_price'] = round(
                    $services[$key]['service_price'] - ($services[$key]['service_price'] * $ratio),
                    2
                );
            }
        } else {
            $ratio = -1 * (1 - round(($bundlePrice / $servicesPrice), 8));
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
     * Check if bundle has child records that prevent deletion
     */
    public static function hasChildRecords(int $bundleId, int $accountId): bool
    {
        // Check if bundle is used in any package bundles
        if (PackageBundles::where('bundle_id', $bundleId)->exists()) {
            return true;
        }

        // Check if bundle is used in any appointments
        if (Appointments::where('bundle_id', $bundleId)
            ->where('account_id', $accountId)
            ->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Format bundle data for datatable display
     */
    public static function formatForDatatable(Bundles $bundle): array
    {
        return [
            'id' => $bundle->id,
            'name' => $bundle->name,
            'price' => number_format($bundle->price, 2),
            'total_services' => $bundle->total_services,
            'apply_discount' => $bundle->apply_discount ? 'Yes' : 'No',
            'start' => $bundle->start ? Carbon::parse($bundle->start)->format('D M, j Y') : null,
            'end' => $bundle->end ? Carbon::parse($bundle->end)->format('D M, j Y') : null,
            'active' => $bundle->active,
            'created_at' => $bundle->created_at,
        ];
    }

    /**
     * Validate date range
     */
    public static function isValidDateRange(?string $start, ?string $end): bool
    {
        if (empty($start) || empty($end)) {
            return true; // Allow null dates
        }

        return strtotime($start) <= strtotime($end);
    }

    /**
     * Calculate total services price from service IDs
     */
    public static function calculateTotalServicesPrice(array $serviceIds, array $servicePrices): float
    {
        $total = 0.00;
        foreach ($servicePrices as $price) {
            $total += (float) $price;
        }
        return $total;
    }

    /**
     * Get filter values for datatable
     */
    public static function getFilterValues(): array
    {
        return [
            'status' => self::getStatusOptions(),
            'tax_treatment_types' => self::getTaxTreatmentTypes(),
            'services' => self::getServices(),
        ];
    }
}
