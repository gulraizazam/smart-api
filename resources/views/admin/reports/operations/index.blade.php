@extends('admin.layouts.master')
@section('title', 'Operation Reports')
@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
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

    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Operation Reports'])

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
                            <h3 class="card-label">Operation Reports</h3>
                        </div>

                    </div>

                    <div class="card-body">

                        <div class="mt-2 mb-7">

                            <div class="row align-items-center">

                                <div class="col-lg-12 col-xl-12">
                                    <div class="row align-items-center">

                                        <div class="form-group col-md-2 sn-select @if($errors->has('report_type')) has-error @endif">
                                            {!! Form::label('report_type', 'Report Type*', ['class' => 'control-label']) !!}
                                            <select name="report_type" id="report_type" style="width: 100%;"
                                                    class="form-control select2">
                                                @if(Gate::allows('operations_reports_dar_report'))
                                                    <option value="dar_report">DAR Report</option>
                                                @endif

                                                @if(Gate::allows('operations_reports_dar_report'))
                                                    <option value="agent_report">Agent Report</option>
                                                @endif

                                                @if(Gate::allows('operations_reports_dar_report'))
                                                    <option value="walking_report">Walkin Report</option>
                                                @endif

                                            </select>
                                            <span id="report_type_handler"></span>
                                        </div>
                                        {!! Form::hidden('medium_type', 'web', ['id' => 'medium_type']) !!}
                                        <div class="form-group col-md-3 sn-select @if($errors->has('date_range')) has-error @endif"
                                             id="date_range_e">
                                            {!! Form::label('date_range', 'Date Range*', ['class' => 'control-label']) !!}
                                            <div class="input-group">
                                                {!! Form::text('date_range', null, ['id' => 'date_range', 'class' => 'form-control']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group col-md-2 sn-select @if($errors->has('location_id')) has-error @endif"
                                             id="locations">
                                            {!! Form::label('location_id', 'Centre', ['class' => 'control-label']) !!}
                                            {!! Form::select('location_id', $locations, null, ['multiple', 'id' => 'location_id', 'style' => 'width: 100%;', 'class' => 'form-control select2']) !!}
                                            <span id="location_id_handler"></span>
                                        </div>

                                        {{--<div class="form-group col-md-2 sn-select @if($errors->has('location_id')) has-error @endif"
                                             id="locations">
                                            {!! Form::label('status_id', 'Status', ['class' => 'control-label']) !!}
                                            {!! Form::select('status_id', ['1' => 'Arrived', '0' => 'Not Arrived'], null, ['id' => 'status_id', 'style' => 'width: 100%;', 'class' => 'form-control']) !!}
                                            <span id="location_id_handler"></span>
                                        </div>--}}

                                        <div class="form-group col-md-3 sn-select @if($errors->has('service_id')) has-error @endif"
                                             id="services" style="display: none;">
                                            {!! Form::label('service_id', 'Services', ['class' => 'control-label']) !!}
                                            <select class="form-control select2" id="service_id" name="service_id">
                                                <option value="">Select Service</option>
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

                                        <div class="form-group col-md-3 sn-select @if($errors->has('service_id')) has-error @endif"
                                             id="agent" style="display: none;">
                                            {!! Form::label('agent_id', 'agent', ['class' => 'control-label']) !!}
                                            <select class="form-control select2" id="agent_id" name="agent_id">
                                                <option value="">All</option>
                                                @foreach($agents as $agent)

                                                    <option value="{{$agent->id ?? ''}}">{{$agent->name ?? ''}}</option>
                                                @endforeach
                                            </select>

                                        </div>

                                        <div style="display: none;" id="appointment_type_C"
                                             class="form-group col-md-3 sn-select @if($errors->has('appointment_type')) has-error @endif">
                                            {!! Form::label('appointment_type_id', 'Appointment Type', ['class' => 'control-label']) !!}
                                            {!! Form::select('appointment_type_id', $appointment_types, null, ['id' => 'appointment_type_id', 'style' => 'width: 100%;', 'class' => 'form-control select2']) !!}
                                            <span id="appointment_type_id_handler"></span>
                                        </div>

                                        <div class="form-group col-md-2 sn-select @if($errors->has('group_id')) has-error @endif">
                                            {!! Form::label('load_report', '&nbsp;', ['class' => 'control-label']) !!}<br/>
                                            <a href="javascript:void(0);" onclick="loadReport($(this));" id="load_report"
                                               class="btn btn-success spinner-button">Load Report</a>
                                        </div>

                                        <div class="clear clearfix"></div>
                                        <div style="overflow: hidden; width: 100%;" id="content"></div>

                                        {!! Form::open(['method' => 'POST', 'target' => '_blank', 'route' => ['admin.reports.operations_report_load'], 'id' => 'report-form']) !!}
                                        {!! Form::hidden('date_range', null, ['id' => 'date_range-report']) !!}
                                        {!! Form::hidden('date_range_by', null, ['id' => 'date_range_by-report']) !!}
                                        {!! Form::hidden('date_range_by_first', null, ['id' => 'date_range_by_first-report']) !!}
                                        {!! Form::hidden('month', null, ['id' => 'month-report']) !!}
                                        {!! Form::hidden('year', null, ['id' => 'year-report']) !!}
                                        {!! Form::hidden('days_count', null, ['id' => 'days_count-report']) !!}
                                        {!! Form::hidden('completed_working_days', null, ['id' => 'completed_working_days-report']) !!}
                                        {!! Form::hidden('region_id', null, ['id' => 'region_id-report']) !!}
                                        {!! Form::hidden('city_id', null, ['id' => 'city_id-report']) !!}
                                        {!! Form::hidden('location_id', null, ['id' => 'location_id-report']) !!}
                                        {!! Form::hidden('service_id', null, ['id' => 'service_id-report']) !!}
                                        {!! Form::hidden('user_id', null, ['id' => 'user_id-report']) !!}
                                        {!! Form::hidden('type', null, ['id' => 'type-report']) !!}
                                        {!! Form::hidden('consultancy_type', null, ['id' => 'consultancy_type-report']) !!}
                                        {!! Form::hidden('appointment_type_id', null, ['id' => 'appointment_type_id-report']) !!}
                                        {!! Form::hidden('patient_id', null, ['id' => 'patient_id-report']) !!}
                                        {!! Form::hidden('medium_type', null, ['id' => 'medium_type-report']) !!}
                                        {!! Form::hidden('report_type', null, ['id' => 'report_type-report']) !!}
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



    @push('datatable-js')
        <script src="{{asset('assets/js/pages/admin_settings/settings.js')}}"></script>

    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>

    @endpush

    @push('js')

        <script>
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
                endDate  : moment(),
            });

            var loadReport = function (that) {

                if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
                    return false;
                }

               showSpinner();

                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: route('admin.reports.operations_report_load'),
                    type: "POST",
                    data: {
                        date_range: $('#date_range').val(),
                        agent_id: $('#agent_id').val(),
                        date_range_by: $('#date_range_by').val(),
                        date_range_by_first: $('#date_range_by_first').val(),
                        year: $('#year').val(),
                        month: $('#month').val(),
                        days_count: $('#days_count').val(),
                        completed_working_days: $('#completed_working_days').val(),
                        region_id: $('#region_id').val(),
                        city_id: $('#city_id').val(),
                        location_id: $('#location_id').val(),
                        service_id: $('#service_id').val(),
                        user_id: $('#user_id').val(),
                        type: $('#type').val(),
                        consultancy_type: $('#consultancy_type').val(),
                        appointment_type_id: $('#appointment_type_id').val(),
                        patient_id: $('#patient_id').val(),
                        medium_type: $('#medium_type').val(),
                        report_type: $('#report_type').val(),
                    },
                    success: function(response){
                        $('#content').html('');
                        if($('#medium_type').val() == 'web') {
                            $('#content').html(response);

                            $('#test').DataTable();

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
            };

            var printReport = function (medium_type) {
                $('#date_range-report').val($('#date_range').val());
                $('#date_range_by-report').val($('#date_range_by').val());
                $('#date_range_by_first-report').val($('#date_range_by_first').val());
                $('#year-report').val($('#year').val());
                $('#month-report').val($('#month').val());
                $('#days_count-report').val($('#days_count').val());
                $('#completed_working_days-report').val($('#completed_working_days').val());
                $('#region_id-report').val($('#region_id').val());
                $('#city_id-report').val($('#city_id').val());
                $('#location_id-report').val($('#location_id').val());
                $('#service_id-report').val($('#service_id').val());
                $('#user_id-report').val($('#user_id').val());
                $('#type-report').val($('#type').val());
                $('#consultancy_type-report').val($('#consultancy_type').val());
                $('#appointment_type_id-report').val($('#appointment_type_id').val());
                $('#patient_id-report').val($('#patient_id').val());
                $('#medium_type-report').val(medium_type);
                $('#report_type-report').val($('#report_type').val());
                $('#report-form').submit();
            }


            $(document).on('change', '#report_type', function () {
                var type_p = $("#report_type").val();
                if (type_p == 'dar_report') {
                    $('#services').hide();
                    $('#agent').hide();
                } else if (type_p == 'walking_report') {
                    $('#services').hide();
                    $('#agent').hide();
                } else if (type_p == 'agent_report') {
                    $('#services').hide();
                    $('#agent').show();
                }
            });


        </script>

    @endpush

@endsection
