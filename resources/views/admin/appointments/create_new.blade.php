@extends('layouts.app')

@section('stylesheets')
    <link href="{{ url('css/appstyle.css') }}" rel="stylesheet">
    <link href="{{ url('css/jquery.steps.css') }}" rel="stylesheet">
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-timepicker/css/bootstrap-timepicker.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datetimepicker/css/bootstrap-datetimepicker.min.css') }}" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css">
    <link href="{{ url('metronic/assets/global/plugins/fullcalendar/fullcalendar.min.css') }}" rel="stylesheet" type="text/css" />
    {{--<link href="{{ url('metronic/assets/global/plugins/fullcalendar/scheduler.min.css') }}" rel="stylesheet" type="text/css" />--}}
@endsection


@section('content')
    <section class="content-header">
        <h1 class="page-title col-md-10">@lang('global.appointments.title')</h1>
        <p class="col-md-2">
            <a href="{{ route('admin.appointments.index') }}" class="btn btn-success pull-right">@lang('global.app_back')</a>
        </p>
    </section>

    <div class="clearfix"></div>

    <div class="row">
        <div class="col-md-12 appoint-form">
            {!! Form::open(['method' => 'POST', 'id' => 'appointmentform', 'route' => ['admin..store']]) !!}
            @if($cities)
                <div>
                    <h3>Select City <span class="subtitle city_head"></span></h3>
                    @php( $array = ['lahore', 'karachi', 'islamabad', 'peshawar'])
                    @php( $counter = 0)
                    <section class="checkbox-module">
                        @foreach($cities as $citie)
                            <label>
                                <input type="radio" data-city_name="{{ $citie->name }}" name="city_id" value="{{ $citie->id }}" class="required city_id"/>
                                <div class="@if(in_array(strtolower($citie->name), $array)){{ strtolower($citie->name) }}@else{{ 'lahore' }}@endif box">
                                    <span>{{ $citie->name }}</span>
                                </div>
                            </label>
                            @php($counter++)
                        @endforeach
                    </section>
                    <h3>Select Center <span class="subtitle location_head"></span></h3>
                    <section class="checkbox-module dcenters-moduls">
                        @php( $acl_centres = \App\Helpers\ACL::getUserCentres())
                        @foreach($cities as $citie)
                            <div id="city_location{{ $citie->id }}" class="locations_outer" style="display: none;">
                                @if(count($citie->locationsActive))
                                    @foreach($citie->locationsActive as $location)
                                        @if(in_array($location->id, $acl_centres))
                                            <label>
                                                <input type="radio" data-location_name="{{ $location->name }}" name="location_id" value="{{ $location->id }}" class="required location_id"/>
                                                <div class="dcenter box">
                                                    <span>{{ $location->name }}</span>
                                                </div>
                                            </label>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        @endforeach
                    </section>
                    <h3>Select Doctor <span class="subtitle doctor_head"></span></h3>
                    <section class="checkbox-module doctors-moduls">
                        @foreach($cities as $citie)
                            @if(count($citie->locationsActive))
                                @foreach($citie->locationsActive as $location)
                                    @if(array_key_exists($location->id, $doctors) && count($doctors[$location->id]))
                                        <div id="location_doctor{{ $location->id }}" class="doctors_outer" style="display: none;">
                                            @foreach($doctors[$location->id] as $doctor)
                                                <label>
                                                    <input type="radio" data-doctor_name="{{ $doctor['name'] }}" name="doctor_id" value="{{ $doctor['user_id'] }}" class="required doctor_id"/>
                                                    <div class="tdoctor box">
                                                        <span>{{ $doctor['name'] }}</span>
                                                    </div>
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif
                                @endforeach
                            @endif
                        @endforeach
                    </section>
                    <h3>Appointment Detail <span class="subtitle appointment_head"></span></h3>
                    <section class="checkbox-module-step4">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="portlet light portlet-fit bordered calendar">
                                    <div class="portlet-title">
                                        <div class="caption">
                                            <i class=" icon-layers font-green"></i>
                                            <span class="caption-subject font-green sbold uppercase">Calendar</span>
                                        </div>
                                    </div>
                                    <div class="portlet-body">
                                        <div class="row">
                                            <!-- Starts Form Validation Messages -->
                                            @include('partials.messages')
                                            <!-- Ends Form Validation Messages -->
                                            <div class="col-md-3 col-sm-12">
                                                <!-- BEGIN DRAGGABLE EVENTS PORTLET-->
                                                <h3 class="event-form-title margin-bottom-20">Draggable Events</h3>
                                                <div id="external-events">
                                                    <form class="inline-form">
                                                        {!! Form::hidden('lead_id', (old('lead_id')) ? old('lead_id') : $lead['id'], ['id' => 'lead_id']) !!}
                                                        {!! Form::hidden('patient_id', (old('patient_id')) ? old('patient_id') : $lead['patient_id'], ['id' => 'patient_id']) !!}
                                                        {!! Form::select('service_id', $services, $lead['service_id'], ['id' => 'service_id', 'class' => 'form-control']) !!}
                                                        {!! Form::number('phone', (old('phone')) ? old('phone') : $lead['phone'], ['id' => 'phone', 'size' => 11, 'class' => 'form-control', 'placeholder' => 'Patient Phone*']) !!}
                                                        {!! Form::text('name', (old('name')) ? old('name') : $lead['name'], ['id' => 'name', 'class' => 'form-control', 'placeholder' => 'Patient Name*']) !!}
                                                        {!! Form::email('email', null, ['id' => 'email', 'style' => 'display:none;', 'placeholder' => 'Patient Email', 'class' => 'form-control']) !!}
                                                        {!! Form::select('lead_source_id',$lead_sources, null, ['id' => 'lead_source_id', 'style' => 'display:none;', 'class' => 'form-control']) !!}
                                                        <br/>
                                                        <a href="javascript:;" id="event_add" class="btn green"> Add Appointment </a>
                                                    </form>
                                                    <hr/>
                                                    <div id="event_box" class="margin-bottom-10"></div>
                                                    <label class="mt-checkbox mt-checkbox-single mt-checkbox-outline" for="drop-remove"> remove after drop
                                                        <input type="checkbox" class="group-checkable" id="drop-remove" />
                                                        <span></span>
                                                    </label>
                                                    <hr class="visible-xs" /> </div>
                                                <!-- END DRAGGABLE EVENTS PORTLET-->
                                            </div>
                                            <div class="col-md-9 col-sm-12">
                                                <div id="calendar" class="has-toolbar"> </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            @endif
            {!! Form::close() !!}
        </div>
    </div>
@stop

@section('javascript')
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/moment.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-timepicker/js/bootstrap-timepicker.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-datetimepicker/js/bootstrap-datetimepicker.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/fullcalendar/fullcalendar.min.js') }}" type="text/javascript"></script>
    {{--<script src="{{ url('metronic/assets/global/plugins/fullcalendar/scheduler.min.js') }}" type="text/javascript"></script>--}}
    <script src="{{ url('metronic/assets/global/plugins/jquery-ui/jquery-ui.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('js/admin/appointments/calendar.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
    <script src="{{ url('js/jquery.steps.min.js') }}"></script>
    <script src="{{ url('js/jquery.validate.min.js') }}"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.js"></script>

    <script type="text/javascript">
        function loadLead() {
            var phone = $('#phone').val();
            var lead_id = $('#lead_id').val();
            var service_id = $('#service_id').val();

            var flag = true;

            if(!phone) {
                $('#phone').valid();
                flag = false;
            }
            if(!service_id) {
                $('#service_id').valid();
                flag = false;
            }

            if(!flag) {
                return false;
            } else {
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    type: "POST",
                    url: route('admin.appointments.load_lead'),
                    data: {
                        phone: phone,
                        service_id: service_id,
                        lead_id: lead_id,
                    },
                    success: function(response){
                        $('#email').show();
                        $('#lead_source_id').show().attr('class','required form-control');
                        $('#phone').val(response.phone);
                        $('#email').val(response.email);
                        $('#name').val(response.name);
                        $('#service_id').val(response.service_id);
                        $('#lead_source_id').val(response.lead_source_id);
                        $('#lead_id').val(response.lead_id);
                        $('#patient_id').val(response.patient_id);
                    }
                });
            }
        }
        $(document).ready(function () {
            $('#phone').blur(function () {
                loadLead();
            });
            $('#service_id').change(function () {
                loadLead();
            });

            var date = new Date();
            date.setDate(date.getDate());
            $('#scheduled_date').datepicker({
                format: 'yyyy-mm-dd',
                startDate: date
            }).on('changeDate', function(ev){
                $(this).datepicker('hide');
            })
            $('#scheduled_time').timepicker({
                timeFormat: 'h:mm p',
                interval: 15,
                minTime: '09:00am',
                maxTime: '10:30pm',
                dynamic: false,
                dropdown: true,
                scrollbar: true
            });

            // City Operations
            $('.city_id').click(function () {
                var city_id = $(this).val();
                $('.city_head').html($(this).data('city_name'));
                setLocation(city_id);
                form.children("div").steps('next');
            });

            // Location Operations
            $('.location_id').click(function () {
                var location_id = $(this).val();
                $('.location_head').html($(this).data('location_name'));
                setDoctors(location_id);
                form.children("div").steps('next');
            });

            // Location Operations
            $('.doctor_id').click(function () {
                var doctor_id = $(this).val();
                $('.doctor_head').html($(this).data('doctor_name'));
                form.children("div").steps('next');
                $('.appointment_head').html('Final Step');

                AppCalendar.init();
            });
        });

        function setLocation(cityId) {
            $('.doctor_id').removeAttrs('checked');
            $('.doctors_outer').hide();
            $('.location_id').removeAttrs('checked');
            $('.locations_outer').hide();
            $('#city_location'+cityId).show();
        }

        function setDoctors(cityId) {
            $('.doctor_id').removeAttrs('checked');
            $('.doctors_outer').hide();
            $('#location_doctor'+cityId).show();
        }
    </script>

    <script type="text/javascript">

        var form = $("#appointmentform");
        var r = $(".alert-danger"), i = $(".alert-success");
        form.validate({
            errorPlacement: function errorPlacement(error, element) { element.before(error); },
            rules: {
                confirm: {
                    equalTo: "#password"
                }
            }
        });
        form.children("div").steps({
            headerTag: "h3",
            bodyTag: "section",
            transitionEffect: "slideLeft",
            onStepChanging: function (event, currentIndex, newIndex) {
                // Allways allow step back to the previous step even if the current step is not valid!
                if (currentIndex > newIndex)
                {
                    return true;
                } else {
                    form.validate().settings.ignore = ":disabled,:hidden";
                    return form.valid();
                }
            },
            onFinishing: function (event, currentIndex) {
                form.validate().settings.ignore = ":disabled";
                return form.valid();
            },
            onFinished: function (event, currentIndex) {
                window.location = route('admin.appointments.index');
                // form.submit();
            },
        });

        function x(action, method, data, callback) {

            i.show(), r.hide();
            // $("input[type=submit]",form).attr('disabled', true);
            x(form.attr('action'),form.attr('method'), form.serialize(), function (response) {
                if(response.status == '1') {
                    r.hide();
                    i.html(response.message);
                    window.location = route('admin.appointments.index');
                } else {
                    console.log('Here I am');
                    // $("input[type=submit]",form).removeAttr('disabled');
                    i.hide();
                    r.html(response.message);
                    r.show();
                }
            });

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: action,
                type: method,
                data: data,
                cache: false,
                success: function(response) {
                    if(response.status == '1') {
                        callback({
                            'status': response.status,
                            'message': response.message,
                        });
                    } else {
                        callback({
                            'status': response.status,
                            'message': response.message.join('<br/>'),
                        });
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    if(xhr.status == '401') {
                        callback({
                            'status': 0,
                            'message': 'You are not authorized to access this resouce',
                        });
                    } else {
                        callback({
                            'status': 0,
                            'message': 'Unable to process your request, please try again later.',
                        });
                    }
                }
            });
        }
    </script>
@stop

