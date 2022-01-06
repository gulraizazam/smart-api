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
        {!! Form::label('city_id', 'City*', ['class' => 'control-label']) !!}
        {!! Form::select('city_id', $cities, old('city_id'), ['class' => 'form-control city select2', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('city_id'))
            <p class="help-block">
                {{ $errors->first('city_id') }}
            </p>
        @endif
    </div>
</div>
<div class="clearfix"></div>