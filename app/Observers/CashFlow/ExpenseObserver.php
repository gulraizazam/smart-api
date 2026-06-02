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
 * Reject REVERSES the pool deduction; resubmit (rejected -> pending)
 * re-applies it with the current amount/pool (Model A). A rejected expense
 * therefore nets to zero pool impact, and recalculatePoolBalances likewise
 * EXCLUDES rejected rows. This matches the legacy app (crm2): crm2 and crm3
 * share one prod DB and the same cached_balance column during coexistence,
 * so both MUST treat a rejected expense identically or the shared balance
 * diverges. (This reverts the 2026-05-14 "Model B" choice, which was
 * incompatible with the Model-A history crm2 built and still maintains.)
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

        // Status -> Rejected: reverse the pool deduction (Model A). The cash
        // is "given back" on reject; the recompute also excludes rejected
        // rows, so a rejected expense nets to zero pool impact.
        if ($expense->isDirty('status') && $expense->status === ExpenseStatus::Rejected) {
            if ($expense->paid_from_pool_id) {
                $this->incrementPoolBalance($expense->paid_from_pool_id, $expense->amount);
            }
            $this->bustPoolCache((int) $expense->account_id);
            Log::info('CashFlow: Expense rejected, pool reversed', ['expense_id' => $expense->id]);
            return;
        }

        // Rejected -> Pending (resubmit / edit-save of a rejected row):
        // re-apply the deduction with the current (possibly edited)
        // amount + pool. Returns before the delta logic below so the full
        // amount is re-debited, not a diff.
        if ($expense->isDirty('status')
            && $expense->status === ExpenseStatus::Pending
            && $expense->getOriginal('status') === ExpenseStatus::Rejected
        ) {
            if ($expense->paid_from_pool_id) {
                $this->decrementPoolBalance($expense->paid_from_pool_id, $expense->amount);
            }
            $this->bustPoolCache((int) $expense->account_id);
            Log::info('CashFlow: Expense resubmitted, pool re-debited', ['expense_id' => $expense->id]);
            return;
        }

        // A row that is still Rejected has no standing pool deduction (it was
        // reversed at reject), so amount/pool edits on it must not adjust the
        // pool.
        if ($expense->status === ExpenseStatus::Rejected) {
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
        // Voided rows: void() already refunded the pool AND deleted the
        // vendor tx — nothing to do.
        if ($expense->voided_at !== null) {
            return;
        }

        // Rejected rows already had their pool deduction reversed at reject
        // time (Model A), so do NOT refund the pool again. The vendor tx
        // (untouched by reject) is still reversed below.
        if ($expense->status !== ExpenseStatus::Rejected && $expense->paid_from_pool_id) {
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

        // A rejected row carries no pool deduction (reversed at reject), so
        // restoring it must not re-debit the pool.
        if ($expense->status !== ExpenseStatus::Rejected && $expense->paid_from_pool_id) {
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
