<div class="mt-2 mb-7">
    <div class="row mb-6">
        <div class="col-lg-3 mb-lg-0 mb-6" id="patient_id">

            <label>Patient Search:</label>
            <input class="form-control filter-field search_patient">
            <input type="hidden" class="filter-field search_field" id="search_patient">
            <span onclick="addUsers();" class="croxcli" style="padding-left: 0% !important; top:36px; right:22px; position: absolute;"><i class="fa fa-times" aria-hidden="true"></i></span>
            <div class="suggesstion-box" style="display: none;">
                <ul class="suggestion-list"></ul>
            </div>

        </div>
        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Centres:</label>
            <select class="form-control filter-field select2" id="search_centres">
            </select>
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Plans:</label>
            <select class="form-control filter-field package_id" id="search_plans">
            </select>
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Create at:</label>
            <div class="input-group input-daterange to-from-datepicker">
                <input type="text" id="search_created_from" class="form-control filter-field datatable-input" name="created_from" placeholder="From">
                <div class="input-group-append">
                    <span class="input-group-text">
                        <i class="la la-ellipsis-h"></i>
                    </span>
                </div>
                <input type="text" id="search_created_to" class="form-control filter-field datatable-input" name="created_to" placeholder="To">
            </div>
        </div>
        <div class="col-lg-2 mb-lg-0 mt-8">
        @include('admin.partials.filter-buttons')
        </div>
    </div>

</div>
