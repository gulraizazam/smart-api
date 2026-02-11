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
                                <a href="javascript:void(0);" onclick="createBundle('{{ route('admin.packages.create') }}');" class="btn btn-success" data-toggle="modal"
                                    data-target="#modal_add_bundle">
                                    <i class="la la-plus"></i>
                                    Add Bundle
                                </a>
                                &nbsp;
                                <a href="javascript:void(0);" onclick="createMembership('{{ route('admin.packages.create') }}');" class="btn btn-warning" data-toggle="modal"
                                    data-target="#modal_add_membership">
                                    <i class="la la-plus"></i>
                                    Add Membership
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

    {{-- Include shared modals --}}
    @include('admin.packages.modals')

    @push('js')
        <script src="{{ asset('assets/js/pages/appointments/referred-by-patient-search.js') }}"></script>
        <script src="{{ asset('assets/js/pages/admin_settings/create-plan.js') }}"></script>
        <script src="{{ asset('assets/js/pages/admin_settings/create-bundle.js') }}"></script>
        <script src="{{ asset('assets/js/pages/admin_settings/create-membership.js') }}"></script>
        <script src="{{ asset('assets/js/pages/admin_settings/edit-bundle.js') }}"></script>
        <script src="{{ asset('assets/js/pages/crud/forms/validation/admin_settings/refunds.js') }}"></script>

        <script>
            function getUserCentre() {
                $.ajax({
                    url: '{{ route('admin.users.get_centers') }}',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.status && response.data && response.data.centers) {
                            // Populate the search_location_id dropdown with centers
                            var locationOptions = '<option value="">All</option>';
                            Object.entries(response.data.centers).forEach(function([id, name]) {
                                locationOptions += '<option value="' + id + '">' + name + '</option>';
                            });
                            $("#search_location_id").html(locationOptions);
                            
                            // Auto-select if only one center
                            var centerKeys = Object.keys(response.data.centers);
                            if (centerKeys.length === 1) {
                                $("#search_location_id").val(centerKeys[0]).change();
                                $("#add_plan_location_id").val(centerKeys[0]).change();
                                $("#add_bundle_location_id").val(centerKeys[0]).change();
                                $("#add_membership_location_id").val(centerKeys[0]).change();
                            }
                        }
                    },
                    error: function() {
                        console.error('Failed to load user centers');
                    }
                });
            }
        </script>
    @endpush

@endsection
