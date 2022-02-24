@inject('request', 'Illuminate\Http\Request')
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
    <!-- END PAGE LEVEL PLUGINS -->
    <!--Css for dropzone-->
    <link href="{{ url('metronic/assets/global/plugins/dropzone/dropzone.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/dropzone/basic.min.css') }}" rel="stylesheet" type="text/css"/>

    <style type="text/css">
        .table-checkable tr > td:first-child, .table-checkable tr > th:first-child {
            text-align: left;
            max-width: 50px;
            min-width: 40px;
            padding-left: 10px;
            padding-right: 10px;
        }
    </style>
@stop

@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.app_appointmentmedical')</h1>
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

                @if(Gate::allows('appointments_medical_create'))
                    <a class="btn btn-success btn-to-focus"
                       href="{{ route('admin.appointmentsmedical.create',[$appointment->id]) }}"
                       data-target="#ajax_medical_addnew" data-toggle="modal">@lang('global.app_add_new')</a>
                @endif
                <a href="{{ route('admin.appointments.index') }}" class="btn btn-success">@lang('global.app_back')</a>
            </div>
        </div>
        <div class="portlet-body">
            <div class="table-container">
                {{--Start of Detail body--}}
                <table class="table table-striped">
                    <tbody>
                    <tr>
                        <th>Patient Name</th>
                        <td>{{ ($appointment->name) ? $appointment->name : $appointment->patient->name }}</td>
                        <th>Patient Phone</th>
                        <td>@if($appointment->patient->phone){{ \App\Helpers\GeneralFunctions::prepareNumber4Call($appointment->patient->phone) }}@else{{'N/A'}}@endif</td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td>@if($appointment->patient->email){{ $appointment->patient->email }}@else{{'N/A'}}@endif</td>
                        <th>Gender</th>
                        <td>@if($appointment->patient->gender){{ Config::get('constants.gender_array')[$appointment->patient->gender] }}@else{{'N/A'}}@endif</td>
                    </tr>
                    <tr>
                        <th>Appointment Time</th>
                        <td>@if($appointment->scheduled_date){{ \Carbon\Carbon::parse($appointment->scheduled_date, null)->format('M j, y') . ' at ' . \Carbon\Carbon::parse($appointment->scheduled_time, null)->format('h:i A') }}@else{{'-'}}@endif</td>
                        <th>Doctor</th>
                        <td>@if($appointment->doctor_id){{ $appointment->doctor->name }}@else{{'N/A'}}@endif</td>
                    </tr>
                    <tr>
                        <th>City</th>
                        <td>@if($appointment->city_id){{ $appointment->city->name }}@else{{'N/A'}}@endif</td>
                        <th>Centre</th>
                        <td>@if($appointment->location_id){{ $appointment->location->name }}@else{{'N/A'}}@endif</td>
                    </tr>
                    <tr>
                        <th>Appointment Status</th>
                        <td @if($appointment->appointment_status_id != Config::get('constants.appointment_status_not_show'))@endif>@if($appointment->appointment_status_id){{ $appointment->appointment_status->name }}@else{{'N/A'}}@endif</td>
                        <th>Service/Consultancy</th>
                        <td>{{$appointment->service->name}}</td>
                    </tr>
                    <tr>
                        @if($appointment->appointment_status_id == Config::get('constants.appointment_status_not_show'))
                            <th>{{ trans('global.cancellation_reasons.word') }}</th>
                            <td>@if($appointment->cancellation_reason_id && isset($appointment->cancellation_reason->name)){{ $appointment->cancellation_reason->name }}@else{{ 'N/A' }}@endif</td>
                        @endif
                    </tr>
                    @if(($appointment->appointment_status_id == Config::get('constants.appointment_status_not_show')) &&
                        ($appointment->cancellation_reason_id == Config::get('constants.cancellation_reason_other_reason')))
                        <tr>
                            <th>Reason</th>
                            <td colspan="3">@if($appointment->reason){{ $appointment->reason }}@else{{ 'N/A' }}@endif</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
                {{--End of detail body--}}
                {{--Start of datatable body--}}
                <table class="table table-striped table-bordered table-hover table-checkable" id="datatable_ajax">
                    <thead>
                    <tr role="row" class="heading">
                        <th>@lang('global.appointmentmedical.fields.name')</th>
                        <th>@lang('global.appointmentmedical.fields.patient_name')</th>
                        <th>@lang('global.appointmentmedical.fields.created_by')</th>
                        <th>@lang('global.appointmentmedical.fields.created_at')</th>
                        <th width="20%">@lang('global.appointmentmedical.fields.actions')</th>
                    </tr>
                    <tr role="row" class="filter">
                        <td>
                            <input type="hidden" id="appointment_id" value="{{ $appointment->id }}"/>
                        </td>
                        <td></td>
                        <td>
                            {!! Form::select('user_id', $users, '', ['class' => 'form-control form-filter input-sm select2',]) !!}
                        </td>
                        <td>
                            <div class="input-icon input-icon-sm right margin-bottom-5">
                                <i class="fa fa-calendar"></i>
                                <input name="created_from" data-date-format="yyyy-mm-dd" type="text" readonly=""
                                       class="form-control form-filter input-sm created_from" placeholder="From">
                            </div>
                            <div class="input-icon input-icon-sm right margin-bottom-5">
                                <i class="fa fa-calendar"></i>
                                <input name="created_to" data-date-format="yyyy-mm-dd" type="text" readonly=""
                                       class="form-control form-filter input-sm created_to" placeholder="To">
                            </div>
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

                {{--End of Datatable Body--}}
            </div>
        </div>
    </div>
    <!-- End: Demo Datatable 1 -->
    <!--Add New View model Start-->
    <div class="modal fade" id="ajax_medical_addnew" role="basic" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--Add new View model End-->

@stop

@section('javascript')
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/datatables/datatables.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"
            type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
    <!-- BEGIN PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/scripts/datatable.js') }}" type="text/javascript"></script>
    <script src="{{ url('js/admin/medicals/datatable.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/select2/js/select2.full.min.js') }}"
            type="text/javascript"></script>
    <script src="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'}}"
            type="text/javascript"></script>
@endsection