@if($package->is_refund == '0')
    @if($package->active)
        @if(Gate::allows('patients_plan_inactive'))
            {!! Form::open(array(
            'style' => 'display: inline-block;',
            'method' => 'POST',
            'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
            'route' => ['admin.plans.inactive', $package->id])) !!}
            {!! Form::submit(trans('global.app_inactive'), array('class' => 'btn btn-xs btn-warning')) !!}
            {!! Form::close() !!}
        @else
            {{ 'Active' }}
        @endif
    @else
        @if(Gate::allows('patients_plan_active'))
            {!! Form::open(array(
            'style' => 'display: inline-block;',
            'method' => 'POST',
            'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
            'route' => ['admin.plans.active', $package->id])) !!}
            {!! Form::submit(trans('global.app_active'), array('class' => 'btn btn-xs btn-primary')) !!}
            {!! Form::close() !!}
        @else
            {{ 'Inactive' }}
        @endif
    @endif
@endif
