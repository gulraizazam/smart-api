<div class="mt-2 mb-7">

    <div class="row mb-6">

       <div class="col-lg-6 mb-lg-0 mb-6">
            <label>Patient:</label>
            <input class="form-control filter-field search_patient">
            <input type="hidden" class="filter-field search_field" id="search_patient_id">
            <span onclick="addUsers();" class="croxcli" style="padding-left: 0% !important; top:36px; right:22px; position: absolute;"><i class="fa fa-times" aria-hidden="true"></i></span>
            <div class="suggesstion-box" style="display: none;">
                <ul class="suggestion-list"></ul>
            </div>
        </div>

        <div class="col-lg-6 mb-lg-0 mb-6">
            <label>Product:</label>
            <select class="form-control filter-field select2 product_id" name="search_product_id"  id="search_product_id">

            </select>
        </div>
    </div>
    <div class="row">
        <div class="col-md-10">

        @php
            $search_id = 'apply-filters';
            if (isset($customId)) {
                $search_id = $customId;
            }
        @endphp
    <div class="input-icon mb-1" style="width: 215px;">
        <button class="btn btn-primary btn-primary--icon" id="{{$search_id}}">
            <i class="la la-search"></i>
            Search
        </button>

        @if(isset($custom_reset) && $custom_reset != '')

            <button class="btn btn-secondary btn-secondary--icon ml-3" onclick="resetCustomFilters();" id="reset-filters">
                <i class="la la-close"></i>
                Reset
            </button>
        @else
            <button class="btn btn-secondary btn-secondary--icon ml-3" onclick="resetFilterOrder();" id="reset-filters">
                <i class="la la-close"></i>
                Reset
            </button>
        @endif


    </div>


        </div>
    </div>
</div>


