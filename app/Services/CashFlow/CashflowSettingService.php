<?php

namespace App\Services\CashFlow;

use App\Models\CashFlow\CashflowSetting;
use Illuminate\Support\Facades\Cache;

class CashflowSettingService
{
    private const CACHE_PREFIX = 'cashflow_settings_';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get a single setting value.
     */
    public function get(string $key, int $accountId, $default = null)
    {
        $all = $this->getAll($accountId);
        return $all[$key] ?? $default;
    }

    /**
     * Get all settings for an account (cached).
     */
    public function getAll(int $accountId): array
    {
        return Cache::remember(
            self::CACHE_PREFIX . $accountId,
            self::CACHE_TTL,
            fn () => CashflowSetting::getAllForAccount($accountId)
        );
    }

    /**
     * Set a single setting value and clear cache.
     */
    public function set(string $key, $value, int $accountId, ?string $description = null): void
    {
        CashflowSetting::setValue($key, $value, $accountId, $description);
        $this->clearCache($accountId);
    }

    /**
     * Update multiple settings at once.
     */
    public function updateMany(array $settings, int $accountId): void
    {
        // Go-live date frozen after first period lock (Sec 3.1)
        if (isset($settings['go_live_date'])) {
            $hasLocks = \App\Models\CashFlow\PeriodLock::where('account_id', $accountId)->exists();
            if ($hasLocks) {
                unset($settings['go_live_date']); // silently skip — frozen
            }
        }

        foreach ($settings as $key => $value) {
            CashflowSetting::setValue($key, $value, $accountId);
        }
        $this->clearCache($accountId);
    }

    /**
     * Get the go-live date.
     */
    public function getGoLiveDate(int $accountId): ?string
    {
        return $this->get('go_live_date', $accountId);
    }

    /**
     * Check if the module is configured (has go-live date).
     */
    public function isModuleConfigured(int $accountId): bool
    {
        return !empty($this->getGoLiveDate($accountId));
    }

    /**
     * Get the approval threshold.
     */
    public function getApprovalThreshold(int $accountId): float
    {
        return (float) $this->get('approval_threshold', $accountId, 10000);
    }

    /**
     * Clear the settings cache for an account.
     */
    public function clearCache(int $accountId): void
    {
        Cache::forget(self::CACHE_PREFIX . $accountId);
    }
}
