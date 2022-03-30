<div class="mt-2 mb-7">

    <div class="row mb-6">

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Patient ID:</label>
            <input class="form-control filter-field" id="search_patient_id" name="patient_id">
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6" id="patient_id">
            <label>Patient:</label>
            <select class="form-control patient_search_id" id="search_patient"></select>
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Create at:</label>
            <div class="input-daterange input-group to-from-datepicker" >
                <input type="text" id="search_created_from" autocomplete="off" class="form-control filter-field datatable-input" name="created_from" placeholder="From" data-col-index="5">
                <div class="input-group-append">
                    <span class="input-group-text">
                        <i class="la la-ellipsis-h"></i>
                    </span>
                </div>
                <input type="text" id="search_created_to" autocomplete="off" class="form-control filter-field datatable-input" name="created_to" placeholder="To" data-col-index="5">
            </div>
        </div>

        <div class="col-md-3 mb-lg-0 mt-8">

            @include('admin.partials.filter-buttons', ['custom_reset' => $custom_reset])

        </div>

    </div>

</div>
