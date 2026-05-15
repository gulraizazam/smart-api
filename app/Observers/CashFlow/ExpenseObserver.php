<?php

declare(strict_types=1);
namespace App\Observers\CashFlow;

use App\Enums\ExpenseStatus;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\Vendor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Owns the pool-balance side effects of every Expense lifecycle event.
 *
 * Exceptions are NOT swallowed. If a pool update fails, the surrounding
 * `DB::transaction` rolls back the expense write too — better a 500 and
 * a clean state than a silent ledger drift.
 *
 * The reject/approve cycle is intentionally not a pool-impact event
 * (Model B / GAAP substance-over-form, 2026-05-14). Cash already left
 * the till at creation; status reflects record-validity, not whether
 * the spend occurred. Use `void` (separate flow) when the entry
 * shouldn't exist and the cash should be reversed.
 */
class ExpenseObserver
{
    public function created(Expense $expense): void
    {
        if (! $expense->paid_from_pool_id) {
            return;
        }
        $this->decrementPoolBalance($expense->paid_from_pool_id, $expense->amount);
        $this->bustPoolCache((int) $expense->account_id);
    }

    public function updated(Expense $expense): void
    {
        // Voided: ExpenseService::void() handles pool reversal directly.
        if ($expense->isDirty('voided_at') && $expense->voided_at !== null) {
            $this->bustPoolCache((int) $expense->account_id);
            return;
        }

        // Already-voided rows are immutable from a pool-balance standpoint.
        if ($expense->isVoided()) {
            return;
        }

        $amountChanged = $expense->isDirty('amount');
        $poolChanged = $expense->isDirty('paid_from_pool_id');

        if (! $amountChanged && ! $poolChanged) {
            return;
        }

        $oldPoolId = $expense->getOriginal('paid_from_pool_id');
        $newPoolId = $expense->paid_from_pool_id;
        if (! $oldPoolId && ! $newPoolId) {
            return;
        }
        $oldAmount = (float) $expense->getOriginal('amount');
        $newAmount = (float) $expense->amount;

        if ($poolChanged) {
            $this->incrementPoolBalance($oldPoolId, $oldAmount);
            $this->decrementPoolBalance($newPoolId, $newAmount);
            // No amounts / pool ids in logs — payment details belong
            // in the audit-trail table (cashflow_audit_logs), not log
            // files that ship to less-secure sinks. The expense_id is
            // sufficient to correlate from the audit row when needed.
            Log::info('CashFlow: Expense pool changed', [
                'expense_id' => $expense->id,
                'pool_changed' => true,
                'amount_changed' => $amountChanged,
            ]);
        } elseif ($amountChanged) {
            $diff = $newAmount - $oldAmount;
            if ($diff > 0) {
                $this->decrementPoolBalance($newPoolId, $diff);
            } elseif ($diff < 0) {
                $this->incrementPoolBalance($newPoolId, abs($diff));
            }
            Log::info('CashFlow: Expense amount adjusted', [
                'expense_id' => $expense->id,
                'direction' => $diff > 0 ? 'increase' : ($diff < 0 ? 'decrease' : 'no-change'),
            ]);
        }

        $this->bustPoolCache((int) $expense->account_id);
    }

    /**
     * Soft-delete safety net.
     *
     * `void()` is the user-facing reversal path. Soft-delete should
     * never reach an active row in normal operation, but any future
     * code path or admin tool calling `$expense->delete()` directly
     * would leak the pool balance unless we mirror the void refund.
     * Already-voided rows are no-ops — void() already refunded.
     */
    public function deleted(Expense $expense): void
    {
        if ($expense->voided_at !== null) {
            return;
        }

        if ($expense->paid_from_pool_id) {
            $this->incrementPoolBalance(
                (int) $expense->paid_from_pool_id,
                (float) $expense->amount,
            );
        }

        // Reverse the vendor transaction too — leaves the vendor's
        // outstanding balance consistent with the missing expense.
        $vendorTx = $expense->vendorTransaction;
        if ($vendorTx && $expense->vendor_id) {
            DB::table('cashflow_vendors')
                ->where('id', $expense->vendor_id)
                ->increment('cached_balance', $vendorTx->amount);
            $vendorTx->delete();
        }

        $this->bustPoolCache((int) $expense->account_id);

        Log::warning('CashFlow: Expense soft-deleted without void — pool refunded as safety net', [
            'expense_id' => $expense->id,
        ]);
    }

    /**
     * Restore mirror of deleted(): re-apply the pool debit on a
     * previously-soft-deleted, non-voided expense.
     */
    public function restored(Expense $expense): void
    {
        if ($expense->voided_at !== null) {
            return;
        }

        if ($expense->paid_from_pool_id) {
            $this->decrementPoolBalance(
                (int) $expense->paid_from_pool_id,
                (float) $expense->amount,
            );
        }

        $this->bustPoolCache((int) $expense->account_id);

        Log::info('CashFlow: Expense restored — pool re-debited', [
            'expense_id' => $expense->id,
        ]);
    }

    private function decrementPoolBalance(?int $poolId, $amount): void
    {
        if (! $poolId) {
            return;
        }
        DB::table('cash_pools')
            ->where('id', $poolId)
            ->decrement('cached_balance', $amount);
    }

    private function incrementPoolBalance(?int $poolId, $amount): void
    {
        if (! $poolId) {
            return;
        }
        DB::table('cash_pools')
            ->where('id', $poolId)
            ->increment('cached_balance', $amount);
    }

    /**
     * Bust the per-account pool list cache (`cashflow_pools_{accountId}`)
     * so the dashboard sees the just-mutated balance immediately rather
     * than waiting for the natural TTL. Cheap on file cache.
     */
    private function bustPoolCache(int $accountId): void
    {
        if ($accountId <= 0) {
            return;
        }
        Cache::forget("cashflow_pools_{$accountId}");
    }
}
