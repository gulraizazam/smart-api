<?php

declare(strict_types=1);
namespace App\Observers\CashFlow;

use App\Enums\VendorTransactionStatus;
use App\Enums\VendorTransactionType;
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
            if ($transaction->type === VendorTransactionType::Purchase) {
                // Only count delivered purchases in outstanding balance
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
        } catch (\Exception $e) {
            Log::error('CashFlow VendorTransactionObserver::created failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
