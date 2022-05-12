<div class="mt-2 mb-7">

    <div class="row mb-6 plan-filters">

        <div class="col-lg-1 mb-lg-0 mb-6">
            <label style="width: 122%;">Patient Id:</label>
            <input style="width: 122%;" type="text" class="form-control filter-field" placeholder="Enter ID" id="search_id" />
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6" id="patient_id">
            <label>Patient Search:</label>
            <select style="width: 120%;" class="form-control filter-field patient_id" id="search_patient_id"></select>
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6 search_input">
            <label>Plans:</label>
            <select class="form-control filter-field package_id" id="search_plan_id"></select>
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Centre:</label>
            <select class="form-control filter-field select2" id="search_location_id"></select>
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Status:</label>
            <select class="form-control filter-field select2" id="search_status">
            </select>
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Create at:</label>
            <div class="input-daterange input-group to-from-datepicker" style="width: 158%;">
                <input type="text" id="search_created_from" autocomplete="off" class="form-control filter-field datatable-input" name="created_from" placeholder="From" data-col-index="5">
                <div class="input-group-append">
                    <span class="input-group-text">
                        <i class="la la-ellipsis-h"></i>
                    </span>
                </div>
                <input type="text" id="search_created_to" autocomplete="off" class="form-control filter-field datatable-input" name="created_to" placeholder="To" data-col-index="5">
            </div>
        </div>

    </div>

    <div class="row">
        <div class="col-md-10">

            @include('admin.partials.filter-buttons')

        </div>
    </div>
</div>
