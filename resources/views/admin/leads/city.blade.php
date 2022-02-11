@if(Gate::allows('leads_city'))
    <a href="{{ route('admin.leads.cities') }}"
       class="city" data-type="select"
       data-pk="{{ $lead->lead_id }}" data-value="@if($lead->city_id){{ $lead->city->id }}@else{{''}}@endif"
       data-source="{{ route('admin.leads.cities') }}" data-title="Change City">
        @if($lead->city_id){{ $lead->city->name }}@else{{''}}@endif
    </a>
@else
    @if($lead->city_id){{ $lead->city->name }}@else{{''}}@endif
@endif
