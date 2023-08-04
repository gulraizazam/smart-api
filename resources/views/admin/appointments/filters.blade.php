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

        <div class="filterouterdiv mb-0 position-relative">
            <label>Patient Search:</label>
            <input class="form-control filter-field appointment_patient_id" onchange="SetPatient()" placeholder="Patients Search">
            <input type="hidden" class="filter-field search_field" id="appointment_patient_id">
            <span onclick="addUsers()" class="croxcli" ><i class="fa fa-times" aria-hidden="true"></i></span>
            <div class="suggesstion-box" style="display: none;">
                <ul class="suggestion-list w-100"></ul>
            </div>
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
            <select class="form-control filter-field select2" id="appoint_search_service" onchange="SetService()"></select>
        </div>

        <div class="filterouterdiv  mb-0 doctor-filter">
            <label >Phone:</label>
            <input  type="text"  id="appoint_search_phone" placeholder="Phone No." class="form-control filter-field" onchange="SetPhone()">
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
    <div class="row mb-8 advance-filters" style="display: none;">

        <div class="col-lg-2 mb-lg-0 mt-6">
            <label >Doctor:</label>
            <br>
            <select class="form-control filter-field select2" id="appoint_search_doctor" onchange="SetDocId()"></select>
        </div>
        <div class="col-lg-2 mb-lg-0 mt-6" >
            <label>City:</label>
            <select class="form-control filter-field select2" id="appoint_search_city" onchange="SetCity()"></select>
        </div>

        <div class="col-lg-2 mb-lg-0 mt-6">
            <label>Region:</label>
            <select class="form-control filter-field select2" id="appoint_search_region" onchange="SetRegion()"></select>
        </div>
        <div class="col-lg-2 mb-lg-0 mt-6 appoint_search_status" >
            <label>Created By:</label>
            <select class="form-control filter-field select2" id="appoint_search_created_by" onchange="SetCreated()">
            </select>
        </div>
        <div class="col-lg-2 mb-lg-0 mb-6 mt-6">
            <label>Consultancy Type:</label>
            <select class="form-control filter-field select2" id="appoint_search_consultancy_type" onchange="SetConsultancyType()"></select>
        </div>
        <div class="col-md-3 mb-lg-0 mb-6 mt-6 @if($errors->has('date_range')) has-error @endif">
            {!! Form::label('date_range', 'Created at:', ['class' => 'control-label']) !!}
            <div class="input-group">
                {!! Form::text('date_range', null, ['id' => 'date_range', 'class' => 'form-control', 'autocomplete' => 'off']) !!}
            </div>
        </div>
        <div class="col-lg-2 mb-lg-0 mb-6 mt-6">
            <label>Updated By:</label>
            <select class="form-control filter-field select2" id="appoint_search_updated_by" onchange="SetUpdatedBy()">
            </select>
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6 mt-6">
            <label>Rescheduled By:</label>
            <select class="form-control filter-field select2" id="appoint_search_rescheduled_by" onchange="SetRescheduledBy()">
            </select>
        </div>

    </div>

</div>
