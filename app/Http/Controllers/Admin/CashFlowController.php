<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

class CashFlowController extends Controller
{
    public function dashboard()
    {
        if (!Gate::allows('cashflow_dashboard')) {
            return abort(401);
        }
        return view('admin.cashflow.dashboard');
    }

    public function expenses()
    {
        if (!Gate::allows('cashflow_expense_create') && !Gate::allows('cashflow_expense_approve')) {
            return abort(401);
        }
        return view('admin.cashflow.expenses');
    }

    public function transfers()
    {
        if (!Gate::allows('cashflow_transfer_create')) {
            return abort(401);
        }
        return view('admin.cashflow.transfers');
    }

    public function vendors()
    {
        if (!Gate::allows('cashflow_vendor_manage') && !Gate::allows('cashflow_vendor_ledger_view')) {
            return abort(401);
        }
        return view('admin.cashflow.vendors');
    }

    public function staff()
    {
        if (!Gate::allows('cashflow_staff_advance')) {
            return abort(401);
        }
        return view('admin.cashflow.staff');
    }

    public function reports()
    {
        if (!Gate::allows('cashflow_reports')) {
            return abort(401);
        }
        return view('admin.cashflow.reports');
    }

    public function settings()
    {
        if (!Gate::allows('cashflow_settings')) {
            return abort(401);
        }
        return view('admin.cashflow.settings');
    }

    public function fdmView()
    {
        if (!Gate::allows('cashflow_fdm_view')) {
            return abort(401);
        }
        return view('admin.cashflow.fdm');
    }
}
