@extends('admin.layouts.master')
@section('title', 'Sort Packages')
@section('content')
    <style>
        .sort-list { padding: 5px; min-height: 40px; }
        .sort-item {
            display: flex; align-items: center; padding: 10px 12px; margin: 4px 0;
            background: #fff; border: 1px solid #ebedf3; border-radius: 4px;
            cursor: grab; transition: box-shadow 0.15s, border-color 0.15s;
        }
        .sort-item:hover { border-color: #3699FF; box-shadow: 0 2px 6px rgba(54,153,255,0.12); }
        .sort-item:active { cursor: grabbing; }
        .sort-item .drag-handle {
            color: #B5B5C3; margin-right: 12px; font-size: 16px; flex-shrink: 0;
        }
        .sort-item .sort-num {
            background: #F3F6F9; color: #7E8299; font-size: 11px; font-weight: 600;
            width: 24px; height: 24px; border-radius: 50%; display: flex;
            align-items: center; justify-content: center; margin-right: 10px; flex-shrink: 0;
        }
        .sort-item .sort-name { font-size: 13px; color: #3F4254; }

        /* Draggable mirror/source styles */
        .draggable-mirror { opacity: 0.9; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .draggable--original { opacity: 0.3; }
        .draggable-source--is-dragging { opacity: 0.3; }

        @media (max-width: 768px) {
            #kt_subheader { display: none !important; }
            #kt_content { padding: 4px 0 0 !important; }
            .d-flex.flex-column-fluid { padding: 0 !important; }
            .container { padding: 0 6px !important; }
            .card-custom { border-radius: 0 !important; box-shadow: none !important; }
            .card-header { padding: 8px 10px !important; min-height: auto !important; flex-wrap: wrap !important; gap: 6px; }
            .card-header .card-title { margin: 0 !important; }
            .card-header .card-label { font-size: 14px !important; }
            .card-header .card-icon { display: none !important; }
            .card-body { padding: 8px !important; }
            .sort-item { padding: 12px 10px; }
            .sort-item .drag-handle { font-size: 18px; margin-right: 10px; }
            .sort-item .sort-name { font-size: 14px; }
        }
    </style>

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'Sort Packages', 'title' => 'Sort Packages'])

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
                            <h3 class="card-label">Sort Packages</h3>
                        </div>
                        <div class="card-toolbar">
                            <a href="{{ route('admin.bundles.index') }}" class="btn btn-sm btn-light-primary">
                                <i class="fa fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body" id="sort-container">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="text-muted mt-2">Loading bundles...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('js')
        <script src="{{ asset('assets/plugins/custom/draggable/draggable.bundle.js?v=7.2.9') }}"></script>
        <script src="{{ asset('assets/js/pages/bundles-sort.js') }}"></script>
    @endpush
@endsection
