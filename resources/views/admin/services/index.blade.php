@extends('admin.layouts.master')
@section('title', 'Services')
@section('content')
    @push('css')
        <link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.8/dist/trix.css">
        <style>
            trix-editor {
                border: 1px solid #E4E6EF !important;
                border-radius: 0 !important;
                padding: 0.75rem 1rem !important;
                background-color: #ffffff !important;
                min-height: 150px;
                overflow: visible !important;
            }

            @media (max-width: 768px) {
                /* Datatable */
                .datatable { overflow-x: auto !important; -webkit-overflow-scrolling: touch; }
                .datatable table { min-width: 100%; width: auto !important; }
                .datatable-cell { padding: 6px 8px !important; font-size: 13px !important; }
                .datatable-head .datatable-cell { padding: 8px !important; font-size: 12px !important; }
                .datatable, .datatable-head, .datatable-header, .datatable-wrapper {
                    margin-top: 0 !important; padding-top: 0 !important; margin-bottom: 0 !important;
                }

                /* Kill all excess spacing */
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

                /* Toolbar */
                .card-toolbar { width: 100%; display: flex !important; flex-wrap: wrap; gap: 6px; padding: 2px 0; }
                .card-toolbar .btn { font-size: 12px; padding: 5px 10px; }

                /* Modals full-width */
                #modal_add_services .modal-dialog,
                #modal_edit_services .modal-dialog {
                    margin: 10px; max-width: calc(100% - 20px);
                }
                #modal_add_services .modal-body,
                #modal_edit_services .modal-body {
                    padding-left: 10px !important; padding-right: 10px !important;
                    margin-left: 0 !important; margin-right: 0 !important;
                }

                /* Instructions modal - full screen on mobile */
                #modal_service_instructions .modal-dialog {
                    margin: 0; max-width: 100%; height: 100%; width: 100%;
                }
                #modal_service_instructions .modal-content {
                    height: 100%; border-radius: 0; border: none;
                }
                #modal_service_instructions .modal-header {
                    padding: 12px 15px; position: sticky; top: 0; z-index: 1;
                    background: #fff; border-bottom: 1px solid #ebedf3;
                }
                #modal_service_instructions .modal-header h2 { font-size: 0.95rem !important; }
                #modal_service_instructions .modal-body {
                    padding: 12px 15px; max-height: none !important; flex: 1 1 auto; overflow-y: auto;
                    -webkit-overflow-scrolling: touch;
                }
                #modal_service_instructions .modal-footer {
                    position: sticky; bottom: 0; background: #fff;
                    border-top: 1px solid #ebedf3; padding: 10px 15px;
                }
                #modal_service_instructions #service_instructions_content {
                    padding: 5px !important; font-size: 14px; line-height: 1.6;
                }
                #modal_service_instructions #service_instructions_content img { max-width: 100% !important; height: auto !important; }
                #modal_service_instructions #service_instructions_content table { display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; }
                #modal_service_instructions #service_instructions_content pre { overflow-x: auto; white-space: pre-wrap; word-break: break-word; }
            }
        </style>
    @endpush

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'Service List', 'title' => 'Services'])

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
                            <h3 class="card-label">Services</h3>
                        </div>

                        <div class="card-toolbar">
                            @can('services.destroy')
                                <div class="delete-records d-none">
                                    <span>Selected: <span class="checkbox-count"></span></span>
                                    <a id="delete-table-rows" href="javascript:void(0);" class="btn btn-sm btn-danger">
                                        <i class="fa fa-trash-alt"></i> Delete
                                    </a>
                                </div>
                            @endcan
                            @can('services.sort')
                                <a href="{{route('admin.services.sort_get')}}" class="btn btn-sm btn-info mr-2">
                                    <i class="fa fa-sort-amount-up"></i> Sort
                                </a>
                            @endcan
                            @can('services.create')
                                <a href="javascript:void(0);" onclick="createService('{{ route('admin.services.create') }}');" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal_add_services">
                                    <i class="la la-plus"></i> Add New
                                </a>
                            @endcan
                        </div>
                    </div>

                    <div class="card-body">
                        @include('admin.services.filters')
                        <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add Service Modal --}}
    <div class="modal fade" id="modal_add_services" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered form-popup">
            @include('admin.services.create')
        </div>
    </div>

    {{-- Edit Service Modal --}}
    <div class="modal fade" id="modal_edit_services" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered form-popup">
            @include('admin.services.edit')
        </div>
    </div>

    {{-- Bundle Impact Confirmation Modal --}}
    <div class="modal fade" id="modal_bundle_impact" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="display: flex; align-items: center; justify-content: space-between;">
                    <h2 class="fw-bolder" style="font-size: 1.25rem; margin: 0;">Confirm Bundle Re-pricing</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" id="bundle_impact_close" aria-label="Close">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <p class="mb-4">Changing this service's price will re-price the following bundle(s):</p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Bundle</th>
                                    <th class="text-end">Current Price</th>
                                    <th class="text-end">New Price</th>
                                </tr>
                            </thead>
                            <tbody id="bundle_impact_rows"></tbody>
                        </table>
                    </div>
                    <p class="text-muted small mt-3 mb-0">Already-sold bundles keep their original price — only the catalog templates are updated.</p>
                </div>
                <div class="modal-footer" style="padding: 10px 20px;">
                    <button type="button" class="btn btn-light" id="bundle_impact_cancel">Cancel</button>
                    <button type="button" class="btn btn-primary" id="bundle_impact_confirm">Confirm &amp; Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Instructions Modal --}}
    <div class="modal fade" id="modal_service_instructions" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="display: flex; align-items: center; justify-content: space-between;">
                    <h2 class="fw-bolder" style="font-size: 1.25rem; word-break: break-word; margin: 0;">Service Instructions</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" data-kt-users-modal-action="close">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <div id="service_instructions_content" style="min-height: 100px; padding: 10px; word-wrap: break-word;">
                    </div>
                </div>
                <div class="modal-footer" style="padding: 10px 20px;">
                    <button type="button" class="btn btn-light popup-close">Close</button>
                </div>
            </div>
        </div>
    </div>

    @push('datatable-js')
        <script>
            var hasEditRights = {{ Gate::allows('services.edit') || Gate::allows('services.destroy') || Gate::allows('services.activate') || Gate::allows('services.deactivate') ? 'true' : 'false' }};
        </script>
        <script src="{{asset('assets/js/pages/admin_settings/services.js')}}"></script>
    @endpush

    @push('js')
        <script src="https://unpkg.com/trix@2.0.8/dist/trix.umd.min.js"></script>
        <script src="{{asset('assets/js/pages/crud/forms/validation/admin_settings/services.js')}}"></script>
        <script>
            function getColor() {
                var service = $('#add_parent_service').val();
                if (service > 0) {
                    $.ajax({
                        type: 'GET',
                        url: "{{route('admin.dashboard.getcolor')}}",
                        data: { service: service },
                        success: function(data) { $("#service_color").val(data.color); }
                    });
                    $('.servicefield').show();
                    $('#endnode').prop('checked', true);
                } else {
                    $('.servicefield').hide();
                }
            }
            function getEditColor() {
                var service = $('#edit_parent_service').val();
                if (service > 0) {
                    $.ajax({
                        type: 'GET',
                        url: "{{route('admin.dashboard.getcolor')}}",
                        data: { service: service },
                        success: function(data) { $("#edit_color").val(data.color); }
                    });
                    $('.servicefield').show();
                } else {
                    $('.servicefield').hide();
                }
            }
        </script>
    @endpush
@endsection
