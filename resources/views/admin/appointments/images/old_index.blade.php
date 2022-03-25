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
@stop

@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.app_appointmentimages')</h1>
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
                <a href="{{ route('admin.appointments.index') }}"
                   class="btn btn-success pull-right">@lang('global.app_back')</a>
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
                        <td @if($appointment->appointment_status_id != Config::get('constants.appointment_status_not_show')) @endif>@if($appointment->appointment_status_id){{ $appointment->appointment_status->name }}@else{{'N/A'}}@endif</td>
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
                    <tr>
                        <th>Patient ID</th>
                        <td>{{ ($appointment->name) ? \App\Helpers\GeneralFunctions::patientSearchStringAdd($appointment->patient_id) : \App\Helpers\GeneralFunctions::patientSearchStringAdd($appointment->patient->id) }}</td>
                    </tr>
                    
                    </tbody>
                </table>
                {{--End of detail body--}}
                <br>
                {{--Start of dropzone body--}}
                @if(Gate::allows('appointments_image_upload'))
                    <div class="tabbable tabbable-tabdrop">
                        <ul class="nav nav-pills">
                            <li class="active">
                                <a href="#tab11" data-toggle="tab" id="checkedbefore" onclick="changeUrl(this)">Before
                                    Appointment</a>
                            </li>
                            <li>
                                <a href="#tab12" data-toggle="tab" id="checkedafter" onclick="changeUrl(this)">After
                                    Appointment</a>
                            </li>
                        </ul>
                        <div class="tab-content">
                            <?php $check = null; ?>
                            <input type="hidden" id="appointment_id" value="{{$appointment->id}}"/>
                            <div class="tab-pane active" id="tab11">
                            </div>
                            <div class="tab-pane" id="tab12">
                            </div>
                            <form action="{{ route('admin.appointmentsimage.imagestore_before',[$appointment->id]) }}"
                                  class="dropzone" id="a-form-element">
                                <input type="hidden" value="" id="hiddentext" name="type"/>
                            </form>
                            <br>
                            <button class="btn btn-success" id="submit-all-1">Upload</button>
                        </div>
                    </div>
                @endif
                {{--End of dropzone body--}}
                <br>
                {{--Start of datatable body--}}
                @if(Gate::allows('appointments_image_destroy'))
                    <div class="table-actions-wrapper">
                        <span> </span>
                        <select class="table-group-action-input form-control input-inline input-small input-sm">
                            <option value="">Select</option>
                            <option value="Delete">Delete</option>
                        </select>
                        <button class="btn btn-sm red table-group-action-submit">
                            <i class="fa fa-check"></i> Submit
                        </button>
                    </div>
                @endif
                <table class="table table-striped table-bordered table-hover table-checkable" id="datatable_ajax">
                    <thead>
                    <tr role="row" class="heading">
                        <th width="3%">
                            <label class="mt-checkbox mt-checkbox-single mt-checkbox-outline">
                                <input type="checkbox" class="group-checkable" data-set="#sample_2 .checkboxes"/>
                                <span></span>
                            </label>
                        </th>
                        <th>@lang('global.appointmentimages.fields.patient_id')</th>
                        <th>@lang('global.appointmentimages.fields.name')</th>
                        <th>@lang('global.appointmentimages.fields.type')</th>
                        <th>@lang('global.appointmentimages.fields.created_at')</th>
                        <th width="20%">@lang('global.appointmentimages.fields.actions')</th>
                    </tr>
                    <tr role="row" class="filter">
                        <td></td>
                        <td>
                            <input type="text" class="form-control form-filter input-sm" name="id">
                        </td>
                        <td>
                            <input type="hidden" id="appointment_id" value="{{ $appointment->id }}"/>
                        </td>
                        <td>
                            {!! Form::select('type', array('' => 'All', 'Before Appointment' => 'Before Appointment', 'After Appointment' => 'After Appointment'), '', ['class' => 'form-control form-filter input-sm select2',]) !!}
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
@stop

@section('javascript')
    <script>
        function changeUrl(that) {
            $('#hiddentext').val($(that).attr("id"));
        }

        $(document).ready(function () {
            $("#checkedbefore").trigger("onclick");
        });
    </script>
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
    <script src="{{ url('js/admin/appointmentimages/datatable.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/select2/js/select2.full.min.js') }}"
            type="text/javascript"></script>
    <script src="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'}}"
            type="text/javascript"></script>
    <!--Script of dropzone-->
    <script src="{{ url('metronic/assets/global/plugins/dropzone/dropzone.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/scripts/app.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('js/admin/appointments/form-dropzone.js') }}" type="text/javascript"></script>
@endsection