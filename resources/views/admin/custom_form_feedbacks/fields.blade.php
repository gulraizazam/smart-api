<div class="form-group col-md-8">
    {!! Form::label('form_id', 'Select Form*', ['class' => 'control-label']) !!}
    {!! Form::select('form_id', array( '' => 'Choose an option', 1 => 'Yes', 0 => 'No') + $forms, old('is_featured'), ['class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
    @if($errors->has('is_featured'))
        <p class="help-block">
            {{ $errors->first('is_featured') }}
        </p>
    @endif
</div>
<div class="clearfix"></div>