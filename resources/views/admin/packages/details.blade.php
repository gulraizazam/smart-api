@extends('admin.layouts.master')
@section('title', 'Plan Details')
@section('content')
    <style>
        .form-control:disabled,
        .form-control[readonly] {
            background-color: #F3F6F9 !important;
            opacity: 1;
        }
    </style>
    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'Plan Details', 'title' => 'Plan Details'])
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
                            <h3 class="card-label">Plan Details</h3>
                        </div>
                        <div class="card-toolbar">
                            <!--begin::Dropdown-->
                            @if (Gate::allows('plans_edit'))
                                <a href="javascript:void(0);" onclick="editRow('{{ $url }}');" class="btn btn-primary" data-toggle="modal" data-target="#modal_add_plan">
                                    <i class="la la-pencil"></i>
                                    Edit
                                </a>
                            @endif

                            <!--end::Button-->
                        </div>
                    </div>
                    <div class="card-body">
                        <!--begin::Form-->
                        <div class="d-flex flex-column scroll-y me-n7 pe-7 mt-10" id="kt_modal_plans_scroll">
                            <div class="form-group">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label style="font-size: 14px;">Patient</label>
                                        <strong style="font-size:18px;display: block;" id="user_name"></strong>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label style="font-size: 14px;">Centre</label>
                                        <strong style="font-size:18px;display: block;" id="location_name"></strong>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="row">
                                    <div class="table-responsive">
                                        <table id="plans_service" class="table table-bordered table-advance">
                                            <thead>
                                                <tr>
                                                    <th>Service Name</th>
                                                    <th>Regular Price</th>
                                                    <th>Discount Name</th>
                                                    <th>Type</th>
                                                    <th>Discount Value</th>
                                                    <th>Subtotal</th>
                                                    <th>Tax</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>
                                            <tbody class="display_plans"></tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-10 col-xs-8"></div>
                                    <div class="col-md-2 col-xs-4 invoice-block">
                                        <ul class="list-unstyled amounts">
                                            <li>
                                                <strong style="font-weight: bold;">Total:</strong> <span class="package_total_price"></span>/-
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="table-responsive">
                                        <h4>History</h4>
                                        <table id="plan_history" class="table table-bordered table-advance">
                                            <thead>
                                                <tr>
                                                    <th>Payment Mode</th>
                                                    <th>Cash Flow</th>
                                                    <th>Cash Amount</th>
                                                    <th>Created At</th>
                                                </tr>
                                            </thead>
                                            <tbody class="plan_history">
                                                <tr>
                                                    <td id="payment_mode"></td>
                                                    <td id="cash_flow"></td>
                                                    <td id="cash_amount"></td>
                                                    <td id="Created At"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="flex">
                                    <div class="row float-left">
                                        <div class="col-md-12 col-sm-12 col-xs-12 mt-10">
                                            <a class="btn btn-outline-dark margin-bottom-5" href="/admin/packages"> <i class="fa fa-arrow-left" aria-hidden="true"></i>Back To Plans
                                            </a>
                                        </div>
                                    </div>
                                    <div class="row float-right">
                                        <div class="col-md-12 col-sm-12 col-xs-12 mt-10">
                                            <a id="package_pdf" class="btn btn-lg btn-primary blue hidden-print margin-bottom-5" target="_blank" href="">Print
                                                <i class="fa fa-print"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--end::Scroll-->
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->

        <div class="modal fade" id="modal_edit_plan" tabindex="-1" aria-hidden="true">
            <!--begin::Modal dialog-->
            <div class="modal-dialog modal-dialog-centered very-big-modal" id="packages_edit">

                @include('admin.packages.edit')

            </div>
            <!--end::Modal dialog-->
        </div>
    </div>

    @push('js')
        <script src="{{ asset('assets/js/pages/admin_settings/create-plan.js') }}"></script>
        <script src="{{ asset('assets/js/pages/crud/forms/validation/admin_settings/refunds.js') }}"></script>

        <script>
            $(document).ready(function() {
                id = '{{ $id }}';
                url = route('admin.packages.display', {
                    id: id
                });
                viewPlan(url);
            });

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