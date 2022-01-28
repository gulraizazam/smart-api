@inject('request', 'Illuminate\Http\Request')
@extends('layouts.app')

@section('stylesheets')
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <link href="{{ url('metronic/assets/global/plugins/datatables/datatables.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}" rel="stylesheet" type="text/css"/>
    <!-- END PAGE LEVEL PLUGINS -->
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-editable/bootstrap-editable/css/bootstrap-editable.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-editable/inputs-ext/address/address.css') }}" rel="stylesheet" type="text/css" />
    <!-- END PAGE LEVEL PLUGINS -->
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css">
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-timepicker/css/bootstrap-timepicker.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css')}}" rel="stylesheet" type="text/css" />
    {{--Page Level calender events css start--}}
    <link href="{{ url('js/full_calender/fullcalendar.min.css') }}" rel='stylesheet' />
    <link href="{{ url('js/full_calender/fullcalendar.print.min.css') }}" rel='stylesheet' media='print' />
    {{--Page Level calender events css end--}}

@stop
@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.resourcerotas.title')</h1>
    <!-- END PAGE TITLE-->
@endsection
@section('content')
    <div class="portlet light portlet-fit portlet-datatable bordered">
        <div class="portlet-title">
            <i class=" icon-layers font-green"></i>
            <span class="caption-subject font-green sbold uppercase">Calendar</span>
        </div>
        {{--Message for display--}}
        <div id="backdateenvent" class="alert alert-danger display-hide">
            <button class="close" data-close="alert"></button>
            Not able to perform action in back date.
        </div>
        {{--End message--}}
        <div class="portlet-body">
            <h2 style="text-align: center">{{$resource->name}}</h2>
            <div class="row">
                <div class="col-md-12">
                    <div class="portlet light portlet-fit bordered calendar">
                        <div class="portlet-title">
                        </div>
                        <div class="portlet-body">
                            <div id='calendar'></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{--This is Edit model in calender action--}}
    <div class="modal fade" id="ajax_resourcerotas_calenderedit" role="basic" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-body">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                    <h4 class="modal-title">@lang('global.app_update')</h4>
                </div>
                <div class="modal-body">
                    <div class="portlet-body form">
                        <div class="form-group">
                            {!! Form::open(['method' => 'POST', 'id' => 'form-validation', 'route' => ['admin.resourcerotas.store_Calender_edit']]) !!}
                            <div class="form-body">
                                <!-- Starts Form Validation Messages -->
                                @include('partials.messages')
                                <!-- Ends Form Validation Messages -->
                                <div class="row">
                                    <div class="col-md-5">
                                            <h5>From</h5>

                                    </div>
                                    <div class="col-md-5">
                                            <h5>To</h5>
                                    </div>
                                    <div class="col-md-2">
                                    </div>
                                </div>
                                <div class="row">
                                    <div id="dayOperation">     
                                        <div class="form-group col-md-5">
                                            {!! Form::text('start_time', old('start_time'), ['id'=>'resource_days_start_time', 'class' => 'form-control timepicker timepicker-no-seconds time_to_Rota mondaytime mondayfrom']) !!}
                                            @if($errors->has('start_time'))
                                                <p class="help-block">
                                                    {{ $errors->first('start_time') }}
                                                </p>
                                            @endif
                                        </div>

                                        <div class="form-group col-md-5">
                                            {!! Form::text('end_time', old('end_time'), ['id'=>'resource_days_end_time', 'class' => 'form-control timepicker timepicker-no-seconds time_to_Rota mondaytime mondayto']) !!}
                                            @if($errors->has('end_time'))
                                                <p class="help-block">
                                                    {{ $errors->first('end_time') }}
                                                </p>
                                            @endif
                                        </div>
                                        <br>
                                        <div class="form-group col-md-5">
                                            <h5>From Break</h5>
                                            {!! Form::text('start_off', old('start_off'), ['id'=>'resource_days_start_time_break', 'class' => 'form-control timepicker timepicker-no-seconds time_to_Rota mondaytime_break mondayfrom_break']) !!}
                                            @if($errors->has('start_off'))
                                                <p class="help-block">
                                                    {{ $errors->first('start_off') }}
                                                </p>
                                            @endif
                                        </div>

                                        <div class="form-group col-md-5">
                                            <h5>To Break</h5>
                                            {!! Form::text('end_off', old('end_off'), ['id'=>'resource_days_end_time_break', 'class' => 'form-control timepicker timepicker-no-seconds time_to_Rota mondaytime_break mondayto_break']) !!}
                                            @if($errors->has('end_off'))
                                                <p class="help-block">
                                                    {{ $errors->first('end_off') }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mt-checkbox-list">
                                            <label class="mt-checkbox" style="font-size: 11px;font-weight: bold;">
                                                <input id="dayElement" type="checkbox" name="dayElement"/>Leave
                                                <span></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="hidden" name="resource_days_id" id="resource_days_id" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="hidden" name="resource_days_date" id="resource_days_date" class="form-control">
                                    </div>
                                </div>
                            </div>

                        </div>
                        <div>
                            {!! Form::submit(trans('global.app_update'), ['class' => 'btn btn-success']) !!}
                        </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script src="{{ url('metronic/assets/global/plugins/jquery-ui/jquery-ui.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/clipboard/clipboard.min.js') }}"></script>
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/datatables/datatables.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/select2/js/select2.full.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-editable/bootstrap-editable/js/bootstrap-editable.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-editable/inputs-ext/address/address.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-timepicker/js/bootstrap-timepicker.min.js') }}" type="text/javascript"></script>

    {{--Start Script for calender--}}
    <script src="{{ url('js/lib/moment.min.js')}}"></script>
    <script src="{{ url('js/full_calender/fullcalendar.min.js')}}"></script>
    {{--End script for calender--}}

    <script>
        var BASEURL ="{{ url('/') }}";
        $(document).ready(function() {
            $('#calendar').fullCalendar({
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'month,basicWeek,basicDay',

                    /*'month,basicWeek,basicDay'*/
                },
                defaultDate: '{{ \Carbon\Carbon::now()->format('Y-m-d') }}',
                navLinks: true, // can click day/week names to navigate views
                editable: true,
                eventLimit: true, // allow "more" link when too many events
                events: BASEURL +'/admin/resourcerotas/calender/events/{{ $id }}',

                eventClick:  function(event, jsEvent, view) {
                    $('#resource_days_id').val(event.id);
                    $('#resource_days_date').val(event.date);
                    $('#resource_days_start_time').val(event.start_time);
                    $('#resource_days_end_time').val(event.end_time);

                    $('#resource_days_start_time_break').val(event.start_off);
                    $('#resource_days_end_time_break').val(event.end_off);

                    if($('#resource_days_start_time').val())
                    {
                        $('#dayOperation :input').removeAttr('disabled');
                        $("#dayElement").prop( "checked", false );
                    }
                    else{
                        $("#estado_cat").prop( "checked", true );
                        $('#dayOperation :input').attr('disabled', true);
                        $("#dayElement").prop( "checked", true );
                    }
                    if(event.checked == 1){
                        $('#backdateenvent').hide();
                        $('#ajax_resourcerotas_calenderedit').modal('show');
                    } else {
                        $('#backdateenvent').show();
                    }
                }
            });
        });
        $(document).ready(function () {
            var date = new Date();
            date.setDate(date.getDate());
            $('.date_to_rota').datepicker({
                format: 'yyyy-mm-dd',
                startDate: date
            }).on('changeDate', function(ev){
                $(this).datepicker('hide');
            })
            $('.time_to_Rota').timepicker({
                format:'H:i A',
            })
        });
        $(document).ready(function(){

            $('#dayElement').on('change', function () {

                if ($('#dayElement').is(':checked')) {

                    $('#dayOperation :input').attr('disabled', true);

                    $('.daytime').val('','');
                } else {

                    $('#dayOperation :input').removeAttr('disabled');

                }
            });
        });

    </script>

@endsection