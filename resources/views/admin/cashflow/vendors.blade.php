@extends('admin.layouts.master')
@section('title', 'Cash Flow - Vendors')
@section('content')
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'Vendor Management', 'title' => 'Vendors'])
        <div class="d-flex flex-column-fluid">
            <div class="container-fluid px-4">

                <div class="row">
                    {{-- ========== LEFT PANEL (35%) – Vendor List + Requests ========== --}}
                    <div class="col-lg-5 col-xl-4" id="vendor-left-panel">

                        {{-- Vendor List Card --}}
                        <div class="card card-custom mb-4">
                            <div class="card-header py-3" style="min-height:auto;">
                                <div class="card-title mb-0"><h3 class="card-label font-size-h6 mb-0"><i class="la la-store mr-1"></i>Vendors</h3></div>
                                <div class="card-toolbar">
                                    @if(Gate::allows('cashflow_vendor_create'))
                                        <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal_vendor"><i class="la la-plus"></i> Add</button>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body py-3 px-4">
                                <div class="d-flex mb-3">
                                    <select id="filter-active" class="form-control form-control-sm mr-2" style="width:100px;">
                                        <option value="">All</option>
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="filter-search" class="form-control" placeholder="Search..." />
                                        <div class="input-group-append">
                                            <button id="btn-filter" class="btn btn-primary btn-sm"><i class="la la-search p-0"></i></button>
                                            <button id="btn-reset-filters" class="btn btn-secondary btn-sm" title="Reset"><i class="la la-undo p-0"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <div id="vendors-list" style="max-height:calc(100vh - 340px);overflow-y:auto;">
                                    <div class="text-center text-muted py-5"><div class="spinner spinner-primary spinner-sm"></div></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted" id="vendors-pagination-info"></small>
                                    <div id="vendors-pagination-links"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Vendor Requests Card --}}
                        <div class="card card-custom mb-4">
                            <div class="card-header py-3" style="min-height:auto;">
                                <div class="card-title mb-0"><h3 class="card-label font-size-h6 mb-0"><i class="la la-inbox mr-1"></i>Vendor Requests</h3></div>
                                <div class="card-toolbar">
                                    <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#modal_vendor_request"><i class="la la-plus"></i> Request</button>
                                </div>
                            </div>
                            <div class="card-body py-3 px-4">
                                <div id="requests-list" style="max-height:250px;overflow-y:auto;">
                                    <div class="text-center text-muted py-3">Loading...</div>
                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- ========== RIGHT PANEL (65%) – Vendor Ledger Detail ========== --}}
                    <div class="col-lg-7 col-xl-8" id="vendor-right-panel">
                        {{-- Vendor Overview (default view) --}}
                        <div id="vendor-overview">
                            {{-- Summary Cards --}}
                            <div class="row mb-4">
                                <div class="col-6 col-md-3">
                                    <div class="card card-custom" style="border-left:4px solid #3699FF;">
                                        <div class="card-body py-3 px-4">
                                            <div class="text-muted font-size-xs text-uppercase font-weight-bold">Balance on <span id="ov-month-label">1st</span></div>
                                            <div class="font-size-h5 font-weight-bolder mt-1" id="ov-opening">—</div>
                                            <div class="text-muted font-size-xs" id="ov-vendor-count">—</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="card card-custom" style="border-left:4px solid #F64E60;">
                                        <div class="card-body py-3 px-4">
                                            <div class="text-muted font-size-xs text-uppercase font-weight-bold">Outstanding</div>
                                            <div class="font-size-h5 font-weight-bolder mt-1" id="ov-outstanding">—</div>
                                            <div class="text-muted font-size-xs" id="ov-with-balance">—</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="card card-custom" style="border-left:4px solid #FFA800;">
                                        <div class="card-body py-3 px-4">
                                            <div class="text-muted font-size-xs text-uppercase font-weight-bold">This Month Purchases</div>
                                            <div class="font-size-h5 font-weight-bolder mt-1 text-danger" id="ov-month-purchases">—</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="card card-custom" style="border-left:4px solid #1BC5BD;">
                                        <div class="card-body py-3 px-4">
                                            <div class="text-muted font-size-xs text-uppercase font-weight-bold">This Month Payments</div>
                                            <div class="font-size-h5 font-weight-bolder mt-1 text-success" id="ov-month-payments">—</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Top Outstanding Vendors --}}
                            <div class="card card-custom mb-4">
                                <div class="card-header py-3" style="min-height:auto;">
                                    <div class="card-title mb-0"><h3 class="card-label font-size-h6 mb-0"><i class="la la-sort-amount-down mr-1 text-danger"></i>Top Outstanding Vendors</h3></div>
                                </div>
                                <div class="card-body py-3 px-5" id="ov-top-vendors">
                                    <div class="text-center text-muted py-4"><div class="spinner spinner-primary spinner-sm"></div></div>
                                </div>
                            </div>

                            {{-- Recent Transactions --}}
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card card-custom mb-4">
                                        <div class="card-header py-3" style="min-height:auto;">
                                            <div class="card-title mb-0"><h3 class="card-label font-size-h6 mb-0"><i class="la la-arrow-up mr-1 text-danger"></i>Recent Purchases</h3></div>
                                        </div>
                                        <div class="card-body py-2 px-5" id="ov-recent-purchases">
                                            <div class="text-center text-muted py-4"><div class="spinner spinner-primary spinner-sm"></div></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card card-custom mb-4">
                                        <div class="card-header py-3" style="min-height:auto;">
                                            <div class="card-title mb-0"><h3 class="card-label font-size-h6 mb-0"><i class="la la-arrow-down mr-1 text-success"></i>Recent Payments</h3></div>
                                        </div>
                                        <div class="card-body py-2 px-5" id="ov-recent-payments">
                                            <div class="text-center text-muted py-4"><div class="spinner spinner-primary spinner-sm"></div></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Ledger Detail (hidden initially) --}}
                        <div id="vendor-ledger-card" class="d-none">
                            {{-- Vendor Header --}}
                            <div class="card card-custom mb-4">
                                <div class="card-body py-4 px-5">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h4 class="mb-1" id="ledger-vendor-name"></h4>
                                            <div class="d-flex flex-wrap">
                                                <span class="text-muted font-size-sm mr-4" id="ledger-vendor-contact"></span>
                                                <span class="text-muted font-size-sm mr-4" id="ledger-vendor-phone"></span>
                                                <span class="text-muted font-size-sm" id="ledger-vendor-terms"></span>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            @if(Gate::allows('cashflow_vendor_transaction'))
                                                <button class="btn btn-sm btn-primary mr-2" id="btn-record-purchase"><i class="la la-shopping-cart mr-1"></i>Record Purchase</button>
                                            @endif
                                            @if(Gate::allows('cashflow_vendor_edit'))
                                                <button class="btn btn-sm btn-light-primary mr-2" id="btn-edit-current-vendor" title="Edit Vendor"><i class="la la-edit"></i></button>
                                            @endif
                                            @if(Gate::allows('cashflow_vendor_toggle'))
                                                <button class="btn btn-sm btn-light-warning" id="btn-toggle-current-vendor" title="Activate / Deactivate"><i class="la la-toggle-on"></i></button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Balance Summary --}}
                            <div class="row mb-3">
                                <div class="col-4">
                                    <div class="card card-custom" style="border-left:4px solid #3699FF;">
                                        <div class="card-body py-3 px-4">
                                            <div class="text-muted font-size-xs text-uppercase font-weight-bold">Balance on <span id="ledger-opening-date"></span></div>
                                            <div class="font-size-h5 font-weight-bolder mt-1" id="ledger-opening">PKR 0</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="card card-custom" id="ledger-balance-card" style="border-left:4px solid #FFA800;">
                                        <div class="card-body py-3 px-4">
                                            <div class="text-muted font-size-xs text-uppercase font-weight-bold">Outstanding Balance</div>
                                            <div class="font-size-h5 font-weight-bolder mt-1" id="ledger-balance">PKR 0</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="card card-custom" style="border-left:4px solid #8950FC;">
                                        <div class="card-body py-3 px-4">
                                            <div class="text-muted font-size-xs text-uppercase font-weight-bold">Period Transactions</div>
                                            <div class="font-size-h5 font-weight-bolder mt-1" id="ledger-count">0</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {{-- Period Summary Stats --}}
                            <div class="d-flex align-items-center mb-4 px-1" style="gap:20px;">
                                <span class="font-size-sm"><span class="text-muted">Purchases:</span> <span class="font-weight-bold text-danger" id="ledger-stat-purchases">PKR 0</span></span>
                                <span class="font-size-sm"><span class="text-muted">Payments:</span> <span class="font-weight-bold text-success" id="ledger-stat-payments">PKR 0</span></span>
                                <span class="font-size-sm"><span class="text-muted">Net:</span> <span class="font-weight-bold" id="ledger-stat-net">PKR 0</span></span>
                            </div>

                            {{-- Ledger Transactions --}}
                            <div class="card card-custom">
                                <div class="card-header py-3" style="min-height:auto;">
                                    <div class="card-title mb-0"><h3 class="card-label font-size-h6 mb-0"><i class="la la-list-alt mr-1"></i>Transactions</h3></div>
                                    <div class="card-toolbar d-flex align-items-center" style="gap:6px;">
                                        <div class="input-group input-group-sm" style="width:210px;">
                                            <div class="input-group-prepend" ><span class="input-group-text py-0 px-2" style="height:32px !important"><i class="la la-calendar font-size-sm"></i></span></div>
                                            <input type="text" id="ledger-date-range" class="form-control form-control-sm" placeholder="Date range" readonly style="cursor:pointer;background:#fff;" />
                                        </div>
                                        <select id="ledger-type-filter" class="form-control form-control-sm" style="width:110px;">
                                            <option value="">All Types</option>
                                            <option value="purchase">Purchases</option>
                                            <option value="payment">Payments</option>
                                        </select>
                                        @if(Gate::allows('cashflow_vendor_ledger_export'))
                                        <button class="btn btn-sm btn-outline-secondary py-1 px-2" id="btn-export-ledger" title="Export CSV"><i class="la la-download p-0"></i></button>
                                        @endif
                                    </div>
                                </div>
                                <div class="card-body py-3 px-4">
                                    <div id="ledger-transactions" style="max-height:calc(100vh - 480px);overflow-y:auto;">
                                        <div class="text-center text-muted py-5">Loading...</div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <small class="text-muted" id="ledger-pagination-info"></small>
                                        <div id="ledger-pagination-links"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Add/Edit Vendor Modal -->
    <div class="modal fade" id="modal_vendor">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header py-3">
                    <h5 class="modal-title font-size-h6" id="vendor-modal-title"><i class="la la-store mr-1"></i>Add Vendor</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body py-4 px-5">
                    <form id="form-vendor">
                        <input type="hidden" name="vendor_id" />

                        {{-- Name + Contact Person --}}
                        <div class="row">
                            <div class="col-7">
                                <div class="form-group mb-3">
                                    <label class="font-size-sm font-weight-bold mb-1">Vendor Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. MedSource Supplies" />
                                    <div class="invalid-feedback">Vendor name is required.</div>
                                </div>
                            </div>
                            <div class="col-5">
                                <div class="form-group mb-3">
                                    <label class="font-size-sm font-weight-bold mb-1">Contact Person <span class="text-danger">*</span></label>
                                    <input type="text" name="contact_person" class="form-control form-control-sm" placeholder="Full name" />
                                    <div class="invalid-feedback">Contact person is required.</div>
                                </div>
                            </div>
                        </div>

                        {{-- Phone + Email --}}
                        <div class="row">
                            <div class="col-5">
                                <div class="form-group mb-3">
                                    <label class="font-size-sm font-weight-bold mb-1">Phone <span class="text-danger">*</span></label>
                                    <input type="text" name="phone" class="form-control form-control-sm" placeholder="+92 300 0000000" />
                                    <div class="invalid-feedback">Phone number is required.</div>
                                </div>
                            </div>
                            <div class="col-7">
                                <div class="form-group mb-3">
                                    <label class="font-size-sm font-weight-bold mb-1">Email</label>
                                    <input type="email" name="email" class="form-control form-control-sm" placeholder="vendor@example.com" />
                                </div>
                            </div>
                        </div>

                        {{-- Payment Terms + Category --}}
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group mb-3">
                                    <label class="font-size-sm font-weight-bold mb-1">Payment Terms <span class="text-danger">*</span></label>
                                    <select name="payment_terms" class="form-control form-control-sm vendor-select2">
                                        <option value="upfront">Upfront (pay immediately)</option>
                                        <option value="net_7">Net 7 (pay within 7 days)</option>
                                        <option value="net_15">Net 15 (pay within 15 days)</option>
                                        <option value="net_30">Net 30 (pay within 30 days)</option>
                                        <option value="custom">Custom</option>
                                    </select>
                                    <div class="invalid-feedback">Payment terms are required.</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group mb-3">
                                    <label class="font-size-sm font-weight-bold mb-1">Category <span class="text-danger">*</span></label>
                                    <select name="category_id" class="form-control form-control-sm vendor-select2" id="vendor-category-select">
                                        <option value="">— Select Category —</option>
                                    </select>
                                    <div class="invalid-feedback">Category is required.</div>
                                    <small class="text-muted">Categories with Vendor Emphasis enabled</small>
                                </div>
                            </div>
                        </div>

                        {{-- Opening Balance + Active toggle --}}
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group mb-3">
                                    <label class="font-size-sm font-weight-bold mb-1">
                                        Opening Balance
                                        <i class="la la-info-circle text-info ml-1" title="The outstanding amount already owed to this vendor before you started tracking in this system. Enter 0 if no prior balance exists." data-toggle="tooltip" data-placement="top"></i>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text">PKR</span></div>
                                        <input type="number" name="opening_balance" class="form-control form-control-sm" min="0" step="1" value="0" placeholder="0" />
                                    </div>
                                    <small class="text-muted">Prior outstanding balance before system start</small>
                                </div>
                            </div>
                            <div class="col-6 d-flex align-items-center" id="vendor-active-toggle-wrap" style="display:none!important;">
                                <div class="form-group mb-3 w-100">
                                    <label class="font-size-sm font-weight-bold mb-1 d-block">Status</label>
                                    <div class="d-flex align-items-center">
                                        <span class="switch switch-sm" id="vendor-active-switch">
                                            <label>
                                                <input type="checkbox" name="is_active" id="vendor-is-active" value="1" checked />
                                                <span></span>
                                            </label>
                                        </span>
                                        <span class="ml-2 font-size-sm" id="vendor-active-label">Active</span>
                                    </div>
                                    <small class="text-muted">Inactive vendors are hidden from purchase forms</small>
                                </div>
                            </div>
                        </div>

                        {{-- Address --}}
                        <div class="form-group mb-3">
                            <label class="font-size-sm font-weight-bold mb-1">Address</label>
                            <textarea name="address" class="form-control form-control-sm" rows="2" placeholder="Street, City"></textarea>
                        </div>

                        {{-- Notes --}}
                        <div class="form-group mb-0">
                            <label class="font-size-sm font-weight-bold mb-1">Notes</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Internal notes about this vendor"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btn-submit-vendor" class="btn btn-primary btn-sm"><i class="la la-check mr-1"></i>Save Vendor</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Vendor Request Modal -->
    <div class="modal fade" id="modal_vendor_request" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Request New Vendor</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <div class="modal-body">
                    <form id="form-vendor-request">
                        <div class="form-group"><label>Vendor Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required /></div>
                        <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control" /></div>
                        <div class="form-group"><label>Note</label><textarea name="note" class="form-control" rows="2"></textarea></div>
                    </form>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="button" id="btn-submit-request" class="btn btn-primary">Submit Request</button></div>
            </div>
        </div>
    </div>

    <!-- Record Purchase Modal -->
    @if(Gate::allows('cashflow_vendor_transaction'))
    <div class="modal fade" id="modal_purchase" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="purchase-modal-title"><i class="la la-shopping-cart mr-1"></i>Record Purchase — <span id="purchase-vendor-name" class="text-primary"></span></h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="form-purchase">
                        <input type="hidden" name="transaction_id" value="" />
                        <div class="form-group">
                            <label>Description <span class="text-danger">*</span></label>
                            <input type="text" name="description" class="form-control" placeholder="e.g. Medical consumables - syringes, gloves" required maxlength="100" />
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Amount <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text">PKR</span></div>
                                        <input type="number" name="amount" class="form-control" min="1" step="1" required />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Date <span class="text-danger">*</span></label>
                                    <input type="text" name="transaction_date" class="form-control" required readonly style="cursor:pointer;background:#fff;" placeholder="Select date" />
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Reference / Invoice No</label>
                                    <input type="text" name="reference_no" class="form-control" placeholder="Optional" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>For <span class="text-danger">*</span></label>
                                    <select name="for_branch_id" class="form-control">
                                        <option value="">Select</option>
                                        <option value="general">General / Company-wide</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Attachment (Google Drive URL) <span class="text-danger">*</span></label>
                            <input type="url" name="attachment_url" class="form-control" placeholder="https://drive.google.com/..." maxlength="500" required />
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btn-submit-purchase" class="btn btn-primary"><i class="la la-check mr-1"></i>Record Purchase</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Audit Trail Modal -->
    <div class="modal fade" id="modal_tx_audit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background:#F3F6F9;border-bottom:2px solid #E4E6EF;">
                    <h5 class="modal-title font-weight-bolder"><i class="la la-history text-primary mr-2"></i>Transaction Audit Trail</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><i class="la la-times"></i></button>
                </div>
                <div class="modal-body" style="max-height:500px;overflow-y:auto;">
                    <div id="tx-audit-loading" class="text-center py-5"><div class="spinner spinner-primary spinner-lg"></div></div>
                    <div id="tx-audit-timeline" class="d-none"></div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #E4E6EF;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @push('js')
        <script>
            var cfPerms = {
                canManage: {{ Gate::allows('cashflow_vendor_manage') ? 'true' : 'false' }},
                canCreate: {{ Gate::allows('cashflow_vendor_create') ? 'true' : 'false' }},
                canEdit: {{ Gate::allows('cashflow_vendor_edit') ? 'true' : 'false' }},
                canToggle: {{ Gate::allows('cashflow_vendor_toggle') ? 'true' : 'false' }},
                canTransaction: {{ Gate::allows('cashflow_vendor_transaction') ? 'true' : 'false' }},
                canTransactionEdit: {{ Gate::allows('cashflow_vendor_transaction_edit') ? 'true' : 'false' }},
                canTransactionDelete: {{ Gate::allows('cashflow_vendor_transaction_delete') ? 'true' : 'false' }},
                canLedgerExport: {{ Gate::allows('cashflow_vendor_ledger_export') ? 'true' : 'false' }},
                canExpenseCreate: {{ Gate::allows('cashflow_expense_create') ? 'true' : 'false' }},
                canAudit: {{ Gate::allows('cashflow_audit_view') ? 'true' : 'false' }}
            };
        </script>
        <script src="{{ asset('assets/js/pages/cashflow/vendors.js') }}"></script>
    @endpush
@endsection
