<div class="mt-2 mb-7">

    <div class="row mb-6 plan-filters align-items-end">

        <!-- <div class="col-lg-1 mb-lg-0 mb-6">
            <label style="width: 122%;">Patient Id:</label>
            <input style="width: 122%;" type="text" class="form-control filter-field" placeholder="Enter ID" id="search_id" />
        </div> -->

        <div class="col-lg-2 mb-lg-0 mb-6" id="patient_id">
            <label>Patient Search:</label>
            <input style="width: 110%;" class="form-control filter-field patient_id"  placeholder="Patients Search">
            <input type="hidden" class="filter-field search_field" id="search_patient_id">
            <span onclick="addUsers()" class="croxcli" style="position:absolute; padding-left: 0% !important; top:37px; right:3px;"><i class="fa fa-times" aria-hidden="true"></i></span>
            <div class="suggesstion-box" style="display: none;">
                <ul class="suggestion-list"></ul>
            </div>
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6 search_input">
            <label>Plan ID:</label>
            <select class="form-control filter-field package_id" id="search_plan_id"></select>
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Centre:</label>
            <select class="form-control filter-field select2" id="search_location_id"></select>
        </div>
        @if(\Illuminate\Support\Facades\Gate::allows("view_inactive_plans"))
            <div class="col-lg-2 mb-lg-0 mb-6">
                <label>Status:</label>
                <select class="form-control filter-field select2" id="search_status">
                </select>
            </div>
        @endif
        <div class="col-lg mb-lg-0 mb-6">
            <label>Created at:</label>
            <div class="input-daterange input-group to-from-datepicker">
                <input type="text" id="search_created_from" autocomplete="off" class="form-control filter-field datatable-input" name="created_from" placeholder="From" data-col-index="5">
                <div class="input-group-append">
                    <span class="input-group-text">
                        <i class="la la-ellipsis-h"></i>
                    </span>
                </div>
                <input type="text" id="search_created_to" autocomplete="off" class="form-control filter-field datatable-input" name="created_to" placeholder="To" data-col-index="5">
            </div>
        </div>
        <div class="col-lg-2 mb-lg-0 mb-6 pl-0">
            @include('admin.partials.filter-buttons')
        </div>
    </div>

</div>
