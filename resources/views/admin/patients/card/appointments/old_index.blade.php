@extends('admin.patients.card.patient_layout')
@inject('Auth', 'Auth')
@inject('filters', 'App\Helpers\Filters')
@section('patient_stylesheets')
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <link href="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css'}}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/datatables.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css')}}" rel="stylesheet" type="text/css"/>
    <!-- END PAGE LEVEL PLUGINS -->
@endsection

@section('patient_content')
    <div class="portlet-title tabbable-line">
        <div class="caption caption-md">
            <i class="icon-globe theme-font hide"></i>
            <span class="caption-subject font-blue-madison bold uppercase">Appointments</span>
        </div>
    </div>
    <div class="portlet-body">
        <div class="table-container">
            <table class="table table-striped table-bordered table-hover table-checkable" id="datatable_ajax">
                <thead>
                <tr role="row" class="heading">
                    <th>@lang('global.appointments.fields.patient_name')</th>
                    <th>@lang('global.appointments.fields.patient_phone')</th>
                    <th>@lang('global.appointments.fields.scheduled_at')</th>
                    <th>@lang('global.appointments.fields.doctor')</th>
                    <th>@lang('global.appointments.fields.city')</th>
                    <th>@lang('global.appointments.fields.location')</th>
                    <th>@lang('global.appointments.fields.service')</th>
                    <th>@lang('global.appointments.fields.appointment_status')</th>
                    <th>@lang('global.appointments.fields.appointment_type')</th>
                    <th>@lang('global.appointments.fields.consultancy_type')</th>
                    <th>@lang('global.appointments.fields.created_at')</th>
                    <th>@lang('global.appointments.fields.created_by')</th>
                </tr>
                <tr role="row" class="filter">
                    <td>
                        <input type="hidden" id="patient_id" value="{{ $patient->id }}" />
                        <input type="text" class="form-control form-filter input-sm" name="name" value="{{$filters->get(Auth::user()->id,'patient_appointments','name')}}">
                    </td>
                    <td>
                        <input type="text" class="form-control form-filter input-sm" name="phone" value="{{$filters->get(Auth::user()->id,'patient_appointments','phone')}}">
                    </td>
                    <td>
                        <div class="input-group date date-picker margin-bottom-5" data-date-format="yyyy-mm-dd">
                            <input type="text" class="form-control form-filter input-sm" readonly="" name="date_from" placeholder="From"
                                   value="{{ ($filters->get(Auth::User()->id, 'patient_appointments', 'date_from')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'patient_appointments', 'date_from'))->format('Y-m-d') : '' }}">
                            <span class="input-group-btn">
                                    <button class="btn btn-sm default" type="button">
                                        <i class="fa fa-calendar"></i>
                                    </button>
                                </span>
                        </div>
                        <div class="input-group date date-picker" data-date-format="yyyy-mm-dd">
                            <input type="text" class="form-control form-filter input-sm" readonly="" name="date_to" placeholder="To"
                                   value="{{ ($filters->get(Auth::User()->id, 'patient_appointments', 'date_to')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'patient_appointments', 'date_to'))->format('Y-m-d') : '' }}">
                            <span class="input-group-btn">
                                    <button class="btn btn-sm default" type="button">
                                        <i class="fa fa-calendar"></i>
                                    </button>
                                </span>
                        </div>
                    </td>
                    <td>
                        {!! Form::select('doctor_id', $doctors, $filters->get(Auth::user()->id,'patient_appointments','doctor_id'), ['class' => 'form-control form-filter input-sm select2',]) !!}
                    </td>
                    <td>
                        {!! Form::select('city_id', $cities, $filters->get(Auth::user()->id,'patient_appointments','city_id'), ['class' => 'form-control form-filter input-sm select2',]) !!}
                    </td>
                    <td>
                        {!! Form::select('location_id', $locations, $filters->get(Auth::user()->id,'patient_appointments','location_id'), ['class' => 'form-control form-filter input-sm select2',]) !!}
                    </td>
                    <td>
                        {!! Form::select('service_id', $services, $filters->get(Auth::user()->id,'patient_appointments','service_id'), ['class' => 'form-control form-filter input-sm select2',]) !!}
                    </td>
                    <td>
                        {!! Form::select('appointment_status_id', $appointment_statuses, $filters->get(Auth::user()->id, 'patient_appointments','appointment_status_id'), ['class' => 'form-control form-filter input-sm select2',]) !!}
                    </td>
                    <td>
                        {!! Form::select('appointment_type_id', $appointment_types, $filters->get(Auth::user()->id, 'patient_appointments', 'appointment_type_id'), ['class' => 'form-control form-filter input-sm select2',]) !!}
                    </td>
                    <td>
                        {!! Form::select('consultancy_type', array('' => 'All') + Config::get("constants.consultancy_type_array"),$filters->get(Auth::User()->id, 'patient_appointments', 'consultancy_type'),['class' => 'form-control form-filter input-sm select2']) !!}
                    </td>
                    <td>
                        <div class="input-icon input-icon-sm right margin-bottom-5">
                            <i class="fa fa-calendar"></i>
                            <input name="created_from" data-date-format="yyyy-mm-dd" type="text" readonly="" class="form-control form-filter input-sm created_from" placeholder="From"
                                   value="{{ ($filters->get(Auth::User()->id, 'patient_appointments', 'created_from')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'patient_appointments', 'created_from'))->format('Y-m-d') : '' }}">
                        </div>
                        <div class="input-icon input-icon-sm right margin-bottom-5">
                            <i class="fa fa-calendar"></i>
                            <input name="created_to" data-date-format="yyyy-mm-dd" type="text" readonly="" class="form-control form-filter input-sm created_to" placeholder="To"
                                   value="{{ ($filters->get(Auth::User()->id, 'patient_appointments', 'created_to')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'patient_appointments', 'created_to'))->format('Y-m-d') : '' }}">
                        </div>
                    </td>
                    <td>
                        {!! Form::select('created_by', $users, $filters->get(Auth::user()->id,'patient_appointments','created_by'), ['class' => 'form-control form-filter input-sm select2',]) !!}
                        <div class="margin-bottom-5">
                            <button class="btn btn-sm green btn-outline filter-submit margin-bottom">
                                <i class="fa fa-search"></i> Search
                            </button>
                            <button class="btn btn-sm red btn-outline filter-cancel reset_custom">
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
@stop

@section('patient_javascript')
    <script>
        $( ".reset_custom" ).click(function() {
            $('.select2').val(null).trigger('change');
        });
    </script>
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/datatables/datatables.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/select2/js/select2.full.min.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/scripts/datatable.js') }}" type="text/javascript"></script>
    <script src="{{ url('js/admin/patients/card/appointments/datatable.js') }}" type="text/javascript"></script>
@stop