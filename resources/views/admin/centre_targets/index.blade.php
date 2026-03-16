@extends('admin.layouts.master')
@section('title', 'Centre Targets')
@section('content')

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'Centre Target List', 'title' => 'Centre Targets'])

    <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">

                <!--begin::System Targets Card-->
                <div class="card card-custom mb-6" id="systemTargetsCard">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon">
                                <i class="la la-bullseye text-primary" style="font-size: 1.5rem;"></i>
                            </span>
                            <h3 class="card-label">System-Wide Targets</h3>
                        </div>
                        <div class="card-toolbar">
                            <span class="text-muted font-size-sm" id="stSaveStatus"></span>
                        </div>
                    </div>
                    <div class="card-body py-4">
                        <div class="row">
                            <div class="col-md-3 col-sm-6 mb-4">
                                <label class="font-weight-bold font-size-sm">Conversion Rate Target (%)</label>
                                <input type="number" class="form-control form-control-sm st-input" data-key="conversion_pct" step="0.1" min="0" max="100" placeholder="50">
                            </div>
                            <div class="col-md-3 col-sm-6 mb-4">
                                <label class="font-weight-bold font-size-sm">Avg Conversion Revenue</label>
                                <input type="number" class="form-control form-control-sm st-input" data-key="avg_conversion_revenue" step="100" min="0" placeholder="15000">
                            </div>
                            <div class="col-md-3 col-sm-6 mb-4">
                                <label class="font-weight-bold font-size-sm">Feedback Score Target</label>
                                <input type="number" class="form-control form-control-sm st-input" data-key="feedback_score" step="0.1" min="0" max="10" placeholder="9.5">
                            </div>
                            <div class="col-md-3 col-sm-6 mb-4">
                                <label class="font-weight-bold font-size-sm">Upselling Target</label>
                                <input type="number" class="form-control form-control-sm st-input" data-key="upselling_target" step="100" min="0" placeholder="0">
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::System Targets Card-->

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
                            <h3 class="card-label">Centre Targets</h3>
                        </div>

                        <div class="card-toolbar">
                            <!--begin::Dropdown-->
                            @if(Gate::allows('centre_targets_destroy'))
                                <div class="delete-records d-none">
                                    <span>Selected Rows: <span class="checkbox-count"></span></span>
                                    <a id="delete-table-rows" href="javascript:void(0);" class="btn btn-danger font-weight-bolder">
                                        <i class="fa fa-trash-alt"></i>Delete
                                    </a>
                                </div>&nbsp;&nbsp;&nbsp;
                            @endif

                            @if(Gate::allows('centre_targets_create'))
                                <a href="javascript:void(0);" onclick="createCentreTarget('{{ route('admin.centre_targets.create') }}');" class="btn btn-primary" data-toggle="modal" data-target="#modal_add_centre_targets">
                                    <i class="la la-plus"></i>
                                    Add New
                                </a>
                        @endif

                        <!--end::Button-->
                        </div>

                    </div>

                    <div class="card-body">
                        <!--begin::Search Form-->
                    @include('admin.centre_targets.filters')
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

    <div class="modal fade" id="modal_add_centre_targets" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="centre_targets_add">

            @include('admin.centre_targets.create')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_edit_centre_targets" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="centre_targets_edit">

            @include('admin.centre_targets.edit')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_display_centre_targets" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="centre_targets_display">

            @include('admin.centre_targets.display')

        </div>
        <!--end::Modal dialog-->
    </div>


    @push('datatable-js')
        <script src="{{asset('assets/js/pages/admin_settings/centre_targets.js')}}"></script>
    @endpush

    @push('js')
        <script src="{{asset('assets/js/pages/crud/forms/validation/admin_settings/centre_targets.js')}}"></script>
        <script>
            (function() {
                var stRoutes = {
                    index: "{{ route('admin.system_targets.index') }}",
                    save: "{{ route('admin.system_targets.save') }}"
                };
                var csrfToken = document.querySelector('meta[name="csrf-token"]');
                csrfToken = csrfToken ? csrfToken.getAttribute('content') : '';

                function loadSystemTargets() {
                    fetch(stRoutes.index, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            if (res.status && res.data) {
                                res.data.forEach(function(t) {
                                    var input = document.querySelector('.st-input[data-key="' + t.key + '"]');
                                    if (input) input.value = t.value;
                                });
                            }
                        })
                        .catch(function(err) { console.error('System targets load error:', err); });
                }

                function saveTarget(input) {
                    var key = input.dataset.key;
                    var value = parseFloat(input.value) || 0;
                    var statusEl = document.getElementById('stSaveStatus');
                    statusEl.textContent = 'Saving...';

                    fetch(stRoutes.save, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({ key: key, value: value })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        statusEl.textContent = res.status ? 'Saved ✓' : 'Error';
                        setTimeout(function() { statusEl.textContent = ''; }, 2000);
                    })
                    .catch(function() {
                        statusEl.textContent = 'Error saving';
                        setTimeout(function() { statusEl.textContent = ''; }, 2000);
                    });
                }

                document.addEventListener('DOMContentLoaded', function() {
                    loadSystemTargets();
                    document.querySelectorAll('.st-input').forEach(function(input) {
                        input.addEventListener('change', function() { saveTarget(this); });
                    });
                });
            })();
        </script>
    @endpush

@endsection
