@extends('admin.layouts.master')
@section('title', 'Tax Calculation Report')
@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    @push('css')
        <style>
            .daterangepicker .drp-calendar th.month .monthselect,
            .daterangepicker .drp-calendar th.month .yearselect {
                background: #fff !important;
                border: 1px solid #ccc !important;
                border-radius: 4px;
                padding: 2px 4px;
                cursor: pointer;
                font-weight: 600;
                font-size: 13px;
                appearance: auto !important;
                -webkit-appearance: menulist !important;
                -moz-appearance: menulist !important;
            }
            .daterangepicker .drp-calendar th.month .yearselect:hover,
            .daterangepicker .drp-calendar th.month .monthselect:hover {
                border-color: #4e9fe5 !important;
                background: #f0f7ff !important;
            }
            .table-wrapper { overflow-x: scroll; }
            .sn-report-head { display: flex; flex-wrap: wrap; justify-content: space-between; padding: 8px 15px 10px; background-color: #02203d; color: #fff; }
            .shdoc-header { background: rgba(54, 65, 80, 1) !important; color: #fff !important; font-weight: bold !important; }
            .summary-card { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 15px; border: 1px solid #e2e8f0; }
            .summary-card h5 { color: #1a365d; border-bottom: 2px solid #1a365d; padding-bottom: 8px; margin-bottom: 12px; }
            .summary-item { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #e2e8f0; }
            .summary-item:last-child { border-bottom: none; }
            .summary-item.total { font-weight: bold; background: #e2e8f0; padding: 8px; margin: 5px -15px -15px; border-radius: 0 0 8px 8px; }
            .category-capped { background-color: #ffcccc !important; }
            .category-medium { background-color: #ffeeba !important; }
            .category-small { background-color: #c6f6d5 !important; }
            .status-success { color: #276749; font-weight: bold; }
            .status-warning { color: #c53030; font-weight: bold; }

            /* Loader Styles */
            .calculation-loader {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                z-index: 9999;
                justify-content: center;
                align-items: center;
            }
            .calculation-loader.active {
                display: flex;
            }
            .loader-content {
                background: white;
                padding: 30px 40px;
                border-radius: 10px;
                text-align: center;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            }
            .loader-spinner {
                border: 4px solid #f3f3f3;
                border-top: 4px solid #1a365d;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                animation: spin 1s linear infinite;
                margin: 0 auto 15px;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .loader-text {
                color: #1a365d;
                font-size: 16px;
                font-weight: 600;
            }
        </style>
    @endpush

    <!-- Loading Overlay -->
    <div class="calculation-loader" id="calculationLoader">
        <div class="loader-content">
            <div class="loader-spinner"></div>
            <div class="loader-text">Calculating tax report...</div>
        </div>
    </div>

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Tax Calculation Report'])
        <div class="d-flex flex-column-fluid">
            <div class="container">
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon">
                                <span class="svg-icon svg-icon-md svg-icon-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <rect x="0" y="0" width="24" height="24" />
                                            <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13" rx="1.5" />
                                            <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8" rx="1.5" />
                                            <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero" />
                                            <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5" />
                                        </g>
                                    </svg>
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
                                        <div class="form-group col-md-3 sn-select">
                                            {!! Form::label('date_range_fdm', 'Date Range*', ['class' => 'control-label']) !!}
                                            <div class="input-group">
                                                {!! Form::text('date_range', null, ['id' => 'date_range_fdm', 'class' => 'form-control','disabled']) !!}
                                            </div>
                                        </div>
                                        @else
                                        <div class="form-group col-md-3 sn-select">
                                            {!! Form::label('date_range', 'Date Range*', ['class' => 'control-label']) !!}
                                            <div class="input-group">
                                                {!! Form::text('date_range', null, ['id' => 'date_range', 'class' => 'form-control']) !!}
                                            </div>
                                        </div>
                                        @endif

                                        <div class="form-group col-md-3 sn-select">
                                            {!! Form::label('location_id', 'Centres*', ['class' => 'control-label']) !!}
                                            {!! Form::select('location_id', $locations, (Auth::user()->hasRole('FDM')) ? array_keys($locations->toArray()) : null, [ 'id' => 'location_id', 'style' => 'width: 100%;', 'class' => 'form-control select2 sn-select', 'multiple']) !!}
                                        </div>

                                        <div class="form-group col-md-2 sn-select">
                                            {!! Form::label('bank_taxable', 'Bank Taxable (%)*', ['class' => 'control-label']) !!}
                                            {!! Form::number('bank_taxable', 30, ['id' => 'bank_taxable', 'class' => 'form-control', 'step' => '0.01', 'min' => '0', 'max' => '100']) !!}
                                            <small class="text-muted">30% taxable = 70% exempt</small>
                                        </div>

                                        <div class="form-group col-md-2 sn-select">
                                            {!! Form::label('cash_percent', 'Cash % to Use*', ['class' => 'control-label']) !!}
                                            {!! Form::number('cash_percent', 5, ['id' => 'cash_percent', 'class' => 'form-control', 'step' => '0.01', 'min' => '0', 'max' => '100']) !!}
                                            <small class="text-muted">Only this % of cash included</small>
                                        </div>

                                        <div class="form-group col-md-2 sn-select">
                                            {!! Form::label('consultation_amount', 'Invoice Amount*', ['class' => 'control-label']) !!}
                                            {!! Form::select('consultation_amount', [1500 => '1,500', 2000 => '2,000 - 3,000'], 1500, ['id' => 'consultation_amount', 'class' => 'form-control']) !!}
                                            <small class="text-muted">Exempt invoice amount (range = mixed)</small>
                                        </div>
                                    </div>

                                    <div class="row mt-3">
                                        <div class="form-group col-md-12">
                                            <a href="javascript:void(0);" onclick="loadReport();" id="load_report" class="btn btn-success">
                                                <i class="fa fa-calculator"></i> Calculate
                                            </a>
                                            <a href="javascript:void(0);" onclick="exportExcel();" id="export_excel" class="btn btn-primary ml-2" style="display:none;">
                                                <i class="fa fa-file-excel"></i> Download Excel
                                            </a>
                                            <a href="javascript:void(0);" onclick="downloadInvoicesZip();" id="download_invoices" class="btn btn-info ml-2" style="display:none;">
                                                <i class="fa fa-file-archive"></i> Download Invoices
                                            </a>
                                            <a href="javascript:void(0);" onclick="resetPage();" id="reset_button" class="btn btn-secondary ml-2">
                                                <i class="fa fa-redo"></i> Reset
                                            </a>
                                        </div>
                                    </div>

                                    <hr>
                                    <div id="content"></div>

                                    <!-- Hidden form for Excel export -->
                                    <form method="POST" action="{{ route('admin.invoices.export-exempt') }}" id="export-form">
                                        @csrf
                                        <input type="hidden" name="date_range" id="export_date_range">
                                        <input type="hidden" name="bank_taxable" id="export_bank_taxable">
                                        <input type="hidden" name="cash_percent" id="export_cash_percent">
                                        <input type="hidden" name="consultation_amount" id="export_consultation_amount">
                                    </form>

                                    <!-- Hidden form for Invoices ZIP download -->
                                    <form method="POST" action="{{ route('admin.invoices.download-invoices-zip') }}" id="download-invoices-form">
                                        @csrf
                                        <input type="hidden" name="date_range" id="zip_date_range">
                                        <input type="hidden" name="bank_taxable" id="zip_bank_taxable">
                                        <input type="hidden" name="cash_percent" id="zip_cash_percent">
                                        <input type="hidden" name="consultation_amount" id="zip_consultation_amount">
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('js')
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>

    <script>
        var calculationData = null;

        // Loader functions
        function showSpinner() {
            $('#calculationLoader').addClass('active');
            $('#load_report').prop('disabled', true);
        }

        function hideSpinner() {
            $('#calculationLoader').removeClass('active');
            $('#load_report').prop('disabled', false);
        }

        $(document).ready(function() {
            if ($('#date_range').data('daterangepicker')) {
                $('#date_range').data('daterangepicker').remove();
            }
            var minYearVal = parseInt(moment().format('YYYY')) - 10;
            var maxYearVal = parseInt(moment().format('YYYY'));

            $('#date_range').daterangepicker({
                showDropdowns: true,
                linkedCalendars: false,
                minDate: moment().subtract(10, 'years').startOf('year'),
                minYear: minYearVal,
                maxYear: maxYearVal,
                ranges: {
                    'Today': [moment(), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                },
                startDate: moment().subtract(1, 'month').startOf('month'),
                endDate: moment().subtract(1, 'month').endOf('month')
            });

            function fixYearDropdowns() {
                var picker = $('#date_range').data('daterangepicker');
                if (!picker) return;
                picker.container.find('.yearselect').each(function() {
                    var $sel = $(this);
                    var currentVal = parseInt($sel.val());
                    var existingYears = [];
                    $sel.find('option').each(function() { existingYears.push(parseInt($(this).val())); });
                    if (existingYears.length >= (maxYearVal - minYearVal + 1)) return;
                    $sel.empty();
                    for (var y = minYearVal; y <= maxYearVal; y++) {
                        $sel.append('<option value="' + y + '"' + (y === currentVal ? ' selected' : '') + '>' + y + '</option>');
                    }
                });
            }

            var pickerContainer = $('#date_range').data('daterangepicker').container[0];
            var observer = new MutationObserver(function() { fixYearDropdowns(); });
            observer.observe(pickerContainer, { childList: true, subtree: true });

            $('#date_range').on('show.daterangepicker', function() { fixYearDropdowns(); });
        });

        function loadReport() {
            var dateRange = $('#date_range').val();
            var locationIds = $('#location_id').val();
            
            if (!locationIds || locationIds.length === 0) {
                alert('Please select at least one centre');
                return;
            }

            showSpinner();
            $.ajax({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                url: "{{ route('admin.invoices.calculate-amounts') }}",
                type: "POST",
                data: {
                    date_range: dateRange,
                    location_ids: locationIds,
                    bank_taxable: $('#bank_taxable').val() || 30,
                    cash_percent: $('#cash_percent').val() || 5,
                    consultation_amount: $('#consultation_amount').val() || 1500,
                },
                success: function(response) {
                    if (response.success) {
                        calculationData = response.data;
                        renderResults(response.data);
                        $('#export_excel').show();
                        $('#download_invoices').show();
                    } else {
                        $('#content').html('<div class="alert alert-danger">' + response.message + '</div>');
                        $('#export_excel').hide();
                        $('#download_invoices').hide();
                    }
                    hideSpinner();
                },
                error: function(xhr) {
                    hideSpinner();
                    $('#content').html('<div class="alert alert-danger">Error: ' + (xhr.responseJSON?.message || 'Unknown error') + '</div>');
                    $('#export_excel').hide();
                    $('#download_invoices').hide();
                }
            });
        }

        function downloadInvoicesZip() {
            // Clear previous location inputs
            $('#download-invoices-form input[name="location_ids[]"]').remove();
            
            // Set form values
            $('#zip_date_range').val($('#date_range').val());
            $('#zip_bank_taxable').val($('#bank_taxable').val());
            $('#zip_cash_percent').val($('#cash_percent').val());
            $('#zip_consultation_amount').val($('#consultation_amount').val());
            
            // Add location IDs
            var locationIds = $('#location_id').val();
            locationIds.forEach(function(id) {
                $('#download-invoices-form').append('<input type="hidden" name="location_ids[]" value="' + id + '">');
            });
            
            // Show loader with custom message
            $('.loader-text').text('Generating invoice PDFs... This may take a few minutes.');
            showSpinner();
            
            // Submit form (will download as file, then hide spinner)
            $('#download-invoices-form').submit();
            
            // Hide spinner after a delay (form submit won't trigger AJAX callbacks)
            setTimeout(function() {
                hideSpinner();
                $('.loader-text').text('Calculating tax report...');
            }, 5000);
        }

        function exportExcel() {
            // Clear previous location inputs
            $('#export-form input[name="location_ids[]"]').remove();
            
            // Set form values
            $('#export_date_range').val($('#date_range').val());
            $('#export_bank_taxable').val($('#bank_taxable').val());
            $('#export_cash_percent').val($('#cash_percent').val());
            $('#export_consultation_amount').val($('#consultation_amount').val());
            
            // Add location IDs
            var locationIds = $('#location_id').val();
            locationIds.forEach(function(id) {
                $('#export-form').append('<input type="hidden" name="location_ids[]" value="' + id + '">');
            });
            
            $('#export-form').submit();
        }

        function renderResults(data) {
            var html = '';

            // Row 1: Parameters, Capacity, Feasibility
            html += '<div class="row">';
            
            // Parameters
            html += '<div class="col-md-4"><div class="summary-card">';
            html += '<h5><i class="fa fa-cog"></i> Parameters</h5>';
            html += '<div class="summary-item"><span>Date Range:</span><span>' + data.parameters.date_from + ' to ' + data.parameters.date_to + '</span></div>';
            html += '<div class="summary-item"><span>Bank Taxable:</span><span>' + data.parameters.bank_taxable_percent + '% (Exempt: ' + (100 - data.parameters.bank_taxable_percent) + '%)</span></div>';
            html += '<div class="summary-item"><span>Cash %:</span><span>' + data.parameters.cash_percent + '%</span></div>';
            html += '<div class="summary-item"><span>Invoice Amount:</span><span>' + formatNumber(data.parameters.consultation_amount) + '</span></div>';
            html += '</div></div>';

            // Capacity
            html += '<div class="col-md-4"><div class="summary-card">';
            html += '<h5><i class="fa fa-calendar"></i> Capacity</h5>';
            html += '<div class="summary-item"><span>Working Days:</span><span>' + data.capacity.working_days + '</span></div>';
            html += '<div class="summary-item"><span>Invoice Days/Patient:</span><span>' + data.capacity.invoice_days_per_patient + '</span></div>';
            html += '<div class="summary-item"><span>Max Invoices/Patient:</span><span>' + data.capacity.max_invoices_per_patient + '</span></div>';
            html += '<div class="summary-item"><span>Max Exempt/Patient:</span><span>' + formatNumber(data.capacity.max_exempt_per_patient) + '</span></div>';
            html += '</div></div>';

            // Feasibility
            var statusClass = data.feasibility.is_achievable ? 'status-success' : 'status-warning';
            var statusText = data.feasibility.is_achievable ? '✓ ACHIEVABLE' : '✗ NOT ACHIEVABLE';
            html += '<div class="col-md-4"><div class="summary-card">';
            html += '<h5><i class="fa fa-check-circle"></i> Feasibility</h5>';
            // html += '<div class="summary-item"><span>Max Possible:</span><span>' + formatNumber(data.feasibility.max_possible_exempt) + ' (' + data.feasibility.max_possible_percent + '%)</span></div>';
            html += '<div class="summary-item"><span>Target:</span><span>' + data.feasibility.target_range + '</span></div>';
            html += '<div class="summary-item"><span>Status:</span><span class="' + statusClass + '">' + statusText + '</span></div>';
            html += '</div></div>';
            html += '</div>';

            // Row 2: Payment Totals & Pool
            html += '<div class="row">';
            
            // Totals
            html += '<div class="col-md-6"><div class="summary-card">';
            html += '<h5><i class="fa fa-money-bill"></i> Payment Totals</h5>';
            html += '<div class="summary-item"><span>Bank Total:</span><span>' + formatNumber(data.totals.bank.total) + ' (' + data.totals.bank.count + ' records)</span></div>';
            html += '<div class="summary-item"><span>Card Total:</span><span>' + formatNumber(data.totals.card.total) + ' (' + data.totals.card.count + ' records)</span></div>';
            html += '<div class="summary-item"><span>Cash Total:</span><span>' + formatNumber(data.totals.cash.total) + ' (' + data.totals.cash.count + ' records)</span></div>';
            html += '<div class="summary-item"><span>Cash Used (' + data.totals.cash.percent_used + '%):</span><span>' + formatNumber(data.totals.cash.amount_used) + '</span></div>';
            html += '<div class="summary-item" style="background:#fff3f3;"><span><strong>Refunds:</strong></span><span style="color:#c53030;">-' + formatNumber(data.totals.refunds.total) + ' (' + data.totals.refunds.count + ' records)</span></div>';
            html += '<div class="summary-item total"><span>Grand Total:</span><span>' + formatNumber(data.totals.grand_total) + '</span></div>';
            html += '</div></div>';

            // Pool
            html += '<div class="col-md-6"><div class="summary-card">';
            html += '<h5><i class="fa fa-calculator"></i> Pool & Targets</h5>';
            html += '<div class="summary-item"><span>Pool (Bank+Card+Cash%):</span><span>' + formatNumber(data.pool.total) + '</span></div>';
            html += '<div class="summary-item"><span>Target Exempt (' + data.pool.exempt_percent + '%):</span><span>' + formatNumber(data.pool.target_exempt) + '</span></div>';
            html += '<div class="summary-item"><span>Achieved Exempt (' + data.summary.exempt_percent + '%):</span><span>' + formatNumber(data.summary.total_exempt_invoiced) + '</span></div>';
            html += '<div class="summary-item"><span>Actual Taxable Invoiced (' + data.summary.taxable_percent + '%):</span><span>' + formatNumber(data.pool.actual_taxable_invoiced) + '</span></div>';
            html += '<div class="summary-item total"><span>Target Range (' + data.pool.target_range.min_percent + '-' + data.pool.target_range.max_percent + '%):</span><span>' + formatNumber(data.pool.target_range.min) + ' - ' + formatNumber(data.pool.target_range.max) + '</span></div>';
            html += '</div></div>';
            html += '</div>';

            // Final Summary
            html += '<div class="row"><div class="col-md-12"><div class="summary-card" style="background: #e6fffa;">';
            html += '<h5><i class="fa fa-chart-bar"></i> Final Summary</h5>';
            html += '<div class="row">';
            html += '<div class="col-md-2 text-center"><strong>Patients</strong><br><h4>' + data.summary.total_patients + '</h4></div>';
            html += '<div class="col-md-2 text-center"><strong>Pool</strong><br><h4>' + formatNumber(data.summary.total_pool) + '</h4></div>';
            html += '<div class="col-md-2 text-center"><strong>Exempt</strong><br><h4>' + formatNumber(data.summary.total_exempt_invoiced) + '</h4></div>';
            //html += '<div class="col-md-2 text-center"><strong>Taxable</strong><br><h4>' + formatNumber(data.summary.total_taxable) + '</h4></div>';
            html += '<div class="col-md-2 text-center"><strong>Exempt %</strong><br><h4 class="status-success">' + data.summary.exempt_percent + '%</h4></div>';
            html += '<div class="col-md-2 text-center"><strong>Invoices</strong><br><h4>' + data.summary.total_invoices + '</h4></div>';
            html += '</div></div></div></div>';

            // Patient Table
            html += '<div class="card mt-4"><div class="card-header shdoc-header"><h5 class="mb-0">Patient Distribution</h5></div>';
            html += '<div class="card-body"><table class="table table-bordered table-striped" id="patientTable">';
            html += '<thead><tr><th>Patient ID</th><th>Pool Share</th><th>Category</th><th>Exempt %</th><th>Exempt Amount</th><th>Taxable Amount</th></tr></thead><tbody>';
            
            data.patient_distribution.forEach(function(p) {
                html += '<tr><td>' + p.patient_id + '</td>';
                html += '<td class="text-right">' + formatNumber(p.pool_share) + '</td>';
                html += '<td class="category-' + p.category + ' text-center">' + p.category.toUpperCase() + '</td>';
                html += '<td class="text-center">' + p.exempt_percent + '%</td>';
                html += '<td class="text-right">' + formatNumber(p.exempt_amount) + '</td>';
                html += '<td class="text-right">' + formatNumber(p.taxable_amount) + '</td></tr>';
            });
            
            html += '</tbody></table></div></div>';

            $('#content').html(html);
            $('#patientTable').DataTable({ pageLength: 25, order: [[1, 'desc']] });
        }

        function formatNumber(num) {
            return parseFloat(num || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function resetPage() {
            location.reload();
        }
    </script>
    @endpush
@endsection