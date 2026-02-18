@extends('admin.layouts.master')
@section('title', 'Memberships')
@section('content')

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'Memberships List', 'title' => 'Memberships'])

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
                            <h3 class="card-label">Memberships List</h3>

                        </div>

                        <div class="card-toolbar">
                            <!--begin::Dropdown-->
                            
                            @if(Gate::allows('memberships_create'))
                                <a href="javascript:void(0);" class="btn btn-success" data-toggle="modal" data-target="#modal_generate_codes">
                                    <i class="la la-layer-group"></i>
                                    Create Bulk Codes
                                </a>
                            @endif
                            &nbsp;&nbsp;
                          
                            @if(Gate::allows('memberships_create'))
                                <a href="javascript:void(0);" id="create_memberships" onclick="createMembership('{{ route('admin.memberships.create') }}');" class="btn btn-primary" data-toggle="modal" data-target="#modal_add_memberships">
                                    <i class="la la-plus"></i>
                                    Add New
                                </a>
                            @endif
                            <div class="btn-group" style="margin-left: 13px;">
                                    <a class="btn  btn-primary" href="javascript:void(0);" data-toggle="dropdown">
                                        <i class="fa fa-download"></i>
                                        <span class="hidden-xs"> Export </span>
                                        <i class="fa fa-angle-down"></i>
                                    </a>
                                    <ul class="dropdown-menu pull-right export_leads" id="datatable_ajax_tools">
                                        <li>
                                            <a href="#" title="Max pdf export limit is 100 records" id="export-memberships-leads" data-href="{{route('admin.memberships.export.pdf')}}" data-action="0" class="tool-action"><i class="la la-file-pdf"></i>
                                                PDF
                                                <!-- <span class="export-pdf-limit">(1 to {{config('constants.export-lead-pdf-limit')}})</span></a> -->
                                            </a>
                                        </li>
                                        <li>
                                            <a href="#" title="Max export limit is 1000 records" id="export-memberships" data-href="{{route('admin.membership.export.excel')}}" data-action="1" class="tool-action"><i class="la la-file-excel"></i>
                                                Excel
                                                <!-- <span class="export-excel-limit">(1 to {{config('constants.export-lead-excel-limit')}})</span> -->
                                            </a>
                                        </li>
                                        
                                    </ul>
                                </div>
                        <!--end::Button-->
                        </div>

                    </div>

                    <div class="card-body">
                        <!--begin::Search Form-->
                        @include('admin.memberships.filters')
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



    <div class="modal fade" id="modal_add_memberships" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="memberships_add">

            @include('admin.memberships.create')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_edit_memberships" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="edit_memberships">

            @include('admin.memberships.edit')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_import_memberships" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="import_memberships">

            @include('admin.memberships.import')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_generate_codes" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered modal-lg">
            @include('admin.memberships_types.generate-codes-modal')
        </div>
        <!--end::Modal dialog-->
    </div>

    @push('js')
        <script src="{{asset('assets/js/jquery.inputmask.bundle.min.js')}}"></script>
        <script src="{{asset('assets/js/jquery.copy-to-clipboard.js')}}"></script>

        <script src="{{asset('assets/js/pages/crud/forms/validation/admin_settings/memberships.js')}}"></script>
        <script src="{{asset('assets/js/search-phone.js')}}"></script>
    @endpush

    @push('datatable-js')
       
        <script src="{{asset('assets/js/pages/admin_settings/memberships.js')}}"></script>

        <script>
            jQuery(document).ready( function () {
                
                @if(request('from') != '' && request('to') != '')
                    setTimeout( function () {
                        $("#date_range").val("{{request('from')}}");
                        //$("#search_created_from").val("{{request('from')}}");
                        //$("#search_created_to").val("{{request('to')}}");
                        $("#apply-filters").click();

                    }, 800);
                @endif
            });
           
           
            
        
                
            
        </script>
    @endpush

@endsection
