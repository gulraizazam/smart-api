@if($package->is_refund == '0')
    @if(Gate::allows('patients_plan_edit'))
        <a class="btn btn-xs btn-info" href="{{ route('admin.plans.edit',[$package->id]) }}"
           data-target="#ajax_patient_packages_edit" data-toggle="modal">@lang('global.app_edit')</a>
    @endif
    @if(Gate::allows('patients_plan_destroy'))
        {!! Form::open(array(
            'style' => 'display: inline-block;',
            'method' => 'POST',
            'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
            'route' => ['admin.plans.destroy', $package->id])) !!}
        {!! Form::submit(trans('global.app_delete'), array('class' => 'btn btn-xs btn-danger')) !!}
        {!! Form::close() !!}
    @endif
@endif
@if(Gate::allows('patients_plan_manage'))
    <a class="btn btn-xs btn-success" href="{{ route('admin.plans.display',[$package->id]) }}"
       data-target="#ajax_patient_packages_display" data-toggle="modal">@lang('global.app_display')</a>
@endif
@if(Gate::allows('patients_plan_log'))
    <a class="btn btn-xs btn-success" href="{{ route('admin.plans.log',[$package->id,$id, 'web']) }}">@lang('global.app_log')</a>
@endif
@if(Gate::allows('patients_plan_sms_log'))
    <a href="{{ route('admin.packages.sms_logs',[$package->id])  }}" class="btn btn-xs btn-success"
       data-target="#patient_plan_sms_logs" data-toggle="modal">@lang('global.app_sms')</a>
@endif