@extends('admin.layouts.master')
@section('title', 'Doctor Revenue Detail')
@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Doctor Revenue Detail'])
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
                                            <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13" rx="1.5" />
                                            <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8" rx="1.5" />
                                            <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero" />
                                            <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5" />
                                        </g>
                                    </svg>
                                </span>
                            </span>
                            <h3 class="card-label">Doctor Revenue Detail - {{ $doctorName }}</h3>
                        </div>
                        <div class="card-toolbar">
                            <a href="{{ route('reports.doctor_revenue') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Report
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-light-success">
                                    <div class="card-body py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-40 symbol-light-success mr-3">
                                                <span class="symbol-label">
                                                    <i class="fas fa-money-bill-wave text-success"></i>
                                                </span>
                                            </div>
                                            <div>
                                                <div class="font-size-h4 font-weight-bold">{{ number_format($totalRevenue, 2) }}</div>
                                                <div class="text-muted">Total Revenue</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light-primary">
                                    <div class="card-body py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-40 symbol-light-primary mr-3">
                                                <span class="symbol-label">
                                                    <i class="fas fa-receipt text-primary"></i>
                                                </span>
                                            </div>
                                            <div>
                                                <div class="font-size-h4 font-weight-bold">{{ $totalPayments }}</div>
                                                <div class="text-muted">Total Payments</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light-info">
                                    <div class="card-body py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-40 symbol-light-info mr-3">
                                                <span class="symbol-label">
                                                    <i class="fas fa-box text-info"></i>
                                                </span>
                                            </div>
                                            <div>
                                                <div class="font-size-h4 font-weight-bold">{{ $uniquePackages }}</div>
                                                <div class="text-muted">Unique Packages</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <table class="table table-bordered table-hover" id="payments_table">
                            <thead class="thead-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Patient Name</th>
                                    <th>Package ID</th>
                                    <th>Payment Mode</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($allTransactions as $transaction)
                                    <tr class="{{ $transaction->cash_flow == 'out' ? 'table-danger' : '' }}">
                                        <td>{{ \Carbon\Carbon::parse($transaction->created_at)->format('d M Y H:i') }}</td>
                                        <td>
                                            <a href="{{ route('admin.patients.show', $transaction->patient_id) }}" target="_blank">
                                                {{ $transaction->patient_name }}
                                            </a>
                                        </td>
                                        <td>{{ $transaction->package_name }}</td>
                                        <td>{{ $transaction->payment_mode ?? 'N/A' }}</td>
                                        <td>
                                            @if($transaction->cash_flow == 'in')
                                                <span class="badge badge-success">Payment</span>
                                            @else
                                                <span class="badge badge-danger">Refund</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($transaction->cash_flow == 'out')
                                                <span class="text-danger">-{{ number_format($transaction->cash_amount, 2) }}</span>
                                            @else
                                                {{ number_format($transaction->cash_amount, 2) }}
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No transactions found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->
    @push('datatable-js')
        <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
        <script>
            $(document).ready(function() {
                $('#payments_table').DataTable({
                    dom: 'Bfrtip',
                    buttons: [
                        'copy', 'csv', 'excel', 'pdf'
                    ],
                    order: [[0, 'desc']]
                });
            });
        </script>
    @endpush
@endsection
