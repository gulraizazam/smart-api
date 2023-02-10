@extends('admin.layouts.master')

@section('content')

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'Appointment Medical History', 'title' => 'Medical History'])

    <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">

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
                            <h3 class="card-label">Medical History</h3>
                        </div>
                        <div class="card-toolbar">
                            <!--begin::Dropdown-->

                            @if(Gate::allows('appointments_medical_create'))
                                <a href="javascript:void(0);" onclick="addMedicalForm('{{route('admin.appointmentsmedical.create', request('id'))}}');" class="btn btn-primary" data-toggle="modal" data-target="#modal_add_medical_form">
                                    <i class="la la-plus"></i>
                                    Add New
                                </a>
                            @endif
                            &nbsp;
                            <a href="{{ route('admin.appointments.index')}}" class="btn btn-dark">
                                <i class="la la-arrow-left"></i>
                                Back
                            </a>

                        <!--end::Button-->
                        </div>
                    </div>

                    <div class="card-body">
                        <table class="table">
                            <tbody>
                            <tr>
                                <th>Patient Name</th>
                                <td>{{ ($appointment->name) ? $appointment->name : $appointment->patient->name }}</td>
                                <th>Patient Phone</th>
                                <td>@if($appointment->patient->phone){{ \App\Helpers\GeneralFunctions::prepareNumber4Call($appointment->patient->phone) }}@else{{'N/A'}}@endif</td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td>@if($appointment->patient->email){{ $appointment->patient->email }}@else{{'N/A'}}@endif</td>
                                <th>Gender</th>
                                <td>@if($appointment->patient->gender){{ Config::get('constants.gender_array')[$appointment->patient->gender] }}@else{{'N/A'}}@endif</td>
                            </tr>
                            <tr>
                                <th>Appointment Time</th>
                                <td>@if($appointment->scheduled_date){{ \Carbon\Carbon::parse($appointment->scheduled_date, null)->format('M j, y') . ' at ' . \Carbon\Carbon::parse($appointment->scheduled_time, null)->format('h:i A') }}@else{{'-'}}@endif</td>
                                <th>Doctor</th>
                                <td>@if($appointment->doctor_id){{ $appointment->doctor->name }}@else{{'N/A'}}@endif</td>
                            </tr>
                            <tr>
                                <th>City</th>
                                <td>@if($appointment->city_id){{ $appointment->city->name }}@else{{'N/A'}}@endif</td>
                                <th>Centre</th>
                                <td>@if($appointment->location_id){{ $appointment->location->name }}@else{{'N/A'}}@endif</td>
                            </tr>
                            <tr>
                                <th>Appointment Status</th>
                                <td @if($appointment->appointment_status_id != Config::get('constants.appointment_status_not_show'))@endif>@if($appointment->appointment_status_id){{ $appointment->appointment_status->name }}@else{{'N/A'}}@endif</td>
                                <th>Treatment</th>
                                <td>{{$appointment->service->name}}</td>
                            </tr>
                            <tr>
                                @if($appointment->appointment_status_id == Config::get('constants.appointment_status_not_show'))
                                    <th>{{ trans('global.cancellation_reasons.word') }}</th>
                                    <td>@if($appointment->cancellation_reason_id && isset($appointment->cancellation_reason->name)){{ $appointment->cancellation_reason->name }}@else{{ 'N/A' }}@endif</td>
                                @endif
                            </tr>
                            @if(($appointment->appointment_status_id == Config::get('constants.appointment_status_not_show')) &&
                                ($appointment->cancellation_reason_id == Config::get('constants.cancellation_reason_other_reason')))
                                <tr>
                                    <th>Reason</th>
                                    <td colspan="3">@if($appointment->reason){{ $appointment->reason }}@else{{ 'N/A' }}@endif</td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </div>

                    <div class="card-body">
                        <!--begin::Search Form-->
                    @include('admin.appointments.medicals.filters')
                    <!--end::Search Form-->

                        <!--begin: Datatable-->
                        <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
                        <!--end: Datatable-->
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->

    <div class="modal fade" id="modal_add_medical_form" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered mediam-modal" id="medical_form">

            @include('admin.appointments.medicals.AddNewMedical')

        </div>
    </div>

    @push('datatable-js')
        <script>
            let appointment_id = "{{request('id')}}";
        </script>
        <script src="{{asset('assets/js/pages/appointment/medical.js')}}"></script>
    @endpush

@endsection
