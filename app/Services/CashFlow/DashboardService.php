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
            'vendor_outstanding' => $this->getVendorOutstanding($accountId),
            'vendor_due_soon' => $this->getVendorPaymentsDueSoon($accountId),
            'vendor_trends' => $this->getVendorTrends($accountId),
            'staff_advances' => $this->getStaffAdvancesOutstanding($accountId),
            'staff_expenses' => $this->getRecentStaffExpenses($accountId),
            'recent_entries' => $this->getRecentEntries($accountId),
            'voided_recent' => $this->getRecentVoidedEntries($accountId),
            'flagged_entries' => $this->getFlaggedEntries($accountId),
            'pending_expenses' => $this->getPendingExpensesList($accountId),
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
            ->orderByRaw("CASE WHEN type = 'bank_account' THEN 1 ELSE 0 END")
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'cached_balance', 'location_id'])
            ->toArray();
    }

    /**
     * Pending actions: expenses awaiting approval, vendor requests, category requests, flagged entries.
     */
    public function getPendingActions(int $accountId): array
    {
        // Staff advances outstanding = total advances - expenses by staff - returns
        $totalAdvances = (float) StaffAdvance::where('account_id', $accountId)->whereNull('deleted_at')->sum('amount');
        $totalReturns = (float) StaffReturn::where('account_id', $accountId)->whereNull('deleted_at')->sum('amount');
        $staffExpenses = (float) Expense::forAccount($accountId)->whereNull('voided_at')->whereNotNull('staff_id')->sum('amount');
        $advancesOutstanding = max(0, $totalAdvances - $staffExpenses - $totalReturns);

        return [
            'pending_expenses' => Expense::forAccount($accountId)->where('status', 'pending')->whereNull('voided_at')->count(),
            'flagged_entries' => Expense::forAccount($accountId)->where('is_flagged', true)->whereNull('voided_at')->count(),
            'no_receipt_count' => Expense::forAccount($accountId)->whereNull('voided_at')->whereNull('attachment_url')->count(),
            'today_total' => (float) Expense::forAccount($accountId)->whereNull('voided_at')->whereDate('expense_date', Carbon::today())->sum('amount'),
            'mtd_total' => (float) Expense::forAccount($accountId)->whereNull('voided_at')->whereBetween('expense_date', [Carbon::now()->startOfMonth()->toDateString(), Carbon::today()->toDateString()])->sum('amount'),
            'advances_outstanding' => $advancesOutstanding,
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
     * Upcoming vendor payments due soon (based on payment terms).
     */
    public function getVendorPaymentsDueSoon(int $accountId): array
    {
        $vendors = Vendor::forAccount($accountId)
            ->where('is_active', 1)
            ->where('cached_balance', '>', 0)
            ->whereNotNull('payment_terms')
            ->where('payment_terms', '!=', 'custom')
            ->get(['id', 'name', 'cached_balance', 'payment_terms']);

        $termDays = [
            'upfront' => 0,
            'net_7' => 7,
            'net_15' => 15,
            'net_30' => 30,
        ];

        $results = [];
        foreach ($vendors as $vendor) {
            $days = $termDays[$vendor->payment_terms] ?? null;
            if ($days === null || $days === 0) continue;

            // Find the latest unpaid purchase transaction date
            $lastPurchase = \App\Models\CashFlow\VendorTransaction::where('vendor_id', $vendor->id)
                ->where('type', 'purchase')
                ->orderByDesc('created_at')
                ->value('created_at');

            if (!$lastPurchase) continue;

            $dueDate = Carbon::parse($lastPurchase)->addDays($days);
            $daysUntilDue = (int) Carbon::now()->diffInDays($dueDate, false);

            // Show vendors due within 7 days or overdue
            if ($daysUntilDue <= 7) {
                $results[] = [
                    'id' => $vendor->id,
                    'name' => $vendor->name,
                    'balance' => (float) $vendor->cached_balance,
                    'payment_terms' => $vendor->payment_terms,
                    'due_date' => $dueDate->toDateString(),
                    'days_until_due' => $daysUntilDue,
                    'is_overdue' => $daysUntilDue < 0,
                ];
            }
        }

        // Sort by days_until_due ascending (most urgent first)
        usort($results, fn($a, $b) => $a['days_until_due'] <=> $b['days_until_due']);

        return array_slice($results, 0, 10);
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
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Recent 10 expenses made by staff (expenses with staff_id set).
     */
    public function getRecentStaffExpenses(int $accountId): array
    {
        return Expense::forAccount($accountId)
            ->whereNotNull('staff_id')
            ->whereNull('voided_at')
            ->with(['staff:id,name', 'category:id,name'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'expense_date', 'amount', 'description', 'staff_id', 'category_id', 'status'])
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

        // Expenses (outflows) since go-live
        $expenses = 0.0;
        if ($goLiveDate) {
            $expenses = (float) Expense::forAccount($accountId)
                ->whereNull('voided_at')
                ->where('status', '!=', 'rejected')
                ->where('expense_date', '>=', $goLiveDate)
                ->sum('amount');
        }

        // Staff advances (outflows from pools) since go-live
        $staffAdvances = 0.0;
        if ($goLiveDate) {
            $staffAdvances = (float) StaffAdvance::where('account_id', $accountId)
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $goLiveDate)
                ->sum('amount');
        }

        // Staff returns (inflows to pools) since go-live
        $staffReturns = 0.0;
        if ($goLiveDate) {
            $staffReturns = (float) StaffReturn::where('account_id', $accountId)
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $goLiveDate)
                ->sum('amount');
        }

        // Patient refunds (outflows from pools)
        $patientRefunds = 0.0;
        if ($goLiveDate) {
            $patientRefunds = (float) PackageAdvances::where('account_id', $accountId)
                ->where('cash_flow', 'out')
                ->where('is_refund', 1)
                ->where('is_cancel', 0)
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $goLiveDate)
                ->sum('cash_amount');
        }

        // Expected = Opening + Patient Payments - Refunds - Expenses - Staff Advances + Staff Returns
        $expectedTotal = $openingTotal + $patientPayments - $patientRefunds - $expenses - $staffAdvances + $staffReturns;

        $discrepancy = round($cachedTotal - $expectedTotal, 2);

        return [
            'cached_total' => $cachedTotal,
            'calculated_total' => $expectedTotal,
            'opening_balances' => $openingTotal,
            'patient_payments' => $patientPayments,
            'patient_refunds' => $patientRefunds,
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
     * Pending expenses list for inline approve/reject on dashboard (Sec 16 Screen 1).
     */
    public function getPendingExpensesList(int $accountId): array
    {
        return Expense::forAccount($accountId)
            ->where('status', 'pending')
            ->whereNull('voided_at')
            ->with(['category:id,name', 'creator:id,name'])
            ->orderByDesc('expense_date')
            ->limit(15)
            ->get(['id', 'expense_date', 'amount', 'description', 'category_id', 'attachment_url', 'created_by'])
            ->toArray();
    }

    /**
     * Voided entries in last 7 days for dashboard alert (Sec 11.5).
     */
    public function getRecentVoidedEntries(int $accountId): array
    {
        $days = (int) $this->settingService->get('void_alert_days', $accountId, 7);

        return Expense::forAccount($accountId)
            ->whereNotNull('voided_at')
            ->where('voided_at', '>=', Carbon::now()->subDays($days))
            ->with(['category:id,name', 'voidedByUser:id,name', 'forBranch:id,name'])
            ->orderByDesc('voided_at')
            ->limit(10)
            ->get(['id', 'expense_date', 'amount', 'description', 'category_id', 'for_branch_id', 'voided_at', 'voided_by', 'void_reason'])
            ->toArray();
    }

    /**
     * Flagged entries for dashboard alert.
     */
    public function getFlaggedEntries(int $accountId): array
    {
        return Expense::forAccount($accountId)
            ->where('is_flagged', true)
            ->whereNull('voided_at')
            ->with(['category:id,name', 'creator:id,name'])
            ->orderByDesc('expense_date')
            ->limit(10)
            ->get(['id', 'expense_date', 'amount', 'description', 'category_id', 'created_by', 'flag_reason'])
            ->toArray();
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
