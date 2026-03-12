@extends('admin.layouts.master')
@section('title', 'Cash Flow - FDM View')
@section('content')
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'FDM Cash View', 'title' => 'Branch Cash'])
        <div class="d-flex flex-column-fluid">
            <div class="container">

                <!-- Balance Cards Row -->
                <div class="row mb-5">
                    <div class="col-md-6">
                        <div class="card card-custom" id="fdm-balance-card">
                            <div class="card-body text-center py-6">
                                <div class="text-muted font-weight-bold mb-1" id="fdm-branch-name">Loading...</div>
                                <div class="font-weight-bolder display-4 mb-1" id="fdm-balance">PKR 0</div>
                                <div class="text-muted font-size-sm"><i class="la la-circle text-success" style="font-size:8px;vertical-align:middle;"></i> Live Cash Balance</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-custom" id="fdm-opening-card">
                            <div class="card-body text-center py-6">
                                <div class="text-muted font-weight-bold mb-1">Opening Balance</div>
                                <div class="font-weight-bolder display-4 mb-1" id="fdm-opening-balance">PKR 0</div>
                                <div class="text-muted font-size-sm" id="fdm-week-label"><i class="la la-calendar" style="font-size:10px;vertical-align:middle;"></i> Since Sunday</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cash Transfers -->
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title"><h3 class="card-label"><i class="la la-exchange-alt mr-2 text-info"></i>Cash Transfers <span class="text-muted font-size-sm ml-2" id="fdm-transfers-period"></span></h3></div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-head-custom mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th class="text-right">Amount</th>
                                        <th>Description</th>
                                        <th>By</th>
                                    </tr>
                                </thead>
                                <tbody id="fdm-transfers-tbody">
                                    <tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Expenses -->
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title"><h3 class="card-label"><i class="la la-receipt mr-2 text-danger"></i>Expenses <span class="text-muted font-size-sm ml-2" id="fdm-expenses-period"></span></h3></div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-head-custom mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Category</th>
                                        <th>Paid From</th>
                                        <th class="text-right">Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="fdm-expenses-tbody">
                                    <tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Staff Advances -->
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title"><h3 class="card-label"><i class="la la-user-clock mr-2 text-warning"></i>Staff Advances <span class="text-muted font-size-sm ml-2" id="fdm-advances-period"></span></h3></div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-head-custom mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Staff Member</th>
                                        <th>Pool</th>
                                        <th class="text-right">Amount</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody id="fdm-advances-tbody">
                                    <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    @push('js')
        <script src="{{ asset('assets/js/pages/cashflow/fdm.js') }}?v={{ time() }}"></script>
    @endpush
@endsection
