<div class="mt-2 mb-7">

    <div class="row mb-6">

        <!-- <div class="col-lg-4 mb-lg-0 mb-6">
            <label>City:</label>
            <select onchange="loadLocations($(this).val(), 'consultancy');" class="form-control select2" id="consultancy_city_filter"></select>
        </div> -->

        <div class="col-lg-6 mb-lg-0 mb-6">
            <label>Centre:</label>
            <select onchange="loadDoctors($(this).val(), 'consultancy');" class="form-control select2" id="consultancy_location_filter"></select>
        </div>

        <div class="col-lg-6 mb-lg-0 mb-6">
            <label>Doctor:</label>
            <select onchange="ConsultancyDoctorListener($(this).val());" class="form-control select2" id="consultancy_doctor_filter" disabled></select>
        </div>

    </div>

</div>
