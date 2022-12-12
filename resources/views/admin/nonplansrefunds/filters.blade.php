<div class="mt-2 mb-7">

    <div class="row mb-6">

        <div class="col-lg-4 mb-lg-0 mb-6">
            <label>Patient ID:</label>
            <input type="text" id="search_patient_id" class="form-control" placeholder="Enter ID">
        </div>

        <div class="col-lg-4 mb-lg-0 mb-6 select2-search">
            <label>Patient:</label>
            <select class="form-control filter-field select2 patient_id" id="search_patient">
                <option value="">Select Patient</option>
            </select>
        </div>

        <div class="col-lg-4 mb-lg-0 mt-9">
            @include('admin.partials.filter-buttons', ['custom_reset', $custom_reset])
        </div>


    </div>

</div>
