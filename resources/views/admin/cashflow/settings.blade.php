@extends('admin.layouts.master')
@section('title', 'Cash Flow Settings')
@section('content')

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

        @include('admin.partials.breadcrumb', ['module' => 'Cash Flow Settings', 'title' => 'Settings'])

        <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">

                <!--begin::Settings Card-->
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-cog mr-2"></i>General Settings</h3>
                        </div>
                        <div class="card-toolbar">
                            @if(Gate::allows('cashflow_settings'))
                                <button id="btn-reset-module" class="btn btn-outline-danger mr-2 d-none">
                                    <i class="la la-trash"></i> Reset Module
                                </button>
                                <button id="btn-save-settings" class="btn btn-primary">
                                    <i class="la la-save"></i> Save Settings
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="settings-loading" class="text-center py-5">
                            <div class="spinner spinner-primary spinner-lg"></div>
                        </div>
                        <form id="settings-form" class="d-none">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Go-Live Date <span class="text-danger">*</span></label>
                                        <input type="date" name="go_live_date" class="form-control" />
                                        <span class="form-text text-muted">Patient inflows counted from this date.</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Approval Threshold (PKR)</label>
                                        <input type="number" name="approval_threshold" class="form-control" min="0" step="1" />
                                        <span class="form-text text-muted">Expenses above this amount need admin approval.</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Backdate Flag Days</label>
                                        <input type="number" name="backdate_flag_days" class="form-control" min="1" max="90" />
                                        <span class="form-text text-muted">Expenses backdated beyond this are flagged.</span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Daily Auto-Approved Limit (PKR)</label>
                                        <input type="number" name="daily_auto_approved_limit" class="form-control" min="0" step="1" />
                                        <span class="form-text text-muted">Daily total limit before splitting flag.</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Advance Aging Days</label>
                                        <input type="number" name="advance_aging_days" class="form-control" min="1" max="365" />
                                        <span class="form-text text-muted">Days before uncleared advance is flagged.</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Cumulative Advance Threshold (PKR)</label>
                                        <input type="number" name="cumulative_advance_threshold" class="form-control" min="0" step="1" />
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Dormant Vendor Days</label>
                                        <input type="number" name="dormant_vendor_days" class="form-control" min="1" max="365" />
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Digest Send Time</label>
                                        <input type="time" name="digest_send_time" class="form-control" />
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Digest Recipients (emails)</label>
                                        <input type="text" name="digest_recipients" class="form-control" placeholder="email1@example.com, email2@example.com" />
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!--begin::Pools Card-->
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-university mr-2"></i>Cash Pools</h3>
                        </div>
                        <div class="card-toolbar">
                            @if(Gate::allows('cashflow_pool_manage'))
                                <button id="btn-recalculate-pools" class="btn btn-warning mr-2">
                                    <i class="la la-calculator"></i> Recalculate Balances
                                </button>
                                <button id="btn-init-pools" class="btn btn-info mr-2">
                                    <i class="la la-sync"></i> Initialize Branch Pools
                                </button>
                                <button id="btn-add-pool" class="btn btn-primary" data-toggle="modal" data-target="#modal_add_pool">
                                    <i class="la la-plus"></i> Add Pool
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-head-custom table-vertical-center" id="pools-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Branch</th>
                                        <th class="text-right">Opening Balance</th>
                                        <th class="text-right">Current Balance</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="pools-tbody">
                                    <tr><td colspan="7" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!--begin::Pending Requests Card-->
                @if(Gate::allows('cashflow_category_manage') || Gate::allows('cashflow_vendor_manage'))
                <div class="card card-custom mb-5 d-none" id="pending-requests-card">
                    <div class="card-header py-3">
                        <div class="card-title"><h3 class="card-label"><i class="la la-inbox mr-2 text-warning"></i>Pending Requests</h3></div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @can('cashflow_category_manage')
                            <div class="col-lg-6">
                                <h6 class="font-weight-bold mb-3">Category Requests</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-head-custom mb-0">
                                        <thead><tr><th>Name</th><th>Requested By</th><th class="text-center" style="width:100px">Actions</th></tr></thead>
                                        <tbody id="cat-requests-tbody"><tr><td colspan="3" class="text-center text-muted py-3">Loading...</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                            @endcan
                            @can('cashflow_vendor_manage')
                            <div class="col-lg-6">
                                <h6 class="font-weight-bold mb-3">Vendor Requests</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-head-custom mb-0">
                                        <thead><tr><th>Name</th><th>Requested By</th><th class="text-center" style="width:100px">Actions</th></tr></thead>
                                        <tbody id="vendor-requests-tbody"><tr><td colspan="3" class="text-center text-muted py-3">Loading...</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                            @endcan
                        </div>
                    </div>
                </div>
                @endif

                <!--begin::Categories Card-->
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-tags mr-2"></i>Expense Categories</h3>
                        </div>
                        <div class="card-toolbar">
                            @if(Gate::allows('cashflow_category_manage'))
                                <button id="btn-add-category" class="btn btn-primary" data-toggle="modal" data-target="#modal_add_category">
                                    <i class="la la-plus"></i> Add Category
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-head-custom table-vertical-center" id="categories-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Vendor Emphasis</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="categories-tbody">
                                    <tr><td colspan="5" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!--begin::Payment Method Mapping Card-->
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-credit-card mr-2"></i>Payment Method → Pool Mapping</h3>
                        </div>
                        <div class="card-toolbar">
                            @if(Gate::allows('cashflow_settings'))
                                <button id="btn-save-pm-mapping" class="btn btn-primary"><i class="la la-save"></i> Save Mapping</button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted font-size-sm mb-4">Configure which cash pool patient payments are automatically mapped to based on payment method.</p>
                        <div id="pm-mapping-container">
                            <div class="text-center py-3"><div class="spinner spinner-primary spinner-sm"></div> Loading...</div>
                        </div>
                    </div>
                </div>

                <!-- Advance-Eligible Staff (Sec 27.5) -->
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title"><h3 class="card-label"><i class="la la-user-check mr-2"></i>Advance-Eligible Staff</h3></div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted font-size-sm mb-3">Only users marked as eligible can receive cash advances. Toggle the checkbox to change eligibility.</p>
                        <div class="table-responsive">
                            <table class="table table-sm table-head-custom">
                                <thead><tr><th class="px-4">Name</th><th class="px-4">Email</th><th class="text-center px-4">Eligible</th></tr></thead>
                                <tbody id="eligible-staff-tbody">
                                    <tr><td colspan="3" class="text-center text-muted py-4">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!--begin::Audit Trail Card-->
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-history mr-2"></i>Audit Trail</h3>
                        </div>
                        <div class="card-toolbar">
                            <select id="audit-entity-filter" class="form-control form-control-sm kt-select2-general mr-2" style="width:150px;">
                                <option value="">All Types</option>
                                <option value="expense">Expenses</option>
                                <option value="transfer">Transfers</option>
                                <option value="vendor">Vendors</option>
                                <option value="vendor_transaction">Vendor Txn</option>
                                <option value="staff_advance">Advances</option>
                                <option value="staff_return">Returns</option>
                                <option value="cash_pool">Pools</option>
                                <option value="category">Categories</option>
                                <option value="settings">Settings</option>
                                <option value="period_lock">Period Lock</option>
                            </select>
                            <button id="btn-load-audit" class="btn btn-light-primary"><i class="la la-search"></i> Load</button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-head-custom mb-0">
                                <thead>
                                    <tr>
                                        <th class="px-4">Time</th>
                                        <th class="px-4">User</th>
                                        <th class="px-4">Action</th>
                                        <th class="px-4">Entity</th>
                                        <th class="px-4">ID</th>
                                        <th class="px-4">Reason</th>
                                        <th class="px-4">IP</th>
                                    </tr>
                                </thead>
                                <tbody id="audit-trail-tbody">
                                    <tr><td colspan="7" class="text-center text-muted py-4">Select a type and click Load</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center px-4 py-3">
                            <span class="text-muted font-size-sm" id="audit-info"></span>
                            <div id="audit-pagination"></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Add Pool Modal -->
    <div class="modal fade" id="modal_add_pool">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Cash Pool</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="form-add-pool">
                        <div class="form-group">
                            <label>Pool Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required />
                        </div>
                        <div class="form-group">
                            <label>Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-control kt-select2-general" required>
                                <option value="head_office_cash">Head Office Cash</option>
                                <option value="bank_account">Bank Account</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Opening Balance</label>
                            <input type="number" name="opening_balance" class="form-control" min="0" step="0.01" value="0" />
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btn-submit-pool" class="btn btn-primary">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Pool Modal -->
    <div class="modal fade" id="modal_edit_pool" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Cash Pool</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="form-edit-pool">
                        <input type="hidden" name="pool_id" />
                        <div class="form-group">
                            <label>Pool Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required />
                        </div>
                        <div class="form-group">
                            <label>Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-control" required>
                                <option value="branch_cash">Branch Cash</option>
                                <option value="head_office_cash">Head Office Cash</option>
                                <option value="bank_account">Bank Account</option>
                            </select>
                        </div>
                        <div class="form-group" id="edit-opening-balance-group">
                            <label>Opening Balance</label>
                            <input type="number" name="opening_balance" class="form-control" min="0" step="0.01" />
                            <span class="form-text text-muted">Cannot change after first period lock.</span>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btn-submit-edit-pool" class="btn btn-primary">Update</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="modal_add_category">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="category-modal-title">Add Category</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="form-category">
                        <input type="hidden" name="category_id" />
                        <div class="form-group">
                            <label>Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required />
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <div class="checkbox-inline">
                                <label class="checkbox checkbox-primary">
                                    <input type="checkbox" name="vendor_emphasis" value="1" />
                                    <span></span>Vendor Emphasis (highlight vendor field when this category is selected)
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btn-submit-category" class="btn btn-primary">Save</button>
                </div>
            </div>
        </div>
    </div>

    @push('js')
        <script src="{{ asset('assets/js/pages/cashflow/settings.js') }}"></script>
    @endpush
@endsection
