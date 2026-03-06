@extends('admin.layouts.master')
@section('title', 'Cash Flow - Reports')
@section('content')
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'Cash Flow Reports', 'title' => 'Reports'])
        <div class="d-flex flex-column-fluid">
            <div class="container">

                <!-- Report Selector -->
                <div class="card card-custom mb-5">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div class="d-flex align-items-center">
                                <select id="report-type" class="form-control form-control-sm kt-select2-general mr-3" style="width:250px;">
                                    <option value="cashflow-statement">Cash Flow Statement</option>
                                    <option value="branch-comparison">Branch Comparison</option>
                                    <option value="category-trend">Category Trend</option>
                                    <option value="vendor-outstanding">Vendor Outstanding</option>
                                    <option value="staff-advance">Staff Advance Summary</option>
                                    <option value="daily-movement">Daily Cash Movement</option>
                                    <option value="transfer-log">Transfer Log</option>
                                    <option value="flagged-entries">Flagged Entries</option>
                                    <option value="dormant-vendors">Dormant Vendors</option>
                                </select>
                                <input type="text" id="rpt-date-range" class="form-control form-control-sm mr-2" style="width:220px;" readonly />
                                <select id="rpt-branch" class="form-control form-control-sm kt-select2-general mr-2" style="width:180px;">
                                    <option value="">All Branches</option>
                                </select>
                                <select id="rpt-pool" class="form-control form-control-sm kt-select2-general mr-2 d-none" style="width:180px;">
                                    <option value="">All Pools</option>
                                </select>
                                <button id="btn-run-report" class="btn btn-primary mr-2"><i class="la la-play"></i> Generate</button>
                            </div>
                            <div>
                                @can('cashflow_reports_export')
                                <button id="btn-export-csv" class="btn btn-light-success"><i class="la la-file-excel"></i> Export Excel</button>
                                <button id="btn-export-pdf" class="btn btn-light-danger ml-2"><i class="la la-file-pdf"></i> Export PDF</button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Report Output -->
                <div id="report-output">
                    <div class="card card-custom">
                        <div class="card-body text-center py-10">
                            <i class="la la-chart-bar icon-4x text-muted mb-4"></i>
                            <h5 class="text-muted">Select a report and click Generate</h5>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    @push('js')
        <script src="{{ asset('assets/js/pages/cashflow/reports.js') }}"></script>
    @endpush
@endsection
