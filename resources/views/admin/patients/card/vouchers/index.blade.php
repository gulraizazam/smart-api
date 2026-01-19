<div class="card-body page-vouchers-form">
    <!--begin::Search Form-->
<!-- @include('admin.patients.card.vouchers.filters') -->
<!--end::Search Form-->

    <!--begin: Datatable-->
    <div class="datatable datatable-bordered datatable-head-custom voucher-form"></div>
    <!--end: Datatable-->

</div>

{{-- Voucher History Modal --}}
<div class="modal fade" id="voucher_history_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="la la-history text-primary mr-2"></i>
                    Voucher Usage History: <span id="voucher_history_name" class="text-primary"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                {{-- Voucher Summary Card --}}
                <div class="card card-custom bg-light-primary mb-5">
                    <div class="card-body py-4">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="font-size-sm text-muted">Total Amount</div>
                                <div class="font-size-h4 font-weight-bold text-primary" id="voucher_history_total">0.00</div>
                            </div>
                            <div class="col-md-4">
                                <div class="font-size-sm text-muted">Consumed</div>
                                <div class="font-size-h4 font-weight-bold text-danger" id="voucher_history_consumed">0.00</div>
                            </div>
                            <div class="col-md-4">
                                <div class="font-size-sm text-muted">Balance</div>
                                <div class="font-size-h4 font-weight-bold text-success" id="voucher_history_balance">0.00</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Usage History Table --}}
                <h6 class="mb-3"><i class="la la-list-alt mr-2"></i>Usage Details</h6>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th width="50">#</th>
                                <th>Plan ID</th>
                                <th>Service</th>
                                <th>Amount Deducted</th>
                                <th>Applied Date</th>
                            </tr>
                        </thead>
                        <tbody id="voucher_history_body">
                            <tr>
                                <td colspan="5" class="text-center text-muted">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
