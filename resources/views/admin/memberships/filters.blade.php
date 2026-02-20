<div class="mt-2 mb-7">
    <!-- Main Filters Row -->
    <div class="row mb-2 align-items-end" style="gap: 0.5rem;">
        <div class="col mb-0 position-relative" style="flex: 0 0 17%; max-width: 17%; padding: 0 5px;">
            <label>Patient Search:</label>
            <input type="text" class="form-control" id="membership_patient_search_input" placeholder="Search by name or phone" autocomplete="off">
            <input type="hidden" id="search_patient_id" class="filter-field" value="">
            <div id="membership_patient_suggestions" class="position-absolute w-100" style="display: none; z-index: 1000; background: #fff; border: 1px solid #ddd; max-height: 200px; overflow-y: auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
        </div>
        <div class="col mb-0" style="flex: 0 0 8%; max-width: 8%; padding: 0 5px;">
            <label>Code:</label>
            <input class="form-control filter-field" id="search_code_name" placeholder="Code">
        </div>
        <div class="col mb-0" style="flex: 0 0 13%; max-width: 13%; padding: 0 5px;">
            <label>Membership Type:</label>
            <select class="form-control filter-field select2" id="search_membership_type">
                <option value="">Select</option>
                <option value="4">Student Membership</option>
                <option value="3">Gold Membership</option>
            </select>
        </div>
        <div class="col mb-0" style="flex: 0 0 10%; max-width: 10%; padding: 0 5px;">
            <label>Status</label>
            <select class="form-control filter-field select2" id="search_membership_status">
                <option value="">Select</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="expired">Expired</option>
            </select>
        </div>
        <div class="col mb-0" style="flex: 0 0 11%; max-width: 11%; padding: 0 5px;">
            <label>Location</label>
            <select class="form-control filter-field select2" id="search_location_id">
                <option value="">All</option>
            </select>
        </div>
        <div class="col mb-0" style="flex: 0 0 11%; max-width: 11%; padding: 0 5px;">
            <label>Sold By</label>
            <select class="form-control filter-field select2" id="search_sold_by">
                <option value="">All</option>
            </select>
        </div>
        <div class="col mb-0" style="flex: 0 0 15%; max-width: 15%; padding: 0 5px;">
            <label>Assigned Date</label>
            <input type="text" class="form-control filter-field" id="search_assigned_at" placeholder="Select Date Range" readonly>
        </div>
        <div class="col mb-0" style="flex: 0 0 auto; padding: 0 5px; margin-top: 22px;">
            @include('admin.partials.filter-buttons')
        </div>
    </div>
</div>
