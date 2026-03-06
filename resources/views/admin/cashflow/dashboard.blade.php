@extends('admin.layouts.master')
@section('title', 'Cash Flow Dashboard')
@section('content')
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'Cash Flow Dashboard', 'title' => 'Dashboard'])
        <div class="d-flex flex-column-fluid">
            <div class="container">

                <!-- Quick-Action Bar -->
                <div class="card card-custom mb-5">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div class="d-flex align-items-center">
                                @can('cashflow_expense_create')
                                <a href="{{ route('admin.cashflow.expenses') }}?action=add" class="btn btn-primary mr-2"><i class="la la-plus"></i> Expense</a>
                                @endcan
                                @can('cashflow_transfer_create')
                                <a href="{{ route('admin.cashflow.transfers') }}?action=add" class="btn btn-info mr-2"><i class="la la-exchange-alt"></i> Transfer</a>
                                @endcan
                                @can('cashflow_vendor_transaction')
                                <a href="{{ route('admin.cashflow.vendors') }}?action=add" class="btn btn-warning mr-2"><i class="la la-shopping-cart"></i> Vendor Purchase</a>
                                @endcan
                                @can('cashflow_staff_advance')
                                <a href="{{ route('admin.cashflow.staff') }}?action=add" class="btn btn-success"><i class="la la-hand-holding-usd"></i> Advance</a>
                                @endcan
                            </div>
                            <div class="d-flex align-items-center">
                                <button id="btn-refresh-dash" class="btn btn-light-primary"><i class="la la-sync"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Actions Widget -->
                <div class="row mb-5" id="pending-actions-row">
                    <div class="col-md-2"><div class="card card-custom bg-light-warning py-3 px-3 cursor-pointer" id="pa-pending"><div class="font-size-xs font-weight-bold">Pending</div><div class="font-weight-bolder font-size-h4 mt-1" id="pa-pending-count">0</div></div></div>
                    <div class="col-md-2"><div class="card card-custom bg-light-danger py-3 px-3 cursor-pointer" id="pa-flagged"><div class="font-size-xs font-weight-bold">Flagged</div><div class="font-weight-bolder font-size-h4 mt-1" id="pa-flagged-count">0</div></div></div>
                    <div class="col-md-2"><div class="card card-custom bg-light-dark py-3 px-3"><div class="font-size-xs font-weight-bold">No Receipts</div><div class="font-weight-bolder font-size-h4 mt-1" id="pa-no-receipt">0</div></div></div>
                    <div class="col-md-2"><div class="card card-custom bg-light-success py-3 px-3"><div class="font-size-xs font-weight-bold">Today</div><div class="font-weight-bolder font-size-h4 mt-1" id="pa-today-total">PKR 0</div></div></div>
                    <div class="col-md-2"><div class="card card-custom bg-light-info py-3 px-3"><div class="font-size-xs font-weight-bold">This Month</div><div class="font-weight-bolder font-size-h4 mt-1" id="pa-mtd-total">PKR 0</div></div></div>
                    <div class="col-md-2"><div class="card card-custom bg-light-primary py-3 px-3"><div class="font-size-xs font-weight-bold">Advances Owed</div><div class="font-weight-bolder font-size-h4 mt-1" id="pa-advances-owed">PKR 0</div></div></div>
                </div>

                <!-- Pending Expenses Inline List (Sec 16 Screen 1) -->
                @can('cashflow_expense_approve')
                <div class="row mb-5 d-none" id="pending-list-row">
                    <div class="col-lg-12">
                        <div class="card card-custom">
                            <div class="card-header py-3">
                                <div class="card-title"><h3 class="card-label"><i class="la la-hourglass-half mr-2 text-warning"></i>Pending Approval</h3></div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive"><table class="table table-sm table-head-custom mb-0"><thead><tr><th class="px-4">Date</th><th class="px-4">Description</th><th class="px-4">Category</th><th class="text-right px-4">Amount</th><th class="px-4">By</th><th class="px-4">Attach</th><th class="text-center px-4" style="width:120px">Actions</th></tr></thead><tbody id="pending-list-tbody"></tbody></table></div>
                            </div>
                        </div>
                    </div>
                </div>
                @endcan

                <!-- Summary Cards: Inflows | Outflows | Net -->
                <div class="row mb-5">
                    <div class="col-md-4">
                        <div class="card card-custom bg-light-success py-4 px-5">
                            <div class="font-weight-bold text-success mb-1">Inflows</div>
                            <div class="font-weight-bolder font-size-h3" id="sum-inflows">PKR 0</div>
                            <div class="font-size-sm text-muted" id="sum-inflows-change"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-custom bg-light-danger py-4 px-5">
                            <div class="font-weight-bold text-danger mb-1">Outflows</div>
                            <div class="font-weight-bolder font-size-h3" id="sum-outflows">PKR 0</div>
                            <div class="font-size-sm text-muted" id="sum-outflows-change"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-custom bg-light-primary py-4 px-5">
                            <div class="font-weight-bold text-primary mb-1">Net Cash Flow</div>
                            <div class="font-weight-bolder font-size-h3" id="sum-net">PKR 0</div>
                            <div class="font-size-sm text-muted" id="sum-net-change"></div>
                        </div>
                    </div>
                </div>

                <!-- Pool Balance Cards -->
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title"><h3 class="card-label"><i class="la la-wallet mr-2"></i>Pool Balances</h3></div>
                        <div class="card-toolbar d-flex align-items-center" style="gap:16px;">
                            <span style="font-size:11px;color:#7E8299;">Cash Total</span>
                            <span class="font-weight-bolder font-size-h5 text-primary" id="pool-total-cash">PKR 0</span>
                            <span style="width:1px;height:20px;background:#E4E6EF;"></span>
                            <span style="font-size:11px;color:#7E8299;">Bank Total</span>
                            <span class="font-weight-bolder font-size-h5" style="color:#8950FC;" id="pool-total-bank">PKR 0</span>
                        </div>
                    </div>
                    <div class="card-body py-2 px-3">
                        <div class="d-flex flex-wrap" id="pool-balance-strip" style="gap:6px;"><span class="text-muted py-2">Loading...</span></div>
                        <div id="pool-bank-section" class="d-none" style="margin-top:8px;padding-top:8px;border-top:1px dashed #E4E6EF;">
                            <div class="d-flex flex-wrap" id="pool-balance-strip-bank" style="gap:6px;"></div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-5">
                    <!-- Daily Trend -->
                    <div class="col-lg-8">
                        <div class="card card-custom h-100">
                            <div class="card-header py-3"><div class="card-title"><h3 class="card-label"><i class="la la-chart-line mr-2"></i>Daily Inflows vs Outflows</h3></div></div>
                            <div class="card-body"><canvas id="chart-daily-trend" height="250"></canvas></div>
                        </div>
                    </div>
                    <!-- Category Breakdown -->
                    <div class="col-lg-4">
                        <div class="card card-custom h-100">
                            <div class="card-header py-3"><div class="card-title"><h3 class="card-label"><i class="la la-chart-pie mr-2"></i>By Category</h3></div></div>
                            <div class="card-body"><canvas id="chart-category-pie" height="250"></canvas></div>
                        </div>
                    </div>
                </div>

                <!-- Vendor Outstanding + Staff Advances -->
                <div class="row mb-5">
                    <div class="col-lg-6">
                        <div class="card card-custom h-100">
                            <div class="card-header py-3">
                                <div class="card-title"><h3 class="card-label"><i class="la la-file-invoice-dollar mr-2"></i>Vendor Outstanding (Top 10)</h3></div>
                                <div class="card-toolbar"><a href="{{ route('admin.cashflow.vendors') }}" class="btn btn-sm btn-light-primary">View All</a></div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive"><table class="table table-sm table-head-custom mb-0"><thead><tr><th>Vendor</th><th class="text-right">Balance</th><th>Terms</th></tr></thead><tbody id="vendor-outstanding-tbody"></tbody></table></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card card-custom h-100">
                            <div class="card-header py-3">
                                <div class="card-title"><h3 class="card-label"><i class="la la-calendar-check mr-2"></i>Upcoming Vendor Payments Due</h3></div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive"><table class="table table-sm table-head-custom mb-0"><thead><tr><th class="px-4">Vendor</th><th class="text-right px-4">Balance</th><th class="px-4">Terms</th><th class="px-4">Due</th><th class="px-4">Status</th></tr></thead><tbody id="vendor-due-tbody"></tbody></table></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Staff Row: Advances Outstanding + Recent Staff Expenses -->
                <div class="row mb-5">
                    <div class="col-lg-6">
                        <div class="card card-custom h-100">
                            <div class="card-header py-3"><div class="card-title"><h3 class="card-label"><i class="la la-user-clock mr-2"></i>Staff Advances Outstanding</h3></div></div>
                            <div class="card-body p-0">
                                <div class="table-responsive"><table class="table table-sm table-head-custom mb-0"><thead><tr><th>Staff</th><th class="text-right">Outstanding</th><th>Days</th><th>Status</th></tr></thead><tbody id="staff-advances-tbody"></tbody></table></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card card-custom h-100">
                            <div class="card-header py-3"><div class="card-title"><h3 class="card-label"><i class="la la-receipt mr-2"></i>Recent Staff Expenses (Top 10)</h3></div></div>
                            <div class="card-body p-0">
                                <div class="table-responsive"><table class="table table-sm table-head-custom mb-0"><thead><tr><th>Date</th><th>Staff</th><th>Description</th><th class="text-right">Amount</th><th>Status</th></tr></thead><tbody id="staff-expenses-tbody"></tbody></table></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Accountant Widgets (shown only for accountant role) -->
                <div class="row mb-5 d-none" id="accountant-widgets-row">
                    <div class="col-md-3"><div class="card card-custom bg-light-primary py-3 px-4"><div class="font-weight-bold mb-1">My Entries Today</div><div class="font-weight-bolder font-size-h4" id="aw-entries-count">0</div><div class="font-size-sm text-muted" id="aw-entries-total">PKR 0</div></div></div>
                    <div class="col-md-3"><div class="card card-custom bg-light-danger py-3 px-4"><div class="font-weight-bold mb-1">Rejected (Re-entry)</div><div class="font-weight-bolder font-size-h4" id="aw-rejected">0</div></div></div>
                    <div class="col-md-3"><div class="card card-custom bg-light-warning py-3 px-4"><div class="font-weight-bold mb-1">Missing Attachments</div><div class="font-weight-bolder font-size-h4" id="aw-missing">0</div></div></div>
                    <div class="col-md-3"><div class="card card-custom bg-light-info py-3 px-4"><div class="font-weight-bold mb-1">Vendor Trends</div><div class="font-size-sm" id="aw-vendor-trends">Loading...</div></div></div>
                </div>

                <!-- Voided Entries Alert (last 7 days) -->
                <div class="row mb-5">
                    <div class="col-lg-6 d-none" id="voided-alerts-col">
                        <div class="card card-custom border-left border-danger border-3 h-100">
                            <div class="card-header py-3">
                                <div class="card-title"><h3 class="card-label text-danger"><i class="la la-exclamation-triangle mr-2"></i>Voided (Last 7 Days)</h3></div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive"><table class="table table-sm table-head-custom mb-0"><thead><tr><th class="px-4">Date</th><th class="px-4">Description</th><th class="text-right px-4">Amount</th><th class="px-4">Reason</th></tr></thead><tbody id="voided-alerts-tbody"></tbody></table></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 d-none" id="flagged-alerts-col">
                        <div class="card card-custom border-left border-warning border-3 h-100">
                            <div class="card-header py-3">
                                <div class="card-title"><h3 class="card-label text-warning"><i class="la la-flag mr-2"></i>Flagged Entries</h3></div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive"><table class="table table-sm table-head-custom mb-0"><thead><tr><th class="px-4">Date</th><th class="px-4">Description</th><th class="text-right px-4">Amount</th><th class="px-4">Flag Reason</th></tr></thead><tbody id="flagged-alerts-tbody"></tbody></table></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Entries + Reconciliation -->
                <div class="row mb-5">
                    <div class="col-lg-8">
                        <div class="card card-custom">
                            <div class="card-header py-3"><div class="card-title"><h3 class="card-label"><i class="la la-clock mr-2"></i>Recent Entries</h3></div></div>
                            <div class="card-body p-0">
                                <div class="table-responsive"><table class="table table-sm table-head-custom mb-0"><thead><tr><th>Date</th><th>Category</th><th class="text-right">Amount</th><th>Pool</th><th>By</th></tr></thead><tbody id="recent-entries-tbody"></tbody></table></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        @can('cashflow_settings')
                        <div class="card card-custom">
                            <div class="card-header py-3"><div class="card-title"><h3 class="card-label"><i class="la la-balance-scale mr-2"></i>Reconciliation</h3></div></div>
                            <div class="card-body text-center">
                                <p class="text-muted font-size-sm mb-3">Compare cached pool balances against calculated totals.</p>
                                <button id="btn-reconcile" class="btn btn-outline-primary btn-sm"><i class="la la-check-circle"></i> Run Reconciliation Check</button>
                                <div id="reconcile-result" class="mt-3 d-none"></div>
                            </div>
                        </div>
                        @endcan
                    </div>
                </div>

            </div>
        </div>
    </div>

    @push('js')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
        <script src="{{ asset('assets/js/pages/cashflow/dashboard.js') }}"></script>
    @endpush
@endsection
