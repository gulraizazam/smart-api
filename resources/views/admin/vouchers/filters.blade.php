<div class="mt-2 mb-7">
     <div class="row mb-6">

        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Patient:</label>
            <input class="form-control filter-field" id="search_patient" placeholder="Patient Search" autocomplete="off">
            <span onclick="$('.clear-patient-filter').click();" class="croxcli" style="padding-left: 0% !important; top:36px; right:22px; position: absolute;"><i class="fa fa-times" aria-hidden="true"></i></span>
            <div class="suggesstion-box-patient-filter" style="display: none;">
                <ul class="suggestion-list"></ul>
            </div>
            <input type="hidden" id="search_patient_id">
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Voucher Type:</label>
            <select class="form-control filter-field select2" name="voucher_type" id="search_voucher_type">
            </select>
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Created From:</label>
            <div class="input-daterange input-group to-from-datepicker">
                <input type="text" id="search_created_from" autocomplete="off" class="form-control filter-field datatable-input" name="created_from" placeholder="From">
            </div>
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Created To:</label>
            <div class="input-daterange input-group to-from-datepicker">
                <input type="text" id="search_created_to" autocomplete="off" class="form-control filter-field datatable-input" name="created_to" placeholder="To">
            </div>
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6 mt-6">

            @include('admin.partials.filter-buttons')

        </div>
    </div>

</div>
