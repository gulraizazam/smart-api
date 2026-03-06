<?php

namespace App\Observers\CashFlow;

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
     * 1. Rejected → reverse pool deduction
     * 2. Resubmitted (rejected → pending) → re-apply pool deduction
     * 3. Amount changed (admin edit) → adjust difference
     * 4. Pool changed (admin edit) → credit old pool, debit new pool
     * 5. Amount AND pool changed → credit old pool full amount, debit new pool new amount
     * 6. Voided → SKIP (handled directly in ExpenseService::void() to avoid double-counting)
     */
    public function updated(Expense $expense): void
    {
        try {
            // --- Voided: skip entirely — ExpenseService::void() handles pool reversal directly ---
            if ($expense->isDirty('voided_at') && $expense->voided_at !== null) {
                return;
            }

            // --- Status changed to rejected — reverse the pool deduction ---
            if ($expense->isDirty('status') && $expense->status === Expense::STATUS_REJECTED) {
                $this->incrementPoolBalance($expense->paid_from_pool_id, $expense->amount);
                Log::info('CashFlow: Expense rejected, pool reversed', [
                    'expense_id' => $expense->id, 'pool_id' => $expense->paid_from_pool_id,
                    'amount' => $expense->amount,
                ]);
                return;
            }

            // --- Status changed from rejected to pending (resubmission) — re-apply deduction ---
            if ($expense->isDirty('status')
                && $expense->status === Expense::STATUS_PENDING
                && $expense->getOriginal('status') === Expense::STATUS_REJECTED
            ) {
                $this->decrementPoolBalance($expense->paid_from_pool_id, $expense->amount);
                Log::info('CashFlow: Expense resubmitted, pool re-debited', [
                    'expense_id' => $expense->id, 'pool_id' => $expense->paid_from_pool_id,
                    'amount' => $expense->amount,
                ]);
                return;
            }

            // --- Admin edit: amount and/or pool changed on non-rejected, non-voided expense ---
            if ($expense->isVoided() || $expense->status === Expense::STATUS_REJECTED) {
                return; // no pool impact on voided/rejected expenses
            }

            $amountChanged = $expense->isDirty('amount');
            $poolChanged = $expense->isDirty('paid_from_pool_id');

            if (!$amountChanged && !$poolChanged) {
                return; // nothing that affects pools
            }

            $oldPoolId = (int) $expense->getOriginal('paid_from_pool_id');
            $newPoolId = (int) $expense->paid_from_pool_id;
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

    private function decrementPoolBalance(int $poolId, $amount): void
    {
        DB::table('cash_pools')
            ->where('id', $poolId)
            ->decrement('cached_balance', $amount);
    }

    private function incrementPoolBalance(int $poolId, $amount): void
    {
        DB::table('cash_pools')
            ->where('id', $poolId)
            ->increment('cached_balance', $amount);
    }
}
