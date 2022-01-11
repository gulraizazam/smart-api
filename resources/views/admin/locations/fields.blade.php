<div class="row">
    <div class="col-md-6 form-group">
        {!! Form::label('name', 'Name*', ['class' => 'control-label']) !!}
        {!! Form::text('name', old('name'), ['class' => 'form-control inpt-focus', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('name'))
            <p class="help-block">
                {{ $errors->first('name') }}
            </p>
        @endif
    </div>
    <div class="col-md-6 form-group">
        {!! Form::label('fdo_name', 'FDO Name*', ['class' => 'control-label']) !!}
        {!! Form::text('fdo_name', old('fdo_name'), ['class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('fdo_name'))
            <p class="help-block">
                {{ $errors->first('fdo_name') }}
            </p>
        @endif
    </div>
</div>
<div class="row">
    <div class="col-md-6 form-group">
        {!! Form::label('fdo_phone', 'FDO Phone* (03XXXXXXXXX)', ['class' => 'control-label']) !!}
        {!! Form::number('fdo_phone', old('fdo_phone'), ['min' => 0, 'maxlength' => 11, 'size' => 4,'class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('fdo_phone'))
            <p class="help-block">
                {{ $errors->first('fdo_phone') }}
            </p>
        @endif
    </div>
    <div class="col-md-6 form-group">
        {!! Form::label('city_id', 'City*', ['class' => 'control-label']) !!}
        {!! Form::select('city_id',$cities, old('city_id'), ['class' => 'form-control select2', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('city_id'))
            <p class="help-block">
                {{ $errors->first('city_id') }}
            </p>
        @endif
    </div>
</div>
<div class="clearfix"></div>
<div class="row">
    <div class="col-xs-12 form-group">
        {!! Form::label('address', 'Address*', ['class' => 'control-label']) !!}
        {!! Form::text('address', old('address'), ['class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('address'))
            <p class="help-block">
                {{ $errors->first('address') }}
            </p>
        @endif
    </div>
</div>
<div class="row">
    <div class="col-md-6 form-group">
        {!! Form::label('google_map', 'Google Map*', ['class' => 'control-label']) !!}
        {!! Form::text('google_map', old('google_map'), ['class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('google_map'))
            <p class="help-block">
                {{ $errors->first('google_map') }}
            </p>
        @endif
    </div>
    <div class="col-md-6 form-group">
        {!! Form::label('services', 'Services*', ['class' => 'control-label']) !!}
        <select class="form-control select2" id="services" name="services[]" multiple style="width:100% !important;">
            @foreach($Services as $id => $Service)
                @if ($id == 0) @continue; @endif
                @if($id < 0)
                    @php($tmp_id = ($id * -1))
                @else
                    @php($tmp_id = ($id * 1))
                @endif
                <option @if(in_array($tmp_id, $ServiceLocations)) selected="selected"
                        @endif value="@if($id < 0){{ ($id * -1) }}@else{{ $id }}@endif">@if($id < 0)
                        <b>{!! $Service['name'] !!}</b>@else{!! $Service['name'] !!}@endif</option>
            @endforeach
        </select>
        @if($errors->has('services'))
            <p class="help-block">
                {{ $errors->first('services') }}
            </p>
        @endif
    </div>
</div>
<div class="row">
    <div class="col-md-6 form-group">
        {!! Form::label('tax_perentage', 'Tax Percentage*', ['class' => 'control-label']) !!}
        {!! Form::number('tax_percentage', old('tax_percentage'), ['class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('tax_percentage'))
            <p class="help-block">
                {{ $errors->first('tax_percentage') }}
            </p>
        @endif
    </div>
    <div class="col-md-6 form-group">
        {!! Form::label('ntn', 'NTN*', ['class' => 'control-label']) !!}
        {!! Form::text('ntn', old('ntn'), ['class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('ntn'))
            <p class="help-block">
                {{ $errors->first('ntn') }}
            </p>
        @endif
    </div>
</div>
<div class="row">
    <div class="col-md-6 form-group">
        {!! Form::label('stn', 'STN*', ['class' => 'control-label']) !!}
        {!! Form::text('stn', old('stn'), ['class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('stn'))
            <p class="help-block">
                {{ $errors->first('stn') }}
            </p>
        @endif
    </div>

    <div class="col-md-6 form-group">
        {!! Form::label('Select Logo', 'Select Logo', ['class' => 'control-label']) !!}
        <br>
        <div class="fileinput fileinput-new" data-provides="fileinput">
            <div class="fileinput-new thumbnail" style="width: 200px; height: 150px;">
                <img src="" alt=""/>
            </div>
            <div class="fileinput-preview fileinput-exists thumbnail"
                 style="max-width: 200px; max-height: 150px;"></div>
            <div>
                <span class="btn default btn-file">
                      <span class="fileinput-new"> Select image </span>
                      <span class="fileinput-exists"> Change </span>
                      <input type="file" name="file" id="file">
                  </span>
                <a href="javascript:;" class="btn default fileinput-exists" data-dismiss="fileinput">Remove</a>
            </div>
        </div>

    </div>
</div>
<div class="clearfix"></div>