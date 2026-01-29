@extends('admin.layouts.master')
@section('title', 'Plans')
@section('content')
    <style>
        .form-control:disabled,
        .form-control[readonly] {
            background-color: #F3F6F9 !important;
            opacity: 1;
        }

        /* Handle nested modal z-index */
        #modal_edit_sold_by {
            z-index: 1060 !important;
        }

        #modal_edit_sold_by ~ .modal-backdrop {
            z-index: 1055 !important;
        }

        /* Ensure the edit plan modal stays below */
        #modal_edit_plan {
            z-index: 1050;
        }

        #modal_edit_plan ~ .modal-backdrop {
            z-index: 1040;
        }
    </style>
    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

        @include('admin.partials.breadcrumb', ['module' => 'Plans List', 'title' => 'Plans'])

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
                                            <path
                                                d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z"
                                                fill="#000000" fill-rule="nonzero" />
                                            <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5" />
                                        </g>
                                    </svg>
                                    <!--end::Svg Icon-->
                                </span>
                            </span>
                            <h3 class="card-label">Plans</h3>
                        </div>

                        <div class="card-toolbar">
                            <!--begin::Dropdown-->
                            @if (Gate::allows('plans_destroy'))
                                <div class="delete-records d-none">
                                    <span>Selected Rows: <span class="checkbox-count"></span></span>
                                    <a id="delete-table-rows" href="javascript:void(0);" class="btn btn-danger font-weight-bolder">
                                        <i class="fa fa-trash-alt"></i>Delete
                                    </a>
                                </div>&nbsp;&nbsp;&nbsp;
                            @endif

                            @if (Gate::allows('plans_create'))
                                <a href="javascript:void(0);" onclick="createPlan('{{ route('admin.packages.create') }}');" class="btn btn-primary" data-toggle="modal"
                                    data-target="#modal_add_plan">
                                    <i class="la la-plus"></i>
                                    Add Procedures
                                </a>
                                &nbsp;
                                <a href="javascript:void(0);" onclick="createBundle('{{ route('admin.packages.create') }}');" class="btn btn-primary" data-toggle="modal"
                                    data-target="#modal_add_bundle">
                                    <i class="la la-plus"></i>
                                    Add Bundle
                                </a>
                            @endif

                            <!--end::Button-->
                        </div>

                    </div>

                    <div class="card-body">
                        <!--begin::Search Form-->
                        @include('admin.packages.filters', ['custom_reset' => 'custom_reset'])
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

    <div class="modal fade" id="modal_sms_logs" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered mediam-modal" id="invoices_add">

            @include('admin.packages.sms_logs')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_display" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered very-big-modal" id="invoices_display">

            @include('admin.packages.display')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_add_plan" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered very-big-modal" id="packages_add">

            @include('admin.packages.create')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_add_bundle" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered very-big-modal" id="bundles_add">

            @include('admin.packages.create-bundle')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_create_bundle" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered very-big-modal" id="bundles_add">
            @include('admin.packages.create-bundle')
        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_edit_bundle" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered very-big-modal" id="bundles_edit">
            @include('admin.packages.edit-bundle')
        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_edit_plan" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered very-big-modal" id="packages_edit">

            @include('admin.packages.edit')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="plan_edit_cash" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered very-big-modal" id="plan_edit">

            @include('admin.packages.plane-edit')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_edit_refunds" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="refunds_edit">

            @include('admin.refunds.refund')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_edit_sold_by" tabindex="-1" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered modal-md">
            <!--begin::Modal content-->
            <div class="modal-content">
                <!--begin::Modal header-->
                <div class="modal-header">
                    <h2 class="fw-bolder">Edit Sold By</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" onclick="closeSoldByModal()">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                            </svg>
                        </span>
                    </div>
                </div>
                <!--end::Modal header-->
                <!--begin::Modal body-->
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <form id="edit_sold_by_form">
                        <input type="hidden" id="package_service_id" name="package_service_id">
                        <div class="fv-row mb-7">
                            <label class="required fw-bold fs-6 mb-2">Sold By</label>
                            <select id="sold_by_dropdown" name="sold_by" class="form-control form-control-solid" required>
                                <option value="">Select</option>
                            </select>
                            <small class="text-danger"><b id="sold_by_error" class="error-msg"></b></small>
                        </div>
                        <div class="text-center pt-15">
                            <button type="button" class="btn btn-light me-3" onclick="closeSoldByModal()">Cancel</button>
                            <button type="button" id="update_sold_by_btn" class="btn btn-primary">
                                <span class="indicator-label">Update</span>
                            </button>
                        </div>
                    </form>
                </div>
                <!--end::Modal body-->
            </div>
            <!--end::Modal content-->
        </div>
        <!--end::Modal dialog-->
    </div>

    @push('js')
        <script src="{{ asset('assets/js/pages/appointments/referred-by-patient-search.js') }}"></script>
        <script src="{{ asset('assets/js/pages/admin_settings/create-plan.js') }}"></script>
        <script src="{{ asset('assets/js/pages/admin_settings/create-bundle.js') }}"></script>
        <script src="{{ asset('assets/js/pages/admin_settings/edit-bundle.js') }}"></script>
        <script src="{{ asset('assets/js/pages/crud/forms/validation/admin_settings/refunds.js') }}"></script>

        <script>
            function getUserCentre() {
                $.ajax({
                    url: '{{ route('admin.users.get_centers') }}',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.status) {
                            $("#search_location_id").val(response.data.center).change();
                            $("#add_plan_location_id").val(response.data.center).change();
                        }
                    },
                    error: function() {

                    }
                });
            }
        </script>
    @endpush

@endsection
