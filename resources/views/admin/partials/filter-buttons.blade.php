<div class="input-icon mb-1">
    <button class="btn btn-primary btn-primary--icon" id="apply-filters">
        <i class="la la-search"></i>
        Search
    </button>

    @if(isset($custom_reset) && $custom_reset != '')

        <button class="btn btn-secondary btn-secondary--icon ml-3" onclick="resetInvoiceFilters();" id="reset-filters">
            <i class="la la-close"></i>
            Reset
        </button>
    @else
        <button class="btn btn-secondary btn-secondary--icon ml-3" onclick="resetFilters();" id="reset-filters">
            <i class="la la-close"></i>
            Reset
        </button>
    @endif


</div>
