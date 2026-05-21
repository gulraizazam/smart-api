<?php

declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Helpers\CashflowHelper;
use App\Http\Controllers\Controller;
use App\Models\CashFlow\CashflowSetting;
use App\Models\CashFlow\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * Lightweight controller for cashflow lookup / dropdown data.
 * Separated from CashFlowController to avoid instantiating 14 service dependencies
 * just to return simple dropdown lists.
 */
class CashflowLookupsController extends Controller
{
    /**
     * Get dropdown data for the expense form.
     * Replaces CashFlowController::expensesFormData() with zero constructor dependencies.
     */
    public function expensesFormData(): JsonResponse
    {
        if (! Gate::any(['cashflow.expense.create', 'cashflow.expense.edit', 'cashflow.manage'])) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

        try {
            $accountId = Auth::user()->account_id;

            return response()->json([
                'success' => true,
                'data' => [
                    'pools' => CashflowHelper::getActivePools($accountId),
                    'categories' => CashflowHelper::getActiveCategories($accountId),
                    'branches' => CashflowHelper::getActiveBranches($accountId),
                    'payment_modes' => CashflowHelper::getActivePaymentModes(),
                    'vendors' => CashflowHelper::getActiveVendors($accountId),
                    'staff' => CashflowHelper::getAdvanceEligibleStaff($accountId),
                    'threshold' => $this->getApprovalThreshold($accountId),
                    'has_period_locks' => \App\Models\CashFlow\PeriodLock::where('account_id', $accountId)->exists(),
                ],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    /**
     * Get dropdown data for the vendor form (vendor-emphasis categories).
     */
    public function vendorFormData(): JsonResponse
    {
        if (! Gate::any(['cashflow.vendor.create', 'cashflow.vendor.edit', 'cashflow.vendor.manage', 'cashflow.manage'])) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

        try {
            $accountId = Auth::user()->account_id;
            $categories = \App\Models\CashFlow\ExpenseCategory::forAccount($accountId)
                ->active()
                ->where('vendor_emphasis', true)
                ->sorted()
                ->get(['id', 'name']);

            return response()->json(['success' => true, 'data' => [
                'vendor_categories' => $categories,
                // Lets the purchase/deliver dialogs pre-empt a locked-period
                // rejection instead of surfacing a raw server error.
                'has_period_locks' => \App\Models\CashFlow\PeriodLock::where('account_id', $accountId)->exists(),
            ]]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    /**
     * Generic lookups endpoint (pools list for transfers/staff pages).
     */
    public function lookups(): JsonResponse
    {
        if (! Gate::any(['cashflow.dashboard.view', 'cashflow.expense.view', 'cashflow.transfer.view', 'cashflow.vendor.view', 'cashflow.staff_advance.view', 'cashflow.reports.view', 'cashflow.settings.manage', 'cashflow.manage'])) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

        try {
            $accountId = Auth::user()->account_id;

            return response()->json([
                'success' => true,
                'data' => [
                    'pools' => CashflowHelper::getActivePools($accountId),
                    'branches' => CashflowHelper::getActiveBranches($accountId),
                    'payment_modes' => CashflowHelper::getActivePaymentModes(),
                ],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    /**
     * Get approval threshold from settings (cached).
     */
    private function getApprovalThreshold(int $accountId): float
    {
        $settings = Cache::remember(
            "cashflow_settings_{$accountId}",
            3600,
            fn () => CashflowSetting::getAllForAccount($accountId)
        );

        return (float) ($settings['approval_threshold'] ?? 10000);
    }
}
