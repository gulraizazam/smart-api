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
     * After expense updated: handle balance changes for status transitions.
     * - Rejected: reverse pool deduction (increment pool)
     * - Resubmitted (rejected → pending): re-apply pool deduction (decrement pool)
     * - Amount changed by admin edit: adjust difference
     */
    public function updated(Expense $expense): void
    {
        try {
            // Status changed to rejected — reverse the pool deduction
            if ($expense->isDirty('status') && $expense->status === Expense::STATUS_REJECTED) {
                $this->incrementPoolBalance($expense->paid_from_pool_id, $expense->amount);
            }

            // Status changed from rejected to pending (resubmission) — re-apply deduction
            if ($expense->isDirty('status')
                && $expense->status === Expense::STATUS_PENDING
                && $expense->getOriginal('status') === Expense::STATUS_REJECTED
            ) {
                $this->decrementPoolBalance($expense->paid_from_pool_id, $expense->amount);
            }

            // Amount changed (admin edit on non-rejected, non-voided expense)
            if ($expense->isDirty('amount') && !$expense->isVoided() && $expense->status !== Expense::STATUS_REJECTED) {
                $oldAmount = $expense->getOriginal('amount');
                $newAmount = $expense->amount;
                $diff = $newAmount - $oldAmount;

                if ($diff > 0) {
                    $this->decrementPoolBalance($expense->paid_from_pool_id, $diff);
                } elseif ($diff < 0) {
                    $this->incrementPoolBalance($expense->paid_from_pool_id, abs($diff));
                }
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
