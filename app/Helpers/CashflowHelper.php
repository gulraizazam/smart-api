<?php

namespace App\Helpers;

use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\ExpenseCategory;
use App\Models\CashFlow\PeriodLock;
use App\Models\CashFlow\Vendor;
use App\Models\Locations;
use App\Models\PaymentModes;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CashflowHelper
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get active branches (locations) the current user has access to.
     */
    public static function getUserBranches(?int $userId = null): \Illuminate\Support\Collection
    {
        $userId = $userId ?? Auth::id();
        $user = User::with('user_has_locations')->find($userId);

        if (!$user) {
            return collect();
        }

        // If user has the select_all flag, return all active locations
        if ($user->select_all) {
            return Locations::where('active', 1)
                ->where('account_id', $user->account_id)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $locationIds = $user->user_has_locations->pluck('location_id')->toArray();

        return Locations::whereIn('id', $locationIds)
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Get all active branches for an account (cached).
     */
    public static function getActiveBranches(int $accountId): \Illuminate\Support\Collection
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
    public static function getActivePools(int $accountId): \Illuminate\Support\Collection
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
    public static function getActiveCategories(int $accountId): \Illuminate\Support\Collection
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
    public static function getActiveVendors(int $accountId): \Illuminate\Support\Collection
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
    public static function getActivePaymentModes(): \Illuminate\Support\Collection
    {
        return PaymentModes::getActiveOnly()->filter(function ($mode) {
            return (int) $mode->payment_type !== 6; // Exclude 'Settle Amount' — billing-only mode
        })->values();
    }

    /**
     * Get advance-eligible staff for an account.
     */
    public static function getAdvanceEligibleStaff(int $accountId): \Illuminate\Support\Collection
    {
        return User::where('account_id', $accountId)
            ->where('active', 1)
            ->where('is_advance_eligible', 1)
            ->whereNotIn('user_type_id', [3])
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Check if a date falls within a locked period.
     */
    public static function isDateInLockedPeriod(string $date, int $accountId): bool
    {
        $carbon = \Carbon\Carbon::parse($date);
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
        return 'PKR ' . number_format((float) $amount, 2);
    }
}
