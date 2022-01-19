@if(Gate::allows('centre_targets_edit'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.centre_targets.edit',[$centretarget->id]) }}" data-target="#ajax_centretarget" data-toggle="modal">@lang('global.app_edit')</a>
@endif
@if(Gate::allows('centre_targets_manage'))
    <a class="btn btn-xs btn-success" href="{{ route('admin.centre_targets.display',[$centretarget->id]) }}" data-target="#ajax_centre_display" data-toggle="modal">@lang('global.app_display')</a>
@endif
@if(Gate::allows('centre_targets_destroy'))
    {!! Form::open(array(
        'style' => 'display: inline-block;',
        'method' => 'DELETE',
        'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
        'route' => ['admin.centre_targets.destroy', $centretarget->id])) !!}
    {!! Form::submit(trans('global.app_delete'), array('class' => 'btn btn-xs btn-danger')) !!}
    {!! Form::close() !!}
@endif