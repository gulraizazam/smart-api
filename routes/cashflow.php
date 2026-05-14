<?php

use App\Http\Controllers\Api\CashFlow\CashFlowSettingsController;
use App\Http\Controllers\Api\CashFlow\CashFlowPoolsController;
use App\Http\Controllers\Api\CashFlow\CashFlowCategoriesController;
use App\Http\Controllers\Api\CashFlow\CashFlowExpensesController;
use App\Http\Controllers\Api\CashFlow\ExpenseAttachmentsController;
use App\Http\Controllers\Api\CashFlow\CashFlowMovementsController;
use App\Http\Controllers\Api\CashFlow\CashFlowTransfersController;
use App\Http\Controllers\Api\CashFlow\CashFlowVendorsController;
use App\Http\Controllers\Api\CashFlow\CashFlowStaffController;
use App\Http\Controllers\Api\CashFlow\CashFlowDashboardController;
use App\Http\Controllers\Api\CashFlow\CashFlowReportsController;
use App\Http\Controllers\Api\CashFlow\CashFlowPeriodLocksController;
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
        Route::get('data', [CashFlowSettingsController::class, 'settingsData'])->name('data');
        Route::post('save', [CashFlowSettingsController::class, 'settingsUpdate'])->name('save');
        Route::post('update', [CashFlowSettingsController::class, 'settingsUpdate'])->name('update');
        Route::post('reset-module', [CashFlowSettingsController::class, 'settingsResetModule'])->name('reset');
        Route::get('eligible-staff', [CashFlowSettingsController::class, 'eligibleStaffList'])->name('eligible_staff');
        Route::post('toggle-eligibility', [CashFlowSettingsController::class, 'toggleStaffEligibility'])->name('toggle_eligibility');
    });

    // Audit Logs (under settings section)
    Route::get('settings/audit-logs', [CashFlowSettingsController::class, 'auditLogs'])->name('settings.audit_logs')->middleware('permission:cashflow_settings');

    // Pools
    Route::prefix('pools')->name('pools.')->middleware('permission:cashflow_pool_manage')->group(function () {
        Route::get('/', [CashFlowPoolsController::class, 'poolsIndex'])->name('index');
        Route::post('store', [CashFlowPoolsController::class, 'poolsStore'])->name('store');
        Route::post('{id}/update', [CashFlowPoolsController::class, 'poolsUpdate'])->name('update');
        Route::post('{id}/delete', [CashFlowPoolsController::class, 'poolsDelete'])->name('delete');
        Route::post('initialize', [CashFlowPoolsController::class, 'poolsInit'])->name('initialize');
        Route::post('recalculate', [CashFlowPoolsController::class, 'poolsRecalculate'])->name('recalculate');
    });

    // Categories
    Route::prefix('categories')->name('categories.')->middleware('permission:cashflow_category_manage')->group(function () {
        Route::get('/', [CashFlowCategoriesController::class, 'categoriesIndex'])->name('index');
        Route::post('store', [CashFlowCategoriesController::class, 'categoriesStore'])->name('store');
        Route::post('{id}/update', [CashFlowCategoriesController::class, 'categoriesUpdate'])->name('update');
        Route::post('{id}/toggle', [CashFlowCategoriesController::class, 'categoriesToggle'])->name('toggle');
    });

    // Expenses
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('data', [CashFlowExpensesController::class, 'expensesData'])->name('data');
        Route::get('form-data', [CashflowLookupsController::class, 'expensesFormData'])->name('form_data');
        Route::post('store', [CashFlowExpensesController::class, 'expensesStore'])->name('store');
        // approve / resubmit routes stayed retired 2026-05-13 (Option A).
        // reject came back 2026-05-14 as a "return to accountant for
        // fixes" workflow — see ExpenseService::reject + adminEdit.
        Route::post('{id}/edit', [CashFlowExpensesController::class, 'expensesEdit'])->name('edit');
        Route::post('{id}/reject', [CashFlowExpensesController::class, 'expensesReject'])->name('reject');
        Route::post('{id}/void', [CashFlowExpensesController::class, 'expensesVoid'])->name('void');
        Route::post('{id}/unflag', [CashFlowExpensesController::class, 'expensesUnflag'])->name('unflag');
        Route::get('{id}/audit', [CashFlowExpensesController::class, 'expensesAudit'])->name('audit');
        Route::get('export', [CashFlowExpensesController::class, 'expensesExport'])->name('export');

        // Attachments (slice 1 of the URL-to-upload migration). Permission
        // gating is enforced by the controller's FormRequest + per-method
        // checks — no route-level middleware here so the same endpoint can
        // be reached from both the create and the edit flows without
        // duplicating slugs.
        Route::post('attachments', [ExpenseAttachmentsController::class, 'store'])
            ->name('attachments.store');
        Route::get('attachments/{id}/signed-url', [ExpenseAttachmentsController::class, 'signedUrl'])
            ->name('attachments.signed_url');
        Route::delete('attachments/{id}', [ExpenseAttachmentsController::class, 'destroy'])
            ->name('attachments.destroy');
    });

    // Notifications (lightweight controller to avoid heavy CashFlowController instantiation on every page poll)
    Route::prefix('notifications')->name('notifications.')->middleware('permission:cashflow_manage')->group(function () {
        Route::get('/', [CashflowNotificationController::class, 'index'])->name('index');
        Route::post('mark-read', [CashflowNotificationController::class, 'markRead'])->name('mark_read');
    });

    // Cash Movements — unified surface over Transfers + StaffAdvances + StaffReturns.
    // Phase A: additive route group. Old /transfers and /staff routes stay alive
    // throughout the shadow-run window so a flag flip restores the legacy UI.
    // Route-level gate is intentionally permissive (any of the view slugs); each
    // action's controller method re-checks the per-kind slug before dispatching.
    Route::prefix('movements')->name('movements.')->middleware('permission:cashflow_transfer_view|cashflow_staff_advance_view|cashflow_staff_advance|cashflow_manage')->group(function () {
        Route::get('data', [CashFlowMovementsController::class, 'movementsData'])->name('data');
        Route::get('form-data', [CashFlowMovementsController::class, 'formData'])->name('form_data');
        Route::post('store', [CashFlowMovementsController::class, 'store'])->name('store');
        Route::post('{kind}/{id}/void', [CashFlowMovementsController::class, 'void'])->name('void');
        Route::get('{kind}/{id}/audit', [CashFlowMovementsController::class, 'audit'])->name('audit');
        Route::get('pool/{poolId}/ledger', [CashFlowMovementsController::class, 'poolLedger'])->name('pool_ledger');
    });

    // Transfers — defense-in-depth: a route-level gate that fails closed if a
    // controller ever forgets its inline `Gate::allows` check. Either of the
    // listed slugs is sufficient (`cashflow_manage` is the super-slug).
    Route::prefix('transfers')->name('transfers.')->middleware('permission:cashflow_transfer_view|cashflow_manage')->group(function () {
        Route::get('data', [CashFlowTransfersController::class, 'transfersData'])->name('data');
        Route::post('store', [CashFlowTransfersController::class, 'transfersStore'])->name('store');
        Route::post('{id}/void', [CashFlowTransfersController::class, 'transfersVoid'])->name('void');
        Route::post('{id}/edit', [CashFlowTransfersController::class, 'transfersEdit'])->name('edit');
        Route::get('{id}/audit', [CashFlowTransfersController::class, 'transfersAudit'])->name('audit');
    });

    // Vendor form data (category dropdown)
    Route::get('vendors/form-data', [CashflowLookupsController::class, 'vendorFormData'])->name('vendors.form_data');

    // Vendors — defense-in-depth (see Transfers note above).
    Route::prefix('vendors')->name('vendors.')->middleware('permission:cashflow_vendor_view|cashflow_vendor_manage|cashflow_manage')->group(function () {
        Route::get('data', [CashFlowVendorsController::class, 'vendorsData'])->name('data');
        Route::get('overview', [CashFlowVendorsController::class, 'vendorsOverview'])->name('overview');
        Route::post('store', [CashFlowVendorsController::class, 'vendorsStore'])->name('store');
        Route::post('{id}/update', [CashFlowVendorsController::class, 'vendorsUpdate'])->name('update');
        Route::post('{id}/toggle', [CashFlowVendorsController::class, 'vendorsToggle'])->name('toggle');
        Route::get('{id}/ledger', [CashFlowVendorsController::class, 'vendorsLedger'])->name('ledger');
        Route::post('{id}/purchase', [CashFlowVendorsController::class, 'vendorsPurchase'])->name('purchase');
        Route::post('{id}/transactions/{txId}/update', [CashFlowVendorsController::class, 'vendorsTransactionUpdate'])->name('transaction.update');
        Route::post('{id}/transactions/{txId}/deliver', [CashFlowVendorsController::class, 'vendorsTransactionDeliver'])->name('transaction.deliver');
        Route::post('{id}/transactions/{txId}/delete', [CashFlowVendorsController::class, 'vendorsTransactionDelete'])->name('transaction.delete');
        Route::get('{id}/transactions/{txId}/audit', [CashFlowVendorsController::class, 'vendorsTransactionAudit'])->name('transaction.audit');
        Route::get('{id}/ledger/export', [CashFlowVendorsController::class, 'vendorsLedgerExport'])->name('ledger.export');
    });

    // Vendor Requests — defense-in-depth (suggestion store + approve/dismiss).
    // Any of these slugs admits the user; controller methods still gate
    // approve/dismiss on `cashflow_vendor_manage` inline.
    Route::prefix('vendor-requests')->name('vendor_requests.')->middleware('permission:cashflow_vendor_request|cashflow_vendor_manage|cashflow_manage')->group(function () {
        Route::get('data', [CashFlowVendorsController::class, 'vendorRequestsData'])->name('data');
        Route::post('store', [CashFlowVendorsController::class, 'vendorRequestsStore'])->name('store');
        Route::post('{id}/approve', [CashFlowVendorsController::class, 'vendorRequestsApprove'])->name('approve');
        Route::post('{id}/dismiss', [CashFlowVendorsController::class, 'vendorRequestsDismiss'])->name('dismiss');
    });

    // Category Requests — defense-in-depth. Anyone with create-expense can
    // suggest a category (since that's the surface the UI offers it from);
    // category-manage admins approve/dismiss.
    Route::prefix('category-requests')->name('category_requests.')->middleware('permission:cashflow_expense_create|cashflow_category_manage|cashflow_manage')->group(function () {
        Route::get('data', [CashFlowCategoriesController::class, 'categoryRequestsData'])->name('data');
        Route::post('store', [CashFlowCategoriesController::class, 'categoryRequestsStore'])->name('store');
        Route::post('{id}/approve', [CashFlowCategoriesController::class, 'categoryRequestsApprove'])->name('approve');
        Route::post('{id}/dismiss', [CashFlowCategoriesController::class, 'categoryRequestsDismiss'])->name('dismiss');
    });

    // Staff Advances & Returns
    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('summary', [CashFlowStaffController::class, 'staffSummary'])->name('summary');
        Route::get('recent-activity', [CashFlowStaffController::class, 'staffRecentActivity'])->name('recent_activity');
        Route::get('{userId}/ledger', [CashFlowStaffController::class, 'staffLedger'])->name('ledger');
        Route::get('eligible', [CashFlowStaffController::class, 'staffEligible'])->name('eligible');
        Route::post('advance/store', [CashFlowStaffController::class, 'staffAdvanceStore'])->name('advance.store');
        Route::post('advance/{id}/void', [CashFlowStaffController::class, 'staffAdvanceVoid'])->name('advance.void');
        Route::post('advance/{id}/update', [CashFlowStaffController::class, 'staffAdvanceUpdate'])->name('advance.update');
        Route::get('advance/{id}/audit', [CashFlowStaffController::class, 'staffAdvanceAudit'])->name('advance.audit');
        Route::post('return/store', [CashFlowStaffController::class, 'staffReturnStore'])->name('return.store');
        Route::post('return/{id}/void', [CashFlowStaffController::class, 'staffReturnVoid'])->name('return.void');
        Route::get('return/{id}/audit', [CashFlowStaffController::class, 'staffReturnAudit'])->name('return.audit');
    });

    // Dashboard
    Route::prefix('dashboard')->name('dashboard.')->middleware('permission:cashflow_dashboard')->group(function () {
        Route::get('data', [CashFlowDashboardController::class, 'dashboardData'])->name('data');
        Route::get('snapshot', [CashFlowDashboardController::class, 'dashboardSnapshot'])->name('snapshot');
        Route::get('reconciliation', [CashFlowDashboardController::class, 'dashboardReconciliation'])->name('reconciliation');
    });

    // FDM Cash View
    Route::prefix('fdm')->name('fdm.')->middleware('permission:cashflow_fdm_view')->group(function () {
        Route::get('data', [CashFlowDashboardController::class, 'fdmData'])->name('data');
    });

    // Period Locks
    Route::prefix('period-locks')->name('period_locks.')->middleware('permission:cashflow_settings')->group(function () {
        Route::get('data', [CashFlowPeriodLocksController::class, 'periodLocksData'])->name('data');
        Route::post('lock', [CashFlowPeriodLocksController::class, 'periodLocksLock'])->name('lock');
        Route::post('{id}/unlock', [CashFlowPeriodLocksController::class, 'periodLocksUnlock'])->name('unlock');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->middleware('permission:cashflow_reports')->group(function () {
        Route::get('cashflow-statement', [CashFlowReportsController::class, 'reportCashFlowStatement'])->name('cashflow_statement');
        Route::get('branch-comparison', [CashFlowReportsController::class, 'reportBranchComparison'])->name('branch_comparison');
        Route::get('category-trend', [CashFlowReportsController::class, 'reportCategoryTrend'])->name('category_trend');
        Route::get('vendor-outstanding', [CashFlowReportsController::class, 'reportVendorOutstanding'])->name('vendor_outstanding');
        Route::get('staff-advance', [CashFlowReportsController::class, 'reportStaffAdvance'])->name('staff_advance');
        Route::get('daily-movement', [CashFlowReportsController::class, 'reportDailyMovement'])->name('daily_movement');
        Route::get('transfer-log', [CashFlowReportsController::class, 'reportTransferLog'])->name('transfer_log');
        Route::get('flagged-entries', [CashFlowReportsController::class, 'reportFlaggedEntries'])->name('flagged_entries');
        Route::get('dormant-vendors', [CashFlowReportsController::class, 'reportDormantVendors'])->name('dormant_vendors');
        Route::get('export/{type}', [CashFlowReportsController::class, 'reportExport'])->name('export');
    });
});
