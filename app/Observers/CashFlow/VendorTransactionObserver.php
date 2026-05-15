<?php

declare(strict_types=1);
namespace App\Observers\CashFlow;

use App\Enums\VendorTransactionStatus;
use App\Enums\VendorTransactionType;
use App\Models\CashFlow\VendorTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Vendor balance side effects for VendorTransaction writes.
 *
 * Exceptions propagate — a failed vendor-balance update inside the
 * surrounding `DB::transaction` rolls the expense back rather than
 * leaving a silent drift in `cashflow_vendors.cached_balance`.
 */
class VendorTransactionObserver
{
    public function created(VendorTransaction $transaction): void
    {
        if ($transaction->type === VendorTransactionType::Purchase) {
            // Only count delivered purchases in outstanding balance.
            if ($transaction->status === VendorTransactionStatus::Delivered) {
                DB::table('cashflow_vendors')
                    ->where('id', $transaction->vendor_id)
                    ->increment('cached_balance', $transaction->amount);
            }
        } elseif ($transaction->type === VendorTransactionType::Payment) {
            DB::table('cashflow_vendors')
                ->where('id', $transaction->vendor_id)
                ->decrement('cached_balance', $transaction->amount);
        }

        // Same freshness rule as the pool cache: dashboard reads keyed
        // on `cashflow_pools_{accountId}` get a stale snapshot of pool
        // balances after any expense write, and that snapshot's
        // companion vendor card also drifts. Bust both.
        if ($transaction->account_id) {
            Cache::forget("cashflow_pools_{$transaction->account_id}");
        }
    }
}
