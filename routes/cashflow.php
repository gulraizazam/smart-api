<?php

use App\Http\Controllers\Api\CashFlowController;
use App\Http\Controllers\Api\CashflowLookupsController;
use App\Http\Controllers\Api\CashflowNotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cash Flow Module API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded inside the auth.common middleware group in api.php.
| All routes inherit the 'admin.' name prefix automatically.
|
*/

Route::prefix('cashflow')->name('cashflow.')->group(function () {

    // Lookups (common dropdown data)
    Route::get('lookups', [CashflowLookupsController::class, 'lookups'])->name('lookups');

    // Settings
    Route::prefix('settings')->name('settings.')->middleware('permission:cashflow_settings')->group(function () {
        Route::get('data', [CashFlowController::class, 'settingsData'])->name('data');
        Route::post('save', [CashFlowController::class, 'settingsUpdate'])->name('save');
        Route::post('update', [CashFlowController::class, 'settingsUpdate'])->name('update');
        Route::post('reset-module', [CashFlowController::class, 'settingsResetModule'])->name('reset');
        Route::get('eligible-staff', [CashFlowController::class, 'eligibleStaffList'])->name('eligible_staff');
        Route::post('toggle-eligibility', [CashFlowController::class, 'toggleStaffEligibility'])->name('toggle_eligibility');
    });

    // Audit Logs (admin only)
    Route::get('audit-logs', [CashFlowController::class, 'auditLogs'])->name('audit_logs')->middleware('permission:cashflow_settings');

    // Pools
    Route::prefix('pools')->name('pools.')->middleware('permission:cashflow_pool_manage')->group(function () {
        Route::get('/', [CashFlowController::class, 'poolsIndex'])->name('index');
        Route::post('store', [CashFlowController::class, 'poolsStore'])->name('store');
        Route::post('{id}/update', [CashFlowController::class, 'poolsUpdate'])->name('update');
        Route::post('{id}/delete', [CashFlowController::class, 'poolsDelete'])->name('delete');
        Route::post('initialize', [CashFlowController::class, 'poolsInit'])->name('initialize');
        Route::post('recalculate', [CashFlowController::class, 'poolsRecalculate'])->name('recalculate');
    });

    // Categories
    Route::prefix('categories')->name('categories.')->middleware('permission:cashflow_category_manage')->group(function () {
        Route::get('/', [CashFlowController::class, 'categoriesIndex'])->name('index');
        Route::post('store', [CashFlowController::class, 'categoriesStore'])->name('store');
        Route::post('{id}/update', [CashFlowController::class, 'categoriesUpdate'])->name('update');
        Route::post('{id}/toggle', [CashFlowController::class, 'categoriesToggle'])->name('toggle');
    });

    // Expenses
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('data', [CashFlowController::class, 'expensesData'])->name('data');
        Route::get('form-data', [CashflowLookupsController::class, 'expensesFormData'])->name('form_data');
        Route::post('store', [CashFlowController::class, 'expensesStore'])->name('store');
        Route::post('{id}/approve', [CashFlowController::class, 'expensesApprove'])->name('approve');
        Route::post('{id}/reject', [CashFlowController::class, 'expensesReject'])->name('reject');
        Route::post('{id}/resubmit', [CashFlowController::class, 'expensesResubmit'])->name('resubmit');
        Route::post('{id}/edit', [CashFlowController::class, 'expensesEdit'])->name('edit');
        Route::post('{id}/void', [CashFlowController::class, 'expensesVoid'])->name('void');
        Route::post('{id}/unflag', [CashFlowController::class, 'expensesUnflag'])->name('unflag');
        Route::get('{id}/audit', [CashFlowController::class, 'expensesAudit'])->name('audit');
        Route::get('export', [CashFlowController::class, 'expensesExport'])->name('export');
    });

    // Notifications (lightweight controller to avoid heavy CashFlowController instantiation on every page poll)
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [CashflowNotificationController::class, 'index'])->name('index');
        Route::post('mark-read', [CashflowNotificationController::class, 'markRead'])->name('mark_read');
    });

    // Transfers
    Route::prefix('transfers')->name('transfers.')->group(function () {
        Route::get('data', [CashFlowController::class, 'transfersData'])->name('data');
        Route::post('store', [CashFlowController::class, 'transfersStore'])->name('store');
        Route::post('{id}/void', [CashFlowController::class, 'transfersVoid'])->name('void');
        Route::post('{id}/edit', [CashFlowController::class, 'transfersEdit'])->name('edit');
        Route::get('{id}/audit', [CashFlowController::class, 'transfersAudit'])->name('audit');
    });

    // Vendors
    Route::prefix('vendors')->name('vendors.')->group(function () {
        Route::get('data', [CashFlowController::class, 'vendorsData'])->name('data');
        Route::post('store', [CashFlowController::class, 'vendorsStore'])->name('store');
        Route::post('{id}/update', [CashFlowController::class, 'vendorsUpdate'])->name('update');
        Route::post('{id}/toggle', [CashFlowController::class, 'vendorsToggle'])->name('toggle');
        Route::get('{id}/ledger', [CashFlowController::class, 'vendorsLedger'])->name('ledger');
        Route::post('{id}/purchase', [CashFlowController::class, 'vendorsPurchase'])->name('purchase');
    });

    // Vendor Requests
    Route::prefix('vendor-requests')->name('vendor_requests.')->group(function () {
        Route::get('data', [CashFlowController::class, 'vendorRequestsData'])->name('data');
        Route::post('store', [CashFlowController::class, 'vendorRequestsStore'])->name('store');
        Route::post('{id}/approve', [CashFlowController::class, 'vendorRequestsApprove'])->name('approve');
        Route::post('{id}/dismiss', [CashFlowController::class, 'vendorRequestsDismiss'])->name('dismiss');
    });

    // Category Requests
    Route::prefix('category-requests')->name('category_requests.')->group(function () {
        Route::get('data', [CashFlowController::class, 'categoryRequestsData'])->name('data');
        Route::post('store', [CashFlowController::class, 'categoryRequestsStore'])->name('store');
        Route::post('{id}/approve', [CashFlowController::class, 'categoryRequestsApprove'])->name('approve');
        Route::post('{id}/dismiss', [CashFlowController::class, 'categoryRequestsDismiss'])->name('dismiss');
    });

    // Staff Advances & Returns
    Route::prefix('staff')->name('staff.')->middleware('permission:cashflow_staff_advance')->group(function () {
        Route::get('summary', [CashFlowController::class, 'staffSummary'])->name('summary');
        Route::get('{userId}/ledger', [CashFlowController::class, 'staffLedger'])->name('ledger');
        Route::get('eligible', [CashFlowController::class, 'staffEligible'])->name('eligible');
        Route::post('advance/store', [CashFlowController::class, 'staffAdvanceStore'])->name('advance.store');
        Route::post('advance/{id}/void', [CashFlowController::class, 'staffAdvanceVoid'])->name('advance.void');
        Route::post('advance/{id}/update', [CashFlowController::class, 'staffAdvanceUpdate'])->name('advance.update');
        Route::get('advance/{id}/audit', [CashFlowController::class, 'staffAdvanceAudit'])->name('advance.audit');
        Route::post('return/store', [CashFlowController::class, 'staffReturnStore'])->name('return.store');
        Route::post('return/{id}/void', [CashFlowController::class, 'staffReturnVoid'])->name('return.void');
        Route::get('return/{id}/audit', [CashFlowController::class, 'staffReturnAudit'])->name('return.audit');
    });

    // Dashboard
    Route::prefix('dashboard')->name('dashboard.')->middleware('permission:cashflow_dashboard')->group(function () {
        Route::get('data', [CashFlowController::class, 'dashboardData'])->name('data');
        Route::get('reconciliation', [CashFlowController::class, 'dashboardReconciliation'])->name('reconciliation');
    });

    // FDM Cash View
    Route::prefix('fdm')->name('fdm.')->middleware('permission:cashflow_fdm_view')->group(function () {
        Route::get('data', [CashFlowController::class, 'fdmData'])->name('data');
    });

    // Period Locks
    Route::prefix('period-locks')->name('period_locks.')->middleware('permission:cashflow_settings')->group(function () {
        Route::get('data', [CashFlowController::class, 'periodLocksData'])->name('data');
        Route::post('lock', [CashFlowController::class, 'periodLocksLock'])->name('lock');
        Route::post('{id}/unlock', [CashFlowController::class, 'periodLocksUnlock'])->name('unlock');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->middleware('permission:cashflow_reports')->group(function () {
        Route::get('cashflow-statement', [CashFlowController::class, 'reportCashFlowStatement'])->name('cashflow_statement');
        Route::get('branch-comparison', [CashFlowController::class, 'reportBranchComparison'])->name('branch_comparison');
        Route::get('category-trend', [CashFlowController::class, 'reportCategoryTrend'])->name('category_trend');
        Route::get('vendor-outstanding', [CashFlowController::class, 'reportVendorOutstanding'])->name('vendor_outstanding');
        Route::get('staff-advance', [CashFlowController::class, 'reportStaffAdvance'])->name('staff_advance');
        Route::get('daily-movement', [CashFlowController::class, 'reportDailyMovement'])->name('daily_movement');
        Route::get('transfer-log', [CashFlowController::class, 'reportTransferLog'])->name('transfer_log');
        Route::get('flagged-entries', [CashFlowController::class, 'reportFlaggedEntries'])->name('flagged_entries');
        Route::get('dormant-vendors', [CashFlowController::class, 'reportDormantVendors'])->name('dormant_vendors');
        Route::get('export/{type}', [CashFlowController::class, 'reportExport'])->name('export');
    });
});
