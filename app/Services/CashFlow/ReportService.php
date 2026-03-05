<?php

namespace App\Services\CashFlow;

use App\Helpers\CashflowHelper;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\CashTransfer;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\StaffAdvance;
use App\Models\CashFlow\StaffReturn;
use App\Models\CashFlow\Vendor;
use App\Models\PackageAdvances;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    private CashflowSettingService $settingService;

    public function __construct(CashflowSettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    /**
     * Primary report: Cash Flow Statement.
     * A: Opening Balance, B: Inflows, C: Outflows (by category), D: Net, E: Closing, F: Pool Breakdown
     */
    public function cashFlowStatement(int $accountId, array $filters): array
    {
        $dateFrom = $filters['date_from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? Carbon::now()->toDateString();
        $branchId = $filters['branch_id'] ?? null;
        $poolId = $filters['pool_id'] ?? null;
        $goLiveDate = $this->settingService->getGoLiveDate($accountId);

        // A: Opening balance (sum of pool opening_balance + transactions before dateFrom)
        $openingBalance = $this->calculateOpeningBalance($accountId, $dateFrom, $branchId, $poolId, $goLiveDate);

        // B: Inflows (patient payments in period)
        $inflows = $this->getInflowsBreakdown($accountId, $dateFrom, $dateTo, $branchId, $goLiveDate);

        // C: Outflows (grouped by category)
        $outflows = $this->getOutflowsByCategory($accountId, $dateFrom, $dateTo, $branchId, $poolId);

        $totalInflows = array_sum(array_column($inflows, 'total'));
        $totalOutflows = array_sum(array_column($outflows, 'total'));

        // D: Net
        $net = $totalInflows - $totalOutflows;

        // E: Closing
        $closingBalance = $openingBalance + $net;

        // F: Pool breakdown
        $poolBreakdown = $this->getPoolBreakdown($accountId);

        return [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'opening_balance' => $openingBalance,
            'inflows' => $inflows,
            'total_inflows' => $totalInflows,
            'outflows' => $outflows,
            'total_outflows' => $totalOutflows,
            'net_cash_flow' => $net,
            'closing_balance' => $closingBalance,
            'pool_breakdown' => $poolBreakdown,
        ];
    }

    /**
     * Branch comparison report.
     */
    public function branchComparison(int $accountId, array $filters): array
    {
        $dateFrom = $filters['date_from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? Carbon::now()->toDateString();
        $goLiveDate = $this->settingService->getGoLiveDate($accountId);

        $branches = CashflowHelper::getActiveBranches($accountId);

        // Expenses by branch
        $expensesByBranch = Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->select('for_branch_id', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('for_branch_id')
            ->get()
            ->keyBy('for_branch_id');

        // Inflows by branch
        $inflowsByBranch = [];
        if ($goLiveDate) {
            $inflowsByBranch = PackageAdvances::where('account_id', $accountId)
                ->where('cash_flow', 'in')
                ->where('is_cancel', 0)
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $goLiveDate)
                ->whereBetween(DB::raw('DATE(created_at)'), [$dateFrom, $dateTo])
                ->select('location_id', DB::raw('SUM(cash_amount) as total'))
                ->groupBy('location_id')
                ->pluck('total', 'location_id')
                ->toArray();
        }

        $result = [];
        foreach ($branches as $branch) {
            $exp = $expensesByBranch->get($branch->id);
            $result[] = [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'inflows' => (float) ($inflowsByBranch[$branch->id] ?? 0),
                'outflows' => (float) ($exp->total ?? 0),
                'expense_count' => (int) ($exp->count ?? 0),
                'net' => (float) ($inflowsByBranch[$branch->id] ?? 0) - (float) ($exp->total ?? 0),
            ];
        }

        // General / Company-wide (for_branch_id = null)
        $general = $expensesByBranch->get(null);
        $result[] = [
            'branch_id' => null,
            'branch_name' => 'General / Company-wide',
            'inflows' => 0,
            'outflows' => (float) ($general->total ?? 0),
            'expense_count' => (int) ($general->count ?? 0),
            'net' => 0 - (float) ($general->total ?? 0),
        ];

        return $result;
    }

    /**
     * Category trend: monthly per category.
     */
    public function categoryTrend(int $accountId, array $filters): array
    {
        $months = (int) ($filters['months'] ?? 6);
        $dateFrom = Carbon::now()->subMonths($months - 1)->startOfMonth()->toDateString();
        $dateTo = Carbon::now()->toDateString();

        return Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->join('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
            ->select(
                'expense_categories.name as category',
                DB::raw("DATE_FORMAT(expense_date, '%Y-%m') as month"),
                DB::raw('SUM(expenses.amount) as total')
            )
            ->groupBy('expense_categories.name', DB::raw("DATE_FORMAT(expense_date, '%Y-%m')"))
            ->orderBy('month')
            ->get()
            ->toArray();
    }

    /**
     * Vendor outstanding report.
     */
    public function vendorOutstanding(int $accountId): array
    {
        return Vendor::forAccount($accountId)
            ->where(function ($q) {
                $q->where('cached_balance', '!=', 0)->orWhere('opening_balance', '>', 0);
            })
            ->orderByDesc('cached_balance')
            ->get(['id', 'name', 'opening_balance', 'cached_balance', 'payment_terms', 'is_active'])
            ->toArray();
    }

    /**
     * Staff advance summary with aging.
     */
    public function staffAdvanceSummary(int $accountId): array
    {
        $agingDays = (int) $this->settingService->get('advance_aging_days', $accountId, 15);

        $advances = StaffAdvance::where('staff_advances.account_id', $accountId)
            ->join('users', 'staff_advances.user_id', '=', 'users.id')
            ->whereNull('staff_advances.deleted_at')
            ->select('staff_advances.user_id', 'users.name', DB::raw('SUM(staff_advances.amount) as total_advances'), DB::raw('MIN(staff_advances.created_at) as first_advance'), DB::raw('MAX(staff_advances.created_at) as last_advance'))
            ->groupBy('staff_advances.user_id', 'users.name')
            ->get();

        $returns = StaffReturn::where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->select('user_id', DB::raw('SUM(amount) as total_returns'))
            ->groupBy('user_id')
            ->pluck('total_returns', 'user_id');

        $staffExpenses = Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->whereNotNull('staff_id')
            ->select('staff_id', DB::raw('SUM(amount) as total_expenses'))
            ->groupBy('staff_id')
            ->pluck('total_expenses', 'staff_id');

        $result = [];
        foreach ($advances as $adv) {
            $returnAmt = (float) ($returns[$adv->user_id] ?? 0);
            $expenseAmt = (float) ($staffExpenses[$adv->user_id] ?? 0);
            $outstanding = (float) $adv->total_advances - $expenseAmt - $returnAmt;
            $daysSince = $adv->last_advance ? Carbon::parse($adv->last_advance)->diffInDays(Carbon::now()) : 0;

            $aging = 'green';
            if ($daysSince > $agingDays * 2) $aging = 'red';
            elseif ($daysSince > $agingDays) $aging = 'amber';

            $result[] = [
                'user_id' => $adv->user_id,
                'name' => $adv->name,
                'total_advances' => (float) $adv->total_advances,
                'total_expenses' => $expenseAmt,
                'total_returns' => $returnAmt,
                'outstanding' => $outstanding,
                'first_advance' => $adv->first_advance,
                'last_advance' => $adv->last_advance,
                'days_since_last' => $daysSince,
                'aging' => $aging,
            ];
        }

        return $result;
    }

    /**
     * Daily cash movement per pool.
     */
    public function dailyMovement(int $accountId, array $filters): array
    {
        $dateFrom = $filters['date_from'] ?? Carbon::now()->subDays(30)->toDateString();
        $dateTo = $filters['date_to'] ?? Carbon::now()->toDateString();
        $poolId = $filters['pool_id'] ?? null;

        // Expenses (outflows)
        $expenseQuery = Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->whereBetween('expense_date', [$dateFrom, $dateTo]);
        if ($poolId) $expenseQuery->where('paid_from_pool_id', $poolId);

        $expenses = $expenseQuery
            ->select('expense_date as date', 'paid_from_pool_id as pool_id', DB::raw('SUM(amount) as total'))
            ->groupBy('expense_date', 'paid_from_pool_id')
            ->get();

        // Transfers out
        $transfersOut = CashTransfer::forAccount($accountId)
            ->whereBetween('transfer_date', [$dateFrom, $dateTo]);
        if ($poolId) $transfersOut->where('from_pool_id', $poolId);

        $tOut = $transfersOut
            ->select('transfer_date as date', 'from_pool_id as pool_id', DB::raw('SUM(amount) as total'))
            ->groupBy('transfer_date', 'from_pool_id')
            ->get();

        // Transfers in
        $transfersIn = CashTransfer::forAccount($accountId)
            ->whereBetween('transfer_date', [$dateFrom, $dateTo]);
        if ($poolId) $transfersIn->where('to_pool_id', $poolId);

        $tIn = $transfersIn
            ->select('transfer_date as date', 'to_pool_id as pool_id', DB::raw('SUM(amount) as total'))
            ->groupBy('transfer_date', 'to_pool_id')
            ->get();

        return [
            'expenses' => $expenses->toArray(),
            'transfers_out' => $tOut->toArray(),
            'transfers_in' => $tIn->toArray(),
        ];
    }

    /**
     * Transfer log.
     */
    public function transferLog(int $accountId, array $filters): array
    {
        $dateFrom = $filters['date_from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? Carbon::now()->toDateString();

        $query = CashTransfer::forAccount($accountId)
            ->whereBetween('transfer_date', [$dateFrom, $dateTo])
            ->with(['fromPool:id,name', 'toPool:id,name', 'creator:id,name'])
            ->orderByDesc('transfer_date');

        if (!empty($filters['pool_id'])) {
            $pid = $filters['pool_id'];
            $query->where(function ($q) use ($pid) {
                $q->where('from_pool_id', $pid)->orWhere('to_pool_id', $pid);
            });
        }

        return $query->get()->toArray();
    }

    /**
     * Flagged entries report.
     */
    public function flaggedEntries(int $accountId, array $filters): array
    {
        $query = Expense::forAccount($accountId)
            ->where('is_flagged', true)
            ->with(['category:id,name', 'pool:id,name', 'vendor:id,name', 'creator:id,name'])
            ->orderByDesc('created_at');

        if (!empty($filters['date_from'])) {
            $query->where('expense_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('expense_date', '<=', $filters['date_to']);
        }

        return $query->get()->toArray();
    }

    /**
     * Dormant vendors: 90+ days no activity.
     */
    public function dormantVendors(int $accountId): array
    {
        $dormantDays = (int) $this->settingService->get('dormant_vendor_days', $accountId, 90);
        $cutoffDate = Carbon::now()->subDays($dormantDays)->toDateString();

        // All active vendors
        $vendors = Vendor::forAccount($accountId)
            ->active()
            ->get(['id', 'name', 'cached_balance', 'updated_at']);

        // Last transaction date per vendor
        $lastActivity = Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->whereNotNull('vendor_id')
            ->select('vendor_id', DB::raw('MAX(expense_date) as last_date'))
            ->groupBy('vendor_id')
            ->pluck('last_date', 'vendor_id');

        $result = [];
        foreach ($vendors as $vendor) {
            $lastDate = $lastActivity[$vendor->id] ?? null;

            if ($lastDate && $lastDate >= $cutoffDate) continue; // Active vendor
            if (!$lastDate) $lastDate = $vendor->updated_at?->toDateString(); // Never had activity

            $daysSince = $lastDate ? Carbon::parse($lastDate)->diffInDays(Carbon::now()) : null;

            $result[] = [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'cached_balance' => (float) $vendor->cached_balance,
                'last_activity' => $lastDate,
                'days_inactive' => $daysSince,
            ];
        }

        // Sort by days inactive descending
        usort($result, fn ($a, $b) => ($b['days_inactive'] ?? 9999) <=> ($a['days_inactive'] ?? 9999));

        return $result;
    }

    // ===================== PRIVATE HELPERS =====================

    private function calculateOpeningBalance(int $accountId, string $dateFrom, ?int $branchId, ?int $poolId, ?string $goLiveDate): float
    {
        // Pool opening balances
        $poolQuery = CashPool::forAccount($accountId);
        if ($poolId) $poolQuery->where('id', $poolId);
        $openingBalances = (float) $poolQuery->sum('opening_balance');

        // Inflows before dateFrom
        $inflowQuery = PackageAdvances::where('account_id', $accountId)
            ->where('cash_flow', 'in')
            ->where('is_cancel', 0)
            ->whereNull('deleted_at')
            ->where(DB::raw('DATE(created_at)'), '<', $dateFrom);

        if ($goLiveDate) $inflowQuery->where('created_at', '>=', $goLiveDate);
        if ($branchId) $inflowQuery->where('location_id', $branchId);

        $priorInflows = (float) $inflowQuery->sum('cash_amount');

        // Outflows before dateFrom
        $outflowQuery = Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->where('expense_date', '<', $dateFrom);

        if ($branchId) $outflowQuery->where('for_branch_id', $branchId);
        if ($poolId) $outflowQuery->where('paid_from_pool_id', $poolId);

        $priorOutflows = (float) $outflowQuery->sum('amount');

        // Staff advances/returns before dateFrom
        $priorAdvances = (float) StaffAdvance::where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->where(DB::raw('DATE(created_at)'), '<', $dateFrom)
            ->sum('amount');

        $priorReturns = (float) StaffReturn::where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->where(DB::raw('DATE(created_at)'), '<', $dateFrom)
            ->sum('amount');

        return $openingBalances + $priorInflows - $priorOutflows - $priorAdvances + $priorReturns;
    }

    private function getInflowsBreakdown(int $accountId, string $dateFrom, string $dateTo, ?int $branchId, ?string $goLiveDate): array
    {
        $query = PackageAdvances::where('package_advances.account_id', $accountId)
            ->where('package_advances.cash_flow', 'in')
            ->where('package_advances.is_cancel', 0)
            ->whereNull('package_advances.deleted_at')
            ->whereBetween(DB::raw('DATE(package_advances.created_at)'), [$dateFrom, $dateTo])
            ->join('payment_modes', 'package_advances.payment_mode_id', '=', 'payment_modes.id');

        if ($goLiveDate) $query->where('package_advances.created_at', '>=', $goLiveDate);
        if ($branchId) $query->where('package_advances.location_id', $branchId);

        return $query
            ->select('payment_modes.name as method', DB::raw('SUM(package_advances.cash_amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_modes.name')
            ->get()
            ->toArray();
    }

    private function getOutflowsByCategory(int $accountId, string $dateFrom, string $dateTo, ?int $branchId, ?int $poolId): array
    {
        $query = Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->join('expense_categories', 'expenses.category_id', '=', 'expense_categories.id');

        if ($branchId) $query->where('expenses.for_branch_id', $branchId);
        if ($poolId) $query->where('expenses.paid_from_pool_id', $poolId);

        return $query
            ->select('expense_categories.name as category', DB::raw('SUM(expenses.amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('expense_categories.name')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    private function getPoolBreakdown(int $accountId): array
    {
        return CashPool::forAccount($accountId)
            ->active()
            ->with('location:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'cached_balance', 'opening_balance', 'location_id'])
            ->toArray();
    }
}
