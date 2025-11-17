@extends('admin.layouts.master')
@section('title', 'Patients')
@section('content')

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'Patients List', 'title' => 'Patients'])

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
                            <h3 class="card-label">Patients</h3>

                        </div>

                        <div class="card-toolbar">
                            <!--begin::Dropdown-->
                            @if(Gate::allows('patients_destroy'))
                                <div class="delete-records d-none">
                                    <span>Selected Rows: <span class="checkbox-count"></span></span>
                                    <a id="delete-table-rows" href="javascript:void(0);" class="btn btn-danger font-weight-bolder">
                                        <i class="fa fa-trash-alt"></i>Delete
                                    </a>
                                </div>&nbsp;&nbsp;&nbsp;
                            @endif

                            {{-- @if(Gate::allows('patients_create'))
                                <a href="javascript:void(0);" onclick="createPatient('{{ route('admin.patients.create') }}');" class="btn btn-primary" data-toggle="modal" data-target="#modal_add_patients">
                                    <i class="la la-plus"></i>
                                    Add New
                                </a>
                            @endif --}}

                        <!--end::Button-->
                        </div>

                    </div>

                    <div class="card-body">
                        <!--begin::Search Form-->
                        @include('admin.patients.filters')
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

    <div class="modal fade" id="modal_add_patients" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="patients_add">

            @include('admin.patients.create')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_edit_patients" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="edit_patients">

            @include('admin.patients.edit')

        </div>
        <!--end::Modal dialog-->
    </div> 
    <div class="modal fade" id="modal_edit_memberships" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="edit_memberships">

            @include('admin.patients.assignmembership')

        </div>
        <!--end::Modal dialog-->
    </div>
    <div class="modal fade" id="modal_edit_vouchers" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="edit_vouchers">

            @include('admin.patients.assignVoucher')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_add_referral" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="add_referral">

            @include('admin.patients.addreferral')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_import_leads" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="import_leads">

            @include('admin.leads.import')

        </div>
        <!--end::Modal dialog-->
    </div>

    @push('js')

        <script src="{{asset('assets/js/pages/crud/forms/validation/patients/patient.js')}}"></script>

    @endpush

    @push('datatable-js')
        <script src="{{asset('assets/js/pages/patients/patient.js')}}"></script>
    @endpush

@endsection
