<?php

namespace App\Observers\CashFlow;

use App\Models\CashFlow\CashPool;
use App\Models\PackageAdvances;
use App\Models\PaymentModes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PackageAdvanceObserver
{
    /**
     * After a PackageAdvance is created, adjust pool balances.
     *
     * cash_flow = 'in'  → patient payment → credit pool
     * cash_flow = 'out' + is_refund = 1 → refund → debit pool
     * cash_flow = 'out' + is_refund = 0 → settlement/invoice → no cash movement
     */
    public function created(PackageAdvances $advance): void
    {
        try {
            if ($advance->is_cancel) {
                return; // cancelled at creation — no pool impact
            }

            if (!$this->affectsPool($advance)) {
                return;
            }

            $pool = $this->resolvePool($advance->account_id, $advance->payment_mode_id, $advance->location_id);
            if (!$pool) {
                return;
            }

            $this->applyPoolImpact($pool->id, $advance->cash_flow, $advance->cash_amount);

            Log::info('CashFlow: Pool adjusted (advance created)', [
                'pool_id' => $pool->id,
                'pool_name' => $pool->name,
                'cash_flow' => $advance->cash_flow,
                'amount' => $advance->cash_amount,
                'advance_id' => $advance->id,
            ]);
        } catch (\Exception $e) {
            Log::error('CashFlow PackageAdvanceObserver::created failed', [
                'advance_id' => $advance->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * After a PackageAdvance is updated, handle:
     * 1. Cancellation (is_cancel flipped to 1) → reverse pool impact
     * 2. Amount changed (e.g. 10000 → 1000) → adjust difference on same pool
     * 3. Payment method changed (cash → bank) → reverse old pool, apply to new pool
     */
    public function updated(PackageAdvances $advance): void
    {
        try {
            if (!$this->affectsPoolOriginal($advance)) {
                return; // original record was settlement/invoice — no pool impact to adjust
            }

            // --- Case 1: Cancellation ---
            if ($advance->isDirty('is_cancel') && $advance->is_cancel == 1 && $advance->getOriginal('is_cancel') == 0) {
                $oldPool = $this->resolvePool(
                    $advance->account_id,
                    $advance->getOriginal('payment_mode_id'),
                    $advance->getOriginal('location_id')
                );
                if ($oldPool) {
                    $this->reversePoolImpact($oldPool->id, $advance->getOriginal('cash_flow') ?? $advance->cash_flow, $advance->getOriginal('cash_amount') ?? $advance->cash_amount);
                    Log::info('CashFlow: Pool reversed (advance cancelled)', [
                        'pool_id' => $oldPool->id, 'advance_id' => $advance->id,
                    ]);
                }
                return; // cancellation handled, no further adjustments needed
            }

            // Skip if already cancelled — no pool impact to adjust
            if ($advance->is_cancel) {
                return;
            }

            // --- Case 2 & 3: Amount and/or payment method changed ---
            $amountChanged = $advance->isDirty('cash_amount');
            $paymentModeChanged = $advance->isDirty('payment_mode_id');
            $locationChanged = $advance->isDirty('location_id');

            if (!$amountChanged && !$paymentModeChanged && !$locationChanged) {
                return; // nothing that affects pools changed
            }

            $oldPaymentModeId = $advance->getOriginal('payment_mode_id');
            $oldLocationId = $advance->getOriginal('location_id');
            $oldAmount = (float) $advance->getOriginal('cash_amount');
            $newAmount = (float) $advance->cash_amount;
            $cashFlow = $advance->cash_flow;

            $oldPool = $this->resolvePool($advance->account_id, $oldPaymentModeId, $oldLocationId);
            $newPool = $this->resolvePool($advance->account_id, $advance->payment_mode_id, $advance->location_id);

            $oldPoolId = $oldPool ? $oldPool->id : null;
            $newPoolId = $newPool ? $newPool->id : null;

            if ($oldPoolId === $newPoolId) {
                // Same pool — just adjust the difference in amount
                if ($amountChanged && $oldPoolId) {
                    $diff = $newAmount - $oldAmount;
                    if (abs($diff) > 0.01) {
                        $this->applyPoolImpact($oldPoolId, $cashFlow, $diff);
                        Log::info('CashFlow: Pool adjusted (amount edited)', [
                            'pool_id' => $oldPoolId, 'old_amount' => $oldAmount,
                            'new_amount' => $newAmount, 'advance_id' => $advance->id,
                        ]);
                    }
                }
            } else {
                // Different pool — reverse old, apply new
                if ($oldPoolId) {
                    $this->reversePoolImpact($oldPoolId, $cashFlow, $oldAmount);
                    Log::info('CashFlow: Pool reversed (payment method/location changed)', [
                        'pool_id' => $oldPoolId, 'amount' => $oldAmount, 'advance_id' => $advance->id,
                    ]);
                }
                if ($newPoolId) {
                    $this->applyPoolImpact($newPoolId, $cashFlow, $newAmount);
                    Log::info('CashFlow: Pool applied to new pool', [
                        'pool_id' => $newPoolId, 'amount' => $newAmount, 'advance_id' => $advance->id,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('CashFlow PackageAdvanceObserver::updated failed', [
                'advance_id' => $advance->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * After a PackageAdvance is soft-deleted, reverse its pool impact.
     */
    public function deleted(PackageAdvances $advance): void
    {
        try {
            if ($advance->is_cancel) {
                return; // was already cancelled — pool already reversed or never impacted
            }

            if (!$this->affectsPool($advance)) {
                return;
            }

            $pool = $this->resolvePool($advance->account_id, $advance->payment_mode_id, $advance->location_id);
            if (!$pool) {
                return;
            }

            $this->reversePoolImpact($pool->id, $advance->cash_flow, $advance->cash_amount);

            Log::info('CashFlow: Pool reversed (advance deleted)', [
                'pool_id' => $pool->id,
                'pool_name' => $pool->name,
                'cash_flow' => $advance->cash_flow,
                'amount' => $advance->cash_amount,
                'advance_id' => $advance->id,
            ]);
        } catch (\Exception $e) {
            Log::error('CashFlow PackageAdvanceObserver::deleted failed', [
                'advance_id' => $advance->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ===================== HELPERS =====================

    /**
     * Does this advance record affect pool balances?
     * Only inflows and refunds affect pools — settlements/invoices do not.
     */
    private function affectsPool(PackageAdvances $advance): bool
    {
        if ($advance->cash_flow === 'in') {
            return true;
        }
        if ($advance->cash_flow === 'out' && $advance->is_refund) {
            return true;
        }
        return false;
    }

    /**
     * Same check but using original (pre-update) values.
     */
    private function affectsPoolOriginal(PackageAdvances $advance): bool
    {
        $originalCashFlow = $advance->getOriginal('cash_flow') ?? $advance->cash_flow;
        $originalIsRefund = $advance->getOriginal('is_refund') ?? $advance->is_refund;

        if ($originalCashFlow === 'in') {
            return true;
        }
        if ($originalCashFlow === 'out' && $originalIsRefund) {
            return true;
        }
        return false;
    }

    /**
     * Apply pool impact based on cash_flow direction.
     * cash_flow = 'in'  → credit (increment) pool
     * cash_flow = 'out' → debit (decrement) pool
     */
    private function applyPoolImpact(int $poolId, string $cashFlow, float $amount): void
    {
        if ($cashFlow === 'in') {
            $this->incrementPoolBalance($poolId, $amount);
        } else {
            $this->decrementPoolBalance($poolId, $amount);
        }
    }

    /**
     * Reverse pool impact (opposite of applyPoolImpact).
     */
    private function reversePoolImpact(int $poolId, string $cashFlow, float $amount): void
    {
        if ($cashFlow === 'in') {
            $this->decrementPoolBalance($poolId, $amount);
        } else {
            $this->incrementPoolBalance($poolId, $amount);
        }
    }

    /**
     * Resolve which cash pool should be affected based on payment method.
     *
     * Cash → branch_cash pool matching the location_id
     * Card / Bank / Other → first active bank_account pool
     */
    private function resolvePool(int $accountId, $paymentModeId, $locationId): ?CashPool
    {
        $isCash = false;
        if ($paymentModeId) {
            $paymentMode = PaymentModes::find($paymentModeId);
            if ($paymentMode) {
                $isCash = stripos($paymentMode->name, 'cash') !== false;
            }
        }

        if ($isCash && $locationId) {
            // Cash payment → find the branch cash pool for this location
            $pool = CashPool::where('account_id', $accountId)
                ->where('location_id', $locationId)
                ->where('type', CashPool::TYPE_BRANCH_CASH)
                ->where('is_active', 1)
                ->first();

            if ($pool) {
                return $pool;
            }

            // Fallback: try head office cash pool
            $pool = CashPool::where('account_id', $accountId)
                ->where('type', CashPool::TYPE_HEAD_OFFICE_CASH)
                ->where('is_active', 1)
                ->first();

            if ($pool) {
                return $pool;
            }
        } else {
            // Card / Bank / Other → bank account pool
            $pool = CashPool::where('account_id', $accountId)
                ->where('type', CashPool::TYPE_BANK_ACCOUNT)
                ->where('is_active', 1)
                ->first();

            if ($pool) {
                return $pool;
            }
        }

        Log::warning('CashFlow: No matching pool found for advance', [
            'payment_mode_id' => $paymentModeId,
            'location_id' => $locationId,
            'is_cash' => $isCash,
        ]);

        return null;
    }

    private function incrementPoolBalance(int $poolId, $amount): void
    {
        DB::table('cash_pools')
            ->where('id', $poolId)
            ->increment('cached_balance', $amount);
    }

    private function decrementPoolBalance(int $poolId, $amount): void
    {
        DB::table('cash_pools')
            ->where('id', $poolId)
            ->decrement('cached_balance', $amount);
    }
}
