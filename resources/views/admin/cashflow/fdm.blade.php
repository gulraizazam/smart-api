@extends('admin.layouts.master')
@section('title', 'Cash Flow - FDM View')
@section('content')
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'FDM Cash View', 'title' => 'Branch Cash'])
        <div class="d-flex flex-column-fluid">
            <div class="container">

                <!-- Live Balance Card (Full Width) -->
                <div class="row mb-5">
                    <div class="col-md-12">
                        <div class="card card-custom" id="fdm-balance-card">
                            <div class="card-body text-center py-6">
                                <div class="text-muted font-weight-bold mb-1" id="fdm-branch-name">Loading...</div>
                                <div class="font-weight-bolder display-4 mb-1" id="fdm-balance">PKR 0</div>
                                <div class="text-muted font-size-sm"><i class="la la-circle text-success" style="font-size:8px;vertical-align:middle;"></i> Live Cash Balance</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Totals Row -->
                <div class="row mb-5">
                    <div class="col-md-2">
                        <div class="card card-custom border-left border-primary" style="border-left-width:4px !important;">
                            <div class="card-body py-4 px-5">
                                <div class="text-muted font-size-sm font-weight-bold">Opening Balance</div>
                                <div class="font-weight-bolder font-size-h3 text-primary" id="fdm-opening-balance">PKR 0</div>
                                <div class="text-muted font-size-xs" id="fdm-week-label">as of Sunday</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card card-custom border-left border-success" style="border-left-width:4px !important;">
                            <div class="card-body py-4 px-5">
                                <div class="text-muted font-size-sm font-weight-bold">Services Cash</div>
                                <div class="font-weight-bolder font-size-h3 text-success" id="fdm-services-cash">PKR 0</div>
                                <div class="text-muted font-size-xs">since Mar 8</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card card-custom border-left border-success" style="border-left-width:4px !important;">
                            <div class="card-body py-4 px-5">
                                <div class="text-muted font-size-sm font-weight-bold">Inventory Cash</div>
                                <div class="font-weight-bolder font-size-h3 text-success" id="fdm-inventory-cash">PKR 0</div>
                                <div class="text-muted font-size-xs">since Mar 8</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card card-custom border-left border-danger" style="border-left-width:4px !important;">
                            <div class="card-body py-4 px-5">
                                <div class="text-muted font-size-sm font-weight-bold">Expenses Total</div>
                                <div class="font-weight-bolder font-size-h3 text-danger" id="fdm-total-expenses">PKR 0</div>
                                <div class="text-muted font-size-xs" id="fdm-total-expenses-count">0 records</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card card-custom border-left border-info" style="border-left-width:4px !important;">
                            <div class="card-body py-4 px-5">
                                <div class="text-muted font-size-sm font-weight-bold">Cash Transfers Total</div>
                                <div class="font-weight-bolder font-size-h3 text-info" id="fdm-total-transfers">PKR 0</div>
                                <div class="text-muted font-size-xs" id="fdm-total-transfers-count">0 records</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card card-custom border-left border-warning" style="border-left-width:4px !important;">
                            <div class="card-body py-4 px-5">
                                <div class="text-muted font-size-sm font-weight-bold">Staff Advances Total</div>
                                <div class="font-weight-bolder font-size-h3 text-warning" id="fdm-total-advances">PKR 0</div>
                                <div class="text-muted font-size-xs" id="fdm-total-advances-count">0 records</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expenses -->
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title"><h3 class="card-label"><i class="la la-receipt mr-2 text-danger"></i>Expenses <span class="text-muted font-size-sm ml-2" id="fdm-expenses-period"></span></h3></div>
                    </div>
                    <div class="card-body px-4 py-3">
                        <div class="table-responsive">
                            <table class="table table-head-custom mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Category</th>
                                        <th>Paid From</th>
                                        <th class="text-right">Amount</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width:50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="fdm-expenses-tbody">
                                    <tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Cash Transfers -->
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title"><h3 class="card-label"><i class="la la-exchange-alt mr-2 text-info"></i>Cash Transfers <span class="text-muted font-size-sm ml-2" id="fdm-transfers-period"></span></h3></div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-head-custom mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th class="text-right">Amount</th>
                                        <th>Description</th>
                                        <th>By</th>
                                    </tr>
                                </thead>
                                <tbody id="fdm-transfers-tbody">
                                    <tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Staff Advances -->
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title"><h3 class="card-label"><i class="la la-user-clock mr-2 text-warning"></i>Staff Advances <span class="text-muted font-size-sm ml-2" id="fdm-advances-period"></span></h3></div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-head-custom mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Staff Member</th>
                                        <th>Pool</th>
                                        <th class="text-right">Amount</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody id="fdm-advances-tbody">
                                    <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Expense Detail Modal -->
    <div class="modal fade" id="modal_fdm_expense" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-3">
                    <h5 class="modal-title"><i class="la la-receipt text-danger mr-2"></i>Expense Detail</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <table class="table table-borderless table-sm mb-0">
                        <tr><td class="text-muted" style="width:120px;">Date</td><td class="font-weight-bold" id="fdm-exp-date"></td></tr>
                        <tr><td class="text-muted">Description</td><td id="fdm-exp-desc"></td></tr>
                        <tr><td class="text-muted">Category</td><td id="fdm-exp-category"></td></tr>
                        <tr><td class="text-muted">Paid From</td><td id="fdm-exp-pool"></td></tr>
                        <tr><td class="text-muted">Amount</td><td class="font-weight-bold text-danger" id="fdm-exp-amount"></td></tr>
                        <tr><td class="text-muted">Status</td><td id="fdm-exp-status"></td></tr>
                    </table>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @push('js')
        <script src="{{ asset('assets/js/pages/cashflow/fdm.js') }}?v={{ time() }}"></script>
    @endpush
@endsection
