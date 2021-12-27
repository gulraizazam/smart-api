@if(Gate::allows('roles_edit'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.roles.edit',[$role->id]) }}">@lang('global.app_edit')</a>
@endif
@if(Gate::allows('roles_destroy'))
    {!! Form::open(array(
        'style' => 'display: inline-block;',
        'method' => 'DELETE',
        'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
        'route' => ['admin.roles.destroy', $role->id])) !!}
    {!! Form::submit(trans('global.app_delete'), array('class' => 'btn btn-xs btn-danger')) !!}
    {!! Form::close() !!}
@endif