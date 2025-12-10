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

                                            <div class="form-group col-md-3 sn-select @if($errors->has('exempt_percentage')) has-error @endif">
                                                {!! Form::label('exempt_percentage', 'Exempt Percentage*', ['class' => 'control-label']) !!}
                                                <select name="exempt_percentage" id="exempt_percentage" style="width:100%" class="form-control select2">
                                                    <option value="">Select Exempt Percentage</option>
                                                    <option value="25">25%</option>
                                                    <option value="50">50%</option>
                                                    <option value="70">70%</option>
                                                </select>
                                                <span id="exempt_percentage_handler"></span>
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

                                            {!! Form::open(['method' => 'POST', 'target' => '_blank', 'route' => ['admin.reports.tax_calculation_report_load'], 'id' => 'report-form']) !!}
                                            {!! Form::hidden('date_range', null, ['id' => 'date_range-report']) !!}
                                            {!! Form::hidden('location_id', null, ['id' => 'location_id-report']) !!}
                                            {!! Form::hidden('exempt_percentage', null, ['id' => 'exempt_percentage-report']) !!}
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

                showSpinner();
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: route('admin.reports.tax_calculation_report_load'),
                    type: "POST",
                    data: {
                        date_range: date_ranges,
                        location_id: $('#location_id').val(),
                        exempt_percentage: $('#exempt_percentage').val(),
                        medium_type: $('#medium_type').val(),
                    },
                    success: function(response){
                        $('#content').html('');

                        if($('#medium_type').val() == 'web') {
                            $('#content').html(response);
                        } else {
                            return false;
                        }

                        hideSpinner();
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        hideSpinner();
                        return false;
                    }
                });
            }

            // Print report function
            var printReport = function (medium_type) {
                $('#date_range-report').val($('#date_range').val());
                $('#location_id-report').val($('#location_id').val());
                $('#exempt_percentage-report').val($('#exempt_percentage').val());
                $('#medium_type-report').val(medium_type);
                $('#report-form').submit();
            }

        </script>

    @endpush

@endsection
