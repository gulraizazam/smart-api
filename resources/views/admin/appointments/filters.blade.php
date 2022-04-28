@push("css")
    <style>
        .service-filter .select2 {
            width: 135% !important;
        }
        .type-filter .select2 {
            margin-left: 4%;
            width: 80% !important;
        }

        .doctor-filter .select2 {
            margin-left: -27%;
            width: 80% !important
        }
        .center-filter .select2 {
            margin-left: -89%;
            width: 195% !important;
        }

    </style>
@endpush

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

        <div class="col-lg-1 mb-lg-0 mb-6" id="patient_id">
            <label style="width: 127%">ID:</label>
            <input style="width: 127%" type="text" class="form-control filter-field " id="appoint_search_id" placeholder="Patient ID">
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Patient:</label>
            <input style="width: 70%;" type="text" class="form-control filter-field" id="appoint_search_patient" placeholder="Patient Name">
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6" style="margin-left: -6%;">
            <label>Phone:</label>
            <input style="width: 65%;" type="text" oninput="phoneField(this);" id="appoint_search_phone" placeholder="Phone No." class="form-control filter-field">
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6" style="margin-left: -6.8%;">
            <label>Scheduled:</label>
            <div class="input-daterange input-group to-from-datepicker" style="width: 112%;">
                <input type="text" id="appoint_search_start" autocomplete="off" class="form-control filter-field datatable-input" name="created_start" placeholder="From">
                <div class="input-group-append" style="width: 0;">
                    <span class="input-group-text">
                        <i class="la la-ellipsis-h"></i>
                    </span>
                </div>
                <input type="text" id="appoint_appoint_end" autocomplete="off" class="form-control filter-field datatable-input" name="created_end" placeholder="To" >
            </div>
        </div>

        <div class="col-lg-1 mb-lg-0 mb-6 service-filter">
            <label>Service:</label>
            <select class="form-control filter-field select2" id="appoint_search_service"></select>
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6 type-filter">
            <label>Type:</label>
            <select class="form-control filter-field select2" id="appoint_search_type"></select>

        </div>

        <div class="col-lg-2 mb-lg-0 mb-6 doctor-filter">
            <label style="margin-left: -27%;">Doctor:</label>
            <br>
            <select class="form-control filter-field select2" id="appoint_search_doctor"></select>
        </div>

        <div class="col-lg-1 mb-lg-0 mb-6 center-filter">
            <label style="margin-left: -89%;">Centre:</label>
            <br>
            <select class="form-control filter-field select2" id="appoint_search_centre"></select>
        </div>

    </div>

    <hr class="advance-filters" style="display: none;">
    <div class="row mb-8 advance-filters" style="display: none;">

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Region:</label>
            <select class="form-control filter-field select2" id="appoint_search_region"></select>
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>City:</label>
            <select class="form-control filter-field select2" id="appoint_search_city"></select>
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Status:</label>
            <select class="form-control filter-field select2" id="appoint_search_status"></select>
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Consultancy Type:</label>
            <select class="form-control filter-field select2" id="appoint_search_consultancy_type"></select>
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6 mt-6">
            <label>Create At:</label>
            <div class="input-daterange input-group to-from-datepicker" >
                <input type="text" id="appoint_search_created_from" autocomplete="off" class="form-control filter-field datatable-input" name="created_from" placeholder="From" data-col-index="5">
                <div class="input-group-append" style="width: 0;">
                    <span class="input-group-text">
                        <i class="la la-ellipsis-h"></i>
                    </span>
                </div>
                <input type="text" id="appoint_search_created_to" autocomplete="off" class="form-control filter-field datatable-input" name="created_to" placeholder="To" data-col-index="5">
            </div>
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6 mt-6">
            <label>Created By:</label>
            <select class="form-control filter-field select2" id="appoint_search_created_by">
            </select>
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6 mt-6">
            <label>Updated By:</label>
            <select class="form-control filter-field select2" id="appoint_search_updated_by">
            </select>
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6 mt-6">
            <label>Rescheduled By:</label>
            <select class="form-control filter-field select2" id="appoint_search_rescheduled_by">
            </select>
        </div>

    </div>

    <div class="row">
        <div class="col-md-10">

            @include('admin.partials.filter-buttons', ['custom_reset', $custom_reset])

        </div>
    </div>
</div>
