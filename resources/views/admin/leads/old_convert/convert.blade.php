<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title">Convert</h4>
</div>
<div class="modal-body">
    <div class="form-group">
        {!! Form::open(['method' => 'POST', 'id' => 'convert-validation', 'route' => ['admin.appointments.store']]) !!}
        <div class="form-body">

        {!! Form::hidden('appointment_type', 'consulting', ['id' => 'appointment_type', 'type'=>'hidden']) !!}
        {!! Form::hidden('lead_id', $lead->id, ['id' => 'lead_id']) !!}
        {!! Form::hidden('patient_id', $lead->patient_id, ['id' => 'patient_id', 'type'=>'hidden']) !!}
        {!! Form::hidden('phone', $lead->patient->phone, ['id' => 'phone', 'type'=>'hidden']) !!}
        {!! Form::hidden('name', $lead->patient->name, ['id' => 'name', 'type'=>'hidden']) !!}
        {!! Form::hidden('cnic', $lead->patient->cnic, ['id' => 'cnic', 'type'=>'hidden']) !!}
        {!! Form::hidden('email', $lead->patient->email, ['id' => 'email', 'type'=>'hidden']) !!}
        {!! Form::hidden('dob', $lead->patient->dob, ['id' => 'dob', 'type'=>'hidden']) !!}
        {!! Form::hidden('address', $lead->patient->address, ['id' => 'address', 'type'=>'hidden']) !!}
        {!! Form::hidden('lead_source_id', $lead->lead_source_id, ['id' => 'lead_source_id', 'type'=>'hidden']) !!}
        {!! Form::hidden('referred_by', $user_info->referred_by, ['id' => 'referred_by', 'type'=>'hidden']) !!}
        {!! Form::hidden('service_id', $lead->service_id, ['id' => 'service_id', 'type'=>'hidden']) !!}

        <!-- Starts Form Validation Messages -->
            @include('partials.messages')

            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                    {!! Form::label('city_id', 'City*', ['class' => 'control-label']) !!}
                    {!! Form::select('city_id', $cities, null, ['onchange' => 'FormValidation.loadLocations($(this).val());', 'id' => 'city_id', 'class' => 'form-control select2']) !!}
                </div>
                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                    {!! Form::label('location_id', 'Location*', ['class' => 'control-label']) !!}
                    <div class="location_id">
                        {!! Form::select('location_id', array('' => 'Select a Location'), null, ['id' => 'location_id', 'class' => 'form-control select2']) !!}
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                    {!! Form::label('doctor_id', 'Doctor*', ['class' => 'control-label']) !!}
                    <div class="doctor_id">
                        {!! Form::select('doctor_id', array('' => 'Select a Doctor'), null, ['id' => 'doctor_id', 'class' => 'form-control select2']) !!}
                    </div>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                    {!! Form::label('service_id', 'Treatment*', ['class' => 'control-label']) !!}
                    {!! Form::select('service_id', $services, $lead->service_id, ['id' => 'service_id', 'class' => 'form-control select2']) !!}
                </div>
            </div>
            @if($setting->data == '1')
                <div class="row">
                    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                        {!! Form::label('consultancy_type', 'Consultancy Type*', ['class' => 'control-label']) !!}
                        {!! Form::select('consultancy_type', array('' => 'Select Consultancy Type') + Config::get("constants.consultancy_type_array"),null,[ 'class' => 'form-control select2', 'placeholder' => '', 'required' => '']) !!}
                        @if($errors->has('consultancy_type'))
                            <p class="help-block">
                                {{ $errors->first('consultancy_type') }}
                            </p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
        <div>
            {!! Form::submit(trans('global.app_save'), ['class' => 'btn btn-success']) !!}
        </div>
        {!! Form::close() !!}
    </div>
</div>
<script src="{{ url('js/admin/leads/convert.js') }}" type="text/javascript"></script>