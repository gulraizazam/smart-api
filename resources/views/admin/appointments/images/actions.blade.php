<a class="btn btn-xs btn-warning" href="{{asset('appointment_image/')}}/{{$appointmentimg->image_path}}" target="_blank">@lang('global.app_view')</a>
@if(Gate::allows('appointments_image_destroy'))
    {!! Form::open(array(
        'style' => 'display: inline-block;',
        'method' => 'DELETE',
        'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
        'route' => ['admin.appointmentsimage.destroy', $appointmentimg->id])) !!}
    {!! Form::submit(trans('global.app_delete'), array('class' => 'btn btn-xs btn-danger')) !!}
    {!! Form::close() !!}
@endif