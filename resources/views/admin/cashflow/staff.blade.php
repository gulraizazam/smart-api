@extends('admin.layouts.master')
@section('title', 'Cash Flow - Staff Advances')
@section('content')
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'Staff Advances', 'title' => 'Staff'])
        <div class="d-flex flex-column-fluid">
            <div class="container-fluid px-4">

                <div class="row">
                    {{-- ========== LEFT PANEL (35%) – Staff List ========== --}}
                    <div class="col-lg-5 col-xl-4" id="staff-left-panel">

                        <div class="card card-custom mb-4">
                            <div class="card-header py-3" style="min-height:auto;">
                                <div class="card-title mb-0">
                                    <h3 class="card-label font-size-h6 mb-0"><i class="la la-users mr-1"></i>Staff Members</h3>
                                </div>
                                <div class="card-toolbar" style="gap:6px;display:flex;">
                                    @if(Gate::allows('cashflow.staff_advance.create'))
                                        <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal_advance"><i class="la la-plus"></i> Advance</button>
                                    @endif
                                    @if(Gate::allows('cashflow.staff_return.create'))
                                        <button class="btn btn-sm btn-success" data-toggle="modal" data-target="#modal_return"><i class="la la-undo"></i> Return</button>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body py-3 px-4">
                                <div id="staff-list" style="max-height:calc(100vh - 260px);overflow-y:auto;">
                                    <div class="text-center text-muted py-5"><div class="spinner spinner-primary spinner-sm"></div></div>
                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- ========== RIGHT PANEL (65%) – Overview + Ledger Detail ========== --}}
                    <div class="col-lg-7 col-xl-8" id="staff-right-panel">

                        {{-- Overview (default) --}}
                        <div id="staff-overview">
                            <div class="row mb-4">
                                <div class="col-6 col-md-4">
                                    <div class="card card-custom" style="border-left:4px solid #F64E60;">
                                        <div class="card-body py-3 px-4">
                                            <div class="text-muted font-size-xs text-uppercase font-weight-bold">Total Outstanding</div>
                                            <div class="font-size-h5 font-weight-bolder mt-1 text-danger" id="ov-outstanding">—</div>
                                            <div class="text-muted font-size-xs" id="ov-staff-count">—</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="card card-custom" style="border-left:4px solid #FFA800;">
                                        <div class="card-body py-3 px-4">
                                            <div class="text-muted font-size-xs text-uppercase font-weight-bold">Total Advances</div>
                                            <div class="font-size-h5 font-weight-bolder mt-1" id="ov-advances">—</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="card card-custom" style="border-left:4px solid #1BC5BD;">
                                        <div class="card-body py-3 px-4">
                                            <div class="text-muted font-size-xs text-uppercase font-weight-bold">Total Returns</div>
                                            <div class="font-size-h5 font-weight-bolder mt-1" id="ov-returns">—</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-custom mb-4">
                                <div class="card-header py-3" style="min-height:auto;">
                                    <div class="card-title mb-0">
                                        <h3 class="card-label font-size-h6 mb-0"><i class="la la-sort-amount-desc mr-1 text-danger"></i>Top Outstanding</h3>
                                    </div>
                                </div>
                                <div class="card-body py-3 px-4">
                                    <div id="overview-top-outstanding">
                                        <div class="text-center text-muted py-4">
                                            <div class="spinner spinner-primary spinner-sm"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card card-custom">
                                        <div class="card-header py-3" style="min-height:auto;">
                                            <div class="card-title mb-0">
                                                <h3 class="card-label font-size-h6 mb-0">
                                                    <i class="la la-arrow-up mr-1 text-danger"></i>Recent Advances
                                                </h3>
                                            </div>
                                        </div>
                                        <div class="card-body py-3 px-4" id="overview-recent-advances">
                                            <div class="text-center text-muted py-3"><div class="spinner spinner-primary spinner-sm"></div></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card card-custom">
                                        <div class="card-header py-3" style="min-height:auto;">
                                            <div class="card-title mb-0">
                                                <h3 class="card-label font-size-h6 mb-0">
                                                    <i class="la la-arrow-down mr-1 text-success"></i>Recent Returns
                                                </h3>
                                            </div>
                                        </div>
                                        <div class="card-body py-3 px-4" id="overview-recent-returns">
                                            <div class="text-center text-muted py-3"><div class="spinner spinner-primary spinner-sm"></div></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Ledger Detail (hidden initially) --}}
                        <div id="staff-ledger-panel" class="d-none">

                            {{-- Staff header --}}
                            <div class="card card-custom mb-4">
                                <div class="card-body py-4 px-5">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h4 class="mb-1" id="ledger-staff-name"></h4>
                                            <span class="text-muted font-size-sm" id="ledger-staff-eligible"></span>
                                        </div>
                                        <button class="btn btn-sm btn-light" id="btn-close-ledger"><i class="la la-arrow-left mr-1"></i>Back</button>
                                    </div>
                                </div>
                            </div>

                            {{-- Summary cards --}}
                            <div class="row mb-3">
                                <div class="col-3">
                                    <div class="card card-custom" style="border-left:4px solid #F64E60;">
                                        <div class="card-body py-3 px-4">
                                            <div class="text-muted font-size-xs text-uppercase font-weight-bold">Total Advances</div>
                                            <div class="font-size-h5 font-weight-bolder mt-1 text-danger" id="ledger-advances">PKR 0</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="card card-custom" style="border-left:4px solid #1BC5BD;">
                                        <div class="card-body py-3 px-4">
                                            <div class="text-muted font-size-xs text-uppercase font-weight-bold">Total Returns</div>
                                            <div class="font-size-h5 font-weight-bolder mt-1 text-success" id="ledger-returns">PKR 0</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="card card-custom" style="border-left:4px solid #3699FF;">
                                        <div class="card-body py-3 px-4">
                                            <div class="text-muted font-size-xs text-uppercase font-weight-bold">Expenses</div>
                                            <div class="font-size-h5 font-weight-bolder mt-1 text-primary" id="ledger-expenses">PKR 0</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="card card-custom" style="border-left:4px solid #FFA800;">
                                        <div class="card-body py-3 px-4">
                                            <div class="text-muted font-size-xs text-uppercase font-weight-bold">Outstanding</div>
                                            <div class="font-size-h5 font-weight-bolder mt-1" id="ledger-outstanding">PKR 0</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Advances table --}}
                            <div class="card card-custom mb-4">
                                <div class="card-header py-3" style="min-height:auto;">
                                    <div class="card-title mb-0"><h3 class="card-label font-size-h6 mb-0"><i class="la la-arrow-down mr-1 text-danger"></i>Advances</h3></div>
                                </div>
                                <div class="card-body py-3 px-4">
                                    <div class="table-responsive" style="max-height:280px;overflow-y:auto;">
                                        <table class="table table-sm table-head-custom mb-0">
                                            <thead><tr><th>Date</th><th>Pool</th><th class="text-right">Amount</th><th>Description</th><th>By</th><th class="text-right">Actions</th></tr></thead>
                                            <tbody id="ledger-advances-tbody">
                                                <tr><td colspan="6" class="text-center text-muted py-3">—</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            {{-- Returns table --}}
                            <div class="card card-custom mb-4">
                                <div class="card-header py-3" style="min-height:auto;">
                                    <div class="card-title mb-0"><h3 class="card-label font-size-h6 mb-0"><i class="la la-arrow-up mr-1 text-success"></i>Returns</h3></div>
                                </div>
                                <div class="card-body py-3 px-4">
                                    <div class="table-responsive" style="max-height:280px;overflow-y:auto;">
                                        <table class="table table-sm table-head-custom mb-0">
                                            <thead><tr><th>Date</th><th>Pool</th><th class="text-right">Amount</th><th>Description</th><th>By</th><th class="text-right">Actions</th></tr></thead>
                                            <tbody id="ledger-returns-tbody">
                                                <tr><td colspan="6" class="text-center text-muted py-3">—</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            {{-- Expenses table --}}
                            <div class="card card-custom">
                                <div class="card-header py-3" style="min-height:auto;">
                                    <div class="card-title mb-0"><h3 class="card-label font-size-h6 mb-0"><i class="la la-receipt mr-1 text-primary"></i>Expenses (Paid from Advance)</h3></div>
                                </div>
                                <div class="card-body py-3 px-4">
                                    <div class="table-responsive" style="max-height:280px;overflow-y:auto;">
                                        <table class="table table-sm table-head-custom mb-0">
                                            <thead><tr><th>Date</th><th>Category</th><th class="text-right">Amount</th><th>Description</th><th>Status</th><th>By</th></tr></thead>
                                            <tbody id="ledger-expenses-tbody">
                                                <tr><td colspan="6" class="text-center text-muted py-3">—</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Give Advance Modal -->
    <div class="modal fade" id="modal_advance">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-3"><h5 class="modal-title font-size-h6"><i class="la la-money-bill mr-1"></i>Give Staff Advance</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <div class="modal-body">
                    <form id="form-advance">
                        <div class="form-group">
                            <label class="font-size-sm font-weight-bold mb-1">Staff Member <span class="text-danger">*</span></label>
                            <select name="user_id" class="form-control form-control-sm kt-select2-general" id="advance-staff-select"><option value="">Select staff</option></select>
                        </div>
                        <div class="form-group">
                            <label class="font-size-sm font-weight-bold mb-1">From Pool <span class="text-danger">*</span></label>
                            <select name="pool_id" class="form-control form-control-sm kt-select2-general" id="advance-pool-select"><option value="">Select pool</option></select>
                        </div>
                        <div class="form-group">
                            <label class="font-size-sm font-weight-bold mb-1">Amount (PKR) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control form-control-sm" min="1" step="1" />
                        </div>
                        <div class="form-group mb-0">
                            <label class="font-size-sm font-weight-bold mb-1">Description</label>
                            <input type="text" name="description" class="form-control form-control-sm" maxlength="50" placeholder="Brief description" />
                        </div>
                    </form>
                </div>
                <div class="modal-footer py-2"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button><button type="button" id="btn-submit-advance" class="btn btn-primary btn-sm"><i class="la la-check mr-1"></i>Give Advance</button></div>
            </div>
        </div>
    </div>

    <!-- Record Return Modal -->
    <div class="modal fade" id="modal_return">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-3"><h5 class="modal-title font-size-h6"><i class="la la-undo mr-1"></i>Record Staff Return</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <div class="modal-body">
                    <form id="form-return">
                        <div class="form-group">
                            <label class="font-size-sm font-weight-bold mb-1">Staff Member <span class="text-danger">*</span></label>
                            <select name="user_id" class="form-control form-control-sm kt-select2-general" id="return-staff-select"><option value="">Select staff</option></select>
                        </div>
                        <div class="form-group">
                            <label class="font-size-sm font-weight-bold mb-1">To Pool <span class="text-danger">*</span></label>
                            <select name="pool_id" class="form-control form-control-sm kt-select2-general" id="return-pool-select"><option value="">Select pool</option></select>
                        </div>
                        <div class="form-group">
                            <label class="font-size-sm font-weight-bold mb-1">Amount (PKR) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control form-control-sm" min="1" step="1" />
                        </div>
                        <div class="form-group mb-0">
                            <label class="font-size-sm font-weight-bold mb-1">Description</label>
                            <input type="text" name="description" class="form-control form-control-sm" maxlength="50" placeholder="Brief description" />
                        </div>
                    </form>
                </div>
                <div class="modal-footer py-2"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button><button type="button" id="btn-submit-return" class="btn btn-success btn-sm"><i class="la la-check mr-1"></i>Record Return</button></div>
            </div>
        </div>
    </div>

    <!-- Edit Advance Modal -->
    <div class="modal fade" id="modal_edit_advance">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-3"><h5 class="modal-title font-size-h6"><i class="la la-edit mr-1"></i>Edit Staff Advance</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <div class="modal-body">
                    <form id="form-edit-advance">
                        <input type="hidden" name="advance_id" />
                        <div class="form-group">
                            <label class="font-size-sm font-weight-bold mb-1">Amount (PKR) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control form-control-sm" min="1" step="1" />
                        </div>
                        <div class="form-group">
                            <label class="font-size-sm font-weight-bold mb-1">From Pool <span class="text-danger">*</span></label>
                            <select name="pool_id" class="form-control form-control-sm kt-select2-general" id="edit-advance-pool-select"><option value="">Select pool</option></select>
                        </div>
                        <div class="form-group">
                            <label class="font-size-sm font-weight-bold mb-1">Description</label>
                            <input type="text" name="description" class="form-control form-control-sm" maxlength="50" />
                        </div>
                        <div class="form-group mb-0">
                            <label class="font-size-sm font-weight-bold mb-1">Edit Reason <span class="text-danger">*</span></label>
                            <input type="text" name="edit_reason" class="form-control form-control-sm" maxlength="50" minlength="5" placeholder="Minimum 5 characters" />
                        </div>
                    </form>
                </div>
                <div class="modal-footer py-2"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button><button type="button" id="btn-submit-edit-advance" class="btn btn-primary btn-sm"><i class="la la-check mr-1"></i>Save Changes</button></div>
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
                <div class="modal-body" style="max-height:500px;overflow-y:auto;">
                    <div id="audit-loading" class="text-center py-5"><div class="spinner spinner-primary spinner-lg"></div></div>
                    <div id="audit-timeline" class="d-none"></div>
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
                canAdvanceCreate: {{ Gate::allows('cashflow.staff_advance.create') ? 'true' : 'false' }},
                canEdit: {{ Gate::allows('cashflow.staff_advance.edit') ? 'true' : 'false' }},
                canVoid: {{ Gate::allows('cashflow.staff_advance.void') ? 'true' : 'false' }},
                canReturnCreate: {{ Gate::allows('cashflow.staff_return.create') ? 'true' : 'false' }},
                canReturnVoid: {{ Gate::allows('cashflow.staff_return.void') ? 'true' : 'false' }},
                canAudit: {{ Gate::allows('cashflow.audit.view') ? 'true' : 'false' }}
            };
        </script>
        <script src="{{ asset('assets/js/pages/cashflow/staff.js') }}"></script>
    @endpush
@endsection
