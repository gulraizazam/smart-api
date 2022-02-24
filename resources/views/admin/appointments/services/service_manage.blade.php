@extends('layouts.app')

@section('title')
    <!-- BEGIN PAGE TITLE-->
    <div class="portlet box blue">
        <div class="portlet-title">
            <div class="caption">
                <i class="fa fa-gift"></i>Calendar Filters
            </div>
            <div class="tools">
                <a href="javascript:;" class="collapse" data-original-title="" title=""> </a>
            </div>
        </div>
        <div class="portlet-body" style="display: block; padding-bottom: 0;">
            {!! Form::open(['method' => 'POST', 'id' => 'form-validation', 'route' => ['admin.appointments.store']]) !!}

            <div id="external-events">
                <div class="row">
                    <div class="col-md-3 col-sm-12">
                        <!-- Starts Form Validation Messages -->
                    @include('partials.messages')
                    <!-- Ends Form Validation Messages -->
                        <input type="hidden" id="appointment_manager"
                               value="{{Config::get('constants.appointment_type_service_string')}}">
                        <div class="form-group">
                            {!! Form::select('city_id', $cities, null, ['onchange' => 'FormValidation.loadLocations($(this).val())', 'id' => 'city_id', 'class' => 'form-control select2']) !!}
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-12">
                        <div class="form-group location_id">
                            {!! Form::select('location_id', array('' => 'Select a Centre'), null, ['id' => 'location_id', 'class' => 'form-control select2']) !!}
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-12">
                        <div class="form-group doctor_id">
                            {!! Form::select('doctor_id', array('' => 'Select a Doctor'), null, ['id' => 'doctor_id', 'class' => 'form-control select2']) !!}
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-12">
                        <div class="form-group machine_id">
                            {!! Form::select('machine_id', array('' => 'Select a Machine'), null, ['onchange' => 'FormValidation.machineListener($(this).val())', 'id' => 'machine_id', 'class' => 'form-control select2']) !!}
                        </div>
                    </div>
                </div>
            </div>
            <!-- END DRAGGABLE EVENTS PORTLET-->
            {!! Form::close() !!}

        </div>
    </div>
    <!-- END PAGE TITLE-->
@endsection

@section('stylesheets')
    <link href="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css'}}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-toastr/toastr.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.css" rel="stylesheet"
          type="text/css"/>
    {{--<link href="{{ url('fullcalendar/scheduler.min.css') }}" rel="stylesheet" type="text/css" />--}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar-scheduler/1.9.4/scheduler.min.css" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}"
          rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/pages/css/invoice.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('plugins/timepicker-css/timepicker-css.css') }}" rel="stylesheet" type="text/css"/>
    <style type="text/css">
        .portlet-body #form-validation {
            margin: 0;
        }

        .card-hide {
            background: #35a1d4;
        }

        .card-hide {
            position: absolute;
            top: 12px;
            color: #fff;
            border: 0;
            padding: 5px;
        }

        button.card-hide:focus {
            outline: none;
        }

        #event_box {
            overflow-y: auto;
            max-height: 489px;
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
@endsection

@section('content')
    <div class="portlet light portlet-fit bordered calendar" style="margin-bottom: 0;">
        <div class="portlet-body" id="calander_block" style="display: none;position: relative;">
            <button class="card-hide">Hide Card</button>
            <div class="calender-row">
                <div class="cards">
                    <h3 class="event-form-title margin-bottom-20" style="padding-top: 17px;font-weight: bold;">Un
                        Scheduled Appointments</h3>
                    <button class="btn btn-success" onclick="addAppointment()" style="margin-bottom: 20px">Create
                    </button>
                    <div id="event_box" class="margin-bottom-10"></div>
                </div>
                <div class="calendar-container">

                    <div id="calendar" class="has-toolbar consultancy-clndr"></div>
                </div>
            </div>
        </div>
    </div>
    <input id="backurl" type="hidden" value="{{url()->full()}}">
    <a id="edit_service" style="display: none" class="btn btn-xs btn-info" href="#"
       data-target="#ajax_appointments_detail" data-toggle="modal"><i class="fa fa-edit"></i></a>

    <div class="modal fade" id="ajax_appointments_detail" role="basic" aria-hidden="true"
         style=" left:10%; width: 80%; top:2%;">
        <div class="modal-content">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>

    <!--Edit View model Start-->
    <div class="modal fade" id="ajax_appointments_edit" role="basic" aria-hidden="true"
         style=" left:10%; width: 80%; top:2%;">
        <div class="modal-content">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>

    <!-- add treatmenet -->
    <a id="add_treatment" class="btn btn-xs btn-info" href="{{ route('admin.appointments.treatment.create') }}"
       style="display: none;" data-target="#ajax_appointments_create" data-toggle="modal">create</a>
    <!--Edit View model Start-->
    <div class="modal fade" id="ajax_appointments_create" role="basic" aria-hidden="true"
         style=" left:10%; width: 80%; top:2%;">
        <div class="modal-content">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <div class="modal fade" id="ajax_logs" role="basic" aria-hidden="true" style=" left:10%; width: 80%; top:2%;">
        <div class="modal-content">
            <div class="modal-body">
                <img src="{{ url('metronic/assets/global/img/loading-spinner-grey.gif') }}" alt="" class="loading">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <    <!--Treatment Invoice model Start-->
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
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}"
            type="text/javascript"></script>
    <script src="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'}}"
            type="text/javascript"></script>

    <script src='https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.22.2/moment.min.js'></script>
    {{--<script src='{{ url('fullcalendar/fullcalendar.min.js') }}'></script>--}}
    <script src='https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.js'></script>
    {{--<script src='{{ url('fullcalendar/scheduler.min.js') }}'></script>--}}
    <script src='https://cdnjs.cloudflare.com/ajax/libs/fullcalendar-scheduler/1.9.4/scheduler.min.js'></script>
    <script src="{{ url("metronic/assets/global/plugins/jquery-ui/jquery-ui.min.js") }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-inputmask/jquery.inputmask.bundle.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"
            type="text/javascript"></script>

    <script src="{{ url('js/admin/appointments/services/calendar.js') }}" type="text/javascript"></script>
    <script src="{{ url('js/admin/appointments/services/service_manage.js') }}" type="text/javascript"></script>
    <script src="{{ url('plugins/timepicker/jquery.timepicker.js') }}" type="text/javascript"></script>
    <script type="text/javascript">
        $(document).ready(function () {
            Utils.toggleUnsechuledAppoints();
            Utils.hideLeftSideNavigation();
        });
    </script>
@endsection