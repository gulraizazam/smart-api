@if(Gate::allows('resources_edit'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.resources.edit',[$resource->id]) }}"
       data-target="#ajax_resources"
       data-toggle="modal">@lang('global.app_edit')</a>
@endif
@if(Gate::allows('resources_destroy'))
    {!! Form::open(array(
        'style' => 'display: inline-block;',
        'method' => 'DELETE',
        'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
        'route' => ['admin.resources.destroy', $resource->id])) !!}
    {!! Form::submit(trans('global.app_delete'), array('class' => 'btn btn-xs btn-danger')) !!}
    {!! Form::close() !!}
@endif