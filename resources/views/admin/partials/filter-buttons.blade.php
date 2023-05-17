@php
    $search_id = 'apply-filters';
    if (isset($customId)) {
         $search_id = $customId;
    }
@endphp
<div class="input-icon mb-0" style="width: 215px;">
    <button class="btn btn-primary btn-primary--icon" id="{{$search_id}}">Search</button>
    @if(isset($custom_reset) && $custom_reset != '')
        <button class="btn btn-secondary btn-secondary--icon ml-3" onclick="resetCustomFilters();" id="reset-filters">Reset</button>
    @else
        <button class="btn btn-secondary btn-secondary--icon ml-3" onclick="resetFilters();" id="reset-filters">Reset</button>
    @endif
</div>
