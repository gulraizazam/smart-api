<?php

declare(strict_types=1);

namespace App\Services\CashFlow;

use App\Exceptions\CashflowException;
use App\Helpers\ActivityLogger;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\PeriodLock;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PeriodLockService
{
    public function __construct(
        private readonly CashflowAuditService $auditService,
        private readonly CashflowSettingService $settingService,
    ) {}

    /**
     * Get all period locks for an account.
     */
    public function getLocks(int $accountId): array
    {
        return PeriodLock::forAccount($accountId)
            ->with(['lockedByUser:id,name', 'unlockedByUser:id,name'])
            ->orderByRaw('year DESC, month DESC')
            ->get()
            ->toArray();
    }

    /**
     * Lock a period (month/year).
     * Rules: sequential, current month never lockable, snapshot pool balances.
     */
    public function lockPeriod(int $month, int $year, int $accountId): PeriodLock
    {
        // Current month check
        $now = Carbon::now();
        if ($month === $now->month && $year === $now->year) {
            throw CashflowException::validationFailed('Current month cannot be locked.');
        }

        // Future month check
        $periodDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        if ($periodDate->isFuture()) {
            throw CashflowException::validationFailed('Cannot lock a future period.');
        }

        // Already locked check
        if (PeriodLock::isLocked($accountId, $month, $year)) {
            throw CashflowException::validationFailed('This period is already locked.');
        }

        // Sequential check: previous month must be locked (unless this is the first lock ever)
        $prevMonth = $month === 1 ? 12 : $month - 1;
        $prevYear = $month === 1 ? $year - 1 : $year;

        $hasAnyLock = PeriodLock::hasAnyLock($accountId);
        if ($hasAnyLock && ! PeriodLock::isLocked($accountId, $prevMonth, $prevYear)) {
            $prevLabel = Carbon::createFromDate($prevYear, $prevMonth, 1)->format('F Y');
            throw CashflowException::validationFailed("Cannot lock — {$prevLabel} must be locked first (sequential locking).");
        }

        return DB::transaction(function () use ($month, $year, $accountId) {
            // Snapshot all pool balances
            $pools = CashPool::forAccount($accountId)->get();
            $snapshot = [];
            foreach ($pools as $pool) {
                $snapshot[] = [
                    'pool_id' => $pool->id,
                    'pool_name' => $pool->name,
                    'pool_type' => $pool->type,
                    'balance' => (float) $pool->cached_balance,
                ];
            }

            $lock = PeriodLock::create([
                'account_id' => $accountId,
                'month' => $month,
                'year' => $year,
                'locked_by' => Auth::id(),
                'balance_snapshot' => $snapshot,
            ]);

            // After FIRST lock: freeze opening balances + go-live date
            $this->freezeIfFirstLock($accountId);

            // Audit log — CashflowAuditService::log signature is
            // (action, entityType, entityId, ?array $oldValues, ?array $newValues, ?string $reason).
            // $accountId is read from Auth inside log(); it's not a positional arg.
            $this->auditService->log(
                'locked',
                'period_lock',
                $lock->id,
                null,
                ['month' => $month, 'year' => $year, 'snapshot_pools' => count($snapshot)]
            );

            // Mirror to the unified activities feed so the audit lands in
            // both cashflow_audit_logs (detail) and activities (discoverable).
            try {
                ActivityLogger::logCashflowPeriodLocked(
                    periodLabel: sprintf('%04d-%02d', $year, $month),
                    action: 'locked'
                );
            } catch (\Throwable $e) {
                Log::warning('cashflow.period_lock audit mirror failed', [
                    'event' => 'activities.cashflow_period_locked.mirror_failed',
                    'error' => $e->getMessage(),
                ]);
            }

            return $lock;
        });
    }

    /**
     * Unlock a period (mandatory reason).
     */
    public function unlockPeriod(int $lockId, string $reason, int $accountId): PeriodLock
    {
        $lock = PeriodLock::forAccount($accountId)->find($lockId);

        if (! $lock) {
            throw CashflowException::notFound('Period lock');
        }

        if ($lock->unlocked_at) {
            throw CashflowException::validationFailed('This period is already unlocked.');
        }

        if (strlen(trim($reason)) < 5) {
            throw CashflowException::validationFailed('Unlock reason must be at least 5 characters.');
        }

        return DB::transaction(function () use ($lock, $reason) {
            $oldValues = $lock->toArray();

            $lock->update([
                'unlock_reason' => $reason,
                'unlocked_by' => Auth::id(),
                'unlocked_at' => now(),
            ]);

            // Audit log — see lockPeriod above; $accountId is sourced from Auth.
            $this->auditService->log(
                'unlocked',
                'period_lock',
                $lock->id,
                $oldValues,
                ['unlock_reason' => $reason, 'month' => $lock->month, 'year' => $lock->year]
            );

            // Mirror to the unified activities feed (see lockPeriod above).
            try {
                ActivityLogger::logCashflowPeriodLocked(
                    periodLabel: sprintf('%04d-%02d', $lock->year, $lock->month),
                    action: 'unlocked',
                    reason: $reason
                );
            } catch (\Throwable $e) {
                Log::warning('cashflow.period_unlock audit mirror failed', [
                    'event' => 'activities.cashflow_period_unlocked.mirror_failed',
                    'error' => $e->getMessage(),
                ]);
            }

            return $lock->fresh();
        });
    }

    /**
     * Check if a date is in a locked period.
     */
    public function isDateLocked(string $date, int $accountId): bool
    {
        $carbon = Carbon::parse($date);
        $lock = PeriodLock::forAccount($accountId)
            ->where('month', $carbon->month)
            ->where('year', $carbon->year)
            ->whereNull('unlocked_at')
            ->exists();

        return $lock;
    }

    /**
     * After first-ever lock: freeze opening balances and go-live date.
     */
    private function freezeIfFirstLock(int $accountId): void
    {
        // Check if this is the first lock (count = 1 after create)
        $lockCount = PeriodLock::forAccount($accountId)->count();
        if ($lockCount !== 1) {
            return; // Not the first lock
        }

        // Mark settings as frozen
        $this->settingService->set('opening_balances_frozen', '1', $accountId, 'Frozen after first period lock');
        $this->settingService->set('go_live_date_frozen', '1', $accountId, 'Frozen after first period lock');

        // Audit — see lockPeriod above; $accountId is sourced from Auth.
        $this->auditService->log(
            'updated',
            'settings',
            0,
            null,
            ['action' => 'Opening balances and go-live date frozen after first period lock']
        );
    }
}
