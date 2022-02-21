@if(Gate::allows('patients_edit'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.patients.edit',[$patient->id]) }}" data-target="#ajax_patients" data-toggle="modal">@lang('global.app_edit')</a>
@endif
@if(Gate::allows('patients_destroy'))
    {!! Form::open(array(
        'style' => 'display: inline-block;',
        'method' => 'DELETE',
        'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
        'route' => ['admin.patients.destroy', $patient->id])) !!}
    {!! Form::submit(trans('global.app_delete'), array('class' => 'btn btn-xs btn-danger')) !!}
    {!! Form::close() !!}
@endif
@if(Gate::allows('patients_manage'))
    <a class="btn btn-xs btn-success" href="{{ route('admin.patients.preview',[$patient->id]) }}">@lang('global.app_view')</a>
@endif