@inject('request', 'Illuminate\Http\Request')
@inject('Auth', 'Auth')
@inject('filters', 'App\Helpers\Filters')
@extends('layouts.app')

@section('stylesheets')
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <link href="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css'}}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/datatables.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css') }}"
          rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}"
          rel="stylesheet" type="text/css"/>
    {{--<link href="{{ url('js/timepicker/jquery.timepicker.min.css') }}" rel="stylesheet" type="text/css" />--}}
    <!-- END PAGE LEVEL PLUGINS -->
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-editable/bootstrap-editable/css/bootstrap-editable.css') }}"
          rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-editable/inputs-ext/address/address.css') }}"
          rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/pages/css/invoice.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('plugins/timepicker/jquery.timepicker.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('plugins/timepicker-css/timepicker-css.css') }}" rel="stylesheet" type="text/css"/>
    <!-- END PAGE LEVEL PLUGINS -->
    <style type="text/css">
        body {
            overflow-x: hidden;
        }
        @media screen and (max-width: 767px) {
            .invice-holder .text-right {
                text-align: left !important;
            }
        }
        .custom_alert {
            padding: 15px;
            border: 1px solid transparent;
            background-color: #fbe1e3;
            border-color: #fbe1e3;
            color: #e73d4a;
        }
        .custom_alert .custom_alert_close {
            display: inline-block;
            margin-top: 0;
            margin-right: 0;
            width: 9px;
            height: 9px;
            text-indent: -10000px;
            outline: 0;
            background: transparent url(http://3dlifestyleappointments.test/metronic/assets/global/img/remove-icon-small.png) center center no-repeat !important;
            float: right;
            opacity: .5;
            border: 0px;
        }
    </style>
@stop

@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.appointments.title')</h1>
    <!-- END PAGE TITLE-->
@endsection

@section('content')
    <!-- Begin: Demo Datatable 1 -->
    <div class="portlet light portlet-fit portlet-datatable bordered">
        <div class="portlet-title">
            <div class="caption">
                <i class="icon-list font-dark"></i>
                <span class="caption-subject font-dark sbold uppercase">@lang('global.app_list')</span>
            </div>
            <div class="actions">
                @if(Gate::allows('appointments_services'))
                    <a href="{{ route('admin.appointments.manage_services') }}" class="btn btn-success pull-right">Manage
                        Treatment</a>
                @endif
                @if(Gate::allows('appointments_consultancy'))
                    <a href="{{ route('admin.appointments.create') }}" class="btn btn-success pull-right">Manage
                        Consultancy</a>
                @endif
                @if(Gate::allows('appointments_export'))
                    @if(Gate::allows('appointments_export_all') || Gate::allows('appointments_export_today') || Gate::allows('appointments_export_this_month'))
                        <div class="btn-group">
                            <a class="btn green" href="javascript:;" data-toggle="dropdown">
                                <i class="fa fa-download"></i>
                                <span class="hidden-xs"> Export </span>
                                <i class="fa fa-angle-down"></i>
                            </a>
                            <ul class="dropdown-menu pull-right" id="datatable_ajax_tools">
                                {{--<li>
                                    <a href="javascript:;" data-action="0" class="tool-action"><i class="icon-doc"></i> PDF</a>
                                </li>--}}
                                <li>
                                    <a class="btn sn-white-btn btn-default" href="javascript:;"
                                       onclick="FormControls.excel_Report('excel');">
                                        <i class="icon-paper-clip"></i> Excel
                                    </a>
                                </li>
                                {{--<li>
                                    <a onclick="FormControls.excel_Report('excel');" href="javascript:;"><i class="icon-cloud-upload"></i> CSV</a>
                                </li>--}}
                            </ul>
                        </div>
                    @endif
                @endif
            </div>
        </div>
        <div class="portlet-body">
            <div class="table-container">
                <table class="table table-striped table-bordered table-hover table-checkable" id="datatable_ajax">
                    <thead>
                    <tr role="row" class="heading">
                        <th>@lang('global.leads.fields.patientid')</th>
                        <th>@lang('global.appointments.fields.patient_name')</th>
                        <th>@lang('global.appointments.fields.patient_phone')</th>
                        <th>@lang('global.appointments.fields.scheduled_at')</th>
                        <th>@lang('global.appointments.fields.doctor')</th>
                        <th>@lang('global.appointments.fields.region')</th>
                        <th>@lang('global.appointments.fields.city')</th>
                        <th>@lang('global.appointments.fields.location')</th>
                        <th>@lang('global.appointments.fields.service')</th>
                        <th>@lang('global.appointments.fields.appointment_status')</th>
                        <th>@lang('global.appointments.fields.appointment_type')</th>
                        <th>@lang('global.appointments.fields.consultancy_type')</th>
                        <th>@lang('global.appointments.fields.created_at')</th>
                        <th>@lang('global.appointments.fields.created_by')</th>
                        <th>@lang('global.appointments.fields.updated_by')</th>
                        <th>@lang('global.appointments.fields.rescheduled_by')</th>
                        <th>@lang('global.appointments.fields.actions')</th>
                    </tr>
                    <tr role="row" class="filter">
                        <td>
                            <input type="text" class="form-control form-filter input-sm"
                                   value="{{ $filters->get(Auth::User()->id, 'appointments', 'patient_id') }}"
                                   name="patient_id" id="patient_id">
                        </td>
                        <td>
                            <input type="text" class="form-control form-filter input-sm" name="name"
                                   value="{{ $filters->get(Auth::User()->id, 'appointments', 'name') }}" id="name">
                        </td>
                        <td>
                            <input type="text" class="form-control form-filter input-sm" name="phone"
                                   value="{{ $filters->get(Auth::User()->id, 'appointments', 'phone') }}" id="phone">
                        </td>
                        <td>
                            <div class="input-group date date-picker margin-bottom-5" data-date-format="yyyy-mm-dd">
                                <input type="text" class="form-control form-filter input-sm" readonly=""
                                       name="date_from" placeholder="From"
                                       value="{{ ($filters->get(Auth::User()->id, 'appointments', 'date_from')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'appointments', 'date_from'))->format('Y-m-d') : '' }}"
                                       id="date_from">
                                <span class="input-group-btn">
                                    <button class="btn btn-sm default" type="button">
                                        <i class="fa fa-calendar"></i>
                                    </button>
                                </span>
                            </div>
                            <div class="input-group date date-picker" data-date-format="yyyy-mm-dd">
                                <input type="text" class="form-control form-filter input-sm" readonly="" name="date_to"
                                       placeholder="To"
                                       value="{{ ($filters->get(Auth::User()->id, 'appointments', 'date_to')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'appointments', 'date_to'))->format('Y-m-d') : '' }}"
                                       id="date_to">
                                <span class="input-group-btn">
                                    <button class="btn btn-sm default" type="button">
                                        <i class="fa fa-calendar"></i>
                                    </button>
                                </span>
                            </div>
                        </td>
                        <td>
                            {!! Form::select('doctor_id', $doctors, $filters->get(Auth::User()->id, 'appointments', 'doctor_id'), ['id'=>'doctor_id','class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            {!! Form::select('region_id', $regions, $filters->get(Auth::User()->id, 'appointments', 'region_id'), ['id'=>'region_id','class' => 'form-control form-filter input-sm select2']) !!}
                        </td>
                        <td>
                            {!! Form::select('city_id', $cities, $filters->get(Auth::User()->id, 'appointments', 'city_id'), ['id'=>'city_id','class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            {!! Form::select('location_id', $locations, $filters->get(Auth::User()->id, 'appointments', 'location_id'), ['id'=>'location_id','class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            {!! Form::select('service_id', $services, $filters->get(Auth::User()->id, 'appointments', 'service_id'), ['id'=>'service_id','class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            {!! Form::select('appointment_status_id', $appointment_statuses, $filters->get(Auth::User()->id, 'appointments', 'appointment_status_id'), ['id'=>'appointment_status_id','class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            {!! Form::select('appointment_type_id', $appointment_types, $filters->get(Auth::User()->id, 'appointments', 'appointment_type_id'), ['id'=>'appointment_type_id','class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            {!! Form::select('consultancy_type', array('' => 'All') + Config::get("constants.consultancy_type_array"),$filters->get(Auth::User()->id, 'appointments', 'consultancy_type'),['id'=>'consultancy_type', 'class' => 'form-control form-filter input-sm select2']) !!}
                        </td>
                        <td>
                            <div class="input-icon input-icon-sm right margin-bottom-5">
                                <i class="fa fa-calendar"></i>
                                <input name="created_from" data-date-format="yyyy-mm-dd" type="text" readonly=""
                                       class="form-control form-filter input-sm created_from" placeholder="From"
                                       value="{{ ($filters->get(Auth::User()->id, 'appointments', 'created_from')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'appointments', 'created_from'))->format('Y-m-d') : '' }}"
                                       id="created_from">
                            </div>
                            <div class="input-icon input-icon-sm right margin-bottom-5">
                                <i class="fa fa-calendar"></i>
                                <input name="created_to" data-date-format="yyyy-mm-dd" type="text" readonly=""
                                       class="form-control form-filter input-sm created_to" placeholder="To"
                                       value="{{ ($filters->get(Auth::User()->id, 'appointments', 'created_to')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'appointments', 'created_to'))->format('Y-m-d') : '' }}"
                                       id="created_to">
                            </div>
                        </td>
                        <td>
                            {!! Form::select('created_by', $users, $filters->get(Auth::User()->id, 'appointments', 'created_by'), ['id'=>'created_by','class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            {!! Form::select('converted_by', $users, $filters->get(Auth::User()->id, 'appointments', 'converted_by'), ['id'=>'converted_by','class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            {!! Form::select('updated_by', $users, $filters->get(Auth::User()->id, 'appointments', 'updated_by'), ['id'=>'updated_by','class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            <div class="margin-bottom-5">
                                <button class="btn btn-sm green btn-outline filter-submit margin-bottom">
                                    <i class="fa fa-search"></i> Search
                                </button>
                                <button class="btn btn-sm red btn-outline filter-cancel">
                                    <i class="fa fa-times"></i> Reset
                                </button>
                            </div>
                        </td>
                    </tr>
                    </thead>
                    <tbody>

                    </tbody>
                </table>
            </div>
            <div class="clearfix"></div>
        </div>
        {{--Excel Table start--}}
        {!! Form::open(['method' => 'POST', 'target' => '_blank', 'route' => ['admin.appointments.appointmentexcel'], 'id' => 'report-form']) !!}
        {!! Form::hidden('patient_id', null, ['id' => 'patient_id_report']) !!}
        {!! Form::hidden('name', null, ['id' => 'name_report']) !!}
        {!! Form::hidden('phone', null, ['id' => 'phone_report']) !!}
        {!! Form::hidden('date_from', null, ['id' => 'date_from_report']) !!}
        {!! Form::hidden('date_to', null, ['id' => 'date_to_report']) !!}
        {!! Form::hidden('doctor_id', null, ['id' => 'doctor_id_report']) !!}
        {!! Form::hidden('region_id', null, ['id' => 'region_id_report']) !!}
        {!! Form::hidden('city_id', null, ['id' => 'city_id_report']) !!}
        {!! Form::hidden('location_id', null, ['id' => 'location_id_report']) !!}
        {!! Form::hidden('service_id', null, ['id' => 'service_id_report']) !!}
        {!! Form::hidden('appointment_status_id', null, ['id' => 'appointment_status_id_report']) !!}
        {!! Form::hidden('appointment_type_id', null, ['id' => 'appointment_type_id_report']) !!}
        {!! Form::hidden('consultancy_type', null, ['id' => 'consultancy_type_report']) !!}
        {!! Form::hidden('created_from', null, ['id' => 'created_from_report']) !!}
        {!! Form::hidden('created_to', null, ['id' => 'created_to_report']) !!}
        {!! Form::hidden('created_by', null, ['id' => 'created_by_report']) !!}
        {!! Form::hidden('converted_by', null, ['id' => 'converted_by_report']) !!}
        {!! Form::hidden('updated_by', null, ['id' => 'updated_by_report']) !!}
        {!! Form::close() !!}
        {{--Excel Table end--}}
    </div>
    <!-- End: Demo Datatable 1 -->
    <div class="modal fade" id="ajax_logs" role="basic" aria-hidden="true" style=" left:10%; width: 80%; top:2%;">
        <div class="modal-content">
            <div class="modal-body">
                <img src="{{ url('metronic/assets/global/img/loading-spinner-grey.gif') }}" alt="" class="loading">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal -->
    <div class="modal fade" id="ajax" role="basic" aria-hidden="true" style=" left:10%; width: 80%; top:2%;">
        <div class="modal-content">
            <div class="modal-body">
                <img src="{{ url('metronic/assets/global/img/loading-spinner-grey.gif') }}" alt="" class="loading">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
        <!-- /.modal-dialog -->
    </div>
    <!-- /.modal -->
    <!--Edit View model Start-->
    <div class="modal fade" id="ajax_appointments_edit" role="basic" aria-hidden="true"
         style=" left:10%; width: 80%; top:2%;">
        <div class="modal-content">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--Edit View model End-->
    <div id="clipboardNotify"></div>
    <!--Start Model For appointment Detail-->
    <div class="modal fade" id="ajax_detail_appointment" role="basic" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--End Model for appointment Detail-->
    <!--Treatment Invoice model Start-->
    <div class="modal fade" id="ajax_appointment_invoice" role="basic" aria-hidden="true">
        <div class="modal-content package-custom-popup">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--treatment invoice model End-->
    <!--Consultancy Invoice model Start-->
    <div class="modal fade" id="ajax_appointment_consultancy_invoice" role="basic" aria-hidden="true">
        <div class="modal-content package-custom-popup">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--consultancy invoice model End-->

    <!--Invoice display View model Start-->
    <div class="modal fade" id="ajax_appointments_invoice_display" role="basic" aria-hidden="true">
        <div class="modal-content package-custom-popup">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--display View model End-->
    {{--plans create model create--}}
    <div class="modal fade" id="ajax_packages" role="basic" aria-hidden="true">
        <div class="modal-content package-custom-popup">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    {{--Plans create model end--}}
@stop
@section('javascript')
    <script src="{{ url('metronic/assets/global/plugins/jquery-ui/jquery-ui.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/clipboard/clipboard.min.js') }}"></script>
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/datatables/datatables.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"
            type="text/javascript"></script>
    {{--<script src="{{ url('js/timepicker/jquery.timepicker.min.js') }}"></script>--}}
    <!-- END PAGE LEVEL PLUGINS -->
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/select2/js/select2.full.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-editable/bootstrap-editable/js/bootstrap-editable.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-editable/inputs-ext/address/address.js') }}"
            type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}"
            type="text/javascript"></script>
    <!-- BEGIN PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/scripts/datatable.js') }}" type="text/javascript"></script>

    <script src="{{ url('js/admin/appointments/datatable.js') }}" type="text/javascript"></script>
    <script src="{{ url('js/admin/appointments/excel.js') }}" type="text/javascript"></script>

    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}"
            type="text/javascript"></script>
    <script src="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'}}"
            type="text/javascript"></script>
    <script src="{{ url('plugins/timepicker/jquery.timepicker.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL SCRIPTS -->
@endsection