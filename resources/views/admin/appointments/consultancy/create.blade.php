<style>
.custom_dropdown.form-group{
display:flex;
flex-wrap:wrap;
}
.custom_dropdown.form-group span.help-block, .custom_dropdown.form-group label{
width:100%;
}
.custom_dropdown.form-group span.help-block{
order:4;
}
</style>
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title">@lang('global.app_create')</h4>
</div>
<div class="modal-body">
    <div class="form-group">
        @if($appointment_checkes['status'])
            {!! Form::open(['method' => 'POST', 'id' => 'create-validation', 'route' => ['admin.appointments.store']]) !!}
            <div class="form-body">
            {!! Form::hidden('lead_id', (old('lead_id')) ? old('lead_id') : $lead['id'], ['id' => 'lead_id']) !!}
            {!! Form::hidden('patient_id', (old('patient_id')) ? old('patient_id') : $lead['patient_id'], ['id' => 'patient_id']) !!}
            {!! Form::hidden('city_id',$city_id, null, ['type'=>'hidden']) !!}
            {!! Form::hidden('location_id', $location_id, null, ['type'=>'hidden']) !!}
            {!! Form::hidden('doctor_id', $doctor_id, null, ['type'=>'hidden']) !!}
            {!! Form::hidden('start', request()->get("start"), null, ['type'=>'hidden']) !!}
            {!! Form::hidden('resource_id', request()->get("resource_id"), null, ['type'=>'hidden']) !!}
            {!! Form::hidden('appointment_type', request()->get("appointment_type"), null, ['type'=>'hidden']) !!}
            {!! Form::hidden('cnic',  "", ['type'=>'hidden']) !!}
            {!! Form::hidden('email',  "", ['type'=>'hidden']) !!}
            {!! Form::hidden('dob',  "", ['type'=>'hidden']) !!}
            {!! Form::hidden('address',  "", ['type'=>'hidden']) !!}
            {!! Form::hidden('town_id',  "", ['type'=>'hidden']) !!}
            <!-- Starts Form Validation Messages -->
                @include('partials.messages')
                @if($setting->data == '1')
                    <div class="row">
                        <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 form-group">
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
                <div class="row">
                    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group custom_dropdown">
                        {!! Form::label('service_id', 'Consultancy*', ['class' => 'control-label']) !!}
                        {!! Form::select('service_id', $services, null, ['id' => 'service_id', 'class' => 'form-control select2']) !!}
                        @if($errors->has('service_id'))
                            <p class="help-block">
                                {{ $errors->first('service_id') }}
                            </p>
                        @endif
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                        {!! Form::label('patient_id', 'Patient Search', ['class' => 'control-label']) !!}
                        <select name="patient_id_1" id="parent_id_1" class="patient_id form-control select2 parent_id_1"></select>
                    </div>
                </div>
                <!--div class="row">
                    <div class="col-sm-12 center-check">
                        <label for="new_patient">
                            <input id="new_patient" name="new_patient" type="checkbox" class="form-control" value="0">
                            <span>New Patient</span>
                        </label>
                    </div>
                </div-->
                <div class="row" id="mess_new_pati" style="display: none;">
                    <div class="col-md-12">
                        <h3 style="text-align:center; color: red;">You are going to create new patient</h3>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                        {!! Form::label('phone', 'Phone*', ['class' => 'control-label']) !!}
                        <div class="phone">
                            {!! Form::number('phone', null, ['id' => 'phone', 'readonly' => 'readonly', 'size' => 11, 'class' => 'form-control', 'placeholder' => 'Patient Phone*']) !!}
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                        {!! Form::label('name', 'Patient Name*', ['class' => 'control-label']) !!}
                        <div class="name">
                            {!! Form::text('name',null, ['id' => 'name', 'readonly' => 'readonly', 'class' => 'form-control treatment_patient_name', 'placeholder' => 'Patient Name*']) !!}
                        </div>
                    </div>
                </div>
                <!--div class="row">
                    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                        {!! Form::label('cnic', 'Patient CNIC', ['class' => 'control-label']) !!}
                        <div class="cnic">
                            {!! Form::text('cnic',null, ['id' => 'cnic', 'class' => 'form-control', 'placeholder' => 'Patient Name*']) !!}
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                        {!! Form::label('email', 'Patient Email', ['class' => 'control-label']) !!}
                        <div class="name">
                            {!! Form::email('email', null, ['id' => 'email', 'placeholder' => 'Patient Email', 'class' => 'form-control']) !!}
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                        {!! Form::label('dob', 'Date of Birth', ['class' => 'control-label']) !!}
                        <div class="name">
                            {!! Form::text('dob',null, ['id' => 'dob', 'readonly' => true, 'class' => 'form-control dob']) !!}
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                        {!! Form::label('address', 'Address', ['class' => 'control-label']) !!}
                        <div class="address">
                            {!! Form::text('address', null, ['id' => 'address', 'placeholder' => 'Patient Address', 'class' => 'form-control']) !!}
                        </div>
                    </div>
                </div-->
                <div class="row">
                    <!--div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                        {!! Form::label('town_id', 'Town', ['class' => 'control-label']) !!}
                        {!! Form::select('town_id',$towns, null, ['id' => 'town_id', 'class' => 'form-control select2 town_id']) !!}
                        @if($errors->has('town_id'))
                            <p class="help-block">
                                {{ $errors->first('town_id') }}
                            </p>
                        @endif
                    </div-->
                    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                        {!! Form::label('gender', 'Gender', ['class' => 'control-label']) !!}
                        {!! Form::select('gender', array('' => 'Select a Gender') + Config::get("constants.gender_array"),null, ['id' => 'gender', 'disabled' => 'disabled',  'class' => 'form-control treatment_patient_gender','required' => '']) !!}
                        @if($errors->has('gender'))
                            <p class="help-block">
                                {{ $errors->first('gender') }}
                            </p>
                        @endif
                    </div>
                </div>
                <div class="row">

                    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                        {!! Form::label('referred_by', 'Referred By (Patient Search)', ['class' => 'control-label']) !!}
                        <div class="referred_by">
                            <select name="referred_by" id="referred_by" class="form-control select2-patient-search"></select>
                        </div>
                    </div>

                    <div class="col-sm-6 center-check" style="margin-top: 27px;">
                       <label for="new_patient">
                            <input id="new_patient" name="new_patient" type="checkbox" class="form-control" value="0">
                            <span>I would like to <b>Create New Patient</b></span>
                        </label>
                    </div>
                </div>
            </div>
            <div>
                {!! Form::submit(trans('global.app_save'), ['class' => 'btn btn-success']) !!}
            </div>
            {!! Form::close() !!}
        @else
            <h3>{{$appointment_checkes['message']}}</h3>
        @endif
    </div>
</div>
<script src="{{ url('js/admin/appointments/consultancy/create.js') }}" type="text/javascript"></script>
<script src="{{ url('js/admin/users/ajaxbaseselect2.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/js/pages/appointments/referred-by-patient-search.js') }}"></script>
<script>
    $('#new_patient').change(function () {
        if ($(this).is(":checked")) {
            $('#new_patient').val('1');
            $('#mess_new_pati').show();
            $('#name').attr("readonly",false);
            $('#phone').attr("readonly",false);
            $('#gender').attr("disabled",false);
            $('#name').val("");
            $('#phone').val("");
            $('#gender').val("");
            // Clear lead_id and patient_id when creating new patient
            $('#lead_id').val("");
            $('#patient_id').val("");
        } else {
            $('#new_patient').val('0');
            $('#mess_new_pati').hide();
            $('#name').attr("readonly",true);
            $('#phone').attr("readonly",true);
            $('#gender').attr("disabled",true);
        }
    });
</script>
