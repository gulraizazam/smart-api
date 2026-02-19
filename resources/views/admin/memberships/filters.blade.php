<div class="mt-2 mb-7">
    <div class="row mb-6 align-items-end">
        <div class="col-lg-2 mb-lg-0 mb-2 position-relative">
            <label>Patient Search:</label>
            <input type="text" class="form-control filter-field membership_patient_search" id="membership_patient_search" placeholder="Search by name or ID" autocomplete="off">
            <input type="hidden" id="search_patient_id" value="">
            <span class="croxcli membership-croxcli" style="display: none; position: absolute; right: 10px; top: 32px; cursor: pointer;"><i class="la la-times"></i></span>
            <div class="suggesstion-box membership-suggestion-box" style="display: none; position: absolute; z-index: 1000; background: #fff; border: 1px solid #ddd; width: 100%; max-height: 200px; overflow-y: auto;">
                <ul class="suggestion-list membership-suggestion-list" style="list-style: none; padding: 0; margin: 0;"></ul>
            </div>
        </div>
        <div class="col-lg-1 mb-lg-0 mb-2">
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
            <label>Assigned Status</label>
            <select class="form-control filter-field select2" id="search_assigned_status">
                <option value="">Select</option>
                <option value="1">Assigned</option>
                <option value="0">Not Assigned</option>
            </select>
        </div>
        <div class="col-lg-2 mb-lg-0 mb-2">
            <label>Membership Status</label>
            <select class="form-control filter-field select2" id="search_membership_status">
                <option value="">Select</option>
                <option value="1">Active</option>
                <option value="0">Expired</option>
            </select>
        </div>
        <div class="col-lg-3 mb-lg-0 mb-2">
            @include('admin.partials.filter-buttons')
        </div>
    </div>
</div>
