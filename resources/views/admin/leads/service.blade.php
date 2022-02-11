@if(Gate::allows('leads_manage'))
    {{--<a href="{{ route('admin.leads.treatments') }}"--}}
       {{--class="treatment" data-type="select"--}}
       {{--data-pk="{{ $lead->lead_id }}" data-value="@if($lead->treatment_id){{ $lead->treatment->id }}@else{{''}}@endif"--}}
       {{--data-source="{{ route('admin.leads.treatments') }}" data-title="Change Treatment">--}}
        {{--@if($lead->treatment_id){{ $lead->treatment->name }}@else{{''}}@endif--}}
    {{--</a>--}}
@else
@endif
@if($lead->service_id){{ $lead->service->name }}@else{{''}}@endif
