<?php

namespace App\Observers\CashFlow;

use App\Models\CashFlow\StaffAdvance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StaffAdvanceObserver
{
    /**
     * After advance created: decrement pool balance (cash given out).
     */
    public function created(StaffAdvance $advance): void
    {
        try {
            DB::table('cash_pools')
                ->where('id', $advance->pool_id)
                ->decrement('cached_balance', $advance->amount);
        } catch (\Exception $e) {
            Log::error('CashFlow StaffAdvanceObserver::created failed', [
                'advance_id' => $advance->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
