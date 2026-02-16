@extends('admin.layouts.master')
@section('title', 'Business Closed Periods')
@section('content')

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'Business Closed Periods', 'title' => 'Schedule'])

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
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <rect x="0" y="0" width="24" height="24" />
                                            <path d="M12,22 C7.02943725,22 3,17.9705627 3,13 C3,8.02943725 7.02943725,4 12,4 C16.9705627,4 21,8.02943725 21,13 C21,17.9705627 16.9705627,22 12,22 Z" fill="#000000" opacity="0.3"/>
                                            <path d="M11.9630156,7.5 L12.0475062,7.5 C12.3043819,7.5 12.5194647,7.69464724 12.5450248,7.95024814 L13,12.5 L16.2480695,14.3560397 C16.403857,14.4450611 16.5,14.6107328 16.5,14.7901613 L16.5,15 C16.5,15.2761424 16.2761424,15.5 16,15.5 L15.249851,15.5 L15.249851,15.5 C15.0911498,15.5 14.9461678,15.4140498 14.8714286,15.2764932 L12.0670544,10.0368498 C12.0227605,9.95327182 12,9.86022447 12,9.76553961 L12,7.75 C12,7.61192881 12.1119288,7.5 12.25,7.5 L11.9630156,7.5 Z" fill="#000000"/>
                                        </g>
                                    </svg>
                                </span>
                            </span>
                            <h3 class="card-label">Business Closed Periods</h3>
                        </div>

                        <div class="card-toolbar">
                            @if(Gate::allows('business_closures_create'))
                                <a href="javascript:void(0);" onclick="openAddModal();" class="btn btn-primary" data-toggle="modal" data-target="#modal_add_business_closure">
                                    <i class="la la-plus"></i>
                                    Add New
                                </a>
                            @endif
                        </div>

                    </div>

                    <div class="card-body">
                        <!--begin::Search Form-->
                        @include('admin.business-closures.filters')
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

    <!-- Add Modal -->
    <div class="modal fade" id="modal_add_business_closure" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            @include('admin.business-closures.create')
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="modal_edit_business_closure" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            @include('admin.business-closures.edit')
        </div>
    </div>

    @push('datatable-js')
        <script>
            var changePages = 100;
        </script>
        <script src="{{asset('assets/js/pages/admin_settings/business-closures.js')}}?v={{ time() }}"></script>
    @endpush

@endsection
