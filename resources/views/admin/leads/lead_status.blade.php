{{--<a href="{{ route('admin.leads.lead_statuses') }}"--}}
   {{--class="lead_status" data-type="select"--}}
   {{--data-pk="{{ $lead->lead_id }}" data-value="@if($lead->lead_status_id){{ $lead->lead_status->id }}@else{{''}}@endif"--}}
   {{--data-source="{{ route('admin.leads.lead_statuses') }}" data-title="Select a Status">--}}
    {{--@if($lead->lead_status_id){{ $lead->lead_status->name }}@else{{''}}@endif--}}
{{--</a>--}}

@if(Gate::allows('leads_lead_status'))
    <a id="lead{{ $lead->lead_id }}" href="{{ route('admin.leads.showleadstatus',['id' => $lead->lead_id]) }}" data-target="#ajax" data-toggle="modal">@if($lead->lead_id){{ $lead_status_data->name }}@else{{''}}@endif</a>
@else
    @if($lead->lead_id){{ $lead_status_data->name }}@else{{''}}@endif
@endif
