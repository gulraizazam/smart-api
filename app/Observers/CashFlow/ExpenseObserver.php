<?php

declare(strict_types=1);
namespace App\Observers\CashFlow;

use App\Enums\ExpenseStatus;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpenseObserver
{
    /**
     * After expense created: decrement pool balance.
     * Per spec: pool balance affected immediately (cash already spent), regardless of approval status.
     */
    public function created(Expense $expense): void
    {
        try {
            if (!$expense->paid_from_pool_id) return;
            $this->decrementPoolBalance($expense->paid_from_pool_id, $expense->amount);
        } catch (\Exception $e) {
            Log::error('CashFlow ExpenseObserver::created failed', [
                'expense_id' => $expense->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * After expense updated: handle balance changes for all edit scenarios.
     *
     * Scenarios handled:
     * 1. Amount changed (admin edit) → adjust difference
     * 2. Pool changed (admin edit) → credit old pool, debit new pool
     * 3. Amount AND pool changed → credit old pool full amount, debit new pool new amount
     * 4. Voided → SKIP (handled directly in ExpenseService::void() to avoid double-counting)
     *
     * Reject/resubmit are intentionally NOT pool-impacting events
     * (2026-05-14): in the "reject = send back for revisions" model
     * the money already left the till; only the record needs fixing,
     * so the pool stays debited throughout the reject → edit → re-approve
     * cycle. Use `void` (separate flow) when the entry shouldn't exist
     * and the cash should be reversed.
     */
    public function updated(Expense $expense): void
    {
        try {
            // --- Voided: skip entirely — ExpenseService::void() handles pool reversal directly ---
            if ($expense->isDirty('voided_at') && $expense->voided_at !== null) {
                return;
            }

            // --- Admin edit: amount and/or pool changed on non-voided expense ---
            if ($expense->isVoided()) {
                return; // voided rows are immutable from a pool-balance standpoint
            }

            $amountChanged = $expense->isDirty('amount');
            $poolChanged = $expense->isDirty('paid_from_pool_id');

            if (!$amountChanged && !$poolChanged) {
                return; // nothing that affects pools
            }

            $oldPoolId = $expense->getOriginal('paid_from_pool_id');
            $newPoolId = $expense->paid_from_pool_id;
            if (!$oldPoolId && !$newPoolId) return;
            $oldAmount = (float) $expense->getOriginal('amount');
            $newAmount = (float) $expense->amount;

            if ($poolChanged) {
                // Pool changed — credit old pool the old amount, debit new pool the new amount
                $this->incrementPoolBalance($oldPoolId, $oldAmount);
                $this->decrementPoolBalance($newPoolId, $newAmount);
                Log::info('CashFlow: Expense pool changed', [
                    'expense_id' => $expense->id,
                    'old_pool' => $oldPoolId, 'old_amount' => $oldAmount,
                    'new_pool' => $newPoolId, 'new_amount' => $newAmount,
                ]);
            } elseif ($amountChanged) {
                // Same pool, amount changed — adjust difference
                $diff = $newAmount - $oldAmount;
                if ($diff > 0) {
                    $this->decrementPoolBalance($newPoolId, $diff);
                } elseif ($diff < 0) {
                    $this->incrementPoolBalance($newPoolId, abs($diff));
                }
                Log::info('CashFlow: Expense amount adjusted', [
                    'expense_id' => $expense->id, 'pool_id' => $newPoolId,
                    'old_amount' => $oldAmount, 'new_amount' => $newAmount,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('CashFlow ExpenseObserver::updated failed', [
                'expense_id' => $expense->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function decrementPoolBalance(?int $poolId, $amount): void
    {
        if (!$poolId) return;
        DB::table('cash_pools')
            ->where('id', $poolId)
            ->decrement('cached_balance', $amount);
    }

    private function incrementPoolBalance(?int $poolId, $amount): void
    {
        if (!$poolId) return;
        DB::table('cash_pools')
            ->where('id', $poolId)
            ->increment('cached_balance', $amount);
    }
}
