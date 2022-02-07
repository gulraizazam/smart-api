@if($package->is_refund == '0')
    @if(Gate::allows('plans_edit'))
        <a class="btn btn-xs btn-info" href="{{ route('admin.packages.edit',[$package->id]) }}" data-target="#ajax_packages" data-toggle="modal">@lang('global.app_edit')</a>
    @endif
    @if(Gate::allows('plans_destroy'))
        {!! Form::open(array(
            'style' => 'display: inline-block;',
            'method' => 'DELETE',
            'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
            'route' => ['admin.packages.destroy', $package->id])) !!}
        {!! Form::submit(trans('global.app_delete'), array('class' => 'btn btn-xs btn-danger')) !!}
        {!! Form::close() !!}
    @endif
@endif
@if(Gate::allows('plans_manage'))
    <a class="btn btn-xs btn-success" href="{{ route('admin.packages.display',[$package->id]) }}" data-target="#ajax_packages" data-toggle="modal">@lang('global.app_display')</a>
@endif
@if(Gate::allows('plans_log'))
    <a class="btn btn-xs btn-success" href="{{ route('admin.packages.log',[$package->id, 'web']) }}">@lang('global.app_log')</a>
@endif
@if(Gate::allows('plans_sms_log'))
    <a href="{{ route('admin.packages.sms_logs',[$package->id])  }}" class="btn btn-xs btn-success"
       data-target="#plan_sms_logs" data-toggle="modal">@lang('global.app_sms')</a>
@endif