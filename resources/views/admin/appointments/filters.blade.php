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

        /* Mobile Filter Toggle Styles */
        @media (max-width: 991px) {
            .all-filters-wrapper {
                display: none;
            }
            .mobile-filter-toggle {
                margin-bottom: 10px;
            }
            /* Hide advance button on mobile */
            .advance-search {
                display: none !important;
            }
            /* Show all filters expanded on mobile when opened */
            .all-filters-wrapper .advance-filters {
                display: block !important;
            }
            .all-filters-wrapper hr.advance-filters {
                display: block !important;
            }
        }

        @media (min-width: 992px) {
            .mobile-filter-toggle {
                display: none !important;
            }
        }

        .mobile-filter-toggle .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            padding: 12px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .filter-toggle-arrow {
            margin-left: auto;
            transition: transform 0.3s ease;
        }

        /* Match field sizes in advance filters */
        /* Created At should match Scheduled field size - reduced by 25% to 180px */
        .advance-filters .created-at-field .datefromto {
            width: 180px !important;
        }
        
        /* Standardize all dropdown field sizes - reduced by 25% from 170px to 128px */
        .filterouterdiv .form-control,
        .filterouterdiv .select2,
        .advance-filters .form-control,
        .advance-filters .select2,
        .advance-filters .select2-container,
        .advance-filters .doctor-field .form-control,
        .advance-filters .updated-by-field .form-control,
        .advance-filters .rescheduled-by-field .form-control,
        .advance-filters .appoint_search_status .form-control {
            width: 128px !important;
            min-width: 128px !important;
            max-width: 128px !important;
        }
        
        /* Date range fields - increased by 20% from 90px to 108px */
        .filterouterdiv .datefromto .form-control,
        .advance-filters .created-at-field .datefromto .form-control {
            width: 108px !important;
            min-width: 108px !important;
            max-width: 108px !important;
        }
        
        /* Date range container - increased by 20% from 180px to 216px */
        .filterouterdiv .datefromto,
        .advance-filters .created-at-field .datefromto {
            width: 216px !important;
        }

        /* Make patient search field wider by using space from removed phone filter */
        .filterouterdiv.patient-search-wider {
            flex: 1 1 auto;
            min-width: 170px;
            max-width: 300px;
        }
        
        .filterouterdiv.patient-search-wider .form-control,
        .filterouterdiv.patient-search-wider .select2-container {
            width: 100% !important;
            min-width: 170px !important;
            max-width: 300px !important;
        }
        
        .filterouterdiv.patient-search-wider .select2-container .select2-selection {
            width: 100% !important;
        }

        /* Reduce spacing between advance filter fields to 15px margin */
        .advance-filters > div[class*="col-"] {
            margin-right: 15px !important;
            margin-left: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        
        .advance-filters {
                margin-right: 15px;
        }

    </style>
@endpush

<div class="mt-2 mb-7">

    <!-- Mobile Filter Toggle Button (visible only on mobile) -->
    <div class="row mb-3 mobile-filter-toggle">
        <div class="col-12">
            <button class="btn btn-primary btn-block" onclick="toggleAllFilters();">
                <i class="fa fa-filter mr-2"></i>
                <span>Filters</span>
                <i class="filter-toggle-arrow fa fa-chevron-down"></i>
            </button>
        </div>
    </div>

    <!-- All Filters Wrapper -->
    <div class="all-filters-wrapper">

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


    <div class="row mb-0 flex-column flex-sm-row">

        <div class="filterouterdiv mb-0 position-relative patient-search-wider">
            <label>Patient Search:</label>
            <select class="form-control filter-field select2-patient-search" id="appointment_patient_id" onchange="SetPatient()">
            </select>
        </div>

        <div class="filterouterdiv  mb-0" >
            <label>Scheduled:</label>
            <div class="input-daterange input-group to-from-datepicker datefromto" >
                <input type="text" id="appoint_search_start" autocomplete="off" class="form-control filter-field datatable-input" name="created_start" placeholder="From" onchange="SetFromdate()">
                <div class="input-group-append" style="width: 0;">
                    <span class="input-group-text">
                        <i class="la la-ellipsis-h"></i>
                    </span>
                </div>
                <input type="text" id="appoint_appoint_end" autocomplete="off" class="form-control filter-field datatable-input" name="created_end" placeholder="To" onchange="SetTodate()">
            </div>
        </div>

       <div class="filterouterdiv  mb-0 appoint_search_status">
            <label>Service:</label>
            <select class="form-control filter-field" id="appoint_search_service" onchange="SetService()"></select>
        </div>

        <div class="filterouterdiv mb-0 center-filter">
            <label >Centre:</label>
            <select class="form-control filter-field select2" id="appoint_search_centre" onchange="SetCenter()"></select>
        </div>

        <div class="filterouterdiv  mb-0 appoint_search_status" >
            <label >Status:</label>
            <select class="form-control filter-field select2" id="appoint_search_status" onchange="SetStatus()"></select>
        </div>



        <div class="  mt-8" >

            @include('admin.partials.filter-buttons', ['custom_reset', $custom_reset])

        </div>

    </div>

    <hr class="advance-filters" style="display: none;">
    <div class="row mb-0 flex-column flex-sm-row advance-filters" style="display: none;">

        <div class="filterouterdiv mb-0 doctor-field">
            <label>Doctor:</label>
            <select class="form-control filter-field select2" id="appoint_search_doctor" onchange="SetDocId()"></select>
        </div>
        
        <div class="filterouterdiv mb-0 appoint_search_status">
            <label>Created By:</label>
            <select class="form-control filter-field select2" id="appoint_search_created_by" onchange="SetCreated()">
            </select>
        </div>
        
        <div class="filterouterdiv mb-0 created-at-field">
            <label>Created At:</label>
            <div class="input-daterange input-group to-from-datepicker datefromto">
                <input type="text" id="appoint_search_created_from" autocomplete="off" class="form-control filter-field datatable-input" name="created_from" placeholder="From" data-col-index="5" onchange="SetAdvanceFromdate()">
                <div class="input-group-append" style="width: 0;">
                    <span class="input-group-text">
                        <i class="la la-ellipsis-h"></i>
                    </span>
                </div>
                <input type="text" id="appoint_search_created_to" autocomplete="off" class="form-control filter-field datatable-input" name="created_to" placeholder="To" data-col-index="5" onchange="SetAdvanceTodate()">
            </div>
        </div>
        
        <div class="filterouterdiv mb-0 updated-by-field">
            <label>Updated By:</label>
            <select class="form-control filter-field select2" id="appoint_search_updated_by" onchange="SetUpdatedBy()">
            </select>
        </div>

        <div class="filterouterdiv mb-0 rescheduled-by-field">
            <label>Rescheduled By:</label>
            <select class="form-control filter-field select2" id="appoint_search_rescheduled_by" onchange="SetRescheduledBy()">
            </select>
        </div>

    </div>

    </div>
    <!-- End All Filters Wrapper -->

</div>
