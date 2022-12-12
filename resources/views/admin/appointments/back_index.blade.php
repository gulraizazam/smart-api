@inject('request', 'Illuminate\Http\Request')
@extends('layouts.app')

@section('stylesheets')
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <link href="{{ url('metronic/assets/global/plugins/datatables/datatables.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}" rel="stylesheet" type="text/css"/>
    <!-- END PAGE LEVEL PLUGINS -->
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-editable/bootstrap-editable/css/bootstrap-editable.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-editable/inputs-ext/address/address.css') }}" rel="stylesheet" type="text/css" />
    <!-- END PAGE LEVEL PLUGINS -->
    <style type="text/css">
        body{
            margin: 0;
            height: 100%;
            overflow: hidden
        }
        table{
            margin: 0 auto;
            width: 100%;
            clear: both;
            border-collapse: collapse;
            table-layout: fixed;
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
                <a href="{{ route('admin.appointments.create') }}" class="btn btn-success pull-right">@lang('global.app_add_new')</a>
                @if(Gate::allows('appointments_export'))
                    <div class="btn-group">
                    <a class="btn green" href="javascript:;" data-toggle="dropdown">
                        <i class="fa fa-download"></i>
                        <span class="hidden-xs"> Export </span>
                        <i class="fa fa-angle-down"></i>
                    </a>
                    <ul class="dropdown-menu pull-right" id="datatable_ajax_tools">
                        <li>
                            <a href="javascript:;" data-action="0" class="tool-action"><i class="icon-doc"></i> PDF</a>
                        </li>
                        <li>
                            <a href="javascript:;" data-action="1" class="tool-action"><i class="icon-paper-clip"></i> Excel</a>
                        </li>
                        <li>
                            <a href="javascript:;" data-action="2" class="tool-action"><i class="icon-cloud-upload"></i> CSV</a>
                        </li>
                    </ul>
                </div>
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
                        <th>@lang('global.appointments.fields.city')</th>
                        <th>@lang('global.appointments.fields.location')</th>
                        <th>@lang('global.appointments.fields.treatment')</th>
                        <th>@lang('global.appointments.fields.appointment_status')</th>
                        <th>@lang('global.appointments.fields.created_at')</th>
                        <th>@lang('global.appointments.fields.created_by')</th>
                        <th>@lang('global.appointments.fields.actions')</th>
                    </tr>
                    <tr role="row" class="filter">
                        <td>
                            <input type="text" class="form-control form-filter input-sm" name="patient_id">
                        </td>
                        <td>
                            <input type="text" class="form-control form-filter input-sm" name="full_name">
                        </td>
                        <td>
                            <input type="text" class="form-control form-filter input-sm" name="phone">
                        </td>
                        <td>
                            <div class="input-group date date-picker margin-bottom-5" data-date-format="yyyy-mm-dd">
                                <input type="text" class="form-control form-filter input-sm" readonly="" name="date_from" placeholder="From">
                                <span class="input-group-btn">
                                    <button class="btn btn-sm default" type="button">
                                        <i class="fa fa-calendar"></i>
                                    </button>
                                </span>
                            </div>
                            <div class="input-group date date-picker" data-date-format="yyyy-mm-dd">
                                <input type="text" class="form-control form-filter input-sm" readonly="" name="date_to" placeholder="To">
                                <span class="input-group-btn">
                                    <button class="btn btn-sm default" type="button">
                                        <i class="fa fa-calendar"></i>
                                    </button>
                                </span>
                            </div>
                        </td>
                        <td>
                            {!! Form::select('doctor_id', $doctors, '', ['class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            {!! Form::select('city_id', $cities, '', ['class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            {!! Form::select('location_id', $locations, '', ['class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            {!! Form::select('treatment_id', $treatments, '', ['class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            {!! Form::select('appointment_status_id', $appointment_statuses, '', ['class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            <div class="input-group date date-picker margin-bottom-5" data-date-format="yyyy-mm-dd">
                                <input type="text" class="form-control form-filter input-sm" readonly="" name="created_from" placeholder="From">
                                <span class="input-group-btn">
                                    <button class="btn btn-sm default" type="button">
                                        <i class="fa fa-calendar"></i>
                                    </button>
                                </span>
                            </div>
                            <div class="input-group date date-picker" data-date-format="yyyy-mm-dd">
                                <input type="text" class="form-control form-filter input-sm" readonly="" name="created_to" placeholder="To">
                                <span class="input-group-btn">
                                    <button class="btn btn-sm default" type="button">
                                        <i class="fa fa-calendar"></i>
                                    </button>
                                </span>
                            </div>
                        </td>
                        <td>
                            {!! Form::select('created_by', $users, '', ['class' => 'form-control form-filter input-sm select2',]) !!}
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
    </div>
    <!-- End: Demo Datatable 1 -->

    <div class="modal fade bs-modal-sm" id="ajax" role="basic" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-body">
                    <img src="{{ url('metronic/assets/global/img/loading-spinner-grey.gif') }}" alt="" class="loading">
                    <span> &nbsp;&nbsp;Loading... </span>
                </div>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>
    <!-- /.modal -->
    <div id="clipboardNotify"></div>
    <!--Start Model For appointment Detail-->
    <div class="modal fade" id="ajax_detail_appointment" role="basic" aria-hidden="true"  style="left:20%; width: 70%; top:7%;">
        <div class="modal-content">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--End Model for appointment Detail-->
@stop

@section('javascript')
    <script src="{{ url('metronic/assets/global/plugins/jquery-ui/jquery-ui.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/clipboard/clipboard.min.js') }}"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-growl/jquery.bootstrap-growl.min.js') }}" type="text/javascript"></script>
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/datatables/datatables.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/select2/js/select2.full.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-editable/bootstrap-editable/js/bootstrap-editable.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-editable/inputs-ext/address/address.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}" type="text/javascript"></script>
    <!-- BEGIN PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/scripts/datatable.js') }}" type="text/javascript"></script>
    <script src="{{ url('js/admin/appointments/datatable.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL SCRIPTS -->
@endsection