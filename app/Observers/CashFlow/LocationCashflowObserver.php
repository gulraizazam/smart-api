<?php

namespace App\Observers\CashFlow;

use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\CashPool;
use App\Models\Locations;
use Illuminate\Support\Facades\Log;

class LocationCashflowObserver
{
    /**
     * When a new active location is created, auto-create a branch cash pool.
     */
    public function created(Locations $location): void
    {
        try {
            if (!$location->active) {
                return;
            }

            $this->createPoolForLocation($location);
        } catch (\Exception $e) {
            Log::error('CashFlow: Failed to auto-create pool for location', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * When a location is updated, handle activation/deactivation.
     */
    public function updated(Locations $location): void
    {
        try {
            // If location was just activated, ensure it has a pool
            if ($location->active && $location->isDirty('active')) {
                $existingPool = CashPool::where('location_id', $location->id)
                    ->where('type', CashPool::TYPE_BRANCH_CASH)
                    ->withTrashed()
                    ->first();

                if ($existingPool) {
                    // Reactivate soft-deleted pool
                    if ($existingPool->trashed()) {
                        $existingPool->restore();
                        $existingPool->update(['is_active' => 1]);
                    } elseif (!$existingPool->is_active) {
                        $existingPool->update(['is_active' => 1]);
                    }
                } else {
                    $this->createPoolForLocation($location);
                }
            }

            // If location was deactivated, soft-deactivate its pool
            if (!$location->active && $location->isDirty('active')) {
                $pool = CashPool::where('location_id', $location->id)
                    ->where('type', CashPool::TYPE_BRANCH_CASH)
                    ->where('is_active', 1)
                    ->first();

                if ($pool) {
                    $pool->update(['is_active' => 0]);

                    CashflowAuditLog::create([
                        'account_id' => $location->account_id,
                        'user_id' => null,
                        'action' => CashflowAuditLog::ACTION_DEACTIVATED,
                        'entity_type' => CashflowAuditLog::ENTITY_CASH_POOL,
                        'entity_id' => $pool->id,
                        'old_values' => ['is_active' => 1],
                        'new_values' => ['is_active' => 0],
                        'reason' => 'Branch deactivated: ' . $location->name,
                        'ip_address' => request()->ip(),
                        'created_at' => now(),
                    ]);

                    // Log warning if pool has balance
                    if ($pool->cached_balance > 0) {
                        Log::warning('CashFlow: Branch deactivated with positive pool balance', [
                            'location_id' => $location->id,
                            'pool_id' => $pool->id,
                            'balance' => $pool->cached_balance,
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('CashFlow: Failed to handle location update', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create a branch cash pool for the given location.
     */
    private function createPoolForLocation(Locations $location): void
    {
        $pool = CashPool::create([
            'account_id' => $location->account_id,
            'type' => CashPool::TYPE_BRANCH_CASH,
            'location_id' => $location->id,
            'name' => $location->name . ' Cash',
            'opening_balance' => 0,
            'cached_balance' => 0,
            'is_active' => 1,
        ]);

        CashflowAuditLog::create([
            'account_id' => $location->account_id,
            'user_id' => null,
            'action' => CashflowAuditLog::ACTION_AUTO_CREATED,
            'entity_type' => CashflowAuditLog::ENTITY_CASH_POOL,
            'entity_id' => $pool->id,
            'old_values' => null,
            'new_values' => $pool->toArray(),
            'reason' => 'Auto-created for new branch: ' . $location->name,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
