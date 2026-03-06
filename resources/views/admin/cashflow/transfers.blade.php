@extends('admin.layouts.master')
@section('title', 'Cash Flow - Transfers')
@section('content')
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'Cash Transfers', 'title' => 'Transfers'])
        <div class="d-flex flex-column-fluid">
            <div class="container">
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-exchange-alt mr-2"></i>Cash Transfers</h3>
                        </div>
                        <div class="card-toolbar">
                            @if(Gate::allows('cashflow_transfer_create'))
                                <button id="btn-add-transfer" class="btn btn-primary" data-toggle="modal" data-target="#modal_transfer">
                                    <i class="la la-plus"></i> New Transfer
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-2">
                                <select id="filter-pool" class="form-control form-control-sm kt-select2-general">
                                    <option value="">All Pools</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select id="filter-method" class="form-control form-control-sm kt-select2-general">
                                    <option value="">All Methods</option>
                                    <option value="physical_cash">Physical Cash</option>
                                    <option value="bank_deposit">Bank Deposit</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" id="filter-date-range" class="form-control form-control-sm" placeholder="Date Range" readonly />
                            </div>
                            <div class="col-md-4">
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
                            <table class="table table-head-custom table-vertical-center" id="transfers-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>From Pool</th>
                                        <th>To Pool</th>
                                        <th class="text-right">Amount</th>
                                        <th>Method</th>
                                        <th>By</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="transfers-tbody">
                                    <tr><td colspan="7" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div class="text-muted" id="pagination-info"></div>
                            <div id="pagination-links"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transfer Modal -->
    <div class="modal fade" id="modal_transfer">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Cash Transfer</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="form-transfer">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Date <span class="text-danger">*</span></label>
                                    <input type="text" name="transfer_date" class="form-control" required readonly placeholder="Select date" style="cursor:pointer;background:#fff;" />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Amount (PKR) <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" class="form-control" min="1" step="1" required />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Method <span class="text-danger">*</span></label>
                                    <select name="method" class="form-control kt-select2-general" required>
                                        <option value="physical_cash">Physical Cash</option>
                                        <option value="bank_deposit">Bank Deposit</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>From Pool <span class="text-danger">*</span></label>
                                    <select name="from_pool_id" class="form-control kt-select2-general" required>
                                        <option value="">Select source pool</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>To Pool <span class="text-danger">*</span></label>
                                    <select name="to_pool_id" class="form-control kt-select2-general" required>
                                        <option value="">Select destination pool</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Attachment (Google Drive URL) <span class="text-danger">*</span></label>
                                    <input type="url" name="attachment_url" class="form-control" required placeholder="https://drive.google.com/..." />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Description</label>
                                    <input type="text" name="description" class="form-control" maxlength="50" placeholder="Brief description" />
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btn-submit-transfer" class="btn btn-primary">Submit Transfer</button>
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

    <!-- Edit Transfer Modal -->
    <div class="modal fade" id="modal_edit_transfer">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Cash Transfer</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="form-edit-transfer">
                        <input type="hidden" name="transfer_id" />
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Amount (PKR) <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" class="form-control" min="1" step="1" required />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Method <span class="text-danger">*</span></label>
                                    <select name="method" class="form-control kt-select2-general" required>
                                        <option value="physical_cash">Physical Cash</option>
                                        <option value="bank_deposit">Bank Deposit</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">&nbsp;</div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>From Pool <span class="text-danger">*</span></label>
                                    <select name="from_pool_id" class="form-control kt-select2-general" required id="edit-from-pool-select">
                                        <option value="">Select source pool</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>To Pool <span class="text-danger">*</span></label>
                                    <select name="to_pool_id" class="form-control kt-select2-general" required id="edit-to-pool-select">
                                        <option value="">Select destination pool</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Attachment (Google Drive URL) <span class="text-danger">*</span></label>
                                    <input type="url" name="attachment_url" class="form-control" required placeholder="https://drive.google.com/..." />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Description</label>
                                    <input type="text" name="description" class="form-control" maxlength="50" />
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Edit Reason <span class="text-danger">*</span> (min 5 chars)</label>
                                    <input type="text" name="edit_reason" class="form-control" maxlength="50" required minlength="5" />
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btn-submit-edit-transfer" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Trail Modal -->
    <div class="modal fade" id="modal_audit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-3" style="background:#F3F6F9;border-bottom:2px solid #E4E6EF;">
                    <h5 class="modal-title font-weight-bolder"><i class="la la-history text-primary mr-2"></i>Audit Trail</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><i class="la la-times"></i></button>
                </div>
                <div class="modal-body">
                    <div id="audit-loading" class="text-center py-5"><div class="spinner spinner-primary"></div></div>
                    <div id="audit-timeline" class="d-none"></div>
                </div>
            </div>
        </div>
    </div>

    @push('js')
        <script>
            var cfPerms = {
                canCreate: {{ Gate::allows('cashflow_transfer_create') ? 'true' : 'false' }},
                canEdit: {{ Gate::allows('cashflow_transfer_edit') ? 'true' : 'false' }},
                canVoid: {{ Gate::allows('cashflow_transfer_void') ? 'true' : 'false' }},
                canAudit: {{ Gate::allows('cashflow_audit_view') ? 'true' : 'false' }}
            };
        </script>
        <script src="{{ asset('assets/js/pages/cashflow/transfers.js') }}"></script>
    @endpush
@endsection
