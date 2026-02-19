<div class="mt-2 mb-7">
    <div class="row mb-6 align-items-end">
        <div class="col-lg-2 mb-lg-0 mb-2 position-relative">
            <label>Patient Search:</label>
            <input type="text" class="form-control" id="membership_patient_search_input" placeholder="Search by name or phone" autocomplete="off">
            <input type="hidden" id="search_patient_id" class="filter-field" value="">
            <div id="membership_patient_suggestions" class="position-absolute w-100" style="display: none; z-index: 1000; background: #fff; border: 1px solid #ddd; max-height: 200px; overflow-y: auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
        </div>
        <div class="col mb-lg-1 mb-0" style="flex: 0 0 13%; max-width: 13%;margin-bottom:0px !important">
            <label>Code:</label>
            <input class="form-control filter-field" id="search_code_name" placeholder="Code">
        </div>
        <div class="col-lg-2 mb-lg-0 mb-2">
            <label>Membership Type:</label>
            <select class="form-control filter-field select2" id="search_membership_type">
                <option value="">Select</option>
                <option value="4">Student Membership</option>
                <option value="3">Gold Membership</option>
            </select>
        </div>
        <div class="col-lg-2 mb-lg-0 mb-2">
            <label>Status</label>
            <select class="form-control filter-field select2" id="search_membership_status">
                <option value="">Select</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="expired">Expired</option>
            </select>
        </div>
        <div class="col-lg-2 mb-lg-0 mb-2">
            <label>Location</label>
            <select class="form-control filter-field select2" id="search_location_id">
                <option value="">All</option>
            </select>
        </div>
        <div class="col-lg-2 mb-lg-0 mb-2">
            <label>Sold By</label>
            <select class="form-control filter-field select2" id="search_sold_by">
                <option value="">All</option>
            </select>
        </div>
        <div class="col-lg-2 mb-lg-0 mb-2 mt-6">
            @include('admin.partials.filter-buttons')
        </div>
    </div>
</div>
