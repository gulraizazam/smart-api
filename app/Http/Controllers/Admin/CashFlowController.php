<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

class CashFlowController extends Controller
{
    public function dashboard(): \Illuminate\View\View
    {
        if (!Gate::allows('cashflow_dashboard')) {
            return abort(401);
        }
        return view('admin.cashflow.dashboard');
    }

    public function expenses(): \Illuminate\View\View
    {
        if (!Gate::any(['cashflow_expense_view', 'cashflow_expense_create', 'cashflow_expense_approve', 'cashflow_expense_reject', 'cashflow_expense_edit', 'cashflow_expense_void'])) {
            return abort(401);
        }
        return view('admin.cashflow.expenses');
    }

    public function transfers(): \Illuminate\View\View
    {
        if (!Gate::any(['cashflow_transfer_view', 'cashflow_transfer_create', 'cashflow_transfer_edit', 'cashflow_transfer_void'])) {
            return abort(401);
        }
        return view('admin.cashflow.transfers');
    }

    public function vendors(): \Illuminate\View\View
    {
        if (!Gate::any(['cashflow_vendor_view', 'cashflow_vendor_create', 'cashflow_vendor_edit', 'cashflow_vendor_manage', 'cashflow_vendor_ledger_view', 'cashflow_vendor_transaction'])) {
            return abort(401);
        }
        $branches = \App\Helpers\CashflowHelper::getActiveBranches(auth()->user()->account_id);
        return view('admin.cashflow.vendors', compact('branches'));
    }

    public function staff(): \Illuminate\View\View
    {
        if (!Gate::any(['cashflow_staff_advance_view', 'cashflow_staff_advance_create', 'cashflow_staff_advance_edit', 'cashflow_staff_advance_void', 'cashflow_staff_return_create', 'cashflow_staff_return_void'])) {
            return abort(401);
        }
        return view('admin.cashflow.staff');
    }

    public function reports(): \Illuminate\View\View
    {
        if (!Gate::allows('cashflow_reports')) {
            return abort(401);
        }
        return view('admin.cashflow.reports');
    }

    public function settings(): \Illuminate\View\View
    {
        if (!Gate::allows('cashflow_settings')) {
            return abort(401);
        }
        return view('admin.cashflow.settings');
    }

    public function fdmView(): \Illuminate\View\View
    {
        if (!Gate::allows('cashflow_fdm_view')) {
            return abort(401);
        }
        return view('admin.cashflow.fdm');
    }
}
