<div id="machinenotexist" class="alert alert-warning display-hide">
    <button class="close" data-close="alert"></button>
    Machine Type not exist for this centre, kindly select another centre.
</div>
<div class="row">
    <div class="form-group col-md-6">
        {!! Form::label('location_id', 'Centre*', ['class' => 'control-label']) !!}
        {!! Form::select('location_id',$locations, old('location_id'), ['class' => 'form-control select2', 'placeholder' => 'Select Centre', 'required' => '', 'id'=>'location_id']) !!}
        @if($errors->has('location_id'))
            <p class="help-block">
                {{ $errors->first('location_id') }}
            </p>
        @endif
    </div>
    <div class="form-group col-md-6">
        {!! Form::label('machine_type_id', 'Machine Type*', ['class' => 'control-label']) !!}
        <div id="machine_type_id_dropdown">
            {!! Form::select('machine_type_id',$machinetypes, old('machine_type_id'), ['id' => 'machine_type_id', 'class' => 'form-control select2', 'placeholder' => 'Select Machine Type', 'required' => '']) !!}
        </div>
        @if($errors->has('machine_type_id'))
            <p class="help-block">
                {{ $errors->first('machine_type_id') }}
            </p>
        @endif
    </div>
</div>
<div class="row">
    <div class="form-group col-md-6">
        {!! Form::label('name', 'Name*', ['class' => 'control-label']) !!}
        {!! Form::text('name', old('name'), ['class' => 'form-control inpt-focus', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('name'))
            <p class="help-block">
                {{ $errors->first('name') }}
            </p>
        @endif
    </div>
    <div class="form-group col-md-6">
        {!! Form::label('resource_type_id', 'Resource Type*', ['class' => 'control-label']) !!}
        {!! Form::select('resource_type_id',$resource_types, old('resource_type_id'), ['class' => 'form-control select2', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('resource_type_id'))
            <p class="help-block">
                {{ $errors->first('resource_type_id') }}
            </p>
        @endif
    </div>
</div>
<div class="clearfix"></div>
