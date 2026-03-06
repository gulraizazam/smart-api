<?php

namespace App\Services\CashFlow;

use App\Exceptions\CashflowException;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\PeriodLock;
use App\Models\Locations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PoolService
{
    private CashflowAuditService $auditService;

    public function __construct(CashflowAuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Get all pools for account with location info.
     */
    public function getAllPools(int $accountId)
    {
        return CashPool::forAccount($accountId)
            ->with('location:id,name')
            ->orderByRaw("FIELD(type, 'branch_cash', 'head_office_cash', 'bank_account')")
            ->orderBy('name')
            ->get();
    }

    /**
     * Get active pools for dropdown.
     */
    public function getActivePools(int $accountId)
    {
        return CashPool::forAccount($accountId)
            ->active()
            ->with('location:id,name')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a non-branch pool (head office or bank account).
     */
    public function createPool(array $data, int $accountId): CashPool
    {
        $pool = CashPool::create([
            'account_id' => $accountId,
            'type' => $data['type'],
            'location_id' => null,
            'name' => $data['name'],
            'opening_balance' => $data['opening_balance'] ?? 0,
            'cached_balance' => $data['opening_balance'] ?? 0,
            'is_active' => 1,
        ]);

        $this->auditService->log(
            CashflowAuditLog::ACTION_CREATED,
            CashflowAuditLog::ENTITY_CASH_POOL,
            $pool->id,
            null,
            $pool->toArray()
        );

        $this->clearCache($accountId);
        return $pool;
    }

    /**
     * Update pool (name, opening balance if not frozen).
     */
    public function updatePool(int $poolId, array $data, int $accountId): CashPool
    {
        $pool = CashPool::forAccount($accountId)->findOrFail($poolId);
        $oldValues = $pool->toArray();

        // Check if opening balance change is allowed
        if (isset($data['opening_balance']) && (float) $data['opening_balance'] !== (float) $pool->opening_balance) {
            if ($pool->opening_balance_frozen || PeriodLock::hasAnyLock($accountId)) {
                throw CashflowException::openingBalanceFrozen();
            }

            $balanceDiff = (float) $data['opening_balance'] - (float) $pool->opening_balance;
            $pool->cached_balance = (float) $pool->cached_balance + $balanceDiff;
            $pool->opening_balance = $data['opening_balance'];
        }

        if (isset($data['name'])) {
            $pool->name = $data['name'];
        }

        if (isset($data['is_active'])) {
            $pool->is_active = $data['is_active'];
        }

        $pool->save();

        $this->auditService->log(
            CashflowAuditLog::ACTION_UPDATED,
            CashflowAuditLog::ENTITY_CASH_POOL,
            $pool->id,
            $oldValues,
            $pool->toArray()
        );

        $this->clearCache($accountId);
        return $pool;
    }

    /**
     * Initialize pools for existing branches that don't have them yet.
     */
    public function initializePoolsForExistingBranches(int $accountId): int
    {
        $locations = Locations::where('account_id', $accountId)
            ->where('active', 1)
            ->where('name', '!=', 'All Centres')
            ->get();

        $created = 0;
        foreach ($locations as $location) {
            $exists = CashPool::where('account_id', $accountId)
                ->where('location_id', $location->id)
                ->where('type', CashPool::TYPE_BRANCH_CASH)
                ->exists();

            if (!$exists) {
                $pool = CashPool::create([
                    'account_id' => $accountId,
                    'type' => CashPool::TYPE_BRANCH_CASH,
                    'location_id' => $location->id,
                    'name' => $location->name . ' Cash',
                    'opening_balance' => 0,
                    'cached_balance' => 0,
                    'is_active' => 1,
                ]);

                $this->auditService->log(
                    CashflowAuditLog::ACTION_AUTO_CREATED,
                    CashflowAuditLog::ENTITY_CASH_POOL,
                    $pool->id,
                    null,
                    $pool->toArray()
                );

                $created++;
            }
        }

        $this->clearCache($accountId);
        return $created;
    }

    /**
     * Delete a pool if it has no linked expenses.
     */
    public function deletePool(int $poolId, int $accountId): void
    {
        $pool = CashPool::forAccount($accountId)->findOrFail($poolId);

        // Check if any expenses are linked to this pool
        $expenseCount = \App\Models\CashFlow\Expense::where('paid_from_pool_id', $poolId)->count();
        if ($expenseCount > 0) {
            throw new CashflowException("Cannot delete pool \"{$pool->name}\" — it has {$expenseCount} expense(s) linked to it.");
        }

        // Check if any transfers reference this pool
        $transferCount = \App\Models\CashFlow\CashTransfer::where('from_pool_id', $poolId)
            ->orWhere('to_pool_id', $poolId)
            ->count();
        if ($transferCount > 0) {
            throw new CashflowException("Cannot delete pool \"{$pool->name}\" — it has {$transferCount} transfer(s) linked to it.");
        }

        $oldValues = $pool->toArray();
        $pool->delete();

        $this->auditService->log(
            CashflowAuditLog::ACTION_DELETED,
            CashflowAuditLog::ENTITY_CASH_POOL,
            $poolId,
            $oldValues,
            null
        );

        $this->clearCache($accountId);
    }

    private function clearCache(int $accountId): void
    {
        Cache::forget("cashflow_pools_{$accountId}");
    }
}
