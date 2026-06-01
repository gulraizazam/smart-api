<?php

declare(strict_types=1);
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
    public function __construct(
        private readonly CashflowAuditService $auditService,
    ) {}

    /**
     * Get all pools for account with location info.
     *
     * Base balances come from `cached_balance` — the cash-pool aggregate that
     * the cash-flow observers maintain via per-row credit/debit events. On top
     * of that, inventory (Order) sales are layered at DISPLAY time by
     * applyInventoryOverlay() (never persisted to cached_balance).
     *
     * Inventory is overlaid rather than baked in because the legacy Blade app
     * (crm2) and this SPA (crm3) share ONE prod database and the SAME
     * cached_balance column. The legacy app reads cached_balance and adds the
     * identical inventory overlay at its own display. Baking inventory into
     * cached_balance here would make the legacy app double-count it. See
     * applyInventoryOverlay() for the full rationale and the deferred
     * package_advances.order_id guard.
     */
    public function getAllPools(int $accountId): \Illuminate\Database\Eloquent\Collection
    {
        $pools = CashPool::forAccount($accountId)
            ->with('location:id,name')
            ->orderByRaw("FIELD(type, 'branch_cash', 'head_office_cash', 'bank_account')")
            ->orderBy('name')
            ->get();

        $this->applyInventoryOverlay($pools, $accountId);

        return $pools;
    }

    /**
     * Add inventory (Order) sales to pool balances FOR DISPLAY ONLY — never
     * persisted to cached_balance.
     *
     * Cash sales (payment_mode = 1) credit the branch pool for that location;
     * non-cash (card/bank) sales credit the bank pool; refunds debit. This
     * mirrors the legacy app's PoolService::getPoolBalances overlay.
     *
     * Why an overlay and not baked into cached_balance: the legacy Blade app
     * and this SPA run against the SAME prod database. The legacy app reads
     * cached_balance and adds this same inventory overlay at its own display.
     * If we instead stored inventory inside cached_balance, the legacy app
     * would add it a second time and double-count. So inventory stays OUT of
     * cached_balance and is layered on at display in BOTH apps.
     *
     * NOTE: if/when inventory orders start writing package_advances ledger rows
     * (the deferred package_advances.order_id link), so they DO land in
     * cached_balance, this overlay must be guarded to skip already-ledgered
     * orders or it will double-count.
     */
    public function applyInventoryOverlay(\Illuminate\Database\Eloquent\Collection $pools, int $accountId): void
    {
        $goLiveDate = app(CashflowSettingService::class)->getGoLiveDate($accountId);
        if (! $goLiveDate) {
            return;
        }
        $since = $goLiveDate.' 00:00:00';

        $branchPools = $pools->where('type', CashPool::TYPE_BRANCH_CASH)
            ->filter(fn ($p) => $p->location_id !== null);

        if ($branchPools->isNotEmpty()) {
            $locationIds = $branchPools->pluck('location_id')->all();

            $cashSales = \App\Models\Order::where('account_id', $accountId)
                ->where('order_type', 'sale')->where('payment_mode', 1)
                ->whereIn('location_id', $locationIds)
                ->where('created_at', '>=', $since)
                ->selectRaw('location_id, SUM(total_price) AS total')
                ->groupBy('location_id')->pluck('total', 'location_id');

            $cashRefunds = \App\Models\Order::where('account_id', $accountId)
                ->where('order_type', 'refund')->where('payment_mode', 1)
                ->whereIn('location_id', $locationIds)
                ->where('created_at', '>=', $since)
                ->selectRaw('location_id, SUM(total_price) AS total')
                ->groupBy('location_id')->pluck('total', 'location_id');

            foreach ($branchPools as $pool) {
                $net = (float) ($cashSales[$pool->location_id] ?? 0) - (float) ($cashRefunds[$pool->location_id] ?? 0);
                $pool->cached_balance = (float) $pool->cached_balance + $net;
            }
        }

        $bankPool = $pools->firstWhere('type', CashPool::TYPE_BANK_ACCOUNT);
        if ($bankPool) {
            $nonCashSales = (float) \App\Models\Order::where('account_id', $accountId)
                ->where('order_type', 'sale')->where('payment_mode', '!=', 1)
                ->where('created_at', '>=', $since)->sum('total_price');
            $nonCashRefunds = (float) \App\Models\Order::where('account_id', $accountId)
                ->where('order_type', 'refund')->where('payment_mode', '!=', 1)
                ->where('created_at', '>=', $since)->sum('total_price');
            $bankPool->cached_balance = (float) $bankPool->cached_balance + ($nonCashSales - $nonCashRefunds);
        }
    }

    /**
     * Get active pools for dropdown.
     */
    public function getActivePools(int $accountId): \Illuminate\Database\Eloquent\Collection
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

        DB::transaction(function () use ($pool, $oldValues, $poolId) {
            $pool->delete();

            $this->auditService->log(
                CashflowAuditLog::ACTION_DELETED,
                CashflowAuditLog::ENTITY_CASH_POOL,
                $poolId,
                $oldValues,
                null
            );
        });

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

        // Cash payment-mode IDs via the centralized classifier
        // (PaymentModes::cashIds = payment_type OR name-match + safe
        // fallback). Single source of truth, shared with FDM pool-
        // balance display — do not re-implement inline.
        $cashModeIds = \App\Models\PaymentModes::cashIds($accountId);

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
            ->where('system_created_at', '>=', $goLiveDate)
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
            ->where('system_created_at', '>=', $goLiveDate)
            ->get(['cash_amount', 'payment_mode_id', 'location_id']);

        foreach ($refunds as $ref) {
            $poolId = $this->resolvePoolId($ref, $cashModeIds, $branchPoolMap, $hoPoolId, $bankPoolId);
            if ($poolId && isset($balances[$poolId])) {
                $balances[$poolId] -= (float) $ref->cash_amount;
            }
        }

        // Step 4: Expenses — debit pools for every non-voided, non-rejected
        // row (Model A). Reject reverses the deduction (ExpenseObserver) and
        // void removes it, so neither should be replayed here. Excluding
        // rejected keeps this recompute consistent with the incremental
        // observer AND with the legacy app (crm2), which uses the same filter.
        $expenses = \App\Models\CashFlow\Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->where('status', '!=', 'rejected')
            ->where('system_created_at', '>=', $goLiveDate)
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
            ->where('system_created_at', '>=', $goLiveDate)
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
            ->where('system_created_at', '>=', $goLiveDate)
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
            ->where('system_created_at', '>=', $goLiveDate)
            ->get(['amount', 'pool_id']);

        foreach ($returns as $ret) {
            if (isset($balances[$ret->pool_id])) {
                $balances[$ret->pool_id] += (float) $ret->amount;
            }
        }

        // Step 8: Apply calculated balances
        // NOTE: inventory (Order) sales are intentionally NOT folded into the
        // stored cached_balance here. They are added at DISPLAY time by
        // applyInventoryOverlay() (mirroring the legacy app). Keeping inventory
        // OUT of cached_balance lets the legacy Blade app and this SPA share one
        // prod DB off the same column without double-counting (both overlay it
        // at display; neither stores it).
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
        $isCash = in_array($advance->payment_mode_id, $cashModeIds, true);

        if ($isCash && $advance->location_id) {
            return $branchPoolMap[$advance->location_id] ?? $hoPoolId;
        }

        return $bankPoolId;
    }

    /**
     * Calculate expected pool balances without writing to DB (dry-run of recalculate).
     * Returns ['per_pool' => [...], 'total' => float, 'breakdown' => [...]]
     */
    public function calculateExpectedBalances(int $accountId): array
    {
        $settingService = app(CashflowSettingService::class);
        $goLiveDate = $settingService->getGoLiveDate($accountId);

        if (!$goLiveDate) {
            return ['per_pool' => [], 'total' => 0, 'breakdown' => []];
        }

        $pools = CashPool::forAccount($accountId)->get();

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

        // Cash payment-mode IDs via the centralized classifier
        // (PaymentModes::cashIds = payment_type OR name-match + safe
        // fallback). Single source of truth, shared with FDM pool-
        // balance display — do not re-implement inline.
        $cashModeIds = \App\Models\PaymentModes::cashIds($accountId);

        // Step 1: Reset all pools to opening_balance
        $balances = [];
        $openingTotal = 0.0;
        foreach ($pools as $pool) {
            $balances[$pool->id] = (float) $pool->opening_balance;
            $openingTotal += (float) $pool->opening_balance;
        }

        // Step 2: Patient payments (inflows)
        $patientPayments = 0.0;
        $payments = \App\Models\PackageAdvances::where('account_id', $accountId)
            ->where('cash_flow', 'in')
            ->where('is_cancel', 0)
            ->whereNull('deleted_at')
            ->where('system_created_at', '>=', $goLiveDate)
            ->get(['cash_amount', 'payment_mode_id', 'location_id']);

        foreach ($payments as $pa) {
            $poolId = $this->resolvePoolId($pa, $cashModeIds, $branchPoolMap, $hoPoolId, $bankPoolId);
            if ($poolId && isset($balances[$poolId])) {
                $balances[$poolId] += (float) $pa->cash_amount;
                $patientPayments += (float) $pa->cash_amount;
            }
        }

        // Step 3: Refunds (outflows)
        $patientRefunds = 0.0;
        $refunds = \App\Models\PackageAdvances::where('account_id', $accountId)
            ->where('cash_flow', 'out')
            ->where('is_refund', 1)
            ->where('is_cancel', 0)
            ->whereNull('deleted_at')
            ->where('system_created_at', '>=', $goLiveDate)
            ->get(['cash_amount', 'payment_mode_id', 'location_id']);

        foreach ($refunds as $ref) {
            $poolId = $this->resolvePoolId($ref, $cashModeIds, $branchPoolMap, $hoPoolId, $bankPoolId);
            if ($poolId && isset($balances[$poolId])) {
                $balances[$poolId] -= (float) $ref->cash_amount;
                $patientRefunds += (float) $ref->cash_amount;
            }
        }

        // Step 4: Expenses (non-voided, non-rejected, with pool). Model A:
        // reject reverses the deduction and void removes it, so neither is
        // replayed here. Matches the incremental observer and crm2.
        $totalExpenses = 0.0;
        $expenses = \App\Models\CashFlow\Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->where('status', '!=', 'rejected')
            ->where('system_created_at', '>=', $goLiveDate)
            ->get(['amount', 'paid_from_pool_id']);

        foreach ($expenses as $exp) {
            if ($exp->paid_from_pool_id && isset($balances[$exp->paid_from_pool_id])) {
                $balances[$exp->paid_from_pool_id] -= (float) $exp->amount;
                $totalExpenses += (float) $exp->amount;
            }
        }

        // Step 5: Transfers (zero-sum but affects per-pool)
        $transfers = \App\Models\CashFlow\CashTransfer::where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->whereNull('voided_at')
            ->where('system_created_at', '>=', $goLiveDate)
            ->get(['amount', 'from_pool_id', 'to_pool_id']);

        foreach ($transfers as $tr) {
            if (isset($balances[$tr->from_pool_id])) {
                $balances[$tr->from_pool_id] -= (float) $tr->amount;
            }
            if (isset($balances[$tr->to_pool_id])) {
                $balances[$tr->to_pool_id] += (float) $tr->amount;
            }
        }

        // Step 6: Staff advances (debit pools)
        $staffAdvancesTotal = 0.0;
        $advances = \App\Models\CashFlow\StaffAdvance::where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->whereNull('voided_at')
            ->where('system_created_at', '>=', $goLiveDate)
            ->get(['amount', 'pool_id']);

        foreach ($advances as $adv) {
            if (isset($balances[$adv->pool_id])) {
                $balances[$adv->pool_id] -= (float) $adv->amount;
                $staffAdvancesTotal += (float) $adv->amount;
            }
        }

        // Step 7: Staff returns (credit pools)
        $staffReturnsTotal = 0.0;
        $returns = \App\Models\CashFlow\StaffReturn::where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->whereNull('voided_at')
            ->where('system_created_at', '>=', $goLiveDate)
            ->get(['amount', 'pool_id']);

        foreach ($returns as $ret) {
            if (isset($balances[$ret->pool_id])) {
                $balances[$ret->pool_id] += (float) $ret->amount;
                $staffReturnsTotal += (float) $ret->amount;
            }
        }

        $calculatedTotal = 0.0;
        $perPool = [];
        foreach ($pools as $pool) {
            $calculated = round($balances[$pool->id] ?? (float) $pool->opening_balance, 2);
            $perPool[] = [
                'pool_id' => $pool->id,
                'pool_name' => $pool->name,
                'cached_balance' => (float) $pool->cached_balance,
                'calculated_balance' => $calculated,
                'diff' => round((float) $pool->cached_balance - $calculated, 2),
            ];
            $calculatedTotal += $calculated;
        }

        return [
            'per_pool' => $perPool,
            'total' => round($calculatedTotal, 2),
            'breakdown' => [
                'opening_balances' => $openingTotal,
                'patient_payments' => $patientPayments,
                'patient_refunds' => $patientRefunds,
                'total_expenses' => $totalExpenses,
                'staff_advances' => $staffAdvancesTotal,
                'staff_returns' => $staffReturnsTotal,
            ],
        ];
    }

    private function clearCache(int $accountId): void
    {
        Cache::forget("cashflow_pools_{$accountId}");
    }
}
