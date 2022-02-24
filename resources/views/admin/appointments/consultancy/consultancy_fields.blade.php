<div id="rotaError" class="custom_alert">
    <button class="custom_alert_close"></button>
    Doctor Rota not defined.
</div>
<input type="hidden" id="appointment_manager" value="{{Config::get('constants.appointment_type_consultancy_string')}}">
<input type="hidden" id="back-date" value="{{ $back_date_config->data }}">
<input type="hidden" id="old_phone" name="old_phone" value="{{$appointment->lead->patient->phone}}">
@if($setting->data == '1')
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 form-group">
            {!! Form::label('consultancy_type', 'Consultancy Type*', ['class' => 'control-label']) !!}
            {!! Form::select('consultancy_type', array('' => 'Select Consultancy Type') + Config::get("constants.consultancy_type_array"),$appointment->consultancy_type,['id' => 'consultancy_type_select', 'class' => 'form-control ']) !!}
        </div>
    </div>
@endif
<div class="row">
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('service_id', 'Treatment*', ['class' => 'control-label']) !!}
        {!! Form::select('service_id', $services, $appointment->service_id, ['id' => 'consultancty_service_id', 'disabled'=>'disabled', 'class' => 'form-control ']) !!}
    </div>
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('consultancty_city_id', 'City*', ['class' => 'control-label']) !!}
        {!! Form::select('city_id', $cities, $appointment->city_id, ['onchange' => 'EditFormValidation.loadLocations($(this).val());', 'id' => 'consultancty_city_id', 'class' => 'form-control ']) !!}
    </div>
</div>
<div class="row">
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('consultancty_location_id', 'Location*', ['class' => 'control-label']) !!}
        <div class="consultancty_location_id">
            {!! Form::select('location_id', $locations, $appointment->location_id, ['onchange' => 'EditFormValidation.loadDoctors($(this).val());', 'id' => 'consultancty_location_id', 'class' => 'form-control ']) !!}
        </div>
    </div>
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('consultancty_doctor_id', 'Doctor*', ['class' => 'control-label']) !!}
        <div class="consultancty_doctor_id">
            {!! Form::select('doctor_id', $doctors, $appointment->doctor_id, ['onchange' => 'EditFormValidation.doctorListener($(this).val());', 'id' => 'consultancty_doctor_id', 'class' => 'form-control ']) !!}
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('scheduled_date', 'Scheduled Date*', ['class' => 'control-label']) !!}
        <div class="scheduled_date">
            <input id="scheduled_date" readonly="true" name="scheduled_date" class="form-control" type="text" class="required" value="{{ ($appointment->scheduled_date) ? $appointment->scheduled_date : '' }}" placeholder="Schedule Date^">
        </div>
        <input type="hidden" id="scheduled_date_old" value="{{ ($appointment->scheduled_date) ? $appointment->scheduled_date : '' }}" />
    </div>

    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('scheduled_time', 'Scheduled Time*', ['class' => 'control-label']) !!}
        <div class="scheduled_time">
            <input id="scheduled_time" name="scheduled_time" class="form-control" type="text" class="required" value="{{ ($appointment->scheduled_time) ? \Carbon\Carbon::parse($appointment->scheduled_time)->format('h:i A') : '' }}" placeholder="Schedule Date^">
        </div>
        <input type="hidden" id="scheduled_time_old" value="{{ ($appointment->scheduled_time) ? \Carbon\Carbon::parse($appointment->scheduled_time)->format('h:i A') : '' }}" />
    </div>
</div>
<div class="row">
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group"><i onclick="phoneReset('mobile')" class="fa fa-edit"></i>
        {!! Form::label('phone', 'Patient Phone*', ['class' => 'control-label']) !!}
        <input id="mobile" readonly="true" name="phone" type="text" size="11" class="form-control" value="{{ \App\Helpers\GeneralFunctions::contactStatus($appointment->lead->patient->phone) }}">
    </div>
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('service_id', 'Patient Name*', ['class' => 'control-label']) !!}
        <input id="name" name="name" type="text" class="form-control inpt-focus" value="{{ ($appointment->name) ? $appointment->name : $appointment->lead->patient->name }}">
    </div>
</div>
<div class="row">
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('gender', 'Gender', ['class' => 'control-label']) !!}
        {!! Form::select('gender', array('' => 'Select a Gender') + Config::get("constants.gender_array"),$appointment->lead->patient->gender, ['id' => 'gender', 'class' => 'form-control', 'placeholder' => '','required' => '']) !!}
        @if($errors->has('gender'))
            <p class="help-block">
                {{ $errors->first('gender') }}
            </p>
        @endif
    </div>
</div>
<input type="hidden" name="lead_id" id="lead_id" value="{{ $appointment->lead_id }}" />
<input type="hidden" id="appointment_id" value="{{ $appointment->id }}" />
<input type="hidden" id="resourceRotaDayID" value="{{ $resourceHadRotaDay->id }}" />
<input type="hidden" id="start_time" value="{{ \Carbon\Carbon::parse($resourceHadRotaDay->start_time)->format('h:ia') }}" />
<input type="hidden" id="end_time" value="{{ \Carbon\Carbon::parse($resourceHadRotaDay->end_time)->format('h:ia') }}" />