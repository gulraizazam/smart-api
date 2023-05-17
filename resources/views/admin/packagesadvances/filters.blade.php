@push("css")
    <style>

       .position-relative{
            position: relative;
        }

        .filterouterdiv .croxcli {
            position: absolute;
            bottom: 0px;
            right: 0;
            padding-left: 11px !important;
            padding: 9px 11px;
        }

    </style>
@endpush

<div class="mt-2 mb-7">
    <div class="row mb-0 flex-column flex-sm-row">
        <div class="filterouterdiv mb-0 position-relative">
            <label>Patient Search:</label>
            <input class="form-control filter-field patient_search_id" id="patient_search_id" onchange="patientSearch()">
            <input type="hidden" id="add_patient_id" name="patient_id">
            <span onclick="addUsers()" class="croxcli" ><i class="fa fa-times" aria-hidden="true"></i></span>
            <div class="suggesstion-box" style="display: none;">
                <ul class="suggestion-list w-100"></ul>
            </div>
        </div>

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

        <div class="col-md-3 mb-lg-0 mt-8">

            @include('admin.partials.filter-buttons', ['custom_reset' => $custom_reset])

        </div>

    </div>

</div>
