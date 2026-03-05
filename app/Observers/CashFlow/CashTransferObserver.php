<?php

namespace App\Observers\CashFlow;

use App\Models\CashFlow\CashTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashTransferObserver
{
    /**
     * After transfer created: decrement source pool, increment destination pool.
     */
    public function created(CashTransfer $transfer): void
    {
        try {
            DB::table('cash_pools')
                ->where('id', $transfer->from_pool_id)
                ->decrement('cached_balance', $transfer->amount);

            DB::table('cash_pools')
                ->where('id', $transfer->to_pool_id)
                ->increment('cached_balance', $transfer->amount);
        } catch (\Exception $e) {
            Log::error('CashFlow CashTransferObserver::created failed', [
                'transfer_id' => $transfer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
