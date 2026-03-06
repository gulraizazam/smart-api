@extends('admin.layouts.master')
@section('title', 'Cash Flow - Expenses')
@section('content')
    @push('css')
        <style>
            .status-badge { font-size: 0.85rem; }
            .expense-flagged { border-left: 3px solid #FFA800 !important; }
            .expense-rejected { border-left: 3px solid #F64E60 !important; }
            .expense-voided { border-left: 3px solid #F64E60 !important; opacity: 0.6; }
            .amount-cell { font-weight: 600; white-space: nowrap; }
        </style>
    @endpush

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

        @include('admin.partials.breadcrumb', ['module' => 'Cash Flow Expenses', 'title' => 'Expenses'])

        <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <div class="container">

                <!-- Status Count Cards -->
                <div class="row mb-5" id="status-cards">
                    <div class="col-md-3">
                        <div class="card card-custom bg-light-warning card-stretch">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-40 symbol-light-warning mr-3">
                                        <span class="symbol-label"><i class="la la-clock text-warning icon-lg"></i></span>
                                    </div>
                                    <div>
                                        <div class="font-size-h4 font-weight-bold" id="count-pending">0</div>
                                        <div class="text-muted font-size-sm">Pending</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-custom bg-light-success card-stretch">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-40 symbol-light-success mr-3">
                                        <span class="symbol-label"><i class="la la-check-circle text-success icon-lg"></i></span>
                                    </div>
                                    <div>
                                        <div class="font-size-h4 font-weight-bold" id="count-approved">0</div>
                                        <div class="text-muted font-size-sm">Approved</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-custom bg-light-danger card-stretch">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-40 symbol-light-danger mr-3">
                                        <span class="symbol-label"><i class="la la-times-circle text-danger icon-lg"></i></span>
                                    </div>
                                    <div>
                                        <div class="font-size-h4 font-weight-bold" id="count-rejected">0</div>
                                        <div class="text-muted font-size-sm">Rejected</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-custom bg-light-info card-stretch">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-40 symbol-light-info mr-3">
                                        <span class="symbol-label"><i class="la la-flag text-info icon-lg"></i></span>
                                    </div>
                                    <div>
                                        <div class="font-size-h4 font-weight-bold" id="count-flagged">0</div>
                                        <div class="text-muted font-size-sm">Flagged</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!--begin::Card-->
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-receipt mr-2"></i>Expenses</h3>
                        </div>
                        <div class="card-toolbar">
                            <button id="btn-export-expenses" class="btn btn-light-success mr-2">
                                <i class="la la-file-excel"></i> Export
                            </button>
                            @if(Gate::allows('cashflow_expense_create'))
                                <button id="btn-add-expense" class="btn btn-primary" data-toggle="modal" data-target="#modal_expense">
                                    <i class="la la-plus"></i> New Expense
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-4">
                            <div class="col-md-2">
                                <select id="filter-status" class="form-control form-control-sm kt-select2-general">
                                    <option value="">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                    <option value="flagged">Flagged</option>
                                    <option value="voided">Voided</option>
                                    <option value="edited">Edited</option>
                                    <option value="my_pending">My Pending</option>
                                    <option value="my_rejected">My Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select id="filter-branch" class="form-control form-control-sm kt-select2-general">
                                    <option value="">All Branches</option>
                                    <option value="general">General</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select id="filter-category" class="form-control form-control-sm kt-select2-general">
                                    <option value="">All Categories</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" id="filter-date-range" class="form-control form-control-sm" placeholder="Date Range" readonly />
                            </div>
                            <div class="col-md-3">
                                <div class="input-group input-group-sm">
                                    <input type="text" id="filter-search" class="form-control" placeholder="Search..." />
                                    <div class="input-group-append">
                                        <button id="btn-filter" class="btn btn-primary"><i class="la la-search"></i></button>
                                        <button id="btn-reset-filters" class="btn btn-secondary" title="Reset Filters"><i class="la la-undo"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-head-custom table-vertical-center" id="expenses-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Category</th>
                                        <th>Pool</th>
                                        <th class="text-right">Amount</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="expenses-tbody">
                                    <tr><td colspan="8" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div class="text-muted" id="pagination-info"></div>
                            <div id="pagination-links"></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Create/View Expense Modal -->
    <div class="modal fade" id="modal_expense" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="expense-modal-title">New Expense</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="form-expense">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Date <span class="text-danger">*</span></label>
                                    <input type="text" name="expense_date" class="form-control" required readonly placeholder="Select date" style="cursor:pointer;background:#fff;" />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Amount (PKR) <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" class="form-control" min="1" step="1" required />
                                    <span class="form-text text-muted" id="threshold-hint"></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Category <span class="text-danger">*</span></label>
                                    <select name="category_id" class="form-control kt-select2-general" required>
                                        <option value="">Select category</option>
                                    </select>
                                    <a href="javascript:;" id="btn-category-not-listed" class="form-text text-primary font-size-xs"><i class="la la-plus-circle"></i> Category not listed? Suggest new</a>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Paid From Pool <span class="text-danger">*</span></label>
                                    <select name="paid_from_pool_id" class="form-control kt-select2-general" required>
                                        <option value="">Select pool</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Payment Method <span class="text-danger">*</span></label>
                                    <select name="payment_method_id" class="form-control kt-select2-general" required>
                                        <option value="">Select method</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>For Branch</label>
                                    <select name="for_branch_id" class="form-control kt-select2-general" id="expense-branch-select">
                                        <option value="">Select branch</option>
                                    </select>
                                    <div class="checkbox-inline mt-2">
                                        <label class="checkbox checkbox-primary">
                                            <input type="checkbox" name="is_for_general" value="1" id="chk-general" />
                                            <span></span>General / Company-wide
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group" id="vendor-group">
                                    <label>Vendor</label>
                                    <select name="vendor_id" class="form-control kt-select2-general">
                                        <option value="">Select vendor (optional)</option>
                                    </select>
                                    <a href="javascript:;" id="btn-vendor-not-listed" class="form-text text-primary font-size-xs"><i class="la la-plus-circle"></i> Vendor not listed? Request new</a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Expense By (Staff)</label>
                                    <select name="staff_id" class="form-control kt-select2-general">
                                        <option value="">Select staff (optional)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Attachment (Google Drive URL)</label>
                            <input type="url" name="attachment_url" class="form-control" placeholder="https://drive.google.com/..." maxlength="500" />
                        </div>
                        <div class="form-group">
                            <label>Description <span class="text-danger">*</span></label>
                            <input type="text" name="description" class="form-control" required minlength="3" maxlength="50" placeholder="Brief expense note (max 50 chars)" />
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btn-submit-expense" class="btn btn-primary">Submit Expense</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Vendor Request Modal (from expense form) -->
    <div class="modal fade" id="modal_vendor_request" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request New Vendor</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="form-vendor-request">
                        <div class="form-group">
                            <label>Vendor Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="200" />
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" maxlength="50" />
                        </div>
                        <div class="form-group">
                            <label>Why is this vendor needed?</label>
                            <textarea name="note" class="form-control" rows="2" maxlength="500"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btn-submit-vendor-request" class="btn btn-primary btn-sm">Submit Request</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Suggestion Modal (from expense form) -->
    <div class="modal fade" id="modal_category_request" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Suggest New Category</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="form-category-request">
                        <div class="form-group">
                            <label>Category Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="200" />
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="2" maxlength="500"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btn-submit-category-request" class="btn btn-primary btn-sm">Submit Suggestion</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="modal_reject" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Expense</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="reject-expense-id" />
                    <div class="form-group">
                        <label>Rejection Reason <span class="text-danger">*</span></label>
                        <textarea id="rejection-reason" class="form-control" rows="3" required minlength="5"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btn-confirm-reject" class="btn btn-danger">Reject</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Void Modal -->
    <div class="modal fade" id="modal_void" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Void Expense</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="void-expense-id" />
                    <div class="alert alert-warning">This action will reverse the pool balance deduction.</div>
                    <div class="form-group">
                        <label>Void Reason <span class="text-danger">*</span> (min 10 chars)</label>
                        <textarea id="void-reason" class="form-control" rows="3" required minlength="10"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btn-confirm-void" class="btn btn-danger">Void Expense</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Edit Modal -->
    <div class="modal fade" id="modal_admin_edit" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Expense (Admin)</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="form-admin-edit">
                        <input type="hidden" name="expense_id" />
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Amount (PKR)</label>
                                    <input type="number" name="amount" class="form-control" min="1" step="1" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Category</label>
                                    <select name="category_id" class="form-control kt-select2-general" id="edit-category-select">
                                        <option value="">Keep current</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Paid From Pool</label>
                                    <select name="paid_from_pool_id" class="form-control kt-select2-general" id="edit-pool-select">
                                        <option value="">Keep current</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Payment Method</label>
                                    <select name="payment_method_id" class="form-control kt-select2-general" id="edit-payment-method-select">
                                        <option value="">Keep current</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Attachment (Google Drive URL)</label>
                            <input type="url" name="attachment_url" class="form-control" placeholder="https://drive.google.com/..." />
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <input type="text" name="description" class="form-control" maxlength="50" />
                        </div>
                        <div class="form-group">
                            <label>Edit Reason <span class="text-danger">*</span> (min 5 chars)</label>
                            <input type="text" name="edit_reason" class="form-control" maxlength="50" required minlength="5" />
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btn-submit-admin-edit" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Trail Modal -->
    <div class="modal fade" id="modal_audit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background:#F3F6F9;border-bottom:2px solid #E4E6EF;">
                    <h5 class="modal-title font-weight-bolder"><i class="la la-history text-primary mr-2"></i>Audit Trail</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <i class="la la-times"></i>
                    </button>
                </div>
                <div class="modal-body" style="max-height:500px;overflow-y:auto;">
                    <div id="audit-loading" class="text-center py-5">
                        <div class="spinner spinner-primary spinner-lg"></div>
                    </div>
                    <div id="audit-timeline" class="d-none"></div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #E4E6EF;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Attachment Preview Modal -->
    <div class="modal fade" id="modal_preview" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-3" style="background:#F3F6F9;border-bottom:2px solid #E4E6EF;">
                    <h5 class="modal-title font-weight-bolder"><i class="la la-paperclip text-primary mr-2"></i>Attachment Preview</h5>
                    <div>
                        <a id="preview-open-new" href="#" target="_blank" class="btn btn-sm btn-light-primary mr-2"><i class="la la-external-link-alt"></i> Open in Drive</a>
                        <button type="button" class="close ml-2" data-dismiss="modal" aria-label="Close"><i class="la la-times"></i></button>
                    </div>
                </div>
                <div class="modal-body p-0">
                    <iframe id="preview-iframe" src="" style="width:100%;height:75vh;border:none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    @push('js')
        <script>
            var cfPerms = {
                canApprove: {{ Gate::allows('cashflow_expense_approve') ? 'true' : 'false' }},
                canEdit: {{ Gate::allows('cashflow_expense_edit') ? 'true' : 'false' }},
                canVoid: {{ Gate::allows('cashflow_expense_void') ? 'true' : 'false' }},
                canCreate: {{ Gate::allows('cashflow_expense_create') ? 'true' : 'false' }},
                canAudit: {{ Gate::allows('cashflow_audit_view') ? 'true' : 'false' }},
                userId: {{ auth()->id() }}
            };
        </script>
        <script src="{{ asset('assets/js/pages/cashflow/expenses.js') }}"></script>
    @endpush
@endsection
