{!! Form::hidden('patient_id', $lead->patient->id, ['id' => 'patient_id']) !!}
{!! Form::hidden('id', $lead->id, ['id' => 'lead_id']) !!}
<div class="row">
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('service_id', 'Services*', ['class' => 'control-label']) !!}
        <select class="form-control select2" id="service_id" name="service_id">
            <option value="">Select Service</option>
            @foreach($Services as $id => $Service)
                @if ($id == 0) @continue; @endif
                @if($id < 0)
                    @php($tmp_id = ($id * -1))
                @else
                    @php($tmp_id = ($id * 1))
                @endif
                <option @if($tmp_id==$leadServices) selected="selected"
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
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('phone', 'Phone*', ['class' => 'control-label']) !!}
        {!! Form::number('phone', (old('phone')) ? old('phone') : $lead->patient->phone, ['id' => 'phone', 'class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('phone'))
            <p class="help-block">
                {{ $errors->first('phone') }}
            </p>
        @endif
    </div>
</div>
<div class="row">
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('full_name', 'Full Name*', ['class' => 'control-label']) !!}
        {!! Form::text('name', (old('name')) ? old('name') : $lead->patient->name, ['id' => 'name', 'class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('full_name'))
            <p class="help-block">
                {{ $errors->first('full_name') }}
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
        {!! Form::label('gender', 'Gender*', ['class' => 'control-label']) !!}
        {!! Form::select('gender', array('' => 'Select a Gender') + Config::get("constants.gender_array"), (old('gender')) ? old('gender') : $lead->patient->gender, ['id' => 'gender', 'class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('gender'))
            <p class="help-block">
                {{ $errors->first('gender') }}
            </p>
        @endif
    </div>
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('city_id', 'City*', ['class' => 'control-label']) !!}
        {!! Form::select('city_id',$cities, old('city_id'), ['id' => 'city_id', 'class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
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
        {!! Form::label('lead_source_id', 'Lead Source*', ['class' => 'control-label']) !!}
        {!! Form::select('lead_source_id',$lead_sources, old('lead_source_id'), ['id' => 'lead_source_id', 'class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('lead_source_id'))
            <p class="help-block">
                {{ $errors->first('lead_source_id') }}
            </p>
        @endif
    </div>
    <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
        {!! Form::label('lead_status_id', 'Lead Status*', ['class' => 'control-label']) !!}
        {!! Form::select('lead_status_id',$lead_statuses, old('lead_status_id'), ['id' => 'lead_status_id', 'class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('lead_status_id'))
            <p class="help-block">
                {{ $errors->first('lead_status_id') }}
            </p>
        @endif
    </div>
</div>
<div class="clearfix"></div>