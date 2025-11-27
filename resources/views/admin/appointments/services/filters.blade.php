<div class="mt-2 mb-7">

    <div class="row mb-6">

        <!-- <div class="col-lg-3 mb-lg-0 mb-6">
            <label>City:</label>
            <select class="form-control" id="treatment_city_filter"></select>
        </div> -->

        @php
            $userCentres = $userCentres ?? [];
            $showDropdown = count($userCentres) > 1;
        @endphp

        @if($showDropdown)
        <div class="col-lg-4 mb-lg-0 mb-6">
            <label>Centre:</label>
            <select class="form-control" id="treatment_location_filter"></select>
        </div>
        @else
        <div class="col-lg-4 mb-lg-0 mb-6" style="display: none;">
            <label>Centre:</label>
            <select class="form-control" id="treatment_location_filter"></select>
        </div>
        @endif

        <!-- <div class="col-lg-4 mb-lg-0 mb-6">
            <label>Doctor:</label>
            <select class="form-control" id="treatment_doctor_filter" disabled></select>
        </div>

        <div class="col-lg-4 mb-lg-0 mb-6">
            <label>Resource:</label>
            <select class="form-control" id="treatment_resource_filter" disabled></select>
        </div> -->

    </div>

</div>

<script>
    // Pass user centres to JavaScript for treatment tab
    window.userCentres = window.userCentres || @json($userCentres);
</script>
