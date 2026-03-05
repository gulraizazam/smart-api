@extends('admin.layouts.master')
@section('title', 'Cash Flow - Staff Advances')
@section('content')
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'Staff Advances', 'title' => 'Staff'])
        <div class="d-flex flex-column-fluid">
            <div class="container">

                <!-- Staff Summary -->
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title"><h3 class="card-label"><i class="la la-users mr-2"></i>Staff Advance Summary</h3></div>
                        <div class="card-toolbar">
                            <button class="btn btn-primary btn-sm mr-2" data-toggle="modal" data-target="#modal_advance"><i class="la la-plus"></i> Give Advance</button>
                            <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modal_return"><i class="la la-undo"></i> Record Return</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-head-custom table-vertical-center">
                                <thead>
                                    <tr>
                                        <th>Staff Member</th>
                                        <th>Eligible</th>
                                        <th class="text-right">Total Advances</th>
                                        <th class="text-right">Total Returns</th>
                                        <th class="text-right">Outstanding</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="summary-tbody">
                                    <tr><td colspan="6" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Staff Ledger (shown when clicking a staff member) -->
                <div class="card card-custom d-none" id="staff-ledger-card">
                    <div class="card-header py-3">
                        <div class="card-title"><h3 class="card-label"><i class="la la-list-alt mr-2"></i>Ledger: <span id="ledger-staff-name"></span></h3></div>
                        <div class="card-toolbar">
                            <button class="btn btn-secondary btn-sm" id="btn-close-ledger"><i class="la la-times"></i> Close</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4"><div class="bg-light-danger p-3 rounded text-center"><strong>Total Advances</strong><br/><span id="ledger-advances">0</span></div></div>
                            <div class="col-md-4"><div class="bg-light-success p-3 rounded text-center"><strong>Total Returns</strong><br/><span id="ledger-returns">0</span></div></div>
                            <div class="col-md-4"><div class="bg-light-warning p-3 rounded text-center"><strong>Outstanding</strong><br/><span id="ledger-outstanding">0</span></div></div>
                        </div>

                        <h6 class="mt-4 mb-2">Advances</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-head-custom">
                                <thead><tr><th>Date</th><th>Pool</th><th class="text-right">Amount</th><th>Description</th><th>By</th></tr></thead>
                                <tbody id="ledger-advances-tbody"></tbody>
                            </table>
                        </div>

                        <h6 class="mt-4 mb-2">Returns</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-head-custom">
                                <thead><tr><th>Date</th><th>Pool</th><th class="text-right">Amount</th><th>Description</th><th>By</th></tr></thead>
                                <tbody id="ledger-returns-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Give Advance Modal -->
    <div class="modal fade" id="modal_advance" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Give Staff Advance</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <div class="modal-body">
                    <form id="form-advance">
                        <div class="form-group">
                            <label>Staff Member <span class="text-danger">*</span></label>
                            <select name="user_id" class="form-control searchable" required id="advance-staff-select"><option value="">Select staff</option></select>
                        </div>
                        <div class="form-group">
                            <label>From Pool <span class="text-danger">*</span></label>
                            <select name="pool_id" class="form-control searchable" required id="advance-pool-select"><option value="">Select pool</option></select>
                        </div>
                        <div class="form-group">
                            <label>Amount (PKR) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required />
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="button" id="btn-submit-advance" class="btn btn-primary">Give Advance</button></div>
            </div>
        </div>
    </div>

    <!-- Record Return Modal -->
    <div class="modal fade" id="modal_return" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Record Staff Return</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <div class="modal-body">
                    <form id="form-return">
                        <div class="form-group">
                            <label>Staff Member <span class="text-danger">*</span></label>
                            <select name="user_id" class="form-control searchable" required id="return-staff-select"><option value="">Select staff</option></select>
                        </div>
                        <div class="form-group">
                            <label>To Pool <span class="text-danger">*</span></label>
                            <select name="pool_id" class="form-control searchable" required id="return-pool-select"><option value="">Select pool</option></select>
                        </div>
                        <div class="form-group">
                            <label>Amount (PKR) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required />
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="button" id="btn-submit-return" class="btn btn-success">Record Return</button></div>
            </div>
        </div>
    </div>

    @push('js')
        <script src="{{ asset('assets/js/pages/cashflow/staff.js') }}"></script>
    @endpush
@endsection
