@extends('admin.layouts.master')
@section('title', 'Cash Flow - Vendors')
@section('content')
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'Vendor Management', 'title' => 'Vendors'])
        <div class="d-flex flex-column-fluid">
            <div class="container">

                <!-- Vendors List -->
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title"><h3 class="card-label"><i class="la la-store mr-2"></i>Vendors</h3></div>
                        <div class="card-toolbar">
                            @if(Gate::allows('cashflow_vendor_manage'))
                                <button class="btn btn-primary" data-toggle="modal" data-target="#modal_vendor"><i class="la la-plus"></i> Add Vendor</button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <select id="filter-active" class="form-control form-control-sm kt-select2-general">
                                    <option value="">All Status</option>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <div class="input-group input-group-sm">
                                    <input type="text" id="filter-search" class="form-control" placeholder="Search vendors..." />
                                    <div class="input-group-append">
                                        <button id="btn-filter" class="btn btn-primary"><i class="la la-search"></i></button>
                                        <button id="btn-reset-filters" class="btn btn-secondary" title="Reset Filters"><i class="la la-undo"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-head-custom table-vertical-center">
                                <thead><tr><th>Name</th><th>Contact</th><th>Phone</th><th>Payment Terms</th><th class="text-right">Balance</th><th>Status</th><th class="text-center">Actions</th></tr></thead>
                                <tbody id="vendors-tbody"><tr><td colspan="7" class="text-center">Loading...</td></tr></tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div class="text-muted" id="vendors-pagination-info"></div>
                            <div id="vendors-pagination-links"></div>
                        </div>
                    </div>
                </div>

                <!-- Vendor Requests -->
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title"><h3 class="card-label"><i class="la la-inbox mr-2"></i>Vendor Requests</h3></div>
                        <div class="card-toolbar">
                            <button class="btn btn-info" data-toggle="modal" data-target="#modal_vendor_request"><i class="la la-plus"></i> Request New Vendor</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-head-custom table-vertical-center">
                                <thead><tr><th>Name</th><th>Phone</th><th>Note</th><th>Requested By</th><th>Status</th><th class="text-center">Actions</th></tr></thead>
                                <tbody id="requests-tbody"><tr><td colspan="6" class="text-center">Loading...</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Vendor Ledger (shown when clicking a vendor) -->
                <div class="card card-custom d-none" id="vendor-ledger-card">
                    <div class="card-header py-3">
                        <div class="card-title"><h3 class="card-label"><i class="la la-list-alt mr-2"></i>Vendor Ledger: <span id="ledger-vendor-name"></span></h3></div>
                        <div class="card-toolbar">
                            <button class="btn btn-secondary" id="btn-close-ledger"><i class="la la-times"></i> Close</button>
                            @if(Gate::allows('cashflow_vendor_transaction'))
                                <button class="btn btn-primary ml-2" data-toggle="modal" data-target="#modal_transaction"><i class="la la-plus"></i> Record Purchase</button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4"><div class="bg-light-primary p-3 rounded text-center"><strong>Opening</strong><br/><span id="ledger-opening">0</span></div></div>
                            <div class="col-md-4"><div class="bg-light-warning p-3 rounded text-center"><strong>Current Balance</strong><br/><span id="ledger-balance">0</span></div></div>
                            <div class="col-md-4"><div class="bg-light-info p-3 rounded text-center"><strong>Total Transactions</strong><br/><span id="ledger-count">0</span></div></div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-head-custom table-vertical-center">
                                <thead><tr><th>Date</th><th>Type</th><th class="text-right">Amount</th><th>Description</th><th>Reference</th><th>By</th></tr></thead>
                                <tbody id="ledger-tbody"><tr><td colspan="6" class="text-center">Loading...</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Add/Edit Vendor Modal -->
    <div class="modal fade" id="modal_vendor">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="vendor-modal-title">Add Vendor</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <div class="modal-body">
                    <form id="form-vendor">
                        <input type="hidden" name="vendor_id" />
                        <div class="row">
                            <div class="col-md-6"><div class="form-group"><label>Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required /></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Contact Person</label><input type="text" name="contact_person" class="form-control" /></div></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4"><div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control" /></div></div>
                            <div class="col-md-4"><div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" /></div></div>
                            <div class="col-md-4"><div class="form-group"><label>Payment Terms</label><select name="payment_terms" class="form-control kt-select2-general"><option value="upfront">Upfront</option><option value="net_7">Net 7</option><option value="net_15">Net 15</option><option value="net_30">Net 30</option><option value="custom">Custom</option></select></div></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6"><div class="form-group"><label>Category</label><input type="text" name="category" class="form-control" placeholder="e.g. Cleaning, Office Supplies" /></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Opening Balance</label><input type="number" name="opening_balance" class="form-control" min="0" step="0.01" value="0" /></div></div>
                        </div>
                        <div class="form-group"><label>Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
                        <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                    </form>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="button" id="btn-submit-vendor" class="btn btn-primary">Save</button></div>
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

    <!-- Vendor Purchase Modal -->
    <div class="modal fade" id="modal_transaction" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Record Purchase / Bill</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <div class="modal-body">
                    <p class="text-muted font-size-sm mb-3">Record goods received or bill from vendor. This increases the balance owed. Payments are recorded automatically when expenses are linked to a vendor.</p>
                    <form id="form-transaction">
                        <div class="form-group"><label>Amount (PKR) <span class="text-danger">*</span></label><input type="number" name="amount" class="form-control" min="1" step="1" required /></div>
                        <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                        <div class="form-group"><label>Reference No.</label><input type="text" name="reference_no" class="form-control" /></div>
                    </form>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="button" id="btn-submit-transaction" class="btn btn-primary">Record Purchase</button></div>
            </div>
        </div>
    </div>

    @push('js')
        <script>
            var cfPerms = {
                canManage: {{ Gate::allows('cashflow_vendor_manage') ? 'true' : 'false' }},
                canTransaction: {{ Gate::allows('cashflow_vendor_transaction') ? 'true' : 'false' }},
                canAudit: {{ Gate::allows('cashflow_audit_view') ? 'true' : 'false' }}
            };
        </script>
        <script src="{{ asset('assets/js/pages/cashflow/vendors.js') }}"></script>
    @endpush
@endsection
