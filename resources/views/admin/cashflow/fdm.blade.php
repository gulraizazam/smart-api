@extends('admin.layouts.master')
@section('title', 'Cash Flow - FDM View')
@section('content')
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'FDM Cash View', 'title' => 'Branch Cash'])
        <div class="d-flex flex-column-fluid">
            <div class="container">

                <!-- Big Balance Card -->
                <div class="card card-custom mb-5" id="fdm-balance-card">
                    <div class="card-body text-center py-8">
                        <div class="text-muted font-weight-bold mb-2" id="fdm-branch-name">Loading...</div>
                        <div class="font-weight-bolder display-4 mb-2" id="fdm-balance">PKR 0</div>
                        <div class="text-muted font-size-sm" id="fdm-pool-name"><i class="la la-circle text-success" style="font-size:8px;vertical-align:middle;"></i> Live Cash Balance</div>
                    </div>
                </div>

                <!-- Last 10 Days Cash Movements -->
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title"><h3 class="card-label"><i class="la la-calendar-alt mr-2"></i>Last 10 Days — Cash Movements</h3></div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-head-custom mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th class="text-right text-success">Inflows</th>
                                        <th class="text-right text-danger">Outflows</th>
                                        <th class="text-right">Running Balance</th>
                                    </tr>
                                </thead>
                                <tbody id="fdm-movements-tbody">
                                    <tr><td colspan="4" class="text-center text-muted py-5">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    @push('js')
        <script src="{{ asset('assets/js/pages/cashflow/fdm.js') }}"></script>
    @endpush
@endsection
