<div class="mt-2 mb-7">
    <div class="row align-items-center">
        <div class="advance-search col-md-12 col-lg-12 col-xl-12">
            <div class="row align-items-center mr-2" style="float: right;">
                <div class="row">
                    <button class="btn btn-sm btn-default ml-2 mt-10" onclick="advanceFilters();">
                        <i class="advance-arrow fa fa-caret-right"></i>
                        Advance
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="row mb-6">
        <div class="col-lg-2 mb-lg-0 mb-6" id="patient_id">
            <label>Patient Search:</label>
            <input style="width: 110%;" class="form-control filter-field patient_id"  placeholder="Patients Search">
            <input type="hidden" class="filter-field search_field" id="search_patient_id">
            <span onclick="addUsers()" class="croxcli" style="position:absolute; padding-left: 0% !important; top:37px; right:3px;"><i class="fa fa-times" aria-hidden="true"></i></span>
            <div class="suggesstion-box" style="display: none;">
                <ul class="suggestion-list"></ul>
            </div>
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Name:</label>
            <input class="form-control filter-field" id="search_name" placeholder="Enter Name">
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Email:</label>
            <input class="form-control filter-field" id="search_email" placeholder="Enter Email">
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Gender:</label>
            <select class="form-control filter-field select2" id="search_gender">
            </select>
        </div>
    </div>
    <div class="row mb-8 advance-filters" style="display: none;">
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
        @if(\Illuminate\Support\Facades\Gate::allows("view_inactive_patients"))
            <div class="col-lg-3 mb-lg-0">
                <label>Status:</label>
                <select class="form-control filter-field select2" id="search_status">
                </select>
            </div>
        @endif
    </div>
    <div class="row">
        <div class="col-md-10">
            @include('admin.partials.filter-buttons')
        </div>
    </div>
</div>
