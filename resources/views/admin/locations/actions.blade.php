@if(Gate::allows('locations_edit'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.locations.edit',[$location->id]) }}" data-target="#ajax_locatons" data-toggle="modal">@lang('global.app_edit')</a>
@endif
@if(Gate::allows('locations_destroy'))
    {!! Form::open(array(
        'style' => 'display: inline-block;',
        'method' => 'DELETE',
        'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
        'route' => ['admin.locations.destroy', $location->id])) !!}
    {!! Form::submit(trans('global.app_delete'), array('class' => 'btn btn-xs btn-danger')) !!}
    {!! Form::close() !!}
@endif