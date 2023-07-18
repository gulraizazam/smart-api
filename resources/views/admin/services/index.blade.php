@extends('admin.layouts.master')
@section('title', 'Services')
@section('content')
    @push('css')
        <style>
            .datatable-pager {
                display: none !important;
            }
        </style>
    @endpush

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

        @include('admin.partials.breadcrumb', ['module' => 'Service List', 'title' => 'Services'])

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
                            <h3 class="card-label">Services</h3>
                        </div>

                        <div class="card-toolbar">
                            <!--begin::Dropdown-->
                            @if(Gate::allows('services_destroy'))
                                <div class="delete-records d-none">
                                    <span>Selected Rows: <span class="checkbox-count"></span></span>
                                    <a id="delete-table-rows" href="javascript:void(0);" class="btn btn-danger font-weight-bolder">
                                        <i class="fa fa-trash-alt"></i>Delete
                                    </a>
                                </div>&nbsp;&nbsp;&nbsp;
                            @endif

                            @if(Gate::allows('services_create'))
                                <a href="javascript:void(0);" onclick="createService('{{ route('admin.services.create') }}');" class="btn btn-primary" data-toggle="modal" data-target="#modal_add_services">
                                    <i class="la la-plus"></i>
                                    Add New
                                </a>
                            @endif

                        <!--end::Button-->
                        </div>

                    </div>

                    <div class="card-body">
                     <!--begin::Search Form-->
                        @include('admin.services.filters')
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

    <div class="modal fade" id="modal_add_services" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="services_add">

            @include('admin.services.create')
        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_edit_services" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="services_edit">

            @include('admin.services.edit')
        </div>
        <!--end::Modal dialog-->
    </div>


    @push('datatable-js')
        <script src="{{asset('assets/js/pages/admin_settings/services.js')}}"></script>
    @endpush

    @push('js')
        <script src="{{asset('assets/js/pages/crud/forms/validation/admin_settings/services.js')}}"></script>
        <script>
            function SetName()
            {
                $("#filter_service_name").val($("#search_name").val());

            }
            function SetStatus()
            {
                $("#filter_active").val($("#search_status").val());
            }
            function getColor()
            {
                var service = $('#add_parent_service').val();
                if(service > 0){
                    $.ajax({
                        type:'GET',
                        url:"{{route('admin.dashboard.getcolor')}}",
                        data:{'service':service},
                        success:function(data) {
                            $("#service_color").val(data.color);
                        }
                    });
                    $('.servicefield').show();
                    $('#endnode').attr('checked','checked');
                }else{
                    $('.servicefield').hide();
                }
            }
            function getEditColor()
            {
                var service = $('#edit_parent_service').val();
                if(service > 0){
                    $.ajax({
                        type:'GET',
                        url:"{{route('admin.dashboard.getcolor')}}",
                        data:{'service':service},
                        success:function(data) {
                            $("#edit_color").val(data.color);
                        }
                    });
                    $('.servicefield').show();
                }else{
                    $('.servicefield').hide();
                }
            }
        </script>
    @endpush
@endsection
