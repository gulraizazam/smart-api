<?php

namespace App\Services\CashFlow;

use App\Helpers\CashflowHelper;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\CashTransfer;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\StaffAdvance;
use App\Models\CashFlow\StaffReturn;
use App\Models\CashFlow\Vendor;
use App\Models\CashFlow\VendorRequest;
use App\Models\CashFlow\CategoryRequest;
use App\Models\PackageAdvances;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private CashflowSettingService $settingService;

    public function __construct(CashflowSettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    /**
     * Get full dashboard data.
     */
    public function getDashboardData(int $accountId, array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? Carbon::now()->toDateString();
        $branchId = $filters['branch_id'] ?? null;

        $goLiveDate = $this->settingService->getGoLiveDate($accountId);

        return [
            'summary' => $this->getSummaryCards($accountId, $dateFrom, $dateTo, $branchId, $goLiveDate),
            'pools' => $this->getPoolBalances($accountId),
            'pending_actions' => $this->getPendingActions($accountId),
            'daily_trend' => $this->getDailyTrend($accountId, $dateFrom, $dateTo, $branchId, $goLiveDate),
            'category_breakdown' => $this->getCategoryBreakdown($accountId, $dateFrom, $dateTo, $branchId),
            'top_expenses' => $this->getTopExpenses($accountId, $dateFrom, $dateTo, $branchId),
            'cash_collection' => $this->getCashCollection($accountId, $goLiveDate),
            'vendor_outstanding' => $this->getVendorOutstanding($accountId),
            'vendor_trends' => $this->getVendorTrends($accountId),
            'staff_advances' => $this->getStaffAdvancesOutstanding($accountId),
            'recent_entries' => $this->getRecentEntries($accountId),
        ];
    }

    /**
     * Summary cards: Inflows | Outflows | Net with month-over-month %.
     */
    public function getSummaryCards(int $accountId, string $dateFrom, string $dateTo, ?int $branchId, ?string $goLiveDate): array
    {
        // Current period
        $inflows = $this->getInflows($accountId, $dateFrom, $dateTo, $branchId, $goLiveDate);
        $outflows = $this->getOutflows($accountId, $dateFrom, $dateTo, $branchId);

        // Previous period for comparison
        $daysDiff = Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo)) + 1;
        $prevTo = Carbon::parse($dateFrom)->subDay()->toDateString();
        $prevFrom = Carbon::parse($prevTo)->subDays($daysDiff - 1)->toDateString();

        $prevInflows = $this->getInflows($accountId, $prevFrom, $prevTo, $branchId, $goLiveDate);
        $prevOutflows = $this->getOutflows($accountId, $prevFrom, $prevTo, $branchId);

        return [
            'inflows' => $inflows,
            'outflows' => $outflows,
            'net' => $inflows - $outflows,
            'prev_inflows' => $prevInflows,
            'prev_outflows' => $prevOutflows,
            'prev_net' => $prevInflows - $prevOutflows,
            'inflow_change_pct' => $prevInflows > 0 ? round((($inflows - $prevInflows) / $prevInflows) * 100, 1) : null,
            'outflow_change_pct' => $prevOutflows > 0 ? round((($outflows - $prevOutflows) / $prevOutflows) * 100, 1) : null,
        ];
    }

    /**
     * Pool balance cards.
     */
    public function getPoolBalances(int $accountId): array
    {
        return CashPool::forAccount($accountId)
            ->active()
            ->with('location:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'cached_balance', 'location_id'])
            ->toArray();
    }

    /**
     * Pending actions: expenses awaiting approval, vendor requests, category requests, flagged entries.
     */
    public function getPendingActions(int $accountId): array
    {
        return [
            'pending_expenses' => Expense::forAccount($accountId)->where('status', 'pending')->whereNull('voided_at')->count(),
            'vendor_requests' => VendorRequest::forAccount($accountId)->pending()->count(),
            'category_requests' => CategoryRequest::forAccount($accountId)->pending()->count(),
            'flagged_entries' => Expense::forAccount($accountId)->where('is_flagged', true)->whereNull('voided_at')->count(),
        ];
    }

    /**
     * Daily trend: inflows vs outflows per day for the period.
     */
    public function getDailyTrend(int $accountId, string $dateFrom, string $dateTo, ?int $branchId, ?string $goLiveDate): array
    {
        // Outflows by day
        $outflowQuery = Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->whereBetween('expense_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $outflowQuery->where('for_branch_id', $branchId);
        }

        $outflows = $outflowQuery
            ->select(DB::raw('expense_date as date'), DB::raw('SUM(amount) as total'))
            ->groupBy('expense_date')
            ->pluck('total', 'date')
            ->toArray();

        // Inflows by day (patient payments)
        $inflowQuery = PackageAdvances::where('account_id', $accountId)
            ->where('cash_flow', 'in')
            ->where('is_cancel', 0)
            ->whereNull('deleted_at')
            ->whereBetween(DB::raw('DATE(created_at)'), [$dateFrom, $dateTo]);

        if ($goLiveDate) {
            $inflowQuery->where('created_at', '>=', $goLiveDate);
        }

        if ($branchId) {
            $inflowQuery->where('location_id', $branchId);
        }

        $inflows = $inflowQuery
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(cash_amount) as total'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'date')
            ->toArray();

        // Build day-by-day array
        $days = [];
        $current = Carbon::parse($dateFrom);
        $end = Carbon::parse($dateTo);

        while ($current->lte($end)) {
            $d = $current->toDateString();
            $days[] = [
                'date' => $d,
                'inflows' => (float) ($inflows[$d] ?? 0),
                'outflows' => (float) ($outflows[$d] ?? 0),
            ];
            $current->addDay();
        }

        return $days;
    }

    /**
     * Category breakdown for pie/bar charts.
     */
    public function getCategoryBreakdown(int $accountId, string $dateFrom, string $dateTo, ?int $branchId): array
    {
        $query = Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->join('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
            ->select('expense_categories.name as category', DB::raw('SUM(expenses.amount) as total'), DB::raw('COUNT(*) as count'));

        if ($branchId) {
            $query->where('expenses.for_branch_id', $branchId);
        }

        return $query
            ->groupBy('expense_categories.name')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    /**
     * Top 5 highest individual expenses this month.
     */
    public function getTopExpenses(int $accountId, string $dateFrom, string $dateTo, ?int $branchId): array
    {
        $query = Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->with(['category:id,name', 'pool:id,name', 'vendor:id,name', 'creator:id,name']);

        if ($branchId) {
            $query->where('for_branch_id', $branchId);
        }

        return $query
            ->orderByDesc('amount')
            ->limit(5)
            ->get()
            ->toArray();
    }

    /**
     * Cash collection per branch (patient payments today and this week).
     */
    public function getCashCollection(int $accountId, ?string $goLiveDate): array
    {
        $today = Carbon::today()->toDateString();
        $weekStart = Carbon::now()->startOfWeek()->toDateString();

        $baseQuery = function () use ($accountId, $goLiveDate) {
            $q = PackageAdvances::where('account_id', $accountId)
                ->where('cash_flow', 'in')
                ->where('is_cancel', 0)
                ->whereNull('deleted_at');

            if ($goLiveDate) {
                $q->where('created_at', '>=', $goLiveDate);
            }

            return $q;
        };

        $todayCollection = $baseQuery()
            ->whereDate('created_at', $today)
            ->select('location_id', DB::raw('SUM(cash_amount) as total'))
            ->groupBy('location_id')
            ->pluck('total', 'location_id')
            ->toArray();

        $weekCollection = $baseQuery()
            ->whereBetween(DB::raw('DATE(created_at)'), [$weekStart, $today])
            ->select('location_id', DB::raw('SUM(cash_amount) as total'))
            ->groupBy('location_id')
            ->pluck('total', 'location_id')
            ->toArray();

        // Map to branch names
        $branches = CashflowHelper::getActiveBranches($accountId);
        $result = [];

        foreach ($branches as $branch) {
            $result[] = [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'today' => (float) ($todayCollection[$branch->id] ?? 0),
                'this_week' => (float) ($weekCollection[$branch->id] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Vendor outstanding: top 10 by highest balance.
     */
    public function getVendorOutstanding(int $accountId): array
    {
        return Vendor::forAccount($accountId)
            ->where('cached_balance', '>', 0)
            ->orderByDesc('cached_balance')
            ->limit(10)
            ->get(['id', 'name', 'cached_balance', 'payment_terms'])
            ->toArray();
    }

    /**
     * Vendor purchase trends: top 5 vendors by spend, last 6 months.
     */
    public function getVendorTrends(int $accountId): array
    {
        $sixMonthsAgo = Carbon::now()->subMonths(6)->startOfMonth()->toDateString();

        // Top 5 vendors by total spend
        $topVendors = Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->whereNotNull('vendor_id')
            ->where('expense_date', '>=', $sixMonthsAgo)
            ->select('vendor_id', DB::raw('SUM(amount) as total'))
            ->groupBy('vendor_id')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'vendor_id')
            ->toArray();

        if (empty($topVendors)) {
            return [];
        }

        $vendorIds = array_keys($topVendors);
        $vendors = Vendor::whereIn('id', $vendorIds)->pluck('name', 'id');

        // Monthly breakdown per vendor
        $monthly = Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->whereIn('vendor_id', $vendorIds)
            ->where('expense_date', '>=', $sixMonthsAgo)
            ->select('vendor_id', DB::raw("DATE_FORMAT(expense_date, '%Y-%m') as month"), DB::raw('SUM(amount) as total'))
            ->groupBy('vendor_id', DB::raw("DATE_FORMAT(expense_date, '%Y-%m')"))
            ->get();

        $result = [];
        foreach ($vendorIds as $vid) {
            $vendorMonthly = $monthly->where('vendor_id', $vid)->pluck('total', 'month')->toArray();
            $result[] = [
                'vendor_id' => $vid,
                'vendor_name' => $vendors[$vid] ?? 'Unknown',
                'total' => $topVendors[$vid],
                'monthly' => $vendorMonthly,
            ];
        }

        return $result;
    }

    /**
     * Staff advances outstanding with aging.
     */
    public function getStaffAdvancesOutstanding(int $accountId): array
    {
        $agingDays = (int) $this->settingService->get('advance_aging_days', $accountId, 15);

        $advances = StaffAdvance::where('staff_advances.account_id', $accountId)
            ->join('users', 'staff_advances.user_id', '=', 'users.id')
            ->select(
                'staff_advances.user_id',
                'users.name',
                DB::raw('SUM(staff_advances.amount) as total_advances')
            )
            ->whereNull('staff_advances.deleted_at')
            ->groupBy('staff_advances.user_id', 'users.name')
            ->get();

        $returns = StaffReturn::where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->select('user_id', DB::raw('SUM(amount) as total_returns'))
            ->groupBy('user_id')
            ->pluck('total_returns', 'user_id');

        // Expenses by staff
        $staffExpenses = Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->whereNotNull('staff_id')
            ->select('staff_id', DB::raw('SUM(amount) as total_expenses'))
            ->groupBy('staff_id')
            ->pluck('total_expenses', 'staff_id');

        // Last advance date for aging
        $lastAdvanceDates = StaffAdvance::where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->select('user_id', DB::raw('MAX(created_at) as last_advance'))
            ->groupBy('user_id')
            ->pluck('last_advance', 'user_id');

        $result = [];
        foreach ($advances as $adv) {
            $returnAmt = (float) ($returns[$adv->user_id] ?? 0);
            $expenseAmt = (float) ($staffExpenses[$adv->user_id] ?? 0);
            $outstanding = (float) $adv->total_advances - $expenseAmt - $returnAmt;

            if ($outstanding <= 0) continue;

            $lastDate = $lastAdvanceDates[$adv->user_id] ?? null;
            $daysSince = $lastDate ? Carbon::parse($lastDate)->diffInDays(Carbon::now()) : 0;

            // aging: green < agingDays, amber = agingDays to 2x, red > 2x
            $aging = 'green';
            if ($daysSince > $agingDays * 2) {
                $aging = 'red';
            } elseif ($daysSince > $agingDays) {
                $aging = 'amber';
            }

            $result[] = [
                'user_id' => $adv->user_id,
                'name' => $adv->name,
                'outstanding' => $outstanding,
                'days_since_last' => $daysSince,
                'aging' => $aging,
            ];
        }

        // Sort by outstanding descending
        usort($result, fn ($a, $b) => $b['outstanding'] <=> $a['outstanding']);

        return $result;
    }

    /**
     * Accountant-specific widgets.
     */
    public function getAccountantWidgets(int $accountId, int $userId): array
    {
        $today = Carbon::today()->toDateString();

        return [
            'my_entries_today' => [
                'count' => Expense::forAccount($accountId)->where('created_by', $userId)->whereDate('created_at', $today)->count(),
                'total' => (float) Expense::forAccount($accountId)->where('created_by', $userId)->whereDate('created_at', $today)->sum('amount'),
            ],
            'rejected_needing_reentry' => Expense::forAccount($accountId)
                ->where('created_by', $userId)
                ->where('status', 'rejected')
                ->whereNull('voided_at')
                ->count(),
            'missing_attachments' => Expense::forAccount($accountId)
                ->where('created_by', $userId)
                ->whereNull('attachment_url')
                ->whereNull('voided_at')
                ->count(),
        ];
    }

    /**
     * Recent 5 entries today.
     */
    public function getRecentEntries(int $accountId): array
    {
        return Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->with(['category:id,name', 'pool:id,name', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->toArray();
    }

    /**
     * Reconciliation check: compare cached vs calculated balances.
     * Sum(Pool Balances) = Patient Payments - Expenses + Opening Balances - Staff Advances + Staff Returns
     */
    public function reconciliationCheck(int $accountId): array
    {
        $goLiveDate = $this->settingService->getGoLiveDate($accountId);

        // Cached pool balance total
        $cachedTotal = (float) CashPool::forAccount($accountId)->active()->sum('cached_balance');

        // Opening balances
        $openingTotal = (float) CashPool::forAccount($accountId)->sum('opening_balance');

        // Patient payments (inflows) since go-live
        $patientPayments = 0.0;
        if ($goLiveDate) {
            $patientPayments = (float) PackageAdvances::where('account_id', $accountId)
                ->where('cash_flow', 'in')
                ->where('is_cancel', 0)
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $goLiveDate)
                ->sum('cash_amount');
        }

        // Expenses (outflows)
        $expenses = (float) Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->sum('amount');

        // Staff advances (outflows from pools)
        $staffAdvances = (float) StaffAdvance::where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->sum('amount');

        // Staff returns (inflows to pools)
        $staffReturns = (float) StaffReturn::where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->sum('amount');

        // Expected = Opening + Patient Payments - Expenses - Staff Advances + Staff Returns
        $expectedTotal = $openingTotal + $patientPayments - $expenses - $staffAdvances + $staffReturns;

        $discrepancy = round($cachedTotal - $expectedTotal, 2);

        return [
            'cached_total' => $cachedTotal,
            'calculated_total' => $expectedTotal,
            'opening_balances' => $openingTotal,
            'patient_payments' => $patientPayments,
            'total_expenses' => $expenses,
            'staff_advances' => $staffAdvances,
            'staff_returns' => $staffReturns,
            'discrepancy' => $discrepancy,
            'is_balanced' => abs($discrepancy) < 1, // Allow < 1 PKR rounding
        ];
    }

    // ===================== PRIVATE HELPERS =====================

    /**
     * Get total inflows (patient payments) for a period.
     */
    private function getInflows(int $accountId, string $dateFrom, string $dateTo, ?int $branchId, ?string $goLiveDate): float
    {
        $query = PackageAdvances::where('account_id', $accountId)
            ->where('cash_flow', 'in')
            ->where('is_cancel', 0)
            ->whereNull('deleted_at')
            ->whereBetween(DB::raw('DATE(created_at)'), [$dateFrom, $dateTo]);

        if ($goLiveDate) {
            $query->where('created_at', '>=', $goLiveDate);
        }

        if ($branchId) {
            $query->where('location_id', $branchId);
        }

        return (float) $query->sum('cash_amount');
    }

    /**
     * Get total outflows (expenses) for a period.
     */
    private function getOutflows(int $accountId, string $dateFrom, string $dateTo, ?int $branchId): float
    {
        $query = Expense::forAccount($accountId)
            ->whereNull('voided_at')
            ->whereBetween('expense_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('for_branch_id', $branchId);
        }

        return (float) $query->sum('amount');
    }
}
