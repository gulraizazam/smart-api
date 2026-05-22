<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

class CashFlowController extends Controller
{
    public function dashboard(): \Illuminate\View\View
    {
        if (!Gate::allows('cashflow.dashboard.view')) {
            return abort(403);
        }
        return view('admin.cashflow.dashboard');
    }

    public function expenses(): \Illuminate\View\View
    {
        if (!Gate::any(['cashflow.expense.view', 'cashflow.expense.create', 'cashflow.expense.approve', 'cashflow.expense.reject', 'cashflow.expense.edit', 'cashflow.expense.void'])) {
            return abort(403);
        }
        return view('admin.cashflow.expenses');
    }

    public function transfers(): \Illuminate\View\View
    {
        if (!Gate::any(['cashflow.transfer.view', 'cashflow.transfer.create', 'cashflow.transfer.edit', 'cashflow.transfer.void'])) {
            return abort(403);
        }
        return view('admin.cashflow.transfers');
    }

    public function vendors(): \Illuminate\View\View
    {
        if (!Gate::any(['cashflow.vendor.view', 'cashflow.vendor.create', 'cashflow.vendor.edit', 'cashflow.vendor.manage', 'cashflow.vendor.ledger.view', 'cashflow.vendor.transaction.create'])) {
            return abort(403);
        }
        $branches = \App\Helpers\CashflowHelper::getActiveBranches(auth()->user()->account_id);
        return view('admin.cashflow.vendors', compact('branches'));
    }

    public function staff(): \Illuminate\View\View
    {
        if (!Gate::any(['cashflow.staff_advance.view', 'cashflow.staff_advance.create', 'cashflow.staff_advance.edit', 'cashflow.staff_advance.void', 'cashflow.staff_return.create', 'cashflow.staff_return.void'])) {
            return abort(403);
        }
        return view('admin.cashflow.staff');
    }

    public function reports(): \Illuminate\View\View
    {
        if (!Gate::allows('cashflow.reports.view')) {
            return abort(403);
        }
        return view('admin.cashflow.reports');
    }

    public function settings(): \Illuminate\View\View
    {
        if (!Gate::allows('cashflow.settings.manage')) {
            return abort(403);
        }
        return view('admin.cashflow.settings');
    }

    public function fdmView(): \Illuminate\View\View
    {
        if (!Gate::allows('cashflow.fdm.view')) {
            return abort(403);
        }
        return view('admin.cashflow.fdm');
    }
}
