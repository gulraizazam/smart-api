@if(Gate::allows('users_edit'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.users.edit',[$user->id]) }}" data-target="#ajax_users" data-toggle="modal">@lang('global.app_edit')</a>
@endif
@if(Gate::allows('users_change_password'))
    <a class="btn btn-xs btn-warning" href="{{ route('admin.users.change_password',['id' => $user->id]) }}" data-target="#ajax_users" data-toggle="modal">@lang('global.users.fields.change_password')</a>
@endif
@if(Gate::allows('users_destroy'))
    {!! Form::open(array(
        'style' => 'display: inline-block;',
        'method' => 'DELETE',
        'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
        'route' => ['admin.users.destroy', $user->id])) !!}
    {!! Form::submit(trans('global.app_delete'), array('class' => 'btn btn-xs btn-danger')) !!}
    {!! Form::close() !!}
@endif