<?php

declare(strict_types=1);
namespace App\Policies;

use App\Models\User;

/**
 * Authorization policy for the Cash Flow module.
 *
 * Cash Flow has granular sub-permissions for each feature area:
 * settings, dashboard, vendors, expenses, pools, categories, reports,
 * period locks, staff advances, and FDM (Financial Data Manager) view.
 */
class CashFlowPolicy
{
    /**
     * Access the Cash Flow dashboard.
     */
    public function viewDashboard(User $user): bool
    {
        return $user->can('cashflow_dashboard');
    }

    /**
     * Manage Cash Flow module settings (e.g. go-live date).
     */
    public function manageSettings(User $user): bool
    {
        return $user->can('cashflow_settings');
    }

    /**
     * Full Cash Flow management access.
     */
    public function manage(User $user): bool
    {
        return $user->can('cashflow_manage');
    }

    /**
     * View Cash Flow reports.
     */
    public function viewReports(User $user): bool
    {
        return $user->can('cashflow_reports') || $user->can('cashflow_dashboard');
    }

    /**
     * FDM (Financial Data Manager) restricted view.
     */
    public function fdmView(User $user): bool
    {
        return $user->can('cashflow_fdm_view');
    }

    /**
     * Manage vendors (create, edit, delete).
     */
    public function manageVendors(User $user): bool
    {
        return $user->can('cashflow_vendor') || $user->can('cashflow_manage');
    }

    /**
     * Manage expense entries.
     */
    public function manageExpenses(User $user): bool
    {
        return $user->can('cashflow_expense') || $user->can('cashflow_manage');
    }

    /**
     * Manage cash pools.
     */
    public function managePools(User $user): bool
    {
        return $user->can('cashflow_pool_manage') || $user->can('cashflow_manage');
    }

    /**
     * Manage expense categories.
     */
    public function manageCategories(User $user): bool
    {
        return $user->can('cashflow_category_manage') || $user->can('cashflow_manage');
    }

    /**
     * Lock / unlock a cash flow period.
     */
    public function lockPeriod(User $user): bool
    {
        return $user->can('cashflow_period_lock') || $user->can('cashflow_manage');
    }

    /**
     * Manage staff advances.
     */
    public function manageStaffAdvances(User $user): bool
    {
        return $user->can('cashflow_staff_advance') || $user->can('cashflow_manage');
    }

    /**
     * Transfer cash between pools.
     */
    public function transfer(User $user): bool
    {
        return $user->can('cashflow_transfer') || $user->can('cashflow_manage');
    }

    /**
     * Approve or reject vendor/category requests.
     */
    public function approveRequests(User $user): bool
    {
        return $user->can('cashflow_manage') || $user->can('cashflow_approve');
    }

    /**
     * Export Cash Flow data.
     */
    public function export(User $user): bool
    {
        return $user->can('cashflow_reports') || $user->can('cashflow_manage');
    }
}
