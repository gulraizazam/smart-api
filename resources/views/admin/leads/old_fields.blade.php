{!! Form::hidden('patient_id', $lead->patient->id, ['id' => 'patient_id']) !!}
{!! Form::hidden('id', $lead->id, ['id' => 'lead_id']) !!}
{!! Form::hidden('old_phone',(old('phone')) ? old('phone') : $lead->patient->phone,['id' => 'old_phone']) !!}
<div class="row">
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('service_id', 'Services*', ['class' => 'control-label']) !!}
        {!! Form::select('service_id', $Services, old('service_id'), ['class' => 'form-control form-filter input-sm select2',]) !!}
        @if($errors->has('service_id'))
            <p class="help-block">
                {{ $errors->first('service_id') }}
            </p>
        @endif
    </div>
    @if($edit_status == 1)
        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
            {!! Form::label('patient_id', 'Patient Search', ['class' => 'control-label']) !!}
            <select name="patient_id_1" id="parent_id_1" class="patient_id form-control select2 parent_id_1" disabled></select>
        </div>
    @else
        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
            {!! Form::label('patient_id', 'Patient Search', ['class' => 'control-label']) !!}
            <select name="patient_id_1" id="parent_id_1" class="patient_id form-control select2 parent_id_1"></select>
        </div>
    @endif
</div>
<div class="row">
    <div class="col-sm-12 center-check">
        <label for="new_patient">
            <input id="new_patient" name="new_patient" type="checkbox" class="form-control" value="0">
            <span>New Patient</span>
        </label>
    </div>
</div>
<div class="row" id="mess_new_pati" style="display: none;">
    <div class="col-md-12">
        <h3 style="text-align:center; color: red;">You are going to create new patient</h3>
    </div>
</div>
<div class="row">
    @if($edit_status == 1)
        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group"><i onclick="phoneReset('phone')" class="fa fa-edit"></i>
            {!! Form::label('phone', 'Phone*', ['class' => 'control-label']) !!}
            {!! Form::text('phone', (old('phone')) ? old('phone') : \App\Helpers\GeneralFunctions::contactStatus($lead->patient->phone), ['readonly','id' => 'phone', 'class' => 'form-control', 'placeholder' => '', 'required' => '','onkeypress' => "return ((event.charCode > 47 && event.charCode < 58) || (event.charCode < 96 && event.charCode > 123))"]) !!}
            @if($errors->has('phone'))
                <p class="help-block">
                    {{ $errors->first('phone') }}
                </p>
            @endif
        </div>
    @else
        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
            {!! Form::label('phone', 'Phone*', ['class' => 'control-label']) !!}
            {!! Form::text('phone', (old('phone')) ? old('phone') : $lead->patient->phone, ['id' => 'phone', 'class' => 'form-control', 'placeholder' => '', 'required' => '','onkeypress' => "return ((event.charCode > 47 && event.charCode < 58) || (event.charCode < 96 && event.charCode > 123))"]) !!}
            @if($errors->has('phone'))
                <p class="help-block">
                    {{ $errors->first('phone') }}
                </p>
            @endif
        </div>
    @endif
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('full_name', 'Full Name*', ['class' => 'control-label']) !!}
        {!! Form::text('name', (old('name')) ? old('name') : $lead->patient->name, ['id' => 'name', 'class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('full_name'))
            <p class="help-block">
                {{ $errors->first('full_name') }}
            </p>
        @endif
    </div>
</div>
<div class="clearfix"></div>
<div class="row">
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('cnic', 'CNIC', ['class' => 'control-label']) !!}
        {!! Form::text('cnic', (old('cnic')) ? old('cnic') : $lead->patient->cnic, ['id' => 'cnic', 'class' => 'form-control', 'placeholder' => '']) !!}
        @if($errors->has('cnic'))
            <p class="help-block">
                {{ $errors->first('cnic') }}
            </p>
        @endif
    </div>
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('email', 'Email', ['class' => 'control-label']) !!}
        {!! Form::email('email', (old('email')) ? old('email') : $lead->patient->email, ['id' => 'email', 'class' => 'form-control', 'placeholder' => '']) !!}
        @if($errors->has('email'))
            <p class="help-block">
                {{ $errors->first('email') }}
            </p>
        @endif
    </div>
</div>
<div class="clearfix"></div>
<div class="row">
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('dob', 'Date of Birth', ['class' => 'control-label']) !!}
        {!! Form::text('dob', (old('dob')) ? old('dob') : $lead->patient->dob, ['readonly' => true, 'id' => 'dob', 'class' => 'form-control dob', 'placeholder' => '']) !!}
        @if($errors->has('dob'))
            <p class="help-block">
                {{ $errors->first('dob') }}
            </p>
        @endif
    </div>
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('address', 'Address', ['class' => 'control-label']) !!}
        {!! Form::text('address', (old('address')) ? old('address') : $lead->patient->address, ['id' => 'address', 'class' => 'form-control', 'placeholder' => '']) !!}
        @if($errors->has('address'))
            <p class="help-block">
                {{ $errors->first('address') }}
            </p>
        @endif
    </div>
</div>
<div class="clearfix"></div>
<div class="row">
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('gender', 'Gender', ['class' => 'control-label']) !!}
        {!! Form::select('gender', array('' => 'Select a Gender') + Config::get("constants.gender_array"), (old('gender')) ? old('gender') : $lead->patient->gender, ['id' => 'gender', 'class' => 'form-control select2', 'placeholder' => '','required' => '']) !!}
        @if($errors->has('gender'))
            <p class="help-block">
                {{ $errors->first('gender') }}
            </p>
        @endif
    </div>
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('city_id', 'City', ['class' => 'control-label']) !!}
        {!! Form::select('city_id',$cities, old('city_id'), ['id' => 'city_id', 'class' => 'form-control select2', 'placeholder' => '','required' => '']) !!}
        @if($errors->has('city_id'))
            <p class="help-block">
                {{ $errors->first('city_id') }}
            </p>
        @endif
    </div>
</div>
<div class="clearfix"></div>
<div class="row">
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('town_id', 'Town', ['class' => 'control-label']) !!}
        {!! Form::select('town_id',$towns, old('town_id'), ['id' => 'town_id', 'class' => 'form-control select2', 'placeholder' => '']) !!}
        @if($errors->has('town_id'))
            <p class="help-block">
                {{ $errors->first('town_id') }}
            </p>
        @endif
    </div>
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('lead_source_id', 'Lead Source', ['class' => 'control-label']) !!}
        {!! Form::select('lead_source_id',$lead_sources, old('lead_source_id'), ['id' => 'lead_source_id', 'class' => 'form-control select2', 'placeholder' => '']) !!}
        @if($errors->has('lead_source_id'))
            <p class="help-block">
                {{ $errors->first('lead_source_id') }}
            </p>
        @endif
    </div>
</div>
<div class="row">
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('lead_status_id', 'Lead Status', ['class' => 'control-label']) !!}
        {!! Form::select('lead_status_id',$lead_statuses, old('lead_status_id'), ['id' => 'lead_status_id', 'class' => 'form-control select2', 'placeholder' => '']) !!}
        @if($errors->has('lead_status_id'))
            <p class="help-block">
                {{ $errors->first('lead_status_id') }}
            </p>
        @endif
    </div>
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('referred_by', 'Referred By', ['class' => 'control-label']) !!}
        {!! Form::select('referred_by',$employees, (old('referred_by')) ? old('referred_by') : $lead->patient->referred_by, ['id' => 'referred_by', 'class' => 'form-control referred_by', 'placeholder' => '']) !!}
        @if($errors->has('referred_by'))
            <p class="help-block">
                {{ $errors->first('referred_by') }}
            </p>
        @endif
    </div>
</div>
<div class="clearfix"></div>

<script>
    $('#new_patient').change(function () {
        if ($(this).is(":checked")) {
            $('#new_patient').val('1');
            $('#mess_new_pati').show();
        } else {
            $('#new_patient').val('0');
            $('#mess_new_pati').hide();
        }
    });
</script>