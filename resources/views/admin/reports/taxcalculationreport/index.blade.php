@extends('admin.layouts.master')
@section('title', 'Tax Calculation Report')
@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    @push('css')
        <style>
            .table-wrapper {
                overflow-x: scroll;
            }
            .sn-report-head{
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                padding: 8px 15px 10px;
            }
            .sn-report-head {
                background-color: #02203d;
                color: #fff;
            }
            .sn-white-btn {
                background-color: #35a1d4 !important;
                border: #35a1d4 !important;
                color: #fff !important;
            }
            .sn-white-btn > i {
                color: #fff !important;;
            }
            .shdoc-header {
                background: rgba(54, 65, 80, 1) !important;
                color: #fff !important;
                font-weight: bold !important;
            }
        </style>
    @endpush

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Tax Calculation Report'])

    <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">

                <!--begin::Card-->
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon">
                                <span class="svg-icon svg-icon-md svg-icon-primary">
                                    <!--begin::Svg Icon | path:assets/media/svg/icons/Shopping/Chart-bar1.svg-->
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <rect x="0" y="0" width="24" height="24" />
                                            <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13" rx="1.5" />
                                            <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8" rx="1.5" />
                                            <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero" />
                                            <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5" />
                                        </g>
                                    </svg>
                                    <!--end::Svg Icon-->
                                </span>
                            </span>
                            <h3 class="card-label">Tax Calculation Report</h3>
                        </div>

                    </div>

                    <div class="card-body">

                        <div class="mt-2 mb-7">

                            <div class="row align-items-center">

                                <div class="col-lg-12 col-xl-12">
                                    <div class="row align-items-center">
                                            @if(Auth::user()->hasRole('FDM'))
                                            <div class="form-group col-md-3 sn-select @if($errors->has('date_range')) has-error @endif">
                                                {!! Form::label('date_range_fdm', 'Date Range*', ['class' => 'control-label']) !!}
                                                <div class="input-group">
                                                    {!! Form::text('date_range', null, ['id' => 'date_range_fdm', 'class' => 'form-control','disabled']) !!}
                                                </div>
                                            </div>
                                            @else
                                            <div class="form-group col-md-3 sn-select @if($errors->has('date_range')) has-error @endif">
                                                {!! Form::label('date_range', 'Date Range*', ['class' => 'control-label']) !!}
                                                <div class="input-group">
                                                    {!! Form::text('date_range', null, ['id' => 'date_range', 'class' => 'form-control']) !!}
                                                </div>
                                            </div>
                                            @endif

                                            <div class="form-group col-md-3 sn-select @if($errors->has('location_id')) has-error @endif">
                                                {!! Form::label('location_id', 'Centres*', ['class' => 'control-label']) !!}
                                                {!! Form::select('location_id', $locations, (Auth::user()->hasRole('FDM')) ? array_keys($locations->toArray()) : null, [ 'id' => 'location_id', 'style' => 'width: 100%;', 'class' => 'form-control select2 sn-select', 'multiple']) !!}
                                                <span id="location_id_handler"></span>
                                            </div>

                                            <div class="form-group col-md-3 sn-select @if($errors->has('bank_taxable')) has-error @endif">
                                                {!! Form::label('bank_taxable', 'Bank Taxable (%)', ['class' => 'control-label']) !!}
                                                {!! Form::number('bank_taxable', null, ['id' => 'bank_taxable', 'class' => 'form-control', 'placeholder' => 'Enter Bank Taxable %', 'step' => '0.01', 'min' => '0', 'max' => '100']) !!}
                                                <span id="bank_taxable_handler"></span>
                                            </div>

                                            <div class="form-group col-md-3 sn-select @if($errors->has('cash_taxable')) has-error @endif">
                                                {!! Form::label('cash_taxable', 'Cash Taxable (%)', ['class' => 'control-label']) !!}
                                                {!! Form::number('cash_taxable', null, ['id' => 'cash_taxable', 'class' => 'form-control', 'placeholder' => 'Enter Cash Taxable %', 'step' => '0.01', 'min' => '0', 'max' => '100']) !!}
                                                <span id="cash_taxable_handler"></span>
                                            </div>

                                            <div class="form-group col-md-3 sn-select @if($errors->has('consultation_amount')) has-error @endif">
                                                {!! Form::label('consultation_amount', 'Consultation Amount', ['class' => 'control-label']) !!}
                                                {!! Form::number('consultation_amount', null, ['id' => 'consultation_amount', 'class' => 'form-control', 'placeholder' => 'Enter Consultation Amount', 'step' => '0.01']) !!}
                                                <span id="consultation_amount_handler"></span>
                                            </div>

                                            {!! Form::hidden('medium_type', 'web', ['id' => 'medium_type']) !!}

                                            <div class="form-group col-md-2 sn-select">
                                                {!! Form::label('load_report', '&nbsp;', ['class' => 'control-label']) !!}<br/>
                                                <a href="javascript:void(0);" onclick="loadReport($(this));" id="load_report"
                                                   class="btn btn-success spinner-button">Load Report</a>
                                            </div>

                                        <hr>
                                        <div class="clear clearfix" style="margin-bottom: 15px;"></div>
                                        <div style="overflow: hidden; width: 100%;" id="content"></div>

                                            {!! Form::open(['method' => 'POST', 'target' => '_blank', 'route' => ['admin.invoices.calculate-amounts'], 'id' => 'report-form']) !!}
                                            {!! Form::hidden('date_range', null, ['id' => 'date_range-report']) !!}
                                            {!! Form::hidden('location_id', null, ['id' => 'location_id-report']) !!}
                                            {!! Form::hidden('bank_taxable', null, ['id' => 'bank_taxable-report']) !!}
                                            {!! Form::hidden('cash_taxable', null, ['id' => 'cash_taxable-report']) !!}
                                            {!! Form::hidden('consultation_amount', null, ['id' => 'consultation_amount-report']) !!}
                                            {!! Form::hidden('medium_type', null, ['id' => 'medium_type-report']) !!}
                                            {!! Form::close() !!}

                                    </div>
                                </div>

                            </div>

                        </div>

                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->
    @include('admin.settings.edit')

    @push('js')
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>

        <script>
            // Date range picker configuration
            $('#date_range').daterangepicker({
                locale: {
                },
                ranges   : {
                    'Today'       : [moment(), moment()],
                    'Yesterday'   : [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days' : [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month'  : [moment().startOf('month'), moment().endOf('month')],
                    'Last Month'  : [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                    'This Year'  : [moment().startOf('year'), moment().endOf('year')],
                    'Last Year'  : [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
                },
                startDate: moment().startOf('month'),
                endDate  :  moment().endOf('month')
            });

            // Date range picker for FDM users
            $('#date_range_fdm').daterangepicker({
                locale: {
                },
                ranges   : {
                    'Today' : [moment(), moment()],
                },
                startDate: moment(),
                endDate  :  moment()
            });

            // Load report function
            var loadReport = function (that) {
                if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
                    return false;
                }

                var date_ranges;
                if($("#date_range_fdm").val() != undefined){
                    date_ranges = $("#date_range_fdm").val();
                } else {
                    date_ranges = $("#date_range").val();
                }

                // Validate required fields
                var locationIds = $('#location_id').val();
                if (!locationIds || locationIds.length === 0) {
                    alert('Please select at least one centre');
                    return false;
                }

                showSpinner();
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: "{{ route('admin.invoices.calculate-amounts') }}",
                    type: "POST",
                    data: {
                        date_range: date_ranges,
                        location_ids: $('#location_id').val(),
                        bank_taxable: $('#bank_taxable').val() || 0,
                        cash_taxable: $('#cash_taxable').val() || 0,
                        consultation_amount: $('#consultation_amount').val() || 1500,
                        medium_type: $('#medium_type').val(),
                    },
                    success: function(response){
                        $('#content').html('');

                        if(response.success) {
                            // Render the results
                            renderResults(response.data);
                        } else {
                            $('#content').html('<div class="alert alert-danger">' + response.message + '</div>');
                        }

                        hideSpinner();
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        hideSpinner();
                        var errorMessage = 'An error occurred';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        $('#content').html('<div class="alert alert-danger">' + errorMessage + '</div>');
                        return false;
                    }
                });
            }

            // Render results function
            var renderResults = function(data) {
                var html = '';

                // Summary Section
                html += '<div class="card mb-4">';
                html += '<div class="card-header shdoc-header"><h5 class="mb-0">Summary</h5></div>';
                html += '<div class="card-body">';
                html += '<div class="row">';
                html += '<div class="col-md-3"><strong>Total Patients:</strong> ' + data.summary.total_patients + '</div>';
                html += '<div class="col-md-3"><strong>Total Payments:</strong> ' + data.summary.total_payments + '</div>';
                html += '<div class="col-md-3"><strong>Grand Total:</strong> ' + formatNumber(data.summary.grand_total) + '</div>';
                html += '<div class="col-md-3"><strong>Verification:</strong> ' + (data.summary.verification_match ? '<span class="text-success">✓ Match</span>' : '<span class="text-danger">✗ Mismatch</span>') + '</div>';
                html += '</div>';
                html += '</div>';
                html += '</div>';

                // Totals by Payment Method
                html += '<div class="card mb-4">';
                html += '<div class="card-header shdoc-header"><h5 class="mb-0">Totals by Payment Method</h5></div>';
                html += '<div class="card-body">';
                html += '<table class="table table-bordered">';
                html += '<thead><tr><th>Payment Method</th><th>Total Amount</th><th>Record Count</th><th>Breakdown</th></tr></thead>';
                html += '<tbody>';
                html += '<tr><td><strong>Bank (Bank Transfer + Card)</strong></td><td>' + formatNumber(data.totals.bank.total) + '</td><td>' + data.totals.bank.count + '</td>';
                html += '<td>Bank Transfer: ' + formatNumber(data.totals.bank.breakdown.bank_transfer.total) + ' (' + data.totals.bank.breakdown.bank_transfer.count + ')<br>Card: ' + formatNumber(data.totals.bank.breakdown.card.total) + ' (' + data.totals.bank.breakdown.card.count + ')</td></tr>';
                html += '<tr><td><strong>Cash</strong></td><td>' + formatNumber(data.totals.cash.total) + '</td><td>' + data.totals.cash.count + '</td><td>-</td></tr>';
                html += '<tr class="table-info"><td><strong>Grand Total</strong></td><td><strong>' + formatNumber(data.totals.grand_total) + '</strong></td><td><strong>' + data.totals.total_records + '</strong></td><td>-</td></tr>';
                html += '</tbody>';
                html += '</table>';
                html += '</div>';
                html += '</div>';

                // Taxable/Non-Taxable Splits
                html += '<div class="card mb-4">';
                html += '<div class="card-header shdoc-header"><h5 class="mb-0">Taxable / Non-Taxable Splits</h5></div>';
                html += '<div class="card-body">';
                html += '<table class="table table-bordered">';
                html += '<thead><tr><th>Category</th><th>Bank</th><th>Cash</th><th>Combined</th></tr></thead>';
                html += '<tbody>';
                html += '<tr><td><strong>Taxable (' + data.splits.bank.taxable_percent + '% Bank / ' + data.splits.cash.taxable_percent + '% Cash)</strong></td>';
                html += '<td>' + formatNumber(data.splits.bank.taxable_amount) + '</td>';
                html += '<td>' + formatNumber(data.splits.cash.taxable_amount) + '</td>';
                html += '<td><strong>' + formatNumber(data.splits.combined.taxable_amount) + '</strong></td></tr>';
                html += '<tr><td><strong>Non-Taxable (' + data.splits.bank.non_taxable_percent + '% Bank / ' + data.splits.cash.non_taxable_percent + '% Cash)</strong></td>';
                html += '<td>' + formatNumber(data.splits.bank.non_taxable_amount) + '</td>';
                html += '<td>' + formatNumber(data.splits.cash.non_taxable_amount) + '</td>';
                html += '<td><strong>' + formatNumber(data.splits.combined.non_taxable_amount) + '</strong></td></tr>';
                html += '</tbody>';
                html += '</table>';
                html += '</div>';
                html += '</div>';

                // Patient Shares Table
                html += '<div class="card mb-4">';
                html += '<div class="card-header shdoc-header"><h5 class="mb-0">Patient Shares (' + data.patient_shares.length + ' patients)</h5></div>';
                html += '<div class="card-body">';
                html += '<div class="table-wrapper">';
                html += '<table class="table table-bordered table-striped" id="patientSharesTable">';
                html += '<thead><tr>';
                html += '<th>Patient ID</th>';
                html += '<th>Bank Paid</th>';
                html += '<th>Cash Paid</th>';
                html += '<th>Total Paid</th>';
                html += '<th>Taxable Share</th>';
                html += '<th>Non-Taxable Share</th>';
                html += '<th>Verification</th>';
                html += '</tr></thead>';
                html += '<tbody>';

                data.patient_shares.forEach(function(patient) {
                    var isMatch = Math.abs(patient.total_paid - patient.verification) < 0.01;
                    html += '<tr>';
                    html += '<td>' + patient.patient_id + '</td>';
                    html += '<td>' + formatNumber(patient.bank_paid) + '<br><small class="text-muted">(Bank: ' + formatNumber(patient.bank_paid_breakdown.bank_transfer) + ', Card: ' + formatNumber(patient.bank_paid_breakdown.card) + ')</small></td>';
                    html += '<td>' + formatNumber(patient.cash_paid) + '</td>';
                    html += '<td><strong>' + formatNumber(patient.total_paid) + '</strong></td>';
                    html += '<td>' + formatNumber(patient.taxable_share.total) + '<br><small class="text-muted">(Bank: ' + formatNumber(patient.taxable_share.bank) + ', Cash: ' + formatNumber(patient.taxable_share.cash) + ')</small></td>';
                    html += '<td>' + formatNumber(patient.non_taxable_share.total) + '<br><small class="text-muted">(Bank: ' + formatNumber(patient.non_taxable_share.bank) + ', Cash: ' + formatNumber(patient.non_taxable_share.cash) + ')</small></td>';
                    html += '<td>' + (isMatch ? '<span class="text-success">✓ ' + formatNumber(patient.verification) + '</span>' : '<span class="text-danger">✗ ' + formatNumber(patient.verification) + '</span>') + '</td>';
                    html += '</tr>';
                });

                html += '</tbody>';
                html += '</table>';
                html += '</div>';
                html += '</div>';
                html += '</div>';

                $('#content').html(html);

                // Initialize DataTable
                $('#patientSharesTable').DataTable({
                    pageLength: 25,
                    order: [[3, 'desc']],
                    dom: 'Bfrtip',
                    buttons: [
                        'copy', 'csv', 'excel'
                    ]
                });
            }

            // Format number with commas
            var formatNumber = function(num) {
                if (num === null || num === undefined) return '0';
                return parseFloat(num).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }

            // Print report function
            var printReport = function (medium_type) {
                $('#date_range-report').val($('#date_range').val());
                $('#location_id-report').val($('#location_id').val());
                $('#bank_taxable-report').val($('#bank_taxable').val());
                $('#cash_taxable-report').val($('#cash_taxable').val());
                $('#consultation_amount-report').val($('#consultation_amount').val());
                $('#medium_type-report').val(medium_type);
                $('#report-form').submit();
            }

        </script>

    @endpush

@endsection