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
    <link href="{{ url('metronic/assets/global/plugins/datatables/datatables.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css')}}" rel="stylesheet" type="text/css"/>
    <!-- END PAGE LEVEL PLUGINS -->
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-editable/bootstrap-editable/css/bootstrap-editable.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-editable/inputs-ext/address/address.css') }}" rel="stylesheet" type="text/css" />
    <!-- END PAGE LEVEL PLUGINS -->
    <style type="text/css">
        #service_id span.select2-container {
            z-index: 10050;
        }
        .center-check label {
            display: block;
            width: 100%;
            margin: 0;
            text-align: center;
        }
        .center-check label input[type="checkbox"],
        .center-check label input[type="radio"] {
            width: 13px;
            height: 13px;
            display: inline-block;
            vertical-align: top;
            margin: 4px 3px 0 0;
        }

        .center-check label span {
            display: inline-block;
            vertical-align: top;
        }
    </style>
@stop
@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.leads.title')</h1>
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
                @if(Gate::allows('leads_create'))
                    <a class="btn btn-success pull-right" href="{{ route('admin.leads.create_popup') }}" data-target="#ajax_leads" data-toggle="modal">@lang('global.app_add_new')</a>
                @endif
                @if(Gate::allows('leads_import'))
                    <a href="{{ route('admin.leads.import') }}" class="btn btn-success pull-right margin-r-5">@lang('global.leads.import')</a>
                @endif
                @if(Gate::allows('leads_export'))
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
                        <th width="20%">@lang('global.leads.fields.full_name')</th>
                        <th>@lang('global.leads.fields.phone')</th>
                        <th width="15%">@lang('global.leads.fields.city')</th>
                        <th width="10%">@lang('global.leads.fields.region')</th>
                        <th width="12%">@lang('global.leads.fields.lead_status')</th>
                        <th>@lang('global.leads.fields.service')</th>
                        <th>@lang('global.leads.fields.created_at')</th>
                        <th>@lang('global.leads.fields.created_by')</th>
                        <th width="20%">@lang('global.leads.fields.actions')</th>
                    </tr>
                    <tr role="row" class="filter">
                        <td>
                            <input type="text" class="form-control form-filter input-sm" value="{{ $filters->get(Auth::User()->id, 'leads', 'patient_id') }}" name="patient_id">
                        </td>
                        <td>
                            <input type="text" class="form-control form-filter input-sm" value="{{ $filters->get(Auth::User()->id, 'leads', 'name') }}" name="name">
                        </td>
                        <td>
                            <input type="text" class="form-control form-filter input-sm" value="{{ $filters->get(Auth::User()->id, 'leads', 'phone') }}" name="phone">
                        </td>
                        <td>
                            {!! Form::select('city_id', $cities, $filters->get(Auth::User()->id, 'leads', 'city_id'), ['class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            {!! Form::select('region_id', $regions, $filters->get(Auth::User()->id, 'leads', 'region_id'), ['class' => 'form-control form-filter input-sm select2']) !!}
                        </td>
                        <td>
                            {!! Form::select('lead_status_id', $lead_statuses, $filters->get(Auth::User()->id, 'leads', 'lead_status_id'), ['class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            {!! Form::select('service_id', $Services, $filters->get(Auth::User()->id, 'leads', 'service_id'), ['class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            <div class="input-group date date-picker margin-bottom-5" data-date-format="yyyy-mm-dd">
                                <input type="text" class="form-control form-filter input-sm" readonly="" name="date_from" placeholder="From" value="{{ ($filters->get(Auth::User()->id, 'leads', 'date_from')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'leads', 'date_from'))->format('Y-m-d') : '' }}">
                                <span class="input-group-btn">
                                    <button class="btn btn-sm default" type="button">
                                        <i class="fa fa-calendar"></i>
                                    </button>
                                </span>
                            </div>
                            <div class="input-group date date-picker" data-date-format="yyyy-mm-dd">
                                <input type="text" class="form-control form-filter input-sm" readonly="" name="date_to" placeholder="To" value="{{ ($filters->get(Auth::User()->id, 'leads', 'date_to')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'leads', 'date_to'))->format('Y-m-d') : '' }}">
                                <span class="input-group-btn">
                                    <button class="btn btn-sm default" type="button">
                                        <i class="fa fa-calendar"></i>
                                    </button>
                                </span>
                            </div>
                        </td>
                        <td>
                            {!! Form::select('created_by', $users, $filters->get(Auth::User()->id, 'leads', 'created_by'), ['class' => 'form-control form-filter input-sm select2',]) !!}
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
        </div>
    </div>
    <!-- End: Demo Datatable 1 -->
    <div class="modal fade bs-modal-sm" id="ajax" role="basic" aria-hidden="true">

        <div class="modal-content">
                <div class="modal-body">
                    <img src="{{ url('metronic/assets/global/img/loading-spinner-grey.gif') }}" alt="" class="loading">
                    <span> &nbsp;&nbsp;Loading... </span>
                </div>
            </div>
            <!-- /.modal-content -->

        <!-- /.modal-dialog -->
    </div>
    <!-- /.modal -->
    <div id="clipboardNotify"></div>
    <!--Detail View model Start-->
    <div class="modal fade" id="ajax_leads_detail" role="basic" aria-hidden="true"  style=" left:10%; width: 80%; top:2%;">
        <div class="modal-content">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--Detail View model End-->
    <!--add new View model Start-->
    <div class="modal fade" id="ajax_leads" role="basic" aria-hidden="true"  style=" left:10%; width: 80%; top:2%;">
        <div class="modal-content">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--add new View model End-->
@stop
@section('javascript')
    <script src="{{ url('metronic/assets/global/plugins/jquery-ui/jquery-ui.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/clipboard/clipboard.min.js') }}"></script>
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/datatables/datatables.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-editable/bootstrap-editable/js/bootstrap-editable.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-editable/inputs-ext/address/address.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-inputmask/jquery.inputmask.bundle.min.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
     <script src="{{ url('metronic/assets/global/plugins/select2/js/select2.full.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}" type="text/javascript"></script>
    <!-- BEGIN PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/scripts/datatable.js') }}" type="text/javascript"></script>
    <script src="{{ url('js/admin/leads/datatable.js') }}" type="text/javascript"></script>
    <script src="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'}}"
            type="text/javascript"></script>
    <!-- END PAGE LEVEL SCRIPTS -->
@endsection