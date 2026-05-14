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
        try {
            $accountId = Auth::user()->account_id;
            $categories = \App\Models\CashFlow\ExpenseCategory::forAccount($accountId)
                ->active()
                ->where('vendor_emphasis', true)
                ->sorted()
                ->get(['id', 'name']);

            return response()->json(['success' => true, 'data' => ['vendor_categories' => $categories]]);
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
