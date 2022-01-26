@section('stylesheet')
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css')}}"
          rel="stylesheet" type="text/css"/>
@stop
<div class="row">
    <div class="col-md-4">
        <h4>Resource Name</h4>
    </div>
    <div class="col-md-8">
        <h4><strong>{{$resource_name->name}}</strong></h4>
    </div>
</div>
<div class="row">
    <div class="col-md-4">
        <h4>City</h4>
    </div>
    <div class="col-md-8">
        <h4><strong>{{(($citie->region_id) ? $citie->region->name . ' - ' : '') . $city}}</strong></h4>
    </div>
</div>
<div class="row ">
    <div class="col-md-4">
        <h4>Centre</h4>
    </div>
    <div class="col-md-8">
        <h4><strong>{{$location}}</strong></h4>
    </div>
</div>
<div class="row ">
    <div class="col-md-4">
        <h4>Rota Start Date</h4>
    </div>
    <div class="col-md-8">
        <h4><strong>{{$resourceRota->start}}</strong></h4>
    </div>
</div>
<br>
<div class="row hideonmbl">
    <div class="col-md-4">
    </div>
    <div class="col-md-4">
        <h4 style="font-size: 14px;margin-top: 0"><strong>Effective From</strong></h4>
    </div>
    <div class="col-md-4">
        <h4 style="font-size: 14px;margin-top: 0"><strong>To</strong></h4>
    </div>
</div>
<div class="row">
    <div class="form-group col-md-4">
        <h4 style=" margin-top: 0px;font-size: 14px;"><strong>Date*</strong></h4>
    </div>
    <div class="form-group col-md-4">
        <div class="showonmbl">
            <h5>From</h5>
        </div>
        {!! Form::text('start', (\Carbon\Carbon::now()->format('Y-m-d') >= $resourceRota->start) ? \Carbon\Carbon::now()->format('Y-m-d') : $resourceRota->start , ['class' => 'form-control date_to_rota','required' => '','readonly'=>'true']) !!}
        @if($errors->has('start'))
            <p class="help-block">
                {{ $errors->first('start') }}
            </p>
        @endif
    </div>
    <div class="form-group col-md-4">
        <div class="showonmbl">
            <h5>To</h5>
        </div>
        {!! Form::text('end',old('end') ? \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d'):old('end'), ['class' => 'form-control date_to_rota','required' => '','readonly'=>'true']) !!}
        @if($errors->has('end'))
            <p class="help-block">
                {{ $errors->first('end') }}
            </p>
        @endif
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
        @if($resourceRota->mondaychecked=='on')
            <div class="mt-checkbox-list">
                <div class="showonmbl">
                    <h5>On</h5>
                </div>
                <label class="mt-checkbox check_final">
                    <input id="mondayElement" type="checkbox" name="mondaychecked"/>
                    <span></span>
                </label>
            </div>
        @endif
        @if($resourceRota->mondaychecked!='on')
            <div class="mt-checkbox-list">
                <div class="showonmbl">
                    <h5>On</h5>
                </div>
                <label class="mt-checkbox check_final">
                    <input id="mondayElement" type="checkbox" name="mondaychecked" checked/>
                    <span></span>
                </label>
            </div>
        @endif
        <h4 class="rota-days">Monday</h4>
        @if($resourceRota->copy_all=='1')
            <div class="mt-checkbox-list sm_class" style="padding: 10px 0px 0px 0px; width: 80px;">
                <label class="mt-checkbox" style="color:#333;padding-left:0;margin-left: 5px;">
                    <input type="checkbox" id="copy_all" name="copy_all" value='1' checked/><strong
                            style="font-size: 12px;border-bottom: 1px solid #333;">Copy As All</strong>
                    <span style="display: none;"></span>
                </label>
            </div>
        @endif
        @if($resourceRota->copy_all=='0')
            <div class="mt-checkbox-list sm_class" style="padding: 10px 0px 0px 0px;width: 80px;">
                <label class="mt-checkbox" style="color:#333;padding-left:0;margin-left: 5px;">
                    <input type="checkbox" id="copy_all" name="copy_all" value='0'/><strong
                            style="font-size: 12px;border-bottom: 1px solid #333;">Copy As All</strong>
                    <span style="display: none;"></span>
                </label>
            </div>
        @endif
    </div>
    <div id="mondayOperation">
        <div class="form-group col-md-8">
            <div class="custom-col clearfix">
                <div class="input input-from">
                    <div class="showonmbl">
                        <h5>From</h5>
                    </div>
                    {!! Form::text('time_f_monday', old('time_f_monday'), ['id' => 'monday_from_update','class' => 'form-control timepicker timepicker-no-seconds time_to_Rota mondaytime mondayfrom']) !!}
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
                    {!! Form::text('time_to_monday', old('time_to_monday'), ['id' => 'monday_to_update','class' => 'form-control timepicker timepicker-no-seconds time_to_Rota mondaytime mondayto']) !!}
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
                    {!! Form::text('break_from_monday', old('break_from_monday'), ['id' => 'break_from_update_monday', 'class' => 'form-control timepicker timepicker-no-seconds monday_breake_time_1 break_mondayfrom_1']) !!}
                </div>
                <div class="input input-break-to">
                    <div class="showonmbl">
                        <h5>From To</h5>
                    </div>
                    {!! Form::text('break_to_monday', old('break_to_monday'), ['id' => 'break_to_update_monday', 'class' => 'form-control timepicker timepicker-no-seconds monday_breake_time_1 break_mondayto_1']) !!}
                </div>
            </div>
        </div>
    </div>

</div>

<div id="WeekOperation">
    <div class="row">
        <div class="form-group col-md-4">
            @if($resourceRota->tuesdaychecked=='on')
                <div class="mt-checkbox-list">
                    <div class="showonmbl">
                        <h5>On</h5>
                    </div>
                    <label class="mt-checkbox check_final">
                        <input id="tuesdayElement" type="checkbox" name="tuesdaychecked"/>
                        <span></span>
                    </label>
                </div>
            @endif
            @if($resourceRota->tuesdaychecked!='on')
                <div class="mt-checkbox-list">
                    <div class="showonmbl">
                        <h5>On</h5>
                    </div>
                    <label class="mt-checkbox check_final">
                        <input id="tuesdayElement" type="checkbox" name="tuesdaychecked" checked/>
                        <span></span>
                    </label>
                </div>
            @endif
            <h4 class="rota-days">Tuesday</h4>
        </div>
        <div id="tuesdayOperation">
            <div class="form-group col-md-8">
                <div class="custom-col clearfix">
                    <div class="input input-from">
                        <div class="showonmbl">
                            <h5>From</h5>
                        </div>
                        {!! Form::text('time_f_tuesday', old('time_f_tuesday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota tuesdaytime ftime']) !!}
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
                        {!! Form::text('time_to_tuesday', old('time_to_tuesday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota tuesdaytime ttime']) !!}
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
                        {!! Form::text('break_from_tuesday', old('break_from_tuesday'), ['id' => 'break_from_update_tuesday', 'class' => 'form-control timepicker timepicker-no-seconds breaktime_1 tuesdaytime_break_1 f_time_break_1']) !!}
                    </div>
                    <div class="input input-break-to">
                        <div class="showonmbl">
                            <h5>From To</h5>
                        </div>
                        {!! Form::text('break_to_tuesday', old('break_to_tuesday'), ['id' => 'break_to_update_tuesday','class' => 'form-control timepicker timepicker-no-seconds breaktime_1 tuesdaytime_break_1 t_time_break_1']) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="form-group col-md-4">
            @if($resourceRota->wednesdaychecked=='on')
                <div class="mt-checkbox-list">
                    <div class="showonmbl">
                        <h5>On</h5>
                    </div>
                    <label class="mt-checkbox check_final">
                        <input id="wednesdayElement" type="checkbox" name="wednesdaychecked"/>
                        <span></span>
                    </label>
                </div>
            @endif
            @if($resourceRota->wednesdaychecked!='on')
                <div class="mt-checkbox-list">
                    <div class="showonmbl">
                        <h5>On</h5>
                    </div>
                    <label class="mt-checkbox check_final">
                        <input id="wednesdayElement" type="checkbox" name="wednesdaychecked" checked/>
                        <span></span>
                    </label>
                </div>
            @endif
            <h4 class="rota-days">Wednesday</h4>
        </div>
        <div id="wednesdayOperation">
            <div class="form-group col-md-8">
                <div class="custom-col clearfix">
                    <div class="input input-from">
                        <div class="showonmbl">
                            <h5>From</h5>
                        </div>
                        {!! Form::text('time_f_wednesday', old('time_f_wednesday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota wednesdaytime ftime']) !!}
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
                        {!! Form::text('time_to_wednesday', old('time_to_wednesday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota wednesdaytime ttime']) !!}
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
                        {!! Form::text('break_from_wednesday', old('break_from_wednesday'), ['id' => 'break_from_update_wednesday','class' => 'form-control timepicker timepicker-no-seconds breaktime_1 wednesdaytime_break_1 f_time_break_1']) !!}
                    </div>
                    <div class="input input-break-to">
                        <div class="showonmbl">
                            <h5>From To</h5>
                        </div>
                        {!! Form::text('break_to_wednesday', old('break_to_wednesday'), ['id' => 'break_to_update_wednesday','class' => 'form-control timepicker timepicker-no-seconds breaktime_1 wednesdaytime_break_1 t_time_break_1']) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="form-group col-md-4">
            @if($resourceRota->thursdaychecked=='on')
                <div class="mt-checkbox-list">
                    <div class="showonmbl">
                        <h5>On</h5>
                    </div>
                    <label class="mt-checkbox check_final">
                        <input id="thursdayElement" type="checkbox" name="thursdaychecked"/>
                        <span></span>
                    </label>
                </div>
            @endif
            @if($resourceRota->thursdaychecked!='on')
                <div class="mt-checkbox-list">
                    <div class="showonmbl">
                        <h5>On</h5>
                    </div>
                    <label class="mt-checkbox check_final">
                        <input id="thursdayElement" type="checkbox" name="thursdaychecked" checked/>
                        <span></span>
                    </label>
                </div>
            @endif
            <h4 class="rota-days">Thursday</h4>
        </div>
        <div id="thursdayOperation">
            <div class="form-group col-md-8">
                <div class="custom-col clearfix">
                    <div class="input input-from">
                        <div class="showonmbl">
                            <h5>From</h5>
                        </div>
                        {!! Form::text('time_f_thursday', old('time_f_thursday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota thursdaytime ftime']) !!}
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
                        {!! Form::text('time_to_thursday', old('time_to_thursday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota thursdaytime ttime']) !!}
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
                        {!! Form::text('break_from_thursday', old('break_from_thursday'), ['id' => 'break_from_update_thursday','class' => 'form-control timepicker timepicker-no-seconds breaktime_1 thursdaytime_break_1 f_time_break_1']) !!}
                    </div>
                    <div class="input input-break-to">
                        <div class="showonmbl">
                            <h5>From To</h5>
                        </div>
                        {!! Form::text('break_to_thursday', old('break_to_thursday'), ['id' => 'break_to_update_thursday', 'class' => 'form-control timepicker timepicker-no-seconds breaktime_1 thursdaytime_break_1 t_time_break_1']) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="form-group col-md-4">
            @if($resourceRota->fridaychecked=='on')
                <div class="mt-checkbox-list">
                    <div class="showonmbl">
                        <h5>On</h5>
                    </div>
                    <label class="mt-checkbox check_final">
                        <input id="fridayElement" type="checkbox" name="fridaychecked"/>
                        <span></span>
                    </label>
                </div>
            @endif
            @if($resourceRota->fridaychecked!='on')
                <div class="mt-checkbox-list">
                    <div class="showonmbl">
                        <h5>On</h5>
                    </div>
                    <label class="mt-checkbox check_final">
                        <input id="fridayElement" type="checkbox" name="fridaychecked" checked/>
                        <span></span>
                    </label>
                </div>
            @endif
            <h4 class="rota-days">Friday</h4>
        </div>
        <div id="fridayOperation">
            <div class="form-group col-md-8">
                <div class="custom-col clearfix">
                    <div class="input input-from">
                        <div class="showonmbl">
                            <h5>From</h5>
                        </div>
                        {!! Form::text('time_f_friday', old('time_f_friday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota fridaytime ftime']) !!}
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
                        {!! Form::text('time_to_friday', old('time_to_friday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota fridaytime ttime']) !!}
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
                        {!! Form::text('break_from_friday', old('break_from_friday'), ['id' => 'break_from_update_friday','class' => 'form-control timepicker timepicker-no-seconds breaktime_1 fridaytime_break_1 f_time_break_1']) !!}
                    </div>
                    <div class="input input-break-to">
                        <div class="showonmbl">
                            <h5>From To</h5>
                        </div>
                        {!! Form::text('break_to_friday', old('break_to_friday'), ['id' => 'break_to_update_friday','class' => 'form-control timepicker timepicker-no-seconds breaktime_1 fridaytime_break_1 t_time_break_1']) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="form-group col-md-4">
            @if($resourceRota->saturdaychecked=='on')
                <div class="mt-checkbox-list">
                    <div class="showonmbl">
                        <h5>On</h5>
                    </div>
                    <label class="mt-checkbox check_final">
                        <input id="saturdayElement" type="checkbox" name="saturdaychecked"/>
                        <span></span>
                    </label>
                </div>

            @endif
            @if($resourceRota->saturdaychecked!='on')
                <div class="mt-checkbox-list">
                    <div class="showonmbl">
                        <h5>On</h5>
                    </div>
                    <label class="mt-checkbox check_final">
                        <input id="saturdayElement" type="checkbox" name="saturdaychecked" checked/>
                        <span></span>
                    </label>
                </div>
            @endif
            <h4 class="rota-days">Saturday</h4>
        </div>
        <div id="saturdayOperation">
            <div class="form-group col-md-8">
                <div class="custom-col clearfix">
                    <div class="input input-from">
                        <div class="showonmbl">
                            <h5>From</h5>
                        </div>
                        {!! Form::text('time_f_saturday', old('time_f_saturday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota saturdaytime ftime']) !!}
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
                        {!! Form::text('time_to_saturday', old('time_to_saturday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota saturdaytime ttime']) !!}
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
                        {!! Form::text('break_from_saturday', old('break_from_saturday'), ['id' => 'break_from_update_saturday','class' => 'form-control timepicker timepicker-no-seconds breaktime_1 saturdaytime_break_1 f_time_break_1']) !!}
                    </div>
                    <div class="input input-break-to">
                        <div class="showonmbl">
                            <h5>From To</h5>
                        </div>
                        {!! Form::text('break_to_saturday', old('break_to_saturday'), ['id' => 'break_to_update_saturday','class' => 'form-control timepicker timepicker-no-seconds breaktime_1 saturdaytime_break_1 t_time_break_1']) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="form-group col-md-4">
            @if($resourceRota->sundaychecked=='on')
                <div class="mt-checkbox-list">
                    <div class="showonmbl">
                        <h5>On</h5>
                    </div>
                    <label class="mt-checkbox check_final">
                        <input id="sundayElement" type="checkbox" name="sundaychecked"/>
                        <span></span>
                    </label>
                </div>

            @endif
            @if($resourceRota->sundaychecked!='on')
                <div class="mt-checkbox-list">
                    <div class="showonmbl">
                        <h5>On</h5>
                    </div>
                    <label class="mt-checkbox check_final">
                        <input id="sundayElement" type="checkbox" name="sundaychecked" checked/>
                        <span></span>
                    </label>
                </div>
            @endif
            <h4 class="rota-days">Sunday</h4>
        </div>
        <div id="sundayOperation">


            <div class="form-group col-md-8">
                <div class="custom-col clearfix">
                    <div class="input input-from">
                        <div class="showonmbl">
                            <h5>From</h5>
                        </div>
                        {!! Form::text('time_f_sunday', old('time_f_sunday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota sundaytime ftime']) !!}
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
                        {!! Form::text('time_to_sunday', old('time_to_sunday'), ['class' => 'form-control timepicker timepicker-no-seconds time_to_Rota sundaytime ttime']) !!}
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
                        {!! Form::text('break_from_sunday', old('break_from_sunday'), ['id' => 'break_from_update_sunday','class' => 'form-control timepicker timepicker-no-seconds breaktime_1 sundaytime_break_1 f_time_break_1']) !!}
                    </div>
                    <div class="input input-break-to">
                        <div class="showonmbl">
                            <h5>From To</h5>
                        </div>
                        {!! Form::text('break_to_sunday', old('break_to_sunday'), ['id' => 'break_to_update_sunday','class' => 'form-control timepicker timepicker-no-seconds breaktime_1 sundaytime_break_1 t_time_break_1']) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if($resourceRota->resource_type_id == '2')
        <div class="row">
            <div class="col-md-4"></div>
            <div id="Rota_type_operation">
                <div class="form-group col-md-4">
                    <label>Consultancy</label>&nbsp&nbsp&nbsp
                    <label class="mt-checkbox is_consultancy">
                        <input type="hidden" name="is_consultancy" value="0"/>
                        @if($resourceRota->is_consultancy==1)
                            <input id="is_consultancy" type="checkbox" name="is_consultancy" value="1" checked/>
                        @elseif($resourceRota->is_consultancy==0)
                            <input id="is_consultancy" type="checkbox" name="is_consultancy" value="0"/>
                        @endif
                        <span></span>
                    </label>
                </div>
                <div class="form-group col-md-4">
                    <label>Treatment</label>&nbsp&nbsp&nbsp
                    <label class="mt-checkbox is_treatment">
                        <input type="hidden" name="is_treatment" value="0"/>
                        @if($resourceRota->is_treatment==1)
                            <input id="is_treatment" type="checkbox" name="is_treatment" value="1" checked/>
                        @elseif($resourceRota->is_treatment==0)
                            <input id="is_treatment" type="checkbox" name="is_treatment" value="0"/>
                        @endif
                        <span></span>
                    </label>
                </div>
            </div>
        </div>
    @endif
</div>
<div class="clearfix"></div>
