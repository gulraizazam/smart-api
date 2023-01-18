<div class="mt-2 mb-7">

    <div class="row mb-6">

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>City:</label>
            <select onchange="loadLocations($(this).val(), 'treatment');" class="form-control" id="treatment_city_filter"></select>
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Centre:</label>
            <select onchange="loadDoctors($(this).val(), 'treatment');" class="form-control" id="treatment_location_filter"></select>
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Doctor:</label>
            <select onchange="treatmentDoctorListener($(this).val());" class="form-control select2" id="treatment_doctor_filter"></select>
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Resource:</label>
            <select onchange="machineListener($(this).val());" class="form-control select2" id="treatment_resource_filter"></select>
        </div>

    </div>

</div>
