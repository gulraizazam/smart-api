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
        return $user->can('cashflow.dashboard.view');
    }

    /**
     * Manage Cash Flow module settings (e.g. go-live date).
     */
    public function manageSettings(User $user): bool
    {
        return $user->can('cashflow.settings.manage');
    }

    /**
     * Full Cash Flow management access.
     */
    public function manage(User $user): bool
    {
        return $user->can('cashflow.manage');
    }

    /**
     * View Cash Flow reports.
     */
    public function viewReports(User $user): bool
    {
        return $user->can('cashflow.reports.view') || $user->can('cashflow.dashboard.view');
    }

    /**
     * FDM (Financial Data Manager) restricted view.
     */
    public function fdmView(User $user): bool
    {
        return $user->can('cashflow.fdm.view');
    }

    /**
     * Manage vendors (create, edit, delete).
     */
    public function manageVendors(User $user): bool
    {
        return $user->can('cashflow.vendor.manage') || $user->can('cashflow.manage');
    }

    /**
     * Manage expense entries.
     */
    public function manageExpenses(User $user): bool
    {
        return $user->can('cashflow.expense.create') || $user->can('cashflow.manage');
    }

    /**
     * Manage cash pools.
     */
    public function managePools(User $user): bool
    {
        return $user->can('cashflow.pool.manage') || $user->can('cashflow.manage');
    }

    /**
     * Manage expense categories.
     */
    public function manageCategories(User $user): bool
    {
        return $user->can('cashflow.category.manage') || $user->can('cashflow.manage');
    }

    /**
     * Lock / unlock a cash flow period.
     */
    public function lockPeriod(User $user): bool
    {
        return $user->can('cashflow.period.lock') || $user->can('cashflow.manage');
    }

    /**
     * Manage staff advances.
     */
    public function manageStaffAdvances(User $user): bool
    {
        return $user->can('cashflow.staff_advance.view') || $user->can('cashflow.manage');
    }

    /**
     * Transfer cash between pools.
     */
    public function transfer(User $user): bool
    {
        return $user->can('cashflow.transfer.create') || $user->can('cashflow.manage');
    }

    /**
     * Approve or reject vendor/category requests.
     */
    public function approveRequests(User $user): bool
    {
        // Approval is most-aligned with vendor/expense approve flows.
        return $user->can('cashflow.manage') || $user->can('cashflow.expense.approve');
    }

    /**
     * Export Cash Flow data.
     */
    public function export(User $user): bool
    {
        return $user->can('cashflow.reports.view') || $user->can('cashflow.manage');
    }
}
