@extends('admin.layouts.master')

@section('content')

    @push('css')
        <link href="{{asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.css')}}" rel="stylesheet" type="text/css" />
    @endpush
    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'Appointments List', 'title' => 'Patients'])

    <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">

                @include('admin.appointments.partials.menu')

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
                            <h3 class="card-label change-label">Appointments</h3>

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

                            <div class="delete-records export-appointments">
                                <a href="{{route('admin.appointments.export')}}" class="btn btn-primary font-weight-bolder">
                                    <i class="la la-file-export"></i> Export
                                </a>
                            </div>

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

                    <!--Start Treatment Section-->
                    <div class="card-body appointment treatment-section d-none">

                        @include('admin.appointments.services.filters')

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

        <script src="{{asset('assets/js/pages/appointment/consultancy-calendar.js')}}"></script>
        <script src="{{asset('assets/js/pages/appointment/treatment-calendar.js')}}"></script>

        <script src="{{asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.js')}}"></script>
        <script src="{{asset('assets/js/pages/appointment/consultancy-data.js')}}"></script>
        <script src="{{asset('assets/js/pages/appointment/treatment-data.js')}}"></script>

        <script src="{{asset('assets/js/pages/crud/forms/validation/appointment/validation.js')}}"></script>

    @endpush

    @push('datatable-js')
        <script src="{{asset('assets/js/pages/appointment/datatable.js')}}"></script>
    @endpush

@endsection
