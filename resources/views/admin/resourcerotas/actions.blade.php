@if(Gate::allows('resourcerotas_edit'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.resourcerotas.edit',[$resourcerota->id]) }}"
       data-target="#ajax_resourcerotas" data-toggle="modal">@lang('global.app_edit')</a>
@endif
@if(Gate::allows('resourcerotas_destroy'))
    {!! Form::open(array(
        'style' => 'display: inline-block;',
        'method' => 'DELETE',
        'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
        'route' => ['admin.resourcerotas.destroy', $resourcerota->id])) !!}
    {!! Form::submit(trans('global.app_delete'), array('class' => 'btn btn-xs btn-danger')) !!}
    {!! Form::close() !!}
@endif
@if(Gate::allows('resourcerotas_calender'))
    <a class="btn btn-xs btn-success"
       href="{{ route('admin.resourcerotas.calender',[$resourcerota->id]) }}">@lang('global.app_calendar')</a>
@endif