@extends('admin.layouts.master')
@section('title', 'Treatments')
@section('content')

    @push('css')
        <link href="{{asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.css')}}" rel="stylesheet" type="text/css" />
        <style>
            /* Custom Resource Calendar Styles */
            .resource-calendar-container {
                display: flex;
                flex-direction: column;
                border: 1px solid #e4e6ef;
                background: #fff;
                min-height: 600px;
            }
            .resource-calendar-container * {
                box-sizing: border-box;
            }
            .resource-calendar-header {
                display: flex;
                border-bottom: 2px solid #e4e6ef;
                background: #f3f6f9;
                position: sticky;
                top: 0;
                z-index: 10;
                overflow-y: scroll;
                overflow-x: hidden;
            }
            .resource-calendar-header::-webkit-scrollbar {
                width: 17px;
                height: 0;
            }
            .resource-calendar-header::-webkit-scrollbar-track {
                background: transparent;
            }
            .resource-calendar-header::-webkit-scrollbar-thumb {
                background: transparent;
            }
            .resource-calendar-header-doctors {
                display: flex;
                flex: 1;
                min-width: 0;
            }
            .resource-time-column {
                width: 80px;
                min-width: 80px;
                max-width: 80px;
                flex: 0 0 80px;
                border-right: 2px solid #e4e6ef;
                background: #f3f6f9;
                font-weight: 600;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 15px 5px;
            }
            .resource-doctor-header {
                flex: 1;
                min-width: 0;
                padding: 15px 10px;
                text-align: center;
                font-weight: 600;
                border-right: 1px solid #e4e6ef;
                background: #043464;
                color: #fff;
                word-wrap: break-word;
                overflow: hidden;
            }
            .resource-calendar-body {
                display: flex;
                overflow-y: scroll;
                overflow-x: hidden;
                max-height: 700px;
            }
            .resource-time-slots {
                width: 80px;
                min-width: 80px;
                max-width: 80px;
                flex: 0 0 80px;
                border-right: 2px solid #e4e6ef;
                background: #f3f6f9;
                display: flex;
                flex-direction: column;
            }
            .resource-time-slot {
                height: 60px;
                min-height: 60px;
                flex-shrink: 0;
                border-bottom: 1px solid #e4e6ef;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 11px;
                font-weight: 600;
                color: #7e8299;
                box-sizing: border-box;
            }
            .resource-time-slot:last-child {
                border-bottom: 1px solid #e4e6ef;
            }
            .resource-doctors-container {
                display: flex;
                flex: 1;
                min-width: 0;
                align-items: stretch;
            }
            .resource-doctor-column {
                flex: 1 1 0;
                min-width: 0;
                border-right: 1px solid #e4e6ef;
                position: relative;
                background: #fff;
                display: flex;
                flex-direction: column;
            }
            .resource-doctor-slot {
                height: 60px;
                min-height: 60px;
                flex-shrink: 0;
                border-bottom: 1px solid #e4e6ef;
                position: relative;
                cursor: pointer;
                transition: background 0.2s;
                box-sizing: border-box;
            }
            .resource-doctor-slot:last-child {
                border-bottom: 1px solid #e4e6ef;
            }
            .resource-doctor-slot:hover {
                background: #f3f6f9;
            }
            .resource-doctor-slot.has-rota {
                background: #e8fff3 !important;
                cursor: pointer !important;
            }
            .resource-doctor-slot.has-rota:hover {
                background: #d4f7e3 !important;
            }
            .resource-appointment {
                position: absolute;
                left: 2px;
                right: 2px;
                background: #3699ff;
                color: #fff;
                padding: 6px 8px;
                border-radius: 4px;
                font-size: 11px;
                overflow: hidden;
                cursor: pointer;
                border: 1px solid #187de4;
                z-index: 5;
                line-height: 1.3;
            }
            .resource-appointment:hover {
                background: #187de4;
                box-shadow: 0 2px 6px rgba(0,0,0,0.15);
                z-index: 10;
            }
            .resource-calendar-nav {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px 20px;
                background: #f3f6f9;
                border-bottom: 1px solid #e4e6ef;
                margin-bottom: 10px;
            }
            .resource-calendar-nav button {
                padding: 8px 16px;
                margin: 0 5px;
            }
            .resource-calendar-nav .current-date {
                font-weight: 600;
                font-size: 16px;
                padding: 8px 16px;
                background: #fff;
                border-radius: 4px;
                border: 1px solid #e4e6ef;
                transition: all 0.2s;
                display: inline-flex;
                align-items: center;
            }
            .resource-calendar-nav .current-date:hover {
                background: #f3f6f9;
                border-color: #3699ff;
                box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            }
            /* Drag and Drop Styles */
            .resource-appointment.dragging {
                opacity: 0.5;
                cursor: move;
            }
            .resource-doctor-slot.drag-over {
                background: #d4f7e3 !important;
                border: 2px dashed #1BC5BD !important;
            }
            .resource-appointment {
                cursor: move;
            }
            .resource-appointment:hover {
                cursor: move;
            }
        </style>
    @endpush
    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'Treatments List', 'title' => 'Treatments'])

    <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">

                @include('admin.appointments.partials.treatment-menu')

                <!--begin::Card-->
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon">
                                <span class="svg-icon svg-icon-md svg-icon-primary">
                                    <!--begin::Svg Icon | path:assets/media/svg/icons/Shopping/Chart-bar1.svg-->
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <rect x="0" y="0" width="24" height="24" />
                                            <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13" rx="1.5" />
                                            <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8" rx="1.5" />
                                            <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero" />
                                            <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5" />
                                        </g>
                                    </svg>
                                    <!--end::Svg Icon-->
                                </span>
                            </span>
                            <h3 class="card-label change-label">Treatments</h3>

                        </div>

                        <div class="card-toolbar">
                            <!--begin::Dropdown-->
                            @if(Gate::allows('appointments_destroy'))
                                <div class="delete-records d-none">
                                    <span>Selected Rows: <span class="checkbox-count"></span></span>
                                    <a id="delete-table-rows" href="javascript:void(0);" class="btn btn-danger font-weight-bolder">
                                        <i class="fa fa-trash-alt"></i>Delete
                                    </a>
                                </div>&nbsp;&nbsp;&nbsp;
                            @endif
                            @if(Gate::allows('appointments_export_today'))
                                <div class="export-appointments">
                                    <a id="today_consultancies" onclick="loadTodayAppointments('{{date('Y-m-d')}}', 'treatment');" href="javascript:void(0);" class="btn btn-info font-weight-bolder">
                                        Today Treatments
                                    </a>
                                </div>&nbsp;&nbsp;&nbsp;
                            @endif

                            @if(Gate::allows('appointments_export'))
                                    <form method="POST" action="download-filter-data" id="filtersform">
                                        @csrf
                                        <input type="hidden" id="filter_patient_id" name="filter_patient_id">
                                        <input type="hidden" id="filter_date_from" name="filter_date_from">
                                        <input type="hidden" name="appointmenttype" value="2">
                                        <input type="hidden" name="filter_phone" id="filter_phone">
                                        <input type="hidden" id="filter_date_to" name="filter_date_to">
                                        <input type="hidden" id="filter_doctor_id" name="filter_doctor_id">
                                        <input type="hidden" id="filter_center_id" name="filter_center_id">
                                        <input type="hidden" id="filter_status_id" name="filter_status_id">
                                        <input type="hidden" id="filter_created_by_id" name="filter_created_by_id">
                                        <input type="hidden" id="filter_city_id" name="filter_city_id">
                                        <input type="hidden" id="filter_region_id" name="filter_region_id">
                                        <input type="hidden" id="filter_service_id" name="filter_service_id">
                                        <input type="hidden" id="filter_updated_by_id" name="filter_updated_by_id">
                                        <input type="hidden" id="filter_created_from_id" name="filter_created_from_id">
                                        <input type="hidden" id="filter_created_to_id" name="filter_created_to_id">
                                        <input type="hidden" id="filter_rescheduled_by_id" name="filter_rescheduled_by_id">
                                        <a id="appointment_exports_submit" class="btn btn-primary font-weight-bolder">
                                            <i class="la la-file-export"></i> Export
                                        </a>
                                    </form>
                                <!-- <div class="delete-records export-appointments">
                                    <a onclick="changeLimitOffset($(this));" title="On each click Max 1000 records will be export." id="appointment_exports" href="{{route('admin.appointments.export', [1000, 0])}}" class="btn btn-primary font-weight-bolder">
                                        <i class="la la-file-export"></i> Export
                                    </a>
                                </div> -->
                                <!-- <div class="delete-records export-appointments">
                                    <a  title="click to download today's records" href="download-today-treatments" class="btn btn-primary font-weight-bolder">
                                        <i class="la la-file-export"></i> Export
                                    </a>
                                </div> -->
                            @endif

                        <!--end::Button-->
                        </div>

                    </div>

                    <!--Start Appointment Section-->
                    <div class="card-body appointment appointment-section">
                        <!--begin::Search Form-->
                        @include('admin.appointments.treatment-filters', ['custom_reset' => 'custom_reset'])
                        <!--end::Search Form-->

                        <!--begin: Datatable-->
                        <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
                        <!--end: Datatable-->
                    </div>
                    <!--End Appointment Section-->

                    <!--Start Consultancy Section-->
                    <div class="card-body appointment consultancy-section d-none">

                        @include('admin.appointments.consultancy.filters')

                        <div id="consultancy_calendar" style="position: relative">

                            {{--loader befor get celendar events--}}
                            <div class="appointment-loader-base" style="display: none;">
                                <div class="blockui"> <span>Please wait...</span>
                                    <span>
                                        <div class="spinner spinner-primary"></div>
                                    </span>
                                </div>
                            </div>
                            {{--end loader--}}

                        </div>

                    </div>
                    <!--End Consultancy Section-->

                    <!--Start Treatment Section-->
                    <div class="card-body appointment treatment-section d-none">

                        @include('admin.appointments.services.filters')

                        {{-- Custom Resource Calendar View for Treatments --}}
                        <div id="custom_treatment_resource_calendar" style="display: none; position: relative;">
                            <div class="appointment-loader-base" style="display: none;">
                                <div class="blockui"> <span>Please wait...</span>
                                    <span>
                                        <div class="spinner spinner-primary"></div>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div id="treatment_calendar" style="position: relative">

                            {{--loader befor get celendar events--}}
                            <div class="appointment-loader-base" style="display: none;">
                                <div class="blockui"> <span>Please wait...</span>
                                    <span>
                                        <div class="spinner spinner-primary"></div>
                                    </span>
                                </div>
                            </div>
                            {{--end loader--}}

                        </div>

                    </div>
                    <!--End Treatment Section-->

                </div>
                <!--end::Card-->

            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->

    {{--All forms popups--}}
    @include('admin.appointments.appointment-forms.modals')
    @push('js')
        <script defer>
            $(document).ready(function () {
                var result = get_query();
                console.log(result);
                if (typeof result.tab !== 'undefined') {
                    $("." + result.tab+ '-tab').click();
                } else {
                    $(".appointment-tab").addClass("nav-bar-active")
                }
                if (typeof result.city_id !== "undefined"
                    && typeof result.location_id !== "undefined"
                    && typeof result.doctor_id !== "undefined"
                    && typeof result.machine_id !== "undefined"
                    && typeof result.tab !== 'undefined') {
                    loadDoctors(result.location_id, result.tab);
                    setTimeout( function () {
                        console.log('result.city_id', result.city_id);
                        $("#treatment_city_filter option[value='"+result.city_id+"']").attr('selected','selected');
                        $("#treatment_city_filter").val(result.city_id).change();
                        setDashboardFilters();
                    }, 1300);
                } else {
                    setTimeout( function () {
                        setDashboardFilters();
                    }, 1300);
                }
            });
            $(document).on('click', '#appointment_exports_submit', function(e){
                e.preventDefault();
                $("#filtersform").submit();
            });
            let appointment_limit = '{{config('constants.export-appointment-limit')}}';
            var limit = '{{config('constants.export-appointment-limit')}}';
            var offset = 0;
            $(document).ready(function () {
                $("#appointment_exports").attr('href', route('admin.appointments.export', [limit, offset]));
            })
            function changeLimitOffset($this) {
                limit = parseInt(limit) + parseInt(appointment_limit);
                offset = parseInt(offset) + parseInt(appointment_limit);
                setTimeout( function () {
                    $this.attr('href', route('admin.appointments.export', [limit, offset]));
                },1000);
            }
        </script>
        <script>
            function SetFromdate(){
                $("#filter_date_from").val($("#treatment_search_start").val());
            }
            function SetTodate(){
                $("#filter_date_to").val($("#treatment_appoint_end").val());
            }
            function SetPhone()
             {
                $("#filter_phone").val($("#appoint_search_phone").val());
             }
            function SetDocId()
            {
                $("#filter_doctor_id").val($("#treatment_search_doctor").val());
            }
            function SetStatus()
            {
                $("#filter_status_id").val($("#treatment_search_status").val());
            }
            function SetCreated()
            {
                $("#filter_created_by_id").val($("#treatment_search_created_by").val());
            }
            function SetCenter()
            {
                $("#filter_center_id").val($("#treatment_search_centre").val());
            }
            function SetPatient()
            {
                $("#filter_patient_id").val($("#appoint_search_patient").val());
            }
            function SetCity(){
                $("#filter_city_id").val($("#treatment_search_city").val());
            }
            function SetRegion(){
                $("#filter_region_id").val($("#treatment_search_region").val());
            }
            function SetUpdatedBy(){
                $("#filter_updated_by_id").val($("#treatment_search_updated_by").val());
            }
            function SetRescheduledBy(){
                $("#filter_rescheduled_by_id").val($("#treatment_search_rescheduled_by").val());
            }
            function SetAdvanceFromdate(){
                $("#filter_created_from_id").val($("#treatment_search_created_from").val());
            }
            function SetAdvanceTodate(){
                $("#filter_created_to_id").val($("#treatment_search_created_to").val());
            }
            function SetService()
             {
                let service_value = $("#treatment_search_service").val();
                if (service_value.indexOf("bold-") !== -1) {
                    var service = service_value.split("bold-")[1];
                } else {
                    var service = service_value;
                }
                $("#filter_service_id").val(service);
             }

            function setDashboardFilters() {
                let result = get_query();
                if(result?.type != null ) {
                    $("#appoint_search_type").val('{{request('type')}}').change();
                    $("#treatment_search_start").val('{{request('from')}}');
                    $("#treatment_appoint_end").val('{{request('to')}}');
                    @php
                        $ids = explode(',', request('center_id'));
                    @endphp
                        @if (count($ids) == 1)
                            $("#treatment_search_centre").val('{{request('center_id')}}').change();
                        @endif

                    $("#treatment_search_status").val('{{request('appoint_status')}}').change();

                    datatable.search({
                        location_id: '{{request('center_id')}}',
                        appointment_type_id: '{{request('type')}}',
                        date_from: '{{request('from')}}',
                        date_to: '{{request('to')}}',
                        appointment_status_id: '{{request('appoint_status')}}',
                        filter: 'filter',
                    }, 'search');
                }
            }

            function getUserCity() {
                @if (auth()->id() != 1)
                    $.ajax({
                        url: '{{route('admin.users.get_cities')}}',
                        type: 'GET',
                        dataType: 'json',
                        success: function (response) {
                            if (response.status) {
                                $("#consultancy_city_filter").val(response.data.city).change();
                                $("#treatment_city_filter").val(response.data.city).change();
                                $("#appoint_search_city").val(response.data.city).change();
                               setTimeout( function () {
                                   getUserCentre();
                               }, 400);
                            }
                        },
                        error: function () {

                        }
                    });
                @endif
            }

            function getUserCentre() {
                $.ajax({
                    url: '{{route('admin.users.get_centers')}}',
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        if (response.status) {
                            $("#consultancy_location_filter").val(response.data.center).change();
                            $("#treatment_location_filter").val(response.data.center).change();
                            $("#appoint_search_centre").val(response.data.center).change();
                        }
                    },
                    error: function () {
                    }
                });
            }

            // Auto-trigger calendar for single centre users (Treatment)
            function autoTriggerTreatmentCalendarForSingleCentre() {
                // Check if userCentres is defined and has exactly one centre
                if (typeof window.userCentres !== 'undefined' && window.userCentres.length === 1) {
                    var singleCentreId = window.userCentres[0];

                    // Check if treatment section is visible
                    var result = get_query();
                    var isTreatmentVisible = $('.treatment-section').is(':visible') && !$('.treatment-section').hasClass('d-none');

                    if (isTreatmentVisible || (typeof result.tab !== 'undefined' && result.tab === 'treatment')) {
                        // Only trigger if calendar is not already loaded
                        if ($('#treatment_location_filter').val() === '' || $('#treatment_location_filter').val() === null) {
                            // First, ensure the location dropdown is populated
                            $.ajax({
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                url: route('admin.appointments.load_locations'),
                                type: 'POST',
                                data: {
                                    city_id: ''
                                },
                                cache: false,
                                success: function(response) {
                                    if (response.status && response.data.dropdown) {
                                        var dropdown_options = '';
                                        Object.entries(response.data.dropdown).forEach(function (dropdown) {
                                            dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
                                        });
                                        $('#treatment_location_filter').html(dropdown_options);

                                        // Now set the value and trigger calendar
                                        setTimeout(function() {
                                            $("#treatment_location_filter").val(singleCentreId);
                                            console.log('Auto-triggering treatment calendar for centre ID:', singleCentreId);
                                            loadDoctors(singleCentreId, 'treatment');
                                        }, 300);
                                    }
                                },
                                error: function(xhr, ajaxOptions, thrownError) {
                                    console.error('Failed to load locations for treatment auto-trigger');
                                }
                            });
                        }
                    }
                }
            }

            // Call auto-trigger on page load and when treatment tab is clicked
            $(document).ready(function() {
                // Trigger after a delay to ensure page is fully loaded
                setTimeout(function() {
                    autoTriggerTreatmentCalendarForSingleCentre();
                }, 800);
            });

            // Also trigger when treatment tab is clicked
            $(document).on('click', '.treatment-tab', function() {
                setTimeout(function() {
                    autoTriggerTreatmentCalendarForSingleCentre();
                }, 800);
            });

        </script>
        <script src="{{asset('assets/js/pages/appointment/invoice.js?v=1')}}"></script>
        <script src="{{asset('assets/js/pages/appointment/treatment-calendar.js')}}"></script>

        <script src="{{asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.js')}}"></script>
        <script src="{{asset('assets/js/pages/appointment/treatment-data.js')}}"></script>
        <script src="{{asset('assets/js/pages/crud/forms/validation/feedbacks/feedbacks.js')}}"></script>
        <script src="{{asset('assets/js/pages/crud/forms/validation/appointment/validation.js')}}"></script>
        <script src="{{asset('assets/js/pages/appointment/plan/create.js')}}"></script>
        <script src="{{asset('assets/js/pages/appointment/common.js')}}"></script>

    @endpush

    @push('datatable-js')

        <script src="{{asset('assets/js/pages/appointment/treatmentDatatable.js')}}"></script>
    @endpush

@endsection
