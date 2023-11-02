@extends('admin.layouts.master')
@section('title', 'Conversion')
@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    @push('css')
        <style>
            .table-wrapper {
                overflow-x: scroll;
            }
            .table thead th, .table thead td {
                padding-top: 0.3rem !important;
                padding-bottom: 0.3rem !important;
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
            .table thead th, .table thead td {
                padding-top: 0.3rem !important;
                padding-bottom: 0.3rem !important;
            }
        </style>
    @endpush
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Activity Logs Reports'])
        <div class="d-flex flex-column-fluid">
            <div class="container">
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon">
                                <span class="svg-icon svg-icon-md svg-icon-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
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
                            <h3 class="card-label">Activity Logs</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mt-2 mb-7">
                            <div class="row align-items-center">
                                <div class="col-lg-12 col-xl-12">
                                    <div class="row align-items-center">
                                            <div class="form-group col-md-3 sn-select @if($errors->has('date_range')) has-error @endif">
                                                {!! Form::label('date_range', 'Date Range*', ['class' => 'control-label']) !!}
                                                <div class="input-group">
                                                    {!! Form::text('date_range', null, ['id' => 'date_range', 'class' => 'form-control']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group col-md-2 sn-select @if($errors->has('activity_type')) has-error @endif">
                                                {!! Form::label('activity_type', 'Activity', ['class' => 'control-label']) !!}
                                                <select class="form-control select2" id="activity_type" name="activity_type">
                                                    <option value="all">All Logs</option>
                                                    <option value="Consultancy">Consultancy Logs</option>
                                                </select>
                                                <span id="service_id_handler"></span>
                                            </div>
                                            <div class="form-group col-md-2 sn-select @if($errors->has('service_id')) has-error @endif"
                                                    id="service_id_E">
                                                {!! Form::label('service_id', 'Service', ['class' => 'control-label']) !!}
                                                <select class="form-control select2" id="service_id" name="service_id">
                                                    <option value="all">All Services</option>
                                                    @foreach($services as $key=> $service)
                                                        <option value="{{$service}}">{{$key}}</option>
                                                    @endforeach
                                                </select>
                                                <span id="service_id_handler"></span>
                                            </div>
                                            <div class="form-group col-md-3 @if($errors->has('discount_id')) has-error @endif" id="discount"
                                                    style="display: none;">
                                            </div>

                                            <div class="form-group col-md-3 sn-select @if($errors->has('location_id')) has-error @endif"
                                                    id="location_id_E">
                                                   
                                                {!! Form::label('location_id', 'Centres', ['class' => 'control-label']) !!}
                                                {!! Form::select('location_id', $locations, (Auth::user()->hasRole('FDM')) ? array_keys($locations->toArray()) : null, [ 'id' => 'location_id', 'style' => 'width: 100%;', 'class' => 'form-control select2 sn-select']) !!} 
                                                <span id="location_id_handler"></span>
                                            </div>
                                           
                                           
                                            
                                            <div class="form-group col-md-2 sn-select @if($errors->has('doctor_id')) has-error @endif"
                                                    id="doctors_id">
                                                {!! Form::label('doctor_id', 'User', ['class' => 'control-label']) !!}
                                                {!! Form::select('doctor_id', $operators, null, ['id' => 'doctor_id', 'style' => 'width: 100%;', 'class' => 'form-control select2']) !!}
                                                <span id="doctor_id_handler"></span>
                                            </div>
                                            <div class="form-group col-md-2 sn-select @if($errors->has('group_id')) has-error @endif">
                                                {!! Form::label('load_report', '&nbsp;', ['class' => 'control-label']) !!}<br/>
                                                <a href="javascript:void(0);" onclick="loadReport($(this));" id="load_report"
                                                    class="btn btn-success spinner-button">Load Report</a>
                                            </div>
                                        <hr>
                                        <div class="clear clearfix" style="margin-bottom: 15px;"></div>
                                        <div style="overflow: hidden; width: 100%;" ></div>
                                            {!! Form::open(['method' => 'POST', 'target' => '_blank', 'route' => ['admin.reports.account_sales_report_load'], 'id' => 'report-form']) !!}
                                            {!! Form::hidden('date_range', null, ['id' => 'date_range-report']) !!}
                                            {!! Form::hidden('location_id', null, ['id' => 'location_id-report']) !!}
                                            {!! Form::hidden('location_id_com', null, ['id' => 'location_id_com-report']) !!}
                                            {!! Form::hidden('service_id', null, ['id' => 'service_id-report']) !!}
                                            {!! Form::hidden('doctor_id', null, ['id' => 'doctor_id-report']) !!}
                                            {!! Form::close() !!}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row" id="content">
                               
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @include('admin.settings.edit')
    @push('js')
        <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
        <script>
            $("#location_id_com").on('change',function(){
                $("#location_id_com-report").val($("#location_id_com").val());
            });
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
                    'Last Year'  : [moment().subtract(1, 'year').startOf('month'), moment().subtract(1, 'year').endOf('year')],
                },
                startDate: moment().subtract(29, 'days'),
                endDate  : moment()
            });
            var loadReport = function (that) {
               
                // if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
                //     return false;
                // }
               
                showSpinner();
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: route('admin.reports.load_activity_report'),
                    type: "POST",
                    data: {
                        startDate: $('#date_range').data('daterangepicker').startDate.format('YYYY-MM-DD'),
                        endDate: $('#date_range').data('daterangepicker').endDate.format('YYYY-MM-DD'),
                        location_id: $('#location_id').val(),
                        location_id_com: $('#location_id_com').val(),
                        service_id: $('#service_id').val(),
                        user_id: $('#doctor_id').val(),
                        activity_type: $('#activity_type').val(),
                    },
                    success: function(response){

                        $('#content').html(response);
                        
                        hideSpinner();
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        hideSpinner();
                        return false;
                    }
                });
            }
           
            function getCenters(that) {
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: route('admin.appointments.load_locations'),
                    type: "POST",
                    data: {
                        city_id: that.val(),
                    },
                    success: function(response){
                        if (response.status) {
                            let dropdown_options = '<option value="">All</option>';
                            let dropdowns = response.data.dropdown;
                            Object.entries(dropdowns).forEach(function (dropdown) {
                                dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
                            });
                            $("#location_id").html(dropdown_options);
                        }
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        return false;
                    }
                });
            }

            $(document).ready(function(){
               
                loadReport();
            });
        </script>
    @endpush
@endsection
