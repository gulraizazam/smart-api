@push("css")
    <style>
        .treatment-filters .filterouterdiv {
            margin-right: 15px;
            margin-bottom: 10px;
        }
        .treatment-filters .filterouterdiv .form-control,
        .treatment-filters .filterouterdiv .select2-container {
            width: 150px !important;
            min-width: 150px !important;
        }
        .treatment-filters .datefromto .form-control {
            width: 100px !important;
            min-width: 100px !important;
        }
        .treatment-filters .datefromto {
            width: 200px !important;
        }
    </style>
@endpush

<div class="treatment-filters mt-2 mb-5">
    <div class="row mb-0 flex-column flex-sm-row align-items-end">

        <div class="filterouterdiv mb-0">
            <label>Scheduled:</label>
            <div class="input-daterange input-group to-from-datepicker datefromto">
                <input type="text" id="treatment_search_start" autocomplete="off" class="form-control filter-field datatable-input" name="date_from" placeholder="From">
                <div class="input-group-append" style="width: 0;">
                    <span class="input-group-text">
                        <i class="la la-ellipsis-h"></i>
                    </span>
                </div>
                <input type="text" id="treatment_search_end" autocomplete="off" class="form-control filter-field datatable-input" name="date_to" placeholder="To">
            </div>
        </div>

        <div class="filterouterdiv mb-0">
            <label>Service:</label>
            <select class="form-control filter-field select2" id="treatment_search_service"></select>
        </div>

        <div class="filterouterdiv mb-0">
            <label>Centre:</label>
            <select class="form-control filter-field select2" id="treatment_search_centre"></select>
        </div>

        <div class="filterouterdiv mb-0">
            <label>Status:</label>
            <select class="form-control filter-field select2" id="treatment_search_status"></select>
        </div>

        <div class="mt-2">
            <button type="button" class="btn btn-primary btn-sm mr-2" id="treatment-form-search">
                <i class="la la-search"></i> Search
            </button>
            <button type="button" class="btn btn-secondary btn-sm" id="treatment-reset-filters">
                <i class="la la-close"></i> Reset
            </button>
        </div>

    </div>
</div>
