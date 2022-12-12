@extends('layouts.app')

@section('stylesheets')
    <link href="{{ url('css/appstyle.css') }}" rel="stylesheet">
    <link href="{{ url('css/jquery.steps.css') }}" rel="stylesheet">
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-timepicker/css/bootstrap-timepicker.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datetimepicker/css/bootstrap-datetimepicker.min.css') }}" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css">
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
            {!! Form::model($appointment, ['method' => 'PUT', 'id' => 'appointmentform', 'route' => ['admin.appointments.update', $appointment->id]]) !!}
            @if($cities)
                <div>
                    <h3>Select City <span class="subtitle city_head"></span></h3>
                    @php( $array = ['lahore', 'karachi', 'islamabad', 'peshawar'])
                    @php( $counter = 0)
                    <section class="checkbox-module">
                        @foreach($cities as $citie)
                            <label>
                                <input type="radio" data-city_name="{{ $citie->name }}" name="city_id" @if($citie->id == $appointment->city_id) checked="true" @endif value="{{ $citie->id }}" id="city_id{{ $citie->id }}" class="required city_id"/>
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
                                                <input type="radio" data-location_name="{{ $location->name }}" name="location_id" @if($location->id == $appointment->location_id) checked="true" @endif id="location_id{{ $location->id }}" value="{{ $location->id }}" class="required location_id"/>
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
                                                        <input type="radio" data-doctor_name="{{ $doctor['name'] }}" name="doctor_id" @if($doctor['user_id'] == $appointment->doctor_id) checked="true" @endif id="doctor_id{{ $doctor['user_id'] }}" value="{{ $doctor['user_id'] }}" class="required doctor_id"/>
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
                        <div class="col-md-12">
                            <input type="hidden" name="lead_id" id="lead_id" value="{{ $appointment->lead_id }}" />
                            {!! Form::select('service_id', $services, $appointment->service_id, ['id' => 'service_id', 'class' => 'required']) !!}
                            <input id="mobile" readonly="true" name="phone" type="number" size="11" class="required custom-input" value="{{ $appointment->lead->patient->phone }}" placeholder="Patient Phone*">
                            <input id="name" name="name" type="text" class="required custom-input" value="{{ ($appointment->name) ? $appointment->name : $appointment->lead->patient->name }}" placeholder="Patient Name*">
                        </div>
                        <div class="col-md-12">
                            <input id="scheduled_date" readonly="true" name="scheduled_date" type="text" class="required custom-input" value="{{ $appointment->scheduled_date }}" placeholder="Schedule Date^">
                            <input id="scheduled_time" readonly="true" name="scheduled_time" type="text" class="required custom-input" value="{{ \Carbon\Carbon::parse($appointment->scheduled_time)->format('h:i A') }}" placeholder="Schedule Time">
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
    <!-- END PAGE LEVEL PLUGINS -->
    <script src="{{ url('js/jquery.steps.min.js') }}"></script>
    <script src="{{ url('js/jquery.validate.min.js') }}"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.js"></script>

    <script type="text/javascript">
        $(document).ready(function () {
            var date = new Date();
            date.setDate(date.getDate());
            $('#scheduled_date').datepicker({
                format: 'yyyy-mm-dd',
                startDate: date
            });
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
            });

            $('#city_id{{ $appointment->city_id }}').click();
            form.children("div").steps('next');
            $('#location_id{{ $appointment->location_id }}').click();
            form.children("div").steps('next');
            $('#doctor_id{{ $appointment->doctor_id }}').click();
            form.children("div").steps('next');
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
                form.submit();
            },

        });
    </script>
@stop

