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
                                <button id="btn-add-transfer" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modal_transfer">
                                    <i class="la la-plus"></i> New Transfer
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-2">
                                <select id="filter-pool" class="form-control form-control-sm">
                                    <option value="">All Pools</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select id="filter-method" class="form-control form-control-sm">
                                    <option value="">All Methods</option>
                                    <option value="physical_cash">Physical Cash</option>
                                    <option value="bank_deposit">Bank Deposit</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" id="filter-date-range" class="form-control form-control-sm" placeholder="Date Range" readonly />
                            </div>
                            <div class="col-md-2">
                                <input type="text" id="filter-search" class="form-control form-control-sm" placeholder="Search ref..." />
                            </div>
                            <div class="col-md-2">
                                <button id="btn-filter" class="btn btn-primary btn-sm btn-block"><i class="la la-search"></i> Filter</button>
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
                                        <th>Reference</th>
                                        <th>By</th>
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
    <div class="modal fade" id="modal_transfer" tabindex="-1">
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
                                    <input type="date" name="transfer_date" class="form-control" required />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Amount (PKR) <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Method <span class="text-danger">*</span></label>
                                    <select name="method" class="form-control" required>
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
                                    <select name="from_pool_id" class="form-control searchable" required>
                                        <option value="">Select source pool</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>To Pool <span class="text-danger">*</span></label>
                                    <select name="to_pool_id" class="form-control searchable" required>
                                        <option value="">Select destination pool</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Reference No. <span class="text-danger">*</span></label>
                                    <input type="text" name="reference_no" class="form-control" required maxlength="100" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Attachment (Google Drive URL) <span class="text-danger">*</span></label>
                                    <input type="url" name="attachment_url" class="form-control" required placeholder="https://drive.google.com/..." />
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="2" maxlength="500"></textarea>
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

    @push('js')
        <script src="{{ asset('assets/js/pages/cashflow/transfers.js') }}"></script>
    @endpush
@endsection
