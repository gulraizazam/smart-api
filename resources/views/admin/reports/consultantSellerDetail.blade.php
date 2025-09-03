@extends('admin.layouts.master')
@section('title', 'Consultant-Seller Detail')
@section('content')
<style>
.badge {
    font-size: 0.9em;
}

.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: none;
    border-radius: 8px;
}

.table-responsive {
    border-radius: 8px;
}

.btn {
    border-radius: 6px;
}
</style>
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Consultant-Seller Detail Report'])
    <div class="d-flex flex-column-fluid">
        <div class="container">

<div id="consultant_seller_detail">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>{{ $consultantName }} × {{ $sellerName }} - Transaction Details</h4>
        <a href="{{ url()->previous() }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Breakdown
        </a>
    </div>

    <div class="alert alert-primary">
        <i class="fas fa-handshake"></i>
        <strong>Partnership Analysis:</strong> All transactions where <strong>{{ $consultantName }}</strong> performed the appointment and <strong>{{ $sellerName }}</strong> sold the services.
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Amount</h5>
                    <h3>{{ number_format($totalAmount, 2) }}</h3>
                    <small>Generated from partnership</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Consumed Amount</h5>
                    <h3>{{ number_format($totalConsumedAmount, 2) }}</h3>
                    <small>
                        {{ $totalAmount > 0 ? number_format(($totalConsumedAmount/$totalAmount)*100, 1) : 0 }}% consumption rate
                    </small>
                </div>
            </div>
        </div>
        
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Transaction Details</h5>
            <small class="text-muted">
                Consultant: {{ $consultantName }} | Seller: {{ $sellerName }}
            </small>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Package ID</th>
                            <th>Patient</th>
                            <th>Service</th>
                            <th>Amount</th>
                            <th>Consumed</th>
                            <th>Status</th>
                            <th>Appointment Date</th>
                            <th>Sale Date</th>
                            <th>Consumed Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($detailData as $detail)
                            <tr>
                                <td>
                                    <span class="badge badge-info">{{ $detail->package_id }}</span>
                                </td>
                                <td>
                                    <div>
                                        <span class="badge badge-secondary">{{ $detail->patient_id }}</span>
                                        <br>
                                        <small>{{ $detail->patient_name ?? 'N/A' }}</small>
                                    </div>
                                </td>
                                <td>{{ $detail->service_name }}</td>
                                <td class="font-weight-bold">{{ number_format($detail->actual_amount, 2) }}</td>
                                <td class="text-success">{{ number_format($detail->consumed_amount, 2) }}</td>
                                <td>
                                    @if($detail->is_consumed)
                                        <span class="badge badge-success">Consumed</span>
                                    @else
                                        <span class="badge badge-warning">Pending</span>
                                    @endif
                                </td>
                                <td>{{ \Carbon\Carbon::parse($detail->scheduled_date)->format('M d, Y') }}</td>
                                <td>{{ \Carbon\Carbon::parse($detail->created_at)->format('M d, Y') }}</td>
                                <td>
                                    @if($detail->consumed_at)
                                        {{ \Carbon\Carbon::parse($detail->consumed_at)->format('M d, Y') }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="fas fa-exclamation-triangle mb-2"></i><br>
                                    No transactions found between {{ $consultantName }} and {{ $sellerName }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

   
</div>
</div>
    </div>
</div>

@endsection