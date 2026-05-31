<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CashFlow;

use App\Exceptions\CashflowException;
use App\Helpers\CashflowHelper;
use App\Http\Controllers\Controller;
use App\Models\PaymentModes;
use App\Services\CashFlow\CashflowSettingService;
use App\Services\CashFlow\DashboardService;
use App\Services\CashFlow\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CashFlowDashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly NotificationService $notificationService,
        private readonly CashflowSettingService $settingService,
    ) {}

    /**
     * Dashboard data endpoint.
     */
    public function dashboardData(Request $request): JsonResponse
    {
        if (Gate::denies('cashflow.dashboard.view')) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403);
        }

        try {

            $accountId = Auth::user()->account_id;

            $filters = $request->only(['date_from', 'date_to', 'branch_id']);



            $data = $this->dashboardService->getDashboardData($accountId, $filters);



            // Add accountant widgets if user is accountant

            if (Gate::allows('cashflow.expense.create') && !Gate::allows('cashflow.settings.manage')) {

                $data['accountant_widgets'] = $this->dashboardService->getAccountantWidgets($accountId, Auth::id());

            }



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Slim snapshot endpoint — returns only the three sections the
     * SPA's Cash Flow Statement panel actually renders (summary, pools,
     * daily trend), skipping the dozen-plus aggregations that
     * dashboardData computes for the legacy admin page. Cuts response
     * time substantially when the consumer doesn't need vendor /
     * staff / pending / category sections.
     */
    public function dashboardSnapshot(Request $request): JsonResponse
    {
        if (Gate::denies('cashflow.dashboard.view')) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

        try {

            $accountId = Auth::user()->account_id;

            $filters = $request->only(['date_from', 'date_to', 'branch_id']);
            [$dateFrom, $dateTo] = CashflowHelper::defaultDateRange($filters);
            $branchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
            $goLiveDate = $this->settingService->getGoLiveDate($accountId);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary'     => $this->dashboardService->getSummaryCards($accountId, $goLiveDate),
                    'pools'       => $this->dashboardService->getPoolBalances($accountId),
                    'daily_trend' => $this->dashboardService->getDailyTrend($accountId, $dateFrom, $dateTo, $branchId, $goLiveDate),
                    'vendors'     => $this->dashboardService->getVendorSnapshot($accountId),
                ],
            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }
    }

    /**
     * Reconciliation check (admin only).
     */
    public function dashboardReconciliation(): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow.settings.manage')) {

                throw CashflowException::unauthorized('run reconciliation');

            }



            $accountId = Auth::user()->account_id;

            $result = $this->dashboardService->reconciliationCheck($accountId);



            return response()->json(['success' => true, 'data' => $result]);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * FDM Cash View data — read-only, shows all assigned branches.
     * Returns: current balance, opening balance (Sunday), transfers, expenses, staff advances for the current week.
     */
    public function fdmData(Request $request): JsonResponse
    {

        if (Gate::denies('cashflow.fdm.view')) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

        try {

            $accountId = Auth::user()->account_id;

            // Cache the per-tenant cash-mode IDs once — six call sites below
            // would otherwise re-run the SELECT for every block.
            $cashModeIds = PaymentModes::cashIds($accountId);

            $userBranches = CashflowHelper::getUserBranches();

            if ($userBranches->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No branch assigned.'], 403);
            }

            // Return branches list for dropdown if requested
            if ($request->has('branches_only')) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'branches' => $userBranches->map(fn($b) => ['id' => $b->id, 'name' => $b->name])->values(),
                    ],
                ]);
            }

            // Filter by single branch if provided
            $filterBranchId = $request->input('branch_id');
            if ($filterBranchId && $userBranches->pluck('id')->contains((int) $filterBranchId)) {
                $branchIds = [(int) $filterBranchId];
                $branchName = $userBranches->firstWhere('id', (int) $filterBranchId)->name;
            } else {
                $branchIds = $userBranches->pluck('id')->toArray();
                $branchName = $userBranches->count() === 1
                    ? $userBranches->first()->name
                    : 'All Centres';
            }

            // Get branch cash pools for selected branches
            $pools = \App\Models\CashFlow\CashPool::forAccount($accountId)
                ->whereIn('location_id', $branchIds)
                ->where('type', 'branch_cash')
                ->get();



            $poolIds = $pools->pluck('id')->toArray();

            $currentBalance = (float) $pools->sum('cached_balance');



            // NOTE: $currentBalance is the RAW sum of cash_pools.cached_balance
            // and EXCLUDES inventory (Order) sales, which are overlay-only and
            // never stored. PARITY TODO (FDM week-stats): either build
            // $pools via PoolService::getAllPools (overlaid) or add the
            // inventory Order-sum here, to match the inventory-inclusive pool
            // display. (Not a prod regression: prod has no order
            // package_advances rows; this view already excluded inventory.)
            $goLiveDate = $this->settingService->getGoLiveDate($accountId) ?? '2026-03-08';

            // Week range: defaults to Sunday → today, but the caller can
            // override via `date_from` / `date_to` query params. Plan C-1
            // shipped the date-range picker on the SPA side. Bounds are
            // clamped to ISO-8601 date strings; anything malformed falls
            // back to the default current-week window.
            $defaultSunday = \Carbon\Carbon::now()->startOfWeek(\Carbon\Carbon::SUNDAY)->toDateString();
            $defaultToday = \Carbon\Carbon::now()->toDateString();
            $rawFrom = $request->input('date_from');
            $rawTo = $request->input('date_to');
            $sunday = (is_string($rawFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFrom))
                ? $rawFrom
                : $defaultSunday;
            $today = (is_string($rawTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawTo))
                ? $rawTo
                : $defaultToday;
            // Defensive: swap if caller sent date_from > date_to so SQL
            // BETWEEN doesn't return empty silently.
            if (strcmp($sunday, $today) > 0) {
                [$sunday, $today] = [$today, $sunday];
            }
            // Clamp `date_to` to today — FDM is a historical view, future-dated
            // requests would return empty windows and confuse the UI. The SPA
            // picker already enforces this on `<input max>` but a direct API
            // caller could bypass; pin it here as the authority.
            if (strcmp($today, $defaultToday) > 0) {
                $today = $defaultToday;
                if (strcmp($sunday, $today) > 0) {
                    $sunday = $today;
                }
            }
            $sundayStart = $sunday . ' 00:00:00';
            $nowEnd = $today . ' 23:59:59';

            // Debug information
            $debugInfo = [
                'cached_balance' => (float) $pools->sum('cached_balance'),
                'total_current_balance' => $currentBalance,
                'static_opening_balance' => (float) $pools->sum('opening_balance'),
                'current_date' => \Carbon\Carbon::now()->toDateTimeString(),
                'calculated_sunday' => $sunday,
                'calculated_today' => $today,
            ];

            // Last 7 days range - for transaction records display
            $sevenDaysAgo = \Carbon\Carbon::now()->subDays(6)->toDateString(); // 7 days including today
            $sevenDaysAgoStart = $sevenDaysAgo . ' 00:00:00';



            // ---- Individual records for the last 7 days ----



            // Cash Transfers (involving these pools)

            $transfers = [];

            if (!empty($poolIds)) {

                $transfers = \App\Models\CashFlow\CashTransfer::forAccount($accountId)

                    ->whereNull('voided_at')

                    ->where(function ($q) use ($poolIds) {

                        $q->whereIn('from_pool_id', $poolIds)

                          ->orWhereIn('to_pool_id', $poolIds);

                    })

                    ->whereBetween('transfer_date', [$sevenDaysAgo, $today])

                    ->with(['fromPool:id,name', 'toPool:id,name', 'creator:id,name'])

                    ->orderBy('transfer_date', 'desc')

                    ->get()

                    ->map(function ($t) use ($poolIds) {

                        $direction = in_array($t->from_pool_id, $poolIds, true) ? 'out' : 'in';

                        return [

                            'id' => $t->id,

                            'date' => $t->transfer_date->format('Y-m-d'),

                            'amount' => (float) $t->amount,

                            'direction' => $direction,

                            'from_pool' => $t->fromPool?->name ?? 'N/A',

                            'to_pool' => $t->toPool?->name ?? 'N/A',

                            'description' => $t->description,

                            'method' => $t->method,

                            'created_by' => $t->creator?->name ?? 'N/A',

                        ];

                    })->toArray();

            }



            // Expenses (paid from these pools)

            $expenses = [];

            if (!empty($poolIds)) {

                $expenses = \App\Models\CashFlow\Expense::forAccount($accountId)

                    ->whereNull('voided_at')

                    ->whereIn('paid_from_pool_id', $poolIds)

                    ->whereBetween('expense_date', [$sevenDaysAgo, $today])

                    ->with(['category:id,name', 'paidFromPool:id,name'])

                    ->orderBy('expense_date', 'desc')

                    ->get()

                    ->map(fn($e) => [

                            'id' => $e->id,

                            'date' => $e->expense_date->format('Y-m-d'),

                            'amount' => (float) $e->amount,

                            'description' => $e->description,

                            'category' => $e->category?->name ?? 'N/A',

                            'pool' => $e->paidFromPool?->name ?? 'N/A',

                            'status' => $e->status,

                        ])->toArray();

            }



            // Staff Advances (from these pools)

            $staffAdvances = [];

            if (!empty($poolIds)) {

                $staffAdvances = \App\Models\CashFlow\StaffAdvance::forAccount($accountId)

                    ->whereNull('voided_at')

                    ->whereIn('pool_id', $poolIds)

                    ->whereBetween('created_at', [$sevenDaysAgoStart, $nowEnd])

                    ->with(['staffUser:id,name', 'pool:id,name'])

                    ->orderBy('created_at', 'desc')

                    ->get()

                    ->map(fn($a) => [

                            'id' => $a->id,

                            // Expose the staff user id so the SPA can deep-link
                            // straight into that ledger pane (Staff page focus).
                            'user_id' => (int) $a->user_id,

                            'date' => $a->created_at->format('Y-m-d'),

                            'amount' => (float) $a->amount,

                            'staff_name' => $a->staffUser?->name ?? 'N/A',

                            'pool' => $a->pool?->name ?? 'N/A',

                            'description' => $a->description,

                        ])->toArray();

            }

            // $cashModeIds is already computed at the top of fdmData() — reuse.

            // ---- Calculate opening balance (last week's closing balance) ----
            // If we're in the first week since go-live, use the static opening balance
            // Otherwise, calculate what the balance was at the end of last Saturday
            $lastSaturday = \Carbon\Carbon::now()->startOfWeek(\Carbon\Carbon::SUNDAY)->subDay()->endOfDay();

            if ($lastSaturday->lt(\Carbon\Carbon::parse($goLiveDate))) {
                // We're in the first week, use static opening balance
                $openingBalance = (float) $pools->sum('opening_balance');
            } else {
                // Calculate balance at end of last Saturday
                // Start with static opening balance
                $openingBalance = (float) $pools->sum('opening_balance');

                // Add all transactions from go-live to end of last Saturday
                $branchPoolMap = $pools->pluck('id', 'location_id')->toArray();

                // Add package advances (services cash inflows)
                $servicesIn = 0;
                $servicesOut = 0;
                if (!empty($cashModeIds)) {
                    // Services inflows
                    $servicesIn = \App\Models\PackageAdvances::where('account_id', $accountId)
                        ->where('cash_flow', 'in')
                        ->where('is_cancel', 0)
                        ->whereNull('deleted_at')
                        ->whereIn('payment_mode_id', $cashModeIds)
                        ->whereIn('location_id', array_keys($branchPoolMap))
                        ->whereBetween('system_created_at', [$goLiveDate . ' 00:00:00', $lastSaturday])
                        ->sum('cash_amount');
                    $openingBalance += (float) $servicesIn;

                    // Services refunds (outflows)
                    $servicesOut = \App\Models\PackageAdvances::where('account_id', $accountId)
                        ->where('cash_flow', 'out')
                        ->where('is_refund', 1)
                        ->where('is_cancel', 0)
                        ->whereNull('deleted_at')
                        ->whereIn('payment_mode_id', $cashModeIds)
                        ->whereIn('location_id', array_keys($branchPoolMap))
                        ->whereBetween('system_created_at', [$goLiveDate . ' 00:00:00', $lastSaturday])
                        ->sum('cash_amount');
                    $openingBalance -= (float) $servicesOut;
                }

                // NOTE: inventory (Order) sales/refunds are overlay-only and
                // are NOT in package_advances, so this opening balance
                // currently EXCLUDES inventory. PARITY TODO: add the inventory
                // Order-sum here (windowed to go-live..lastSaturday), mirroring
                // crm2, so the FDM week-stats opening matches the
                // inventory-inclusive pool figures. (Not a prod regression:
                // prod has no order package_advances rows today.)

                // Subtract expenses
                $obExpenses = \App\Models\CashFlow\Expense::forAccount($accountId)
                    ->whereNull('voided_at')
                    ->whereIn('paid_from_pool_id', $poolIds)
                    ->whereBetween('expense_date', [\Carbon\Carbon::parse($goLiveDate)->toDateString(), $lastSaturday->toDateString()])
                    ->sum('amount');
                $openingBalance -= (float) $obExpenses;

                // Handle transfers
                $transfersOut = 0;
                $transfersIn = 0;
                $transfersOut = \App\Models\CashFlow\CashTransfer::forAccount($accountId)
                    ->whereNull('voided_at')
                    ->whereIn('from_pool_id', $poolIds)
                    ->whereBetween('transfer_date', [\Carbon\Carbon::parse($goLiveDate)->toDateString(), $lastSaturday->toDateString()])
                    ->sum('amount');
                $openingBalance -= (float) $transfersOut;

                $transfersIn = \App\Models\CashFlow\CashTransfer::forAccount($accountId)
                    ->whereNull('voided_at')
                    ->whereIn('to_pool_id', $poolIds)
                    ->whereBetween('transfer_date', [\Carbon\Carbon::parse($goLiveDate)->toDateString(), $lastSaturday->toDateString()])
                    ->sum('amount');
                $openingBalance += (float) $transfersIn;

                // Subtract staff advances
                $obStaffAdvances = \App\Models\CashFlow\StaffAdvance::forAccount($accountId)
                    ->whereNull('voided_at')
                    ->whereIn('pool_id', $poolIds)
                    ->whereBetween('created_at', [$goLiveDate . ' 00:00:00', $lastSaturday])
                    ->sum('amount');
                $openingBalance -= (float) $obStaffAdvances;

                // Add staff returns
                $obStaffReturns = \App\Models\CashFlow\StaffReturn::forAccount($accountId)
                    ->whereNull('voided_at')
                    ->whereIn('pool_id', $poolIds)
                    ->whereBetween('created_at', [$goLiveDate . ' 00:00:00', $lastSaturday])
                    ->sum('amount');
                $openingBalance += (float) $obStaffReturns;

                // Debug opening balance calculation.
                // `services_in` / `services_out` now include inventory
                // revenue too (since OrderService writes package_advances
                // rows), so the separate inventory_sales / inventory_refunds
                // entries are dropped — would be zero anyway under the new
                // single-ledger model.
                $debugInfo['opening_balance_calc'] = [
                    'static_opening' => (float) $pools->sum('opening_balance'),
                    'services_in' => (float) $servicesIn,
                    'services_out' => (float) $servicesOut,
                    'expenses' => (float) $obExpenses,
                    'transfers_out' => (float) $transfersOut,
                    'transfers_in' => (float) $transfersIn,
                    'staff_advances' => (float) $obStaffAdvances,
                    'staff_returns' => (float) $obStaffReturns,
                    'calculated_opening' => $openingBalance,
                    'last_saturday' => $lastSaturday->toDateTimeString(),
                ];
            }

            // ---- Calculate current week card totals (all NET values) ----
            //
            // Each block below used to do `->sum()` and `->count()` as two
            // separate calls — two round-trips and two full predicate scans
            // for the same predicate. We now fetch both in one
            // `selectRaw('SUM(...) AS total, COUNT(*) AS cnt')->first()` pass,
            // and we switched the date predicates from `whereDate()` (which
            // wraps the column in DATE() and disables index use) to literal
            // datetime bounds so MySQL can use the `created_at` index.
            $sumCount = static function ($builder, string $sumExpr): array {
                $row = $builder
                    ->selectRaw("COALESCE(SUM({$sumExpr}), 0) AS total, COUNT(*) AS cnt")
                    ->first();

                return [
                    'total' => (float) ($row->total ?? 0),
                    'count' => (int) ($row->cnt ?? 0),
                ];
            };

            // Services Cash Inflows: NET = inflows - refunds
            $servicesCashInflows = 0;
            $servicesCashInflowsCount = 0;
            if (!empty($cashModeIds)) {
                // Services KPI — plan/treatment payments only. `whereNull('order_id')`
                // excludes the package_advances rows that OrderService
                // writes for inventory sales; those flow into the
                // inventory KPI below.
                $svcIn = $sumCount(
                    \App\Models\PackageAdvances::where('account_id', $accountId)
                        ->where('cash_flow', 'in')
                        ->where('is_cancel', 0)
                        ->whereNull('deleted_at')
                        ->whereNull('order_id')
                        ->where('cash_amount', '>', 0)
                        ->whereIn('payment_mode_id', $cashModeIds)
                        ->whereIn('location_id', array_keys($branchPoolMap))
                        ->where('system_created_at', '>=', $sundayStart)
                        ->where('system_created_at', '<=', $nowEnd),
                    'cash_amount',
                );

                $svcOut = $sumCount(
                    \App\Models\PackageAdvances::where('account_id', $accountId)
                        ->where('cash_flow', 'out')
                        ->where('is_refund', 1)
                        ->where('is_cancel', 0)
                        ->whereNull('deleted_at')
                        ->whereNull('order_id')
                        ->whereIn('payment_mode_id', $cashModeIds)
                        ->whereIn('location_id', array_keys($branchPoolMap))
                        ->where('system_created_at', '>=', $sundayStart)
                        ->where('system_created_at', '<=', $nowEnd),
                    'cash_amount',
                );

                $servicesCashInflows = $svcIn['total'] - $svcOut['total'];
                $servicesCashInflowsCount = $svcIn['count'] + $svcOut['count'];
            }

            // Inventory KPI — order sales / refunds. Reads from the
            // same package_advances ledger via `whereNotNull('order_id')`
            // so the two FDM KPIs (services + inventory) read from a
            // single source of truth and always sum to the correct total
            // cash inflow without overlap.
            $invIn = $sumCount(
                \App\Models\PackageAdvances::where('account_id', $accountId)
                    ->where('cash_flow', 'in')
                    ->where('is_cancel', 0)
                    ->whereNull('deleted_at')
                    ->whereNotNull('order_id')
                    ->whereIn('payment_mode_id', $cashModeIds)
                    ->whereIn('location_id', array_keys($branchPoolMap))
                    ->where('system_created_at', '>=', $sundayStart)
                    ->where('system_created_at', '<=', $nowEnd),
                'cash_amount',
            );
            $invOut = $sumCount(
                \App\Models\PackageAdvances::where('account_id', $accountId)
                    ->where('cash_flow', 'out')
                    ->where('is_refund', 1)
                    ->where('is_cancel', 0)
                    ->whereNull('deleted_at')
                    ->whereNotNull('order_id')
                    ->whereIn('payment_mode_id', $cashModeIds)
                    ->whereIn('location_id', array_keys($branchPoolMap))
                    ->where('system_created_at', '>=', $sundayStart)
                    ->where('system_created_at', '<=', $nowEnd),
                'cash_amount',
            );
            $inventorySalesAmount = $invIn['total'];
            $inventoryRefundsAmount = $invOut['total'];
            $inventoryCashInflows = $inventorySalesAmount - $inventoryRefundsAmount;
            $inventoryCashInflowsCount = $invIn['count'] + $invOut['count'];

            // Expenses Total (current week)
            $weekExpensesTotal = 0;
            $weekExpensesCount = 0;
            if (!empty($poolIds)) {
                $exp = $sumCount(
                    \App\Models\CashFlow\Expense::forAccount($accountId)
                        ->whereNull('voided_at')
                        ->whereIn('paid_from_pool_id', $poolIds)
                        ->whereBetween('expense_date', [$sunday, $today]),
                    'amount',
                );
                $weekExpensesTotal = $exp['total'];
                $weekExpensesCount = $exp['count'];
            }

            // Cash Transfers Total: NET = out - in (current week)
            $weekTransfersTotal = 0;
            $weekTransfersCount = 0;
            if (!empty($poolIds)) {
                $trfOut = $sumCount(
                    \App\Models\CashFlow\CashTransfer::forAccount($accountId)
                        ->whereNull('voided_at')
                        ->whereIn('from_pool_id', $poolIds)
                        ->whereBetween('transfer_date', [$sunday, $today]),
                    'amount',
                );
                $trfIn = $sumCount(
                    \App\Models\CashFlow\CashTransfer::forAccount($accountId)
                        ->whereNull('voided_at')
                        ->whereIn('to_pool_id', $poolIds)
                        ->whereBetween('transfer_date', [$sunday, $today]),
                    'amount',
                );
                $weekTransfersTotal = $trfOut['total'] - $trfIn['total'];
                $weekTransfersCount = $trfOut['count'] + $trfIn['count'];
            }

            // Staff Advances Total: NET = advances - returns (current week)
            $weekAdvancesTotal = 0;
            $weekAdvancesCount = 0;
            if (!empty($poolIds)) {
                $adv = $sumCount(
                    \App\Models\CashFlow\StaffAdvance::forAccount($accountId)
                        ->whereNull('voided_at')
                        ->whereIn('pool_id', $poolIds)
                        ->whereBetween('created_at', [$sundayStart, $nowEnd]),
                    'amount',
                );
                $ret = $sumCount(
                    \App\Models\CashFlow\StaffReturn::forAccount($accountId)
                        ->whereNull('voided_at')
                        ->whereIn('pool_id', $poolIds)
                        ->whereBetween('created_at', [$sundayStart, $nowEnd]),
                    'amount',
                );
                $weekAdvancesTotal = $adv['total'] - $ret['total'];
                $weekAdvancesCount = $adv['count'] + $ret['count'];
            }

            // ---- Live Cash Balance = Opening + Services + Inventory - Expenses - Transfers - Advances ----
            $liveCashBalance = round($openingBalance, 2)
                + $servicesCashInflows
                + $inventoryCashInflows
                - $weekExpensesTotal
                - $weekTransfersTotal
                - $weekAdvancesTotal;

            return response()->json([

                'success' => true,

                'data' => [

                    'branch_name' => $branchName,

                    'pool_balance' => round($liveCashBalance, 2),

                    'opening_balance' => round($openingBalance, 2),

                    // Plan C-4: expose the 9-component breakdown the SPA
                    // uses to render the "How is this computed?" popover.
                    // Stays null when we're inside the first week post
                    // go-live (no breakdown needed; static opening only).
                    'opening_balance_breakdown' => $debugInfo['opening_balance_calc'] ?? null,

                    'week_start' => $sunday,

                    'records_period_start' => $sevenDaysAgo,

                    'records_period_end' => $today,

                    'services_cash_inflows' => (float) $servicesCashInflows,

                    'services_cash_inflows_count' => $servicesCashInflowsCount,

                    'inventory_cash_inflows' => (float) $inventoryCashInflows,

                    'inventory_cash_inflows_count' => $inventoryCashInflowsCount,

                    'week_expenses_total' => (float) $weekExpensesTotal,

                    'week_expenses_count' => $weekExpensesCount,

                    'week_transfers_total' => (float) $weekTransfersTotal,

                    'week_transfers_count' => $weekTransfersCount,

                    'week_advances_total' => (float) $weekAdvancesTotal,

                    'week_advances_count' => $weekAdvancesCount,

                    'transfers' => $transfers,

                    'expenses' => $expenses,

                    'staff_advances' => $staffAdvances,

                    'debug' => array_merge($debugInfo, [
                        'live_balance_formula' => [
                            'opening_balance' => round($openingBalance, 2),
                            'services_net' => $servicesCashInflows,
                            'inventory' => $inventoryCashInflows,
                            'expenses' => $weekExpensesTotal,
                            'transfers_net' => $weekTransfersTotal,
                            'advances_net' => $weekAdvancesTotal,
                            'live_cash_balance' => round($liveCashBalance, 2),
                        ],
                    ]),

                ],

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }
}
