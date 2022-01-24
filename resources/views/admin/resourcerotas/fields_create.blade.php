@section('stylesheet')
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css')}}"
          rel="stylesheet" type="text/css"/>
@stop
<div class="row">
    <div class="form-group col-md-4">
        <h4 style="font-size: 14px;"><strong>Place</strong></h4>
    </div>
    <div class="form-group col-md-4">
        {!! Form::label('city_id', 'Select City*', ['class' => 'control-label','style'=>'font-size:14px; font-weight: bold;']) !!}
        {!! Form::select('city_id', $cities, null, ['id' => 'city_id_create', 'class' => 'form-control select2']) !!}
        <p class="help-block"></p>
        @if($errors->has('city_id'))
            <p class="help-block">
                {{ $errors->first('city_id') }}
            </p>
        @endif
    </div>
    <div class="form-group col-md-4">
        {!! Form::label('location_id', 'Select Centre*', ['class' => 'control-label','style'=>'font-size:14px; font-weight: bold;']) !!}
        {!! Form::select('location_id', array('' => 'Select a Centre'), null, ['id' => 'location_id_create', 'class' => 'form-control select2']) !!}
        <p class="help-block"></p>
        @if($errors->has('location_id'))
            <p class="help-block">
                {{ $errors->first('location_id') }}
            </p>
        @endif
    </div>
</div>
<div class="row">
    <div class="form-group col-md-4">
        <h4 style="font-size: 14px;"><strong>Type</strong></h4>
    </div>
    <div class="form-group col-md-4">
        {!! Form::label('resource_type_id', 'Select Resource Type*', ['class' => 'control-label','style'=>'font-size:14px; font-weight: bold;']) !!}
        <select name="resource_type_id" id="resource_type_id" class="form-control inpt-focus select2">
            <option value="">Select Resource Type</option>
            @foreach($resourcetype as $type)
                <option value="{{$type->name}}">{{$type->name}}</option>
            @endforeach
        </select>
        <p class="help-block"></p>
        @if($errors->has('resource_type_id'))
            <p class="help-block">
                {{ $errors->first('resource_type_id') }}
            </p>
        @endif
    </div>
    <div class="form-group col-md-4" style="display: none;" id="SelectDoctor_create">
        {!! Form::label('resource_doctor', 'Select Doctor*', ['class' => 'control-label','style'=>'font-size:14px; font-weight: bold;']) !!}
        {!! Form::select('resource_doctor',array('' => 'Select a Doctor'),null, ['id'=>'resource_doctor_create','class' => 'form-control select2']) !!}
        <p class="help-block"></p>
        @if($errors->has('resource_doctor'))
            <p class="help-block">
                {{ $errors->first('resource_doctor') }}
            </p>
        @endif
    </div>
    <div class="form-group col-md-4" style="display: none;" id="SelectMachine_create">
        {!! Form::label('resource_machine', 'Select Machine*', ['class' => 'control-label','style'=>'font-size:14px; font-weight: bold;']) !!}
        {!! Form::select('resource_machine',array('' => 'Select a Machine'),null,['id'=>'resource_machine_create','class' => 'form-control select2']) !!}
        <p class="help-block"></p>
        @if($errors->has('resource_machine'))
            <p class="help-block">
                {{ $errors->first('resource_machine') }}
            </p>
        @endif
    </div>
</div>
<div class="row hideonmbl">
    <div class="col-md-4">
    </div>
    <div class="col-md-4">
        <h4 style="font-size: 14px;margin-top: 0"><strong>From</strong></h4>
    </div>

    <div class="col-md-4">
        <h4 style="font-size: 14px;margin-top: 0"><strong>To</strong></h4>
    </div>
</div>
<div class="row">
    <div class="form-group col-md-4">
        <h4 style="margin-top: 0px;font-size: 14px;"><strong>Date*</strong></h4>
    </div>
    <div class="form-group col-md-4">
        <div class="showonmbl">
            <h5>From</h5>
        </div>
        {!! Form::text('start',Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d'), ['class' => 'form-control date_to_rota_1','readonly'=>'true']) !!}
    </div>
    <div class="form-group col-md-4">
        <div class="showonmbl">
            <h5>To</h5>
        </div>
        {!! Form::text('end',Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d'), ['class' => 'form-control date_to_rota_1','readonly'=>'true']) !!}
    </div>
</div>
<div class="row hideonmbl">
    <div class="col-lg-4 col-md-4 col-sm-4 col-xs-12">
        <h4 style="font-size: 14px;display: inline-block;vertical-align: top;"><strong>On</strong></h4>
        <h4 style="font-size: 14px;display: inline-block;vertical-align: top;margin-left: 15px;"><strong>Days</strong>
        </h4>
    </div>
    <div class="custom-desktop-label col-lg-8 col-md-8 col-sm-12">
        <div class="row">
            <strong class="label label-from">From</strong>
            <strong class="label label-to">To</strong>
            <strong class="label label-b-from">Break From</strong>
            <strong class="label label-b-to">Break To</strong>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-4">
        <div class="mt-checkbox-list">
            <div class="showonmbl">
                <h5>On</h5>
            </div>
            <label class="mt-checkbox check_final_1">
                <input id="mondayElement_1" type="checkbox" name="mondaychecked" checked/>
                <span></span>
            </label>
        </div>
        <h4 class="rota-days">Monday</h4>
        <div class="mt-checkbox-list sm_class" style="padding: 10px 0px 0px 0px;width: 80px;">
            <label class="mt-checkbox" style="color:#333;padding-left:0;margin-left: 5px;">
                <input type="checkbox" id="copy_all_1" name="copy_all" value=''/><strong style="font-size: 12px;border-bottom: 1px solid #333;">Copy As All</strong><span style="display: none;"></span>
            </label>
        </div>
    </div>
    <div id="mondayOperation_1">
        <div class="form-group col-md-8">
            <div class="custom-col clearfix">
                <div class="input input-from">
                    <div class="showonmbl">
                        <h5>From</h5>
                    </div>
                    {!! Form::text('time_f_monday', old('time_f_monday'), ['id' => 'monday_from', 'class' => 'form-control timepicker timepicker-no-seconds mondaytime_1 mondayfrom_1' ]) !!}
                    @if($errors->has('time_f_monday'))
                        <p class="help-block">
                            {{ $errors->first('time_f_monday') }}
                        </p>
                    @endif
                </div>
                <div class="input input-to">
                    <div class="showonmbl">
                        <h5>To</h5>
                    </div>
                    {!! Form::text('time_to_monday', old('time_to_monday'), ['id' => 'monday_to', 'class' => 'form-control timepicker timepicker-no-seconds mondaytime_1 mondayto_1']) !!}
                    @if($errors->has('time_to_monday'))
                        <p class="help-block">
                            {{ $errors->first('time_to_monday') }}
                        </p>
                    @endif
                </div>
                <div class="input input-break-from">
                    <div class="showonmbl">
                        <h5>From Break</h5>
                    </div>
                    {!! Form::text('break_from_monday', old('break_from_monday'), ['id' => 'break_monday_from', 'class' => 'form-control timepicker timepicker-no-seconds monday_breake_time break_mondayfrom']) !!}
                </div>
                <div class="input input-break-to">
                    <div class="showonmbl">
                        <h5>From To</h5>
                    </div>
                    {!! Form::text('break_to_monday', old('break_to_monday'), ['id' => 'break_monday_to', 'class' => 'form-control timepicker timepicker-no-seconds monday_breake_time break_mondayto']) !!}
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row"></div>
<div id="Week_Operation_1">
    <div class="row">
        <div class="form-group col-md-4">
            <div class="mt-checkbox-list">
                <div class="showonmbl">
                    <h5>On</h5>
                </div>
                <label class="mt-checkbox check_final_1">
                    <input id="tuesdayElement_1" type="checkbox" name="tuesdaychecked" checked/>
                    <span></span>
                </label>
            </div>
            <h4 class="rota-days">Tuesday</h4>
        </div>
        <div id="tuesdayOperation_1">
            <div class="form-group col-md-8">
                <div class="custom-col clearfix">
                    <div class="input input-from">
                        <div class="showonmbl">
                            <h5>From</h5>
                        </div>
                        {!! Form::text('time_f_tuesday', old('time_f_tuesday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota_1 tuesdaytime_1 ftime_1' ]) !!}
                        @if($errors->has('time_f_tuesday'))
                            <p class="help-block">
                                {{ $errors->first('time_f_tuesday') }}
                            </p>
                        @endif
                    </div>
                    <div class="input input-to">
                        <div class="showonmbl">
                            <h5>To</h5>
                        </div>
                        {!! Form::text('time_to_tuesday', old('time_to_tuesday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota_1 tuesdaytime_1 ttime_1' ]) !!}
                        @if($errors->has('time_to_tuesday'))
                            <p class="help-block">
                                {{ $errors->first('time_to_tuesday') }}
                            </p>
                        @endif
                    </div>
                    <div class="input input-break-from">
                        <div class="showonmbl">
                            <h5>From Break</h5>
                        </div>
                        {!! Form::text('break_from_tuesday', old('break_from_tuesday'), ['class' => 'form-control timepicker timepicker-no-seconds breaktime tuesdaytime_break f_time_break']) !!}
                    </div>
                    <div class="input input-break-to">
                        <div class="showonmbl">
                            <h5>From To</h5>
                        </div>
                        {!! Form::text('break_to_tuesday', old('break_to_tuesday'), ['class' => 'form-control timepicker timepicker-no-seconds breaktime tuesdaytime_break t_time_break']) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="form-group col-md-4">
            <div class="mt-checkbox-list">
                <div class="showonmbl">
                    <h5>On</h5>
                </div>
                <label class="mt-checkbox check_final_1">
                    <input id="wednesdayElement_1" type="checkbox" name="wednesdaychecked" checked/>
                    <span></span>
                </label>
            </div>
            <h4 class="rota-days">Wednesday</h4>
        </div>
        <div id="wednesdayOperation_1">
            <div class="form-group col-md-8">
                <div class="custom-col clearfix">
                    <div class="input input-from">
                        <div class="showonmbl">
                            <h5>From</h5>
                        </div>
                        {!! Form::text('time_f_wednesday', old('time_f_wednesday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota_1 wednesdaytime_1 ftime_1']) !!}
                        @if($errors->has('time_f_wednesday'))
                            <p class="help-block">
                                {{ $errors->first('time_f_wednesday') }}
                            </p>
                        @endif
                    </div>
                    <div class="input input-to">
                        <div class="showonmbl">
                            <h5>To</h5>
                        </div>
                        {!! Form::text('time_to_wednesday', old('time_to_wednesday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota_1 wednesdaytime_1 ttime_1']) !!}
                        @if($errors->has('time_to_wednesday'))
                            <p class="help-block">
                                {{ $errors->first('time_to_wednesday') }}
                            </p>
                        @endif
                    </div>
                    <div class="input input-break-from">
                        <div class="showonmbl">
                            <h5>From Break</h5>
                        </div>
                        {!! Form::text('break_from_wednesday', old('break_from_wednesday'), ['class' => 'form-control timepicker timepicker-no-seconds breaktime wednesdaytime_break f_time_break']) !!}
                    </div>
                    <div class="input input-break-to">
                        <div class="showonmbl">
                            <h5>From To</h5>
                        </div>
                        {!! Form::text('break_to_wednesday', old('break_to_wednesday'), ['class' => 'form-control timepicker timepicker-no-seconds breaktime wednesdaytime_break t_time_break']) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="form-group col-md-4">
            <div class="mt-checkbox-list">
                <div class="showonmbl">
                    <h5>On</h5>
                </div>
                <label class="mt-checkbox check_final_1">
                    <input id="thursdayElement_1" type="checkbox" name="thursdaychecked" checked/>
                    <span></span>
                </label>
            </div>
            <h4 class="rota-days">Thursday</h4>
        </div>
        <div id="thursdayOperation_1">
            <div class="form-group col-md-8">
                <div class="custom-col clearfix">
                    <div class="input input-from">
                        <div class="showonmbl">
                            <h5>From</h5>
                        </div>
                        {!! Form::text('time_f_thursday', old('time_f_thursday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota_1 thursdaytime_1 ftime_1' ]) !!}
                        @if($errors->has('time_f_thursday'))
                            <p class="help-block">
                                {{ $errors->first('time_f_thursday') }}
                            </p>
                        @endif
                    </div>
                    <div class="input input-to">
                        <div class="showonmbl">
                            <h5>To</h5>
                        </div>
                        {!! Form::text('time_to_thursday', old('time_to_thursday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota_1 thursdaytime_1 ttime_1']) !!}
                        @if($errors->has('time_to_thursday'))
                            <p class="help-block">
                                {{ $errors->first('time_to_thursday') }}
                            </p>
                        @endif
                    </div>
                    <div class="input input-break-from">
                        <div class="showonmbl">
                            <h5>From Break</h5>
                        </div>
                        {!! Form::text('break_from_thursday', old('break_from_thursday'), ['class' => 'form-control timepicker timepicker-no-seconds breaktime thursdaytime_break f_time_break']) !!}
                    </div>
                    <div class="input input-break-to">
                        <div class="showonmbl">
                            <h5>From To</h5>
                        </div>
                        {!! Form::text('break_to_thursday', old('break_to_thursday'), ['class' => 'form-control timepicker timepicker-no-seconds breaktime thursdaytime_break t_time_break']) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="form-group col-md-4">
            <div class="mt-checkbox-list">
                <div class="showonmbl">
                    <h5>On</h5>
                </div>
                <label class="mt-checkbox check_final_1">
                    <input id="fridayElement_1" type="checkbox" name="fridaychecked" checked/>
                    <span></span>
                </label>
            </div>
            <h4 class="rota-days">Friday</h4>
        </div>
        <div id="fridayOperation_1">
            <div class="form-group col-md-8">
                <div class="custom-col clearfix">
                    <div class="input input-from">
                        <div class="showonmbl">
                            <h5>From</h5>
                        </div>
                        {!! Form::text('time_f_friday', old('time_f_friday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota_1 fridaytime_1 ftime_1']) !!}
                        @if($errors->has('time_f_friday'))
                            <p class="help-block">
                                {{ $errors->first('time_f_friday') }}
                            </p>
                        @endif
                    </div>
                    <div class="input input-to">
                        <div class="showonmbl">
                            <h5>To</h5>
                        </div>
                        {!! Form::text('time_to_friday', old('time_to_friday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota_1 fridaytime_1 ttime_1']) !!}
                        @if($errors->has('time_to_friday'))
                            <p class="help-block">
                                {{ $errors->first('time_to_friday') }}
                            </p>
                        @endif
                    </div>
                    <div class="input input-break-from">
                        <div class="showonmbl">
                            <h5>From Break</h5>
                        </div>
                        {!! Form::text('break_from_friday', old('break_from_friday'), ['class' => 'form-control timepicker timepicker-no-seconds breaktime fridaytime_break f_time_break']) !!}
                    </div>
                    <div class="input input-break-to">
                        <div class="showonmbl">
                            <h5>From To</h5>
                        </div>
                        {!! Form::text('break_to_friday', old('break_to_friday'), ['class' => 'form-control timepicker timepicker-no-seconds breaktime fridaytime_break t_time_break']) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="form-group col-md-4">
            <div class="mt-checkbox-list">
                <div class="showonmbl">
                    <h5>On</h5>
                </div>
                <label class="mt-checkbox check_final_1">
                    <input id="saturdayElement_1" type="checkbox" name="saturdaychecked" checked/>
                    <span></span>
                </label>
            </div>
            <h4 class="rota-days">Saturday</h4>
        </div>
        <div id="saturdayOperation_1">
            <div class="form-group col-md-8">
                <div class="custom-col clearfix">
                    <div class="input input-from">
                        <div class="showonmbl">
                            <h5>From</h5>
                        </div>
                        {!! Form::text('time_f_saturday', old('time_f_saturday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota_1 saturdaytime_1 ftime_1']) !!}
                        @if($errors->has('time_f_saturday'))
                            <p class="help-block">
                                {{ $errors->first('time_f_saturday') }}
                            </p>
                        @endif
                    </div>
                    <div class="input input-to">
                        <div class="showonmbl">
                            <h5>To</h5>
                        </div>
                        {!! Form::text('time_to_saturday', old('time_to_saturday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota_1 saturdaytime_1 ttime_1']) !!}
                        @if($errors->has('time_to_saturday'))
                            <p class="help-block">
                                {{ $errors->first('time_to_saturday') }}
                            </p>
                        @endif
                    </div>
                    <div class="input input-break-from">
                        <div class="showonmbl">
                            <h5>From Break</h5>
                        </div>
                        {!! Form::text('break_from_saturday', old('break_from_saturday'), ['class' => 'form-control timepicker timepicker-no-seconds breaktime saturdaytime_break f_time_break']) !!}
                    </div>
                    <div class="input input-break-to">
                        <div class="showonmbl">
                            <h5>From To</h5>
                        </div>
                        {!! Form::text('break_to_saturday', old('break_to_saturday'), ['class' => 'form-control timepicker timepicker-no-seconds breaktime saturdaytime_break t_time_break']) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="form-group col-md-4">
            <div class="mt-checkbox-list">
                <div class="showonmbl">
                    <h5>On</h5>
                </div>
                <label class="mt-checkbox check_final_1">
                    <input id="sundayElement_1" type="checkbox" name="sundaychecked" checked/>
                    <span></span>
                </label>
            </div>
            <h4 class="rota-days">Sunday</h4>
        </div>
        <div id="sundayOperation_1">
            <div class="form-group col-md-8">
                <div class="custom-col clearfix">
                    <div class="input input-from">
                        <div class="showonmbl">
                            <h5>From</h5>
                        </div>
                        {!! Form::text('time_f_sunday', old('time_f_sunday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota_1 sundaytime_1 ftime_1']) !!}
                        @if($errors->has('time_f_sunday'))
                            <p class="help-block">
                                {{ $errors->first('time_f_sunday') }}
                            </p>
                        @endif
                    </div>
                    <div class="input input-to">
                        <div class="showonmbl">
                            <h5>To</h5>
                        </div>
                        {!! Form::text('time_to_sunday', old('time_to_sunday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota_1 sundaytime_1 ttime_1']) !!}
                        @if($errors->has('time_to_sunday'))
                            <p class="help-block">
                                {{ $errors->first('time_to_sunday') }}
                            </p>
                        @endif
                    </div>
                    <div class="input input-break-from">
                        <div class="showonmbl">
                            <h5>From Break</h5>
                        </div>
                        {!! Form::text('break_from_sunday', old('break_from_sunday'), ['class' => 'form-control timepicker timepicker-no-seconds breaktime sundaytime_break f_time_break']) !!}
                    </div>
                    <div class="input input-break-to">
                        <div class="showonmbl">
                            <h5>From To</h5>
                        </div>
                        {!! Form::text('break_to_sunday', old('break_to_sunday'), ['class' => 'form-control timepicker timepicker-no-seconds breaktime sundaytime_break t_time_break']) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4"></div>
        <div id="Rota_type_operation">
            <div class="form-group col-md-4">
                <label>Consultancy</label>&nbsp&nbsp&nbsp
                <label class="mt-checkbox is_consultancy_1">
                    <input type="hidden" name="is_consultancy" value="0"/>
                    <input id="is_consultancy_1" type="checkbox" name="is_consultancy" value="1" checked/>
                    <span></span>
                </label>
            </div>
            <div class="form-group col-md-4">
                <label>Treatment</label>&nbsp&nbsp&nbsp
                <label class="mt-checkbox is_treatment_1">
                    <input type="hidden" name="is_treatment" value="0"/>
                    <input id="is_treatment_1" type="checkbox" name="is_treatment" value="1" checked/>
                    <span></span>
                </label>
            </div>
        </div>
    </div>
</div>
<div class="clearfix"></div>

