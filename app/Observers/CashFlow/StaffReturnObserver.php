<?php

namespace App\Observers\CashFlow;

use App\Models\CashFlow\StaffReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StaffReturnObserver
{
    /**
     * After return created: increment pool balance (cash returned).
     */
    public function created(StaffReturn $return): void
    {
        try {
            DB::table('cash_pools')
                ->where('id', $return->pool_id)
                ->increment('cached_balance', $return->amount);
        } catch (\Exception $e) {
            Log::error('CashFlow StaffReturnObserver::created failed', [
                'return_id' => $return->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
