<?php

namespace App\Observers\CashFlow;

use App\Models\CashFlow\VendorTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendorTransactionObserver
{
    /**
     * After vendor transaction created: update vendor cached_balance.
     * Purchase (owe more): balance increases.
     * Payment (owe less): balance decreases.
     */
    public function created(VendorTransaction $transaction): void
    {
        try {
            if ($transaction->type === VendorTransaction::TYPE_PURCHASE) {
                DB::table('cashflow_vendors')
                    ->where('id', $transaction->vendor_id)
                    ->increment('cached_balance', $transaction->amount);
            } elseif ($transaction->type === VendorTransaction::TYPE_PAYMENT) {
                DB::table('cashflow_vendors')
                    ->where('id', $transaction->vendor_id)
                    ->decrement('cached_balance', $transaction->amount);
            }
        } catch (\Exception $e) {
            Log::error('CashFlow VendorTransactionObserver::created failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
