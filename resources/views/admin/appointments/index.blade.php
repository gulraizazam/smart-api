@extends('admin.layouts.master')
@section('title', 'Consultations')
@section('content')
    @push('css')
        <link href="{{asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.css')}}" rel="stylesheet" type="text/css" />
    @endpush
    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Consultancy List', 'title' => 'Consultancies'])
    <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">
                @include('admin.appointments.partials.consultancy-menu')
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
                            <h3 class="card-label change-label">Consultancies</h3>
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
                                    <a id="today_consultancies" onclick="loadTodayAppointments('{{date('Y-m-d')}}', 'consultancy');" href="javascript:void(0);" class="btn btn-info font-weight-bolder">
                                        Today Consultancies
                                    </a>
                                </div>&nbsp;&nbsp;&nbsp;
                            @endif
                            @if(Gate::allows('appointments_export'))
                                <div class="delete-records export-appointments">
                                    <form method="POST" action="download-filter-data" id="filtersform">
                                        @csrf
                                        <input type="hidden" id="filter_patient_id" name="filter_patient_id">
                                        <input type="hidden" id="filter_lead_id" name="filter_lead_id">
                                        <input type="hidden" id="filter_date_from" name="filter_date_from">
                                        <input type="hidden" name="appointmenttype" value="1">
                                        <input type="hidden" name="filter_phone" id="filter_phone">
                                        <input type="hidden" id="filter_date_to" name="filter_date_to">
                                        <input type="hidden" id="filter_doctor_id" name="filter_doctor_id">
                                        <input type="hidden" id="filter_center_id" name="filter_center_id">
                                        <input type="hidden" id="filter_status_id" name="filter_status_id">
                                        <input type="hidden" id="filter_city_id" name="filter_city_id">
                                        <input type="hidden" id="filter_service_id" name="filter_service_id">
                                        <input type="hidden" id="filter_region_id" name="filter_region_id">
                                        <input type="hidden" id="filter_consultancytype_id" name="filter_consultancytype_id">
                                        <input type="hidden" id="filter_updated_by_id" name="filter_updated_by_id">
                                        <input type="hidden" id="filter_created_from_id" name="filter_created_from_id">
                                        <input type="hidden" id="filter_created_to_id" name="filter_created_to_id">
                                        <input type="hidden" id="filter_rescheduled_by_id" name="filter_rescheduled_by_id">
                                        <a id="appointment_exports_submit" class="btn btn-primary font-weight-bolder">
                                        <i class="la la-file-export"></i> Export
                                    </a>
                                    </form>
                                    <!-- <a onclick="changeLimitOffset($(this));" title="On each click Max 1000 records will be export." id="appointment_exports" href="{{route('admin.appointments.export', [1000, 0])}}" class="btn btn-primary font-weight-bolder">
                                        <i class="la la-file-export"></i> Export
                                    </a> -->
                                </div>
                                <!-- <div class="delete-records export-appointments">
                                    <a  title="Download Today's Records."  href="download-today-consultancies" class="btn btn-primary font-weight-bolder">
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
                        @include('admin.appointments.filters', ['custom_reset' => 'custom_reset'])
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
        <script>
            let appointment_limit = '{{config('constants.export-appointment-limit')}}';
            var limit = '{{config('constants.export-appointment-limit')}}';
            var offset = 0;
            $(document).ready(function () {
                $("#appointment_exports").attr('href', route('admin.appointments.export', [limit, offset]));

            });
            $(document).on('click', '#appointment_exports_submit', function(e){
                e.preventDefault();
                $("#filtersform").submit();

            });
            function changeLimitOffset($this) {
                limit = parseInt(limit) + parseInt(appointment_limit);
                offset = parseInt(offset) + parseInt(appointment_limit);
                setTimeout( function () {
                    $this.attr('href', route('admin.appointments.export', [limit, offset]));
                },1000);
            }
            function SetFromdate(){
                $("#filter_date_from").val($("#appoint_search_start").val());
            }
            function SetTodate(){
                $("#filter_date_to").val($("#appoint_appoint_end").val());
            }
            function SetDocId()
            {
                $("#filter_doctor_id").val($("#appoint_search_doctor").val());

            }
            function SetStatus()
            {
                $("#filter_status_id").val($("#appoint_search_status").val());

            }
            function SetCreated()
            {
                $("#filter_created_by_id").val($("#appoint_search_created_by").val());

            }
            function SetCenter()
            {
                $("#filter_center_id").val($("#appoint_search_centre").val());

            }
            function SetPatient()
            {
                $("#filter_patient_id").val($("#appoint_search_patient").val());

            }
            function SetLead()
            {
                $("#filter_lead_id").val($("#appoint_search_lead").val());

            }
            ///////advance filters////////
            function SetCity(){
                $("#filter_city_id").val($("#appoint_search_city").val());
            }
            function SetRegion(){
                $("#filter_region_id").val($("#appoint_search_region").val());
            }
            function SetConsultancyType(){
                $("#filter_consultancytype_id").val($("#appoint_search_consultancy_type").val());
            }
            function SetUpdatedBy(){
                $("#filter_updated_by_id").val($("#appoint_search_updated_by").val());
            }
            function SetRescheduledBy(){
                $("#filter_rescheduled_by_id").val($("#appoint_search_rescheduled_by").val());
            }
            function SetAdvanceFromdate(){
                $("#filter_created_from_id").val($("#appoint_search_created_from").val());
            }
            function SetAdvanceTodate(){
                $("#filter_created_to_id").val($("#appoint_search_created_to").val());
            }
             function SetService()
             {
                $("#filter_service_id").val($("#appoint_search_service").val());
             }
             function SetPhone()
             {
                $("#filter_phone").val($("#appoint_search_phone").val());
             }
             function changeAppointmentStatus()
             {

                var appointment_id = $("#appointment_id").val();
                var appointment_status_not_show = $("#appointment_status_not_show").val();
                var cancellation_reason_other_reason = $("#cancellation_reason_other_reason").val();
                $.ajax({
                    // headers: {
                    //     'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    // },
                    url: route('admin.appointments.storeappointmentstatus'),
                    type: "post",
                    data: {id: appointment_id,appointment_status_not_show:appointment_status_not_show,cancellation_reason_other_reason:cancellation_reason_other_reason},
                    cache: false,
                    success: function(response) {
                       console.log(response);
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        errorMessage(xhr);
                    }
                });

             }
             </script>
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
                    && typeof result.tab !== 'undefined') {
                    loadDoctors(result.location_id, result.tab);
                    setTimeout( function () {
                        $("#consultancy_city_filter option[value='"+result.city_id+"']").attr('selected','selected');
                        $("#consultancy_city_filter").val(result.city_id).change();
                        setDashboardFilters();
                    }, 1300);
                } else {
                    setTimeout( function () {
                        setDashboardFilters();
                    }, 1300);
                }
            });
            </script>
            <script>
            function setDashboardFilters() {
                let result = get_query();
                if(result?.type != null ) {
                    $("#appoint_search_type").val('{{request('type')}}').change();
                    $("#appoint_search_start").val('{{request('from')}}');
                    $("#appoint_appoint_end").val('{{request('to')}}');
                    @php
                        $ids = explode(',', request('center_id'));
                    @endphp
                        @if (count($ids) == 1)
                            $("#appoint_search_centre").val('{{request('center_id')}}').change();
                        @endif

                    $("#appoint_search_status").val('{{request('appoint_status')}}').change();

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

                setTimeout( function () {

                    let city_value = $("#consultancy_city_filter").val();

                    if (city_value == null || city_value == '') {

                        @if (auth()->id() != 1)

                        $.ajax({
                            url: '{{route('admin.users.get_cities')}}',
                            type: 'GET',
                            dataType: 'json',
                            success: function (response) {
                                if (response.status) {
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

                }, 500);

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

        </script>
        <script src="{{asset('assets/js/pages/appointment/invoice.js?v=1')}}"></script>
        <script src="{{asset('assets/js/pages/appointment/consultancy-calendar.js')}}"></script>

        <script src="{{asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.js')}}"></script>
        <script src="{{asset('assets/js/pages/appointment/consultancy-data.js')}}"></script>
        {{--<script src="{{asset('assets/js/pages/appointment/treatment-data.js')}}"></script>--}}

        <script src="{{asset('assets/js/pages/crud/forms/validation/appointment/validation.js')}}"></script>
        <script src="{{asset('assets/js/pages/appointment/plan/create.js')}}"></script>
        <script src="{{asset('assets/js/pages/appointment/common.js')}}"></script>

    @endpush

    @push('datatable-js')
        <script src="{{asset('assets/js/pages/appointment/datatable.js')}}"></script>
    @endpush

@endsection
