@extends('admin.layouts.master')
@section('title', 'Bundles')
@section('content')
    @push('css')
        <style>
            @media (max-width: 768px) {
                .datatable { overflow-x: auto !important; -webkit-overflow-scrolling: touch; }
                .datatable table { min-width: 100%; width: auto !important; }
                .datatable-cell { padding: 6px 8px !important; font-size: 13px !important; }
                .datatable-head .datatable-cell { padding: 8px !important; font-size: 12px !important; }
                .datatable, .datatable-head, .datatable-header, .datatable-wrapper {
                    margin-top: 0 !important; padding-top: 0 !important; margin-bottom: 0 !important;
                }
                #kt_subheader { display: none !important; }
                #kt_content { padding: 4px 0 0 !important; }
                .d-flex.flex-column-fluid { padding: 0 !important; margin: 0 !important; }
                .container { padding: 0 6px !important; margin: 0 auto !important; }
                .card-custom { border-radius: 0 !important; box-shadow: none !important; }
                .card-header { padding: 6px 10px !important; min-height: auto !important; flex-wrap: wrap !important; gap: 6px; }
                .card-header .card-title { margin: 0 !important; }
                .card-header .card-label { font-size: 14px !important; }
                .card-header .card-icon { display: none !important; }
                .card-body { padding: 6px 8px !important; }
                .card-toolbar { width: 100%; display: flex !important; flex-wrap: wrap; gap: 6px; padding: 2px 0; }
                .card-toolbar .btn { font-size: 12px; padding: 5px 10px; }
                #modal_service_bundles .modal-dialog { margin: 10px; max-width: calc(100% - 20px); }
                #modal_service_bundles .modal-body { padding: 10px !important; margin: 0 !important; }
                #modal_bulk_bundles .modal-dialog { margin: 10px; max-width: calc(100% - 20px); }
                #modal_bulk_bundles .modal-body { padding: 10px !important; margin: 0 !important; }
                #modal_detail_service_bundle .modal-dialog {
                    margin: 0; max-width: 100%; height: 100%; width: 100%;
                }
                #modal_detail_service_bundle .modal-content {
                    height: 100%; border-radius: 0; border: none;
                }
                #modal_detail_service_bundle .modal-body {
                    padding: 12px 15px; max-height: none !important; flex: 1 1 auto;
                    overflow-y: auto; -webkit-overflow-scrolling: touch;
                }
            }
        </style>
    @endpush

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'Bundles', 'title' => 'Bundles'])

        <div class="d-flex flex-column-fluid">
            <div class="container">
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon">
                                <span class="svg-icon svg-icon-md svg-icon-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <rect x="0" y="0" width="24" height="24"/>
                                            <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13" rx="1.5"/>
                                            <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8" rx="1.5"/>
                                            <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero"/>
                                            <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5"/>
                                        </g>
                                    </svg>
                                </span>
                            </span>
                            <h3 class="card-label">Bundles</h3>
                        </div>
                        <div class="card-toolbar">
                            @can('bundles.destroy')
                                <div class="delete-records d-none">
                                    <span>Selected: <span class="checkbox-count"></span></span>
                                    <a id="delete-table-rows" href="javascript:void(0);" class="btn btn-sm btn-danger">
                                        <i class="fa fa-trash-alt"></i> Delete
                                    </a>
                                </div>
                            @endcan
                            @can('bundles.sort')
                                <a href="{{ route('admin.service-bundles.sort') }}" class="btn btn-sm btn-info mr-2">
                                    <i class="fa fa-sort-amount-up"></i> Sort
                                </a>
                            @endcan
                            @can('bundles.create')
                                <a href="javascript:void(0);" class="btn btn-sm btn-success mr-2" id="bulk-create-btn" data-toggle="modal" data-target="#modal_bulk_bundles">
                                    <i class="la la-layer-group"></i> Bulk Create
                                </a>
                                <a href="javascript:void(0);" class="btn btn-sm btn-primary" id="create-btn" data-toggle="modal" data-target="#modal_service_bundles">
                                    <i class="la la-plus"></i> Create Bundle
                                </a>
                            @endcan
                        </div>
                    </div>

                    <div class="card-body">
                        @include('admin.service-bundles.filters')
                        <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Create/Edit Modal --}}
    <div class="modal fade" id="modal_service_bundles" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered form-popup">
            @include('admin.service-bundles.create')
        </div>
    </div>

    {{-- Bulk Create Modal --}}
    <div class="modal fade" id="modal_bulk_bundles" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            @include('admin.service-bundles.bulk-create')
        </div>
    </div>

    {{-- Detail Modal --}}
    <div class="modal fade" id="modal_detail_service_bundle" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            @include('admin.service-bundles.detail')
        </div>
    </div>

    @push('datatable-js')
        <script>
            var hasEditRights = {{ Gate::allows('bundles.edit') || Gate::allows('bundles.destroy') || Gate::allows('bundles.activate') || Gate::allows('bundles.deactivate') ? 'true' : 'false' }};
        </script>
        <script src="{{asset('assets/js/pages/admin_settings/service-bundles.js')}}"></script>
    @endpush

    @push('js')
        <script src="{{asset('assets/js/pages/crud/forms/validation/admin_settings/service-bundles.js')}}"></script>
    @endpush

@endsection
