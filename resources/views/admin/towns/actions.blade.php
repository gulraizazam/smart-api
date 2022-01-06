@if(Gate::allows('towns_edit'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.towns.edit',[$town->id]) }}" data-target="#ajax_towns" data-toggle="modal">@lang('global.app_edit')</a>
@endif
@if(Gate::allows('towns_destroy'))
    {!! Form::open(array(
        'style' => 'display: inline-block;',
        'method' => 'DELETE',
        'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
        'route' => ['admin.towns.destroy', $town->id])) !!}
    {!! Form::submit(trans('global.app_delete'), array('class' => 'btn btn-xs btn-danger')) !!}
    {!! Form::close() !!}
@endif