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
            {!! Form::open(['method' => 'POST', 'id' => 'appointmentform', 'route' => ['admin.appointments.store']]) !!}
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
                                        @if(count($location->doctorsActive))
                                            <div id="location_doctor{{ $location->id }}" class="doctors_outer" style="display: none;">
                                                @foreach($location->doctorsActive as $doctor)
                                                    @if(in_array($doctor->location_id, $acl_centres))
                                                        <label>
                                                            <input type="radio" data-doctor_name="{{ $doctor->name }}" name="doctor_id" value="{{ $doctor->id }}" class="required doctor_id"/>
                                                            <div class="tdoctor box">
                                                                <span>{{ $doctor->name }}</span>
                                                            </div>
                                                        </label>
                                                    @endif
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
                                {!! Form::hidden('lead_id', (old('lead_id')) ? old('lead_id') : $lead['id'], ['id' => 'lead_id']) !!}
                                {!! Form::hidden('patient_id', (old('patient_id')) ? old('patient_id') : $lead['patient_id'], ['id' => 'patient_id']) !!}
                                {!! Form::select('treatment_id', $treatments, $lead['treatment_id'], ['id' => 'treatment_id', 'class' => 'required']) !!}
                                {!! Form::number('phone', (old('phone')) ? old('phone') : $lead['phone'], ['id' => 'phone', 'size' => 11, 'class' => 'required custom-input', 'placeholder' => 'Patient Phone*']) !!}
                                {!! Form::text('full_name', (old('full_name')) ? old('full_name') : $lead['full_name'], ['id' => 'full_name', 'class' => 'required custom-input', 'placeholder' => 'Patient Name*']) !!}
                                {!! Form::email('email', null, ['id' => 'email', 'style' => 'display:none;', 'placeholder' => 'Patient Email', 'class' => 'custom-input']) !!}
                                {!! Form::select('lead_source_id',$lead_sources, null, ['id' => 'lead_source_id', 'style' => 'display:none;', 'class' => 'custom-input']) !!}
                            </div>
                            <div class="col-md-12">
                                {!! Form::text('scheduled_date', \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d'), ['readonly' => 'true', 'id' => 'scheduled_date', 'class' => 'required', 'placeholder' => 'Scheduled Date*']) !!}
                                {!! Form::text('scheduled_time', null, ['readonly' => 'true', 'id' => 'scheduled_time', 'class' => 'required', 'placeholder' => 'Scheduled Time*']) !!}
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
        function loadLead() {
            var phone = $('#phone').val();
            var lead_id = $('#lead_id').val();
            var treatment_id = $('#treatment_id').val();

            var flag = true;

            if(!phone) {
                $('#phone').valid();
                flag = false;
            }
            if(!treatment_id) {
                $('#treatment_id').valid();
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
                        treatment_id: treatment_id,
                        lead_id: lead_id,
                    },
                    success: function(response){
                        $('#email').show();
                        $('#lead_source_id').show().attr('class','required');
                        $('#phone').val(response.phone);
                        $('#email').val(response.email);
                        $('#full_name').val(response.full_name);
                        $('#treatment_id').val(response.treatment_id);
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
            $('#treatment_id').change(function () {
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

