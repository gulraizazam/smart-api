<div class="mt-2 mb-7">
    <div class="row mb-6">
        <div class="col-lg-3 mb-lg-0 mb-6" id="filter_patient_search_container">
            <label>Patient Search:</label>
            <input class="form-control filter-field search_patient" placeholder="Patient Search" autocomplete="off">
            <input type="hidden" class="filter-field search_field" id="search_patient">
            <span onclick="addUsers();" class="croxcli" style="padding-left: 0% !important; top:36px; right:22px; position: absolute;"><i class="fa fa-times" aria-hidden="true"></i></span>
            <div class="suggesstion-box" style="display: none;">
                <ul class="suggestion-list"></ul>
            </div>
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Voucher Type:</label>
            <select class="form-control filter-field select2" name="voucher_type" id="search_voucher_type">
            </select>
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Created at:</label>
            <div class="input-group">
                <input type="text" id="search_date_range" class="form-control filter-field" readonly placeholder="Select Date Range">
            </div>
            <input type="hidden" id="search_created_from">
            <input type="hidden" id="search_created_to">
        </div>

        <div class="col-lg-2 mb-lg-0 mt-8">
            @include('admin.partials.filter-buttons')
        </div>
    </div>
</div>
