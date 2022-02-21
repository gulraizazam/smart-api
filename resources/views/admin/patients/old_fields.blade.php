@if(isset($patient))
{!! Form::hidden('old_phone',(old('phone')) ? old('phone') : $patient->phone) !!}
@endif
<div class="row">
    <div class="form-group col-md-6">
        {!! Form::label('name', 'Name*', ['class' => 'control-label']) !!}
        {!! Form::text('name', old('name'), ['class' => 'form-control inpt-focus', 'placeholder' => '', 'required' => '']) !!}
        <p class="help-block"></p>
        @if($errors->has('name'))
            <p class="help-block">
                {{ $errors->first('name') }}
            </p>
        @endif
    </div>
    <div class="form-group col-md-6">
        {!! Form::label('email', 'Email', ['class' => 'control-label']) !!}
        {!! Form::email('email', old('email'), ['class' => 'form-control', 'placeholder' => '']) !!}
        <p class="help-block"></p>
        @if($errors->has('email'))
            <p class="help-block">
                {{ $errors->first('email') }}
            </p>
        @endif
    </div>
</div>
<div class="row">
    @if(isset($patient))
    <div class="form-group col-md-6 multiselect"><i onclick="phoneReset('phone')" class="fa fa-edit"></i>
        {!! Form::label('phone', 'Phone*', ['class' => 'control-label']) !!}
        {!! Form::text('phone', (old('phone')) ? old('phone') : \App\Helpers\GeneralFunctions::contactStatus($patient->phone), ['readonly','id' => 'phone','class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('phone'))
            <p class="help-block">
                {{ $errors->first('phone') }}
            </p>
        @endif
    </div>
    @else
    <div class="form-group col-md-6 multiselect">
        {!! Form::label('phone', 'Phone*', ['class' => 'control-label']) !!}
        {!! Form::text('phone', old('phone'), ['id' => 'phone','class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('phone'))
            <p class="help-block">
                {{ $errors->first('phone') }}
            </p>
        @endif
    </div>
    @endif
    <div class="form-group col-md-6 multiselect">
        {!! Form::label('gender', 'Gender*', ['class' => 'control-label']) !!}
        {!! Form::select('gender', array('' => 'Select a Gender') + Config::get("constants.gender_array"), old('gender'), [ 'class' => 'form-control select2', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('gender'))
            <p class="help-block">
                {{ $errors->first('gender') }}
            </p>
        @endif
    </div>
</div>