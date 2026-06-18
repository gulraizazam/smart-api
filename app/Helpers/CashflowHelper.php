<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\ExpenseCategory;
use App\Models\CashFlow\PeriodLock;
use App\Models\CashFlow\Vendor;
use App\Models\DoctorHasLocations;
use App\Models\Locations;
use App\Models\PaymentModes;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class CashflowHelper
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get active branches (locations) the current user has access to.
     */
    public static function getUserBranches(?int $userId = null): Collection
    {
        $userId = $userId ?? Auth::id();
        $user = User::with('user_has_locations')->find($userId);

        if (! $user) {
            return collect();
        }

        $accountId = (int) $user->account_id;

        // If user has the select_all flag, return all active real locations.
        if ($user->select_all) {
            return Locations::where('active', 1)
                ->where('account_id', $accountId)
                ->where('name', '!=', 'All Centres')
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $locationIds = array_map('intval', $user->user_has_locations->pluck('location_id')->toArray());

        // Doctors are assigned clinics via doctor_has_locations, not
        // user_has_locations — fall back so an assigned doctor still resolves
        // to their branch (the FDM cash view 403s on an empty branch set).
        if ($locationIds === []) {
            $locationIds = DoctorHasLocations::allocatedLocationIds((int) $user->id);
        }

        $allCentresId = (int) Config::get('constants.all_centres_location_id');

        // If the pivot grants the virtual "All Centres" location, expand to
        // every real active centre for the account — branch queries must run
        // against real ids, not the virtual rollup.
        if ($allCentresId !== 0 && in_array($allCentresId, $locationIds, true)) {
            return Locations::where('active', 1)
                ->where('account_id', $accountId)
                ->where('name', '!=', 'All Centres')
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return Locations::whereIn('id', $locationIds)
            ->where('active', 1)
            ->where('name', '!=', 'All Centres')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Get all active branches for an account (cached).
     */
    public static function getActiveBranches(int $accountId): Collection
    {
        return Cache::remember(
            "cashflow_branches_{$accountId}",
            self::CACHE_TTL,
            fn () => Locations::where('account_id', $accountId)
                ->where('active', 1)
                ->where('name', '!=', 'All Centres')
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    /**
     * Get active cash pools for an account (cached).
     */
    public static function getActivePools(int $accountId): Collection
    {
        return Cache::remember(
            "cashflow_pools_{$accountId}",
            self::CACHE_TTL,
            fn () => CashPool::forAccount($accountId)
                ->active()
                ->with('location:id,name')
                ->orderByRaw("CASE WHEN type = 'bank_account' THEN 1 ELSE 0 END")
                ->orderBy('name')
                ->get()
        );
    }

    /**
     * Get active expense categories for an account (cached).
     */
    public static function getActiveCategories(int $accountId): Collection
    {
        return Cache::remember(
            "cashflow_categories_{$accountId}",
            self::CACHE_TTL,
            fn () => ExpenseCategory::forAccount($accountId)
                ->active()
                ->sorted()
                ->get(['id', 'name', 'vendor_emphasis'])
        );
    }

    /**
     * Get active vendors for an account (cached).
     */
    public static function getActiveVendors(int $accountId): Collection
    {
        return Cache::remember(
            "cashflow_vendors_{$accountId}",
            self::CACHE_TTL,
            fn () => Vendor::forAccount($accountId)
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'cached_balance'])
        );
    }

    /**
     * Get active payment modes (reusing existing CRM model).
     */
    public static function getActivePaymentModes(): Collection
    {
        return PaymentModes::getActiveOnly()->filter(fn ($mode) => (int) $mode->payment_type !== 6)->values();
    }

    /**
     * Get advance-eligible staff for an account.
     */
    public static function getAdvanceEligibleStaff(int $accountId): Collection
    {
        return User::where('account_id', $accountId)
            ->where('active', 1)
            ->where('is_advance_eligible', 1)
            ->whereNotIn('user_type_id', [3])
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Resolve the [from, to] pair for cashflow report filters. Defaults to
     * the current month (start-of-month → today) when either filter key
     * is absent. Matches the convention used across DashboardService,
     * ReportService, and VendorService.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: string}
     */
    public static function defaultDateRange(array $filters): array
    {
        return [
            $filters['date_from'] ?? Carbon::now()->startOfMonth()->toDateString(),
            $filters['date_to'] ?? Carbon::now()->toDateString(),
        ];
    }

    /**
     * Check if a date falls within a locked period.
     */
    public static function isDateInLockedPeriod(string $date, int $accountId): bool
    {
        $carbon = Carbon::parse($date);

        return PeriodLock::isLocked($accountId, $carbon->month, $carbon->year);
    }

    /**
     * Check if the current user can access a specific branch.
     */
    public static function userCanAccessBranch(int $branchId, ?int $userId = null): bool
    {
        $branches = self::getUserBranches($userId);

        return $branches->contains('id', $branchId);
    }

    /**
     * Clear all cashflow-related caches for an account.
     */
    public static function clearAllCaches(int $accountId): void
    {
        Cache::forget("cashflow_branches_{$accountId}");
        Cache::forget("cashflow_pools_{$accountId}");
        Cache::forget("cashflow_categories_{$accountId}");
        Cache::forget("cashflow_vendors_{$accountId}");
        Cache::forget("cashflow_settings_{$accountId}");
    }

    /**
     * Format currency amount (PKR).
     */
    public static function formatCurrency($amount): string
    {
        return 'PKR '.number_format((float) $amount, 2);
    }
}
