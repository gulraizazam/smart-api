@extends('admin.layouts.master')
@section('title', 'General Revenue Reports')
@section('content')

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

    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'General Revenue Reports'])

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
                            <h3 class="card-label">General Sales Reports</h3>
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
                                            <div class="form-group col-md-3 sn-select @if($errors->has('report_type')) has-error @endif">
                                                {!! Form::label('report_type', 'Report Type*', ['class' => 'control-label']) !!}
                                                <select name="report_type" id="report_type" style="width:100%" class="form-control select2">
                                                    @if(Gate::allows('finance_general_revenue_reports_center_performance_stats_by_revenue_finance'))
                                                        <option value="default">Select a Report Type</option>
                                                    @endif
                                                    {{--@if(Gate::allows('finance_general_revenue_reports_center_performance_stats_by_revenue_finance'))
                                                        <option value="center_performance_stats_by_revenue">Center performance stats by
                                                            Revenue
                                                        </option>
                                                    @endif
                                                    @if(Gate::allows('finance_general_revenue_reports_center_performance_stats_by_service_type_finance'))
                                                        <option value="center_performance_stats_by_service_type">Center performance stats by
                                                            Service Type
                                                        </option>
                                                    @endif
                                                    @if(Gate::allows('finance_general_revenue_reports_account_sales_report'))
                                                        <option value="account_sales_report">Account Sales Report</option>
                                                    @endif--}}

                                                    @if(Gate::allows('finance_general_revenue_reports_collection_by_service'))
                                                        <option value="collection_by_service">Sales by Service</option> {{--Ok--}}
                                                    @endif

                                                   {{-- @if(Gate::allows('finance_general_revenue_reports_daily_employee_stats_summary'))
                                                        <option --}}{{--value="daily_employee_stats_summary">Sale Summary Service Wise</option>
                                                    @endif--}}
                                                    @if(Gate::allows('finance_general_revenue_reports_daily_employee_stats'))
                                                        <option value="daily_employee_stats">Sale Summary Doctors Wise</option> {{--Ok--}}
                                                    @endif
                                                   {{-- @if(Gate::allows('finance_general_revenue_reports_sales_by_service_category'))
                                                        <option value="sales_by_service_category">Sale Summary Category Wise</option>
                                                    @endif--}}
                                                    {{--@if(Gate::allows('finance_general_revenue_reports_discount_report'))
                                                        <option value="discount_report">Discount Report</option>
                                                    @endif--}}
                                                    @if(Gate::allows('finance_general_revenue_reports_general_revenue__detail_report'))
                                                        <option value="general_revenue_report_detail">Sales Detail Report</option>
                                                    @endif
                                                    @if(Gate::allows('finance_general_revenue_reports_general_revenue__summary_report'))
                                                        <option value="general_revenue_report_summary">Sales Summary Report</option>
                                                    @endif
                                                   {{-- @if(Gate::allows('finance_general_revenue_reports_pabau_record_revenue_report'))
                                                        <option value="pabau_record_revenue_report">Pabau Record Revenue Report</option>
                                                    @endif
                                                    @if(Gate::allows('finance_general_revenue_reports_machine_wise_collection_report'))
                                                        <option value="machine_wise_collection_report">Machine wise Collection Report</option>
                                                    @endif
                                                    @if(Gate::allows('finance_general_revenue_reports_machine_wise_invoice_revenue_report'))
                                                        <option value="machine_wise_invoice_revenue_report">Machine wise Invoice Revenue
                                                            Report
                                                        </option>
                                                    @endif
                                                    @if(Gate::allows('finance_general_revenue_reports_partner_collection_report'))
                                                        <option value="partner_collection_report">Partner Collection Report
                                                        </option>
                                                    @endif
                                                    @if(Gate::allows('finance_general_revenue_reports_staff_wise_revenue'))
                                                        <option value="staff_wise_revenue">Staff Wise Revenue
                                                        </option>
                                                    @endif

                                                    @if(Gate::allows('finance_general_revenue_reports_consume_plan_revenue_report'))
                                                        <option value="consume_plan_revenue_report">Consume Plan Revenue Report
                                                        </option>
                                                    @endif--}}

                                                        @if(Gate::allows('finance_general_revenue_reports_conversion_report'))
                                                            <option value="conversion_report">Conversion Report
                                                            </option>
                                                        @endif
                                                </select>

                                                <span id="report_type_handler"></span>
                                            </div>
                                            {{--<div class="d-none form-group col-md-3 sn-select @if($errors->has('patient_id')) has-error @endif"
                                                 id="patient_id_E">
                                                {!! Form::label('patient_id', 'Patient', ['class' => 'control-label']) !!}
                                                <select name="patient_id" id="patient_id" class="form-control patient_id"></select>
                                                <span id="patient_id_handler"></span>
                                            </div>--}}
                                            <div class="form-group col-md-3 sn-select @if($errors->has('appointment_type_id')) has-error @endif"
                                                 id="appointment_type_id_E">
                                                {!! Form::label('appointment_type_id', 'Appointment Type', ['class' => 'control-label']) !!}
                                                {!! Form::select('appointment_type_id', $appointment_types, null, ['onchange' => 'getDiscounts()','id' => 'appointment_type_id', 'style' => 'width: 100%;', 'class' => 'form-control select2']) !!}
                                                <span id="appointment_type_id_handler"></span>
                                            </div>
                                            <div class="form-group col-md-3 @if($errors->has('discount_id')) has-error @endif" id="discount"
                                                 style="display: none;">
                                            </div>
                                            <div class="form-group col-md-3 sn-select @if($errors->has('city_id')) has-error @endif"
                                                 id="city_id_E">
                                                {!! Form::label('city_id', 'City', ['class' => 'control-label']) !!}
                                                {!! Form::select('city_id', $cities, null, ['onchange' => 'getCenters($(this));', 'id' => 'city_id', 'style' => 'width: 100%;', 'class' => 'form-control select2']) !!}
                                                <span id="city_id_handler"></span>
                                            </div>
                                            <div class="form-group col-md-3 sn-select @if($errors->has('location_id')) has-error @endif"
                                                 id="location_id_E">
                                                {!! Form::label('location_id', 'Centres', ['class' => 'control-label']) !!}
                                                {!! Form::select('location_id', $locations, (Auth::user()->hasRole('FDM')) ? array_keys($locations->toArray()) : null, [ 'id' => 'location_id', 'style' => 'width: 100%;', 'class' => 'form-control select2 sn-select', 'multiple']) !!}
                                                <span id="location_id_handler"></span>
                                            </div>
                                            <div class="form-group col-md-3 sn-select @if($errors->has('machine')) has-error @endif"
                                                 id="machine"
                                                 style="display: none;">
                                            </div>

                                            <div class="form-group col-md-3 sn-select @if($errors->has('location_id')) has-error @endif"
                                                 style="display: none;" id="location_id_D" onchange="SetLocation()">
                                                {!! Form::label('location_id_com', 'Centres', ['class' => 'control-label']) !!}

                                                {!! Form::select('location_id_com[]', $locations_com, (Auth::user()->hasRole('FDM')) ? array_keys($locations_com->toArray()) : null, ['id' => 'location_id_com','class' => 'form-control select2', 'multiple' => 'multiple']) !!}
                                                <span id="location_id_handler"></span>
                                            </div>

                                            {!! Form::hidden('medium_type', 'web', ['id' => 'medium_type']) !!}
                                            <div class="form-group col-md-3 sn-select @if($errors->has('service_id')) has-error @endif"
                                                 id="service_id_E">
                                                {!! Form::label('service_id', 'Services', ['class' => 'control-label']) !!}
                                                <select class="form-control select2" id="service_id" name="service_id">
                                                    <option value="">All</option>
                                                    @foreach($services as $id => $service)
                                                        @if ($id == 0) @continue; @endif
                                                        @if($id < 0)
                                                            @php($tmp_id = ($id * -1))
                                                        @else
                                                            @php($tmp_id = ($id * 1))
                                                        @endif
                                                        <option value="@if($id < 0){{ ($id * -1) }}@else{{ $id }}@endif">@if($id < 0)
                                                                <b>{!! $service['name'] !!}</b>@else{!! $service['name'] !!}@endif</option>
                                                    @endforeach
                                                </select>
                                                <span id="service_id_handler"></span>
                                            </div>

                                            <div class="form-group col-md-3 sn-select @if($errors->has('appointment_type_id')) has-error @endif"
                                                 id="user_id_E">
                                                {!! Form::label('user_id', 'Employee', ['class' => 'control-label']) !!}
                                                {!! Form::select('user_id', $users, null, ['id' => 'user_id', 'style' => 'width: 100%;', 'class' => 'form-control select2']) !!}
                                                <span id="user_id_handler"></span>
                                            </div>
                                            <div class="form-group col-md-3 sn-select @if($errors->has('doctor_id')) has-error @endif"
                                                 style="display: none" id="doctors_id">
                                                {!! Form::label('doctor_id', 'Doctor', ['class' => 'control-label']) !!}
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
                                        <div style="overflow: hidden; width: 100%;" id="content"></div>

                                            {!! Form::open(['method' => 'POST', 'target' => '_blank', 'route' => ['admin.reports.account_sales_report_load'], 'id' => 'report-form']) !!}
                                            {!! Form::hidden('date_range', null, ['id' => 'date_range-report']) !!}
                                            {!! Form::hidden('patient_id', null, ['id' => 'patient_id-report']) !!}
                                            {!! Form::hidden('appointment_type_id', null, ['id' => 'appointment_type_id-report']) !!}
                                            {!! Form::hidden('city_id', null, ['id' => 'city_id-report']) !!}
                                            {!! Form::hidden('location_id', null, ['id' => 'location_id-report']) !!}
                                            {!! Form::hidden('location_id_com', null, ['id' => 'location_id_com-report']) !!}
                                            {!! Form::hidden('region_id', null, ['id' => 'region_id-report']) !!}
                                            {!! Form::hidden('service_id', null, ['id' => 'service_id-report']) !!}
                                            {!! Form::hidden('user_id', null, ['id' => 'user_id-report']) !!}
                                            {!! Form::hidden('doctor_id', null, ['id' => 'doctor_id-report']) !!}
                                            {!! Form::hidden('machine_id', null, ['id' => 'machine_id-report']) !!}
                                            {!! Form::hidden('medium_type', null, ['id' => 'medium_type-report']) !!}
                                            {!! Form::hidden('report_type', null, ['id' => 'report_type-report']) !!}
                                            {!! Form::hidden('converted', null, ['id' => 'converted_type-report']) !!}
                                            {!! Form::hidden('discount_id', '', ['id' => 'discount_id-report']) !!}
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
                    'Last Year'  : [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
                },
                startDate: moment().startOf('month'),
                endDate  :  moment().endOf('month')
            });
            $('#date_range_fdm').daterangepicker({
                locale: {
                },
                ranges   : {
                    'Today' : [moment(), moment()],
                    
                },
                startDate: moment(),
                endDate  :  moment()
            });

            var loadReport = function (that) {

                if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
                    return false;
                }
                var date_ranges;
                if($("#date_range_fdm").val()!=undefined){
                  
                    date_ranges = $("#date_range_fdm").val();
                }else{
                   
                    date_ranges = $("#date_range").val();
                }
                
                showSpinner();
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: route('admin.reports.account_sales_report_load'),
                    type: "POST",
                    data: {
                        
                        date_range: date_ranges,
                        patient_id: $('#patient_id').val(),
                        appointment_type_id: $('#appointment_type_id').val(),
                        location_id: $('#location_id').val(),
                        location_id_com: $('#location_id_com').val(),
                        region_id: $('#region_id').val(),
                        service_id: $('#service_id').val(),
                        user_id: $('#user_id').val(),
                        doctor_id: $('#doctor_id').val(),
                        medium_type: $('#medium_type').val(),
                        report_type: $('#report_type').val(),
                        city_id: $('#city_id').val(),
                        machine_id: $('#machine_id').val(),
                        discount_id:$('#discount_id').val()
                    },
                    success: function(response){
                        $('#content').html('');
                        if($('#medium_type').val() == 'web') {
                            $('#content').html(response);
                        } else {
                            return false;
                            // loadChart(response.start_date, response.end_date, response.SaleData);
                        }

                        hideSpinner();
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        hideSpinner();
                        return false;
                    }
                });
            }

            var printReport = function (medium_type) {
               
                $('#date_range-report').val($('#date_range').val());
               
                $('#date_range_by-report').val($('#date_range_by').val());
                $('#date_range_by_first-report').val($('#date_range_by_first').val());
                $('#patient_id-report').val($('#patient_id').val());
                $('#scheduled_date-report').val($('#scheduled_date').val());
                $('#doctor_id-report').val($('#doctor_id').val());
                $('#city_id-report').val($('#city_id').val());
                $('#region_id-report').val($('#region_id').val());
                $('#location_id-report').val($('#location_id').val());
                $('#service_id-report').val($('#service_id').val());
                $('#appointment_status_id-report').val($('#appointment_status_id').val());
                $('#appointment_type_id-report').val($('#appointment_type_id').val());
                $('#consultancy_type-report').val($('#consultancy_type').val());
                $('#user_id-report').val($('#user_id').val());
                $('#re_user_id-report').val($('#re_user_id').val());
                $('#up_user_id-report').val($('#up_user_id').val());
                $('#referred_by-report').val($('#referred_by').val());
                $('#medium_type-report').val(medium_type);
                $('#report_type-report').val($('#report_type').val());
                $('#report-form').submit();
            }


            $(document).on('change', '#report_type', function () {
                var type_p = $("#report_type").val();
                $('#city_id_E').hide();
                if (type_p == 'general_revenue_report_detail') {
                    $("#patient_id_E").hide();
                    $("#appointment_type_id_E").hide();
                    $("#location_id_D").show();
                    $("#location_id_E").hide();
                    $("#user_id_E").hide();
                    $("#service_id_E").hide();
                    $("#region_id_E").hide();
                    $("#doctors_id").hide();
                    $("#machine").hide();
                    $('#discount').hide();
                } else if (type_p == 'daily_employee_stats_summary') {
                    $("#machine").hide();
                    $('#discount').hide();
                    $("#doctors_id").hide();
                    $("#user_id_E").hide();
                    $("#appointment_type_id_E").show();
                    $("#patient_id_E").show();
                    $("#location_id_E").show();
                    $("#location_id_D").hide();
                    $("#service_id_E").show();
                } else if (type_p == 'general_revenue_report_summary') {
                    $("#patient_id_E").hide();
                    $("#appointment_type_id_E").hide();
                    $("#location_id_D").hide();
                    $("#location_id_E").hide();
                    $("#user_id_E").hide();
                    $("#service_id_E").hide();
                    $("#region_id_E").show();
                    $("#doctors_id").hide();
                    $("#machine").hide();
                    $('#discount').hide();
                } else if (type_p == 'conversion_report') {
                    $("#location_id_E").show();
                    $("#location_id_D").hide();
                    $("#patient_id_E").show();
                    $("#user_id_E").hide();
                    $("#doctors_id").show();
                    $("#region_id_E").show();
                    $("#city_id_E").show();
                    $("#service_id_E").show();
                    $("#appointment_type_id_E").hide();
                    $("#machine").hide();
                    $('#discount').hide();
                } else if (type_p == "collection_by_service") {
                    $("#location_id_E").show();
                    $("#location_id_D").hide();
                    $("#patient_id_E").hide();
                    $("#user_id_E").hide();
                    $("#doctors_id").hide();
                    $("#region_id_E").show();
                    $("#city_id_E").hide();
                    $("#service_id_E").hide();
                    $("#appointment_type_id_E").hide();
                    $("#machine").hide();
                    $('#discount').hide();
                } else if (type_p == 'daily_employee_stats') {
                    $("#patient_id_E").show();
                    $("#appointment_type_id_E").show();
                    $("#location_id_D").hide();
                    $("#location_id_E").show();
                    $("#user_id_E").hide();
                    $("#service_id_E").show();
                    $("#region_id_E").hide();
                    $("#doctors_id").show();
                    $("#machine").hide();
                    $('#discount').hide();
                } else {
                    $("#location_id_E").show();
                    $("#location_id_D").hide();
                    $("#patient_id_E").hide();
                    $("#user_id_E").hide();
                    $("#doctors_id").hide();
                    $("#region_id_E").show();
                    $("#city_id_E").hide();
                    $("#service_id_E").hide();
                    $("#appointment_type_id_E").hide();
                    $("#machine").hide();
                    $('#discount').hide();
                }
            });
            $('#report_type').change();


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

        </script>

    @endpush

@endsection
