<div class="mt-2 mb-7">

    <div class="row mb-6">

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Patient Id:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter ID" id="search_id" />
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6" id="patient_id">
            <label>Name:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter Name" id="search_name" />
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Patient Name:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter Patient Name" id="search_patient_name" />
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Create at:</label>
            <div class="input-daterange input-group to-from-datepicker" >
                <input type="text" id="search_created_from" class="form-control filter-field datatable-input" name="created_from" placeholder="From" data-col-index="5">
                <div class="input-group-append">
                    <span class="input-group-text">
                        <i class="la la-ellipsis-h"></i>
                    </span>
                </div>
                <input type="text" id="search_created_to" class="form-control filter-field datatable-input" name="created_to" placeholder="To" data-col-index="5">
            </div>
        </div>

    </div>


    <div class="row">
        <div class="col-md-12">

            @include('admin.partials.filter-buttons')

        </div>
    </div>
</div>
