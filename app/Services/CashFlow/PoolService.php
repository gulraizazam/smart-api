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

        if (isset($data['type'])) {
            $pool->type = $data['type'];
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

    /**
     * Recalculate all pool balances from scratch.
     * Resets each pool to opening_balance, then replays all transactions since go-live date.
     */
    public function recalculatePoolBalances(int $accountId): array
    {
        $settingService = app(CashflowSettingService::class);
        $goLiveDate = $settingService->getGoLiveDate($accountId);

        if (!$goLiveDate) {
            throw new CashflowException('Go-live date is not set. Please configure it in settings first.');
        }

        $pools = CashPool::forAccount($accountId)->get();
        $results = [];

        // Build a map of location_id → branch_cash pool_id
        $branchPoolMap = [];
        $hoPoolId = null;
        $bankPoolId = null;

        foreach ($pools as $pool) {
            if ($pool->type === CashPool::TYPE_BRANCH_CASH && $pool->location_id) {
                $branchPoolMap[$pool->location_id] = $pool->id;
            } elseif ($pool->type === CashPool::TYPE_HEAD_OFFICE_CASH && !$hoPoolId) {
                $hoPoolId = $pool->id;
            } elseif ($pool->type === CashPool::TYPE_BANK_ACCOUNT && !$bankPoolId) {
                $bankPoolId = $pool->id;
            }
        }

        // Build cash payment mode IDs
        $cashModeIds = \App\Models\PaymentModes::where('active', 1)
            ->get()
            ->filter(fn($pm) => stripos($pm->name, 'cash') !== false)
            ->pluck('id')
            ->toArray();

        // Step 1: Reset all pools to opening_balance
        $balances = [];
        foreach ($pools as $pool) {
            $balances[$pool->id] = (float) $pool->opening_balance;
        }

        // Step 2: Patient payments (inflows) — credit pools
        $payments = \App\Models\PackageAdvances::where('account_id', $accountId)
            ->where('cash_flow', 'in')
            ->where('is_cancel', 0)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $goLiveDate)
            ->get(['cash_amount', 'payment_mode_id', 'location_id']);

        foreach ($payments as $pa) {
            $poolId = $this->resolvePoolId($pa, $cashModeIds, $branchPoolMap, $hoPoolId, $bankPoolId);
            if ($poolId && isset($balances[$poolId])) {
                $balances[$poolId] += (float) $pa->cash_amount;
            }
        }

        // Step 3: Refunds (outflows) — debit pools
        $refunds = \App\Models\PackageAdvances::where('account_id', $accountId)
            ->where('cash_flow', 'out')
            ->where('is_refund', 1)
            ->where('is_cancel', 0)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $goLiveDate)
            ->get(['cash_amount', 'payment_mode_id', 'location_id']);

        foreach ($refunds as $ref) {
            $poolId = $this->resolvePoolId($ref, $cashModeIds, $branchPoolMap, $hoPoolId, $bankPoolId);
            if ($poolId && isset($balances[$poolId])) {
                $balances[$poolId] -= (float) $ref->cash_amount;
            }
        }

        // Step 4: Expenses — debit pools (non-voided, non-rejected)
        $expenses = \App\Models\CashFlow\Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->where('status', '!=', 'rejected')
            ->where('expense_date', '>=', $goLiveDate)
            ->get(['amount', 'paid_from_pool_id']);

        foreach ($expenses as $exp) {
            if (isset($balances[$exp->paid_from_pool_id])) {
                $balances[$exp->paid_from_pool_id] -= (float) $exp->amount;
            }
        }

        // Step 5: Transfers — debit source, credit destination (exclude voided)
        $transfers = \App\Models\CashFlow\CashTransfer::where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->whereNull('voided_at')
            ->where('created_at', '>=', $goLiveDate)
            ->get(['amount', 'from_pool_id', 'to_pool_id']);

        foreach ($transfers as $tr) {
            if (isset($balances[$tr->from_pool_id])) {
                $balances[$tr->from_pool_id] -= (float) $tr->amount;
            }
            if (isset($balances[$tr->to_pool_id])) {
                $balances[$tr->to_pool_id] += (float) $tr->amount;
            }
        }

        // Step 6: Staff advances — debit pools (exclude voided)
        $advances = \App\Models\CashFlow\StaffAdvance::where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->whereNull('voided_at')
            ->where('created_at', '>=', $goLiveDate)
            ->get(['amount', 'pool_id']);

        foreach ($advances as $adv) {
            if (isset($balances[$adv->pool_id])) {
                $balances[$adv->pool_id] -= (float) $adv->amount;
            }
        }

        // Step 7: Staff returns — credit pools (exclude voided)
        $returns = \App\Models\CashFlow\StaffReturn::where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->whereNull('voided_at')
            ->where('created_at', '>=', $goLiveDate)
            ->get(['amount', 'pool_id']);

        foreach ($returns as $ret) {
            if (isset($balances[$ret->pool_id])) {
                $balances[$ret->pool_id] += (float) $ret->amount;
            }
        }

        // Step 8: Apply calculated balances
        foreach ($pools as $pool) {
            $oldBalance = (float) $pool->cached_balance;
            $newBalance = round($balances[$pool->id] ?? (float) $pool->opening_balance, 2);

            if (abs($oldBalance - $newBalance) > 0.01) {
                $results[] = [
                    'pool' => $pool->name,
                    'old_balance' => $oldBalance,
                    'new_balance' => $newBalance,
                    'diff' => round($newBalance - $oldBalance, 2),
                ];
            }

            DB::table('cash_pools')->where('id', $pool->id)->update(['cached_balance' => $newBalance]);
        }

        $this->clearCache($accountId);

        return $results;
    }

    /**
     * Resolve pool ID for a PackageAdvance record (used by recalculate).
     */
    private function resolvePoolId($advance, array $cashModeIds, array $branchPoolMap, ?int $hoPoolId, ?int $bankPoolId): ?int
    {
        $isCash = in_array($advance->payment_mode_id, $cashModeIds);

        if ($isCash && $advance->location_id) {
            return $branchPoolMap[$advance->location_id] ?? $hoPoolId;
        }

        return $bankPoolId;
    }

    private function clearCache(int $accountId): void
    {
        Cache::forget("cashflow_pools_{$accountId}");
    }
}
