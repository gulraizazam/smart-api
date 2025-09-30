@extends('admin.layouts.master')
@section('title', 'Upselling Report')
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

.table-success {
    background-color: #d4edda !important;
}

.table-light {
    background-color: #f8f9fa !important;
}

.text-muted-small {
    font-size: 0.85em;
    color: #6c757d;
}
</style>
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Upselling Detail Report'])
    <div class="d-flex flex-column-fluid">
        <div class="container">

<div id="doctor_detail_report">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Upselling Details - {{ $doctorName }}</h4>
        <a href="{{ url()->previous() }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Summary
        </a>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Total Upselling Amount</h5>
                    <h3 class="text-primary">{{ number_format($totalAmount, 2) }}</h3>
                    <small class="text-muted">Based on same-day payments received</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Services with Upselling</h5>
                    <h3 class="text-success">{{ $detailData->where('has_upselling', true)->count() }} / {{ $detailData->count() }}</h3>
                    <small class="text-muted">{{ $uniqueUpsellings }} unique packages</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Services Sold Details</h5>
            <div>
                <span class="badge badge-success">With Upselling</span>
                <span class="badge badge-secondary">No Payment</span>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Package ID</th>
                            <th>Patient</th>
                            <th>Service Name</th>
                            <th class="text-right">Service Price</th>
                            <th class="text-right">Payment Received</th>
                            <th class="text-right">Upselling Amount</th>
                            <th>Service Added</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($detailData as $detail)
                            <tr class="{{ $detail->has_upselling ? 'table-success' : 'table-light' }}">
                                <td>
                                    <span class="badge badge-info">{{ $detail->package_id }}</span>
                                </td>
                                <td>
                                    <div>{{ $detail->patient_name ?? 'N/A' }}</div>
                                    <small class="text-muted-small">ID: {{ $detail->patient_id }}</small>
                                </td>
                                <td>
                                    <strong>{{ $detail->service_name }}</strong>
                                    @if(!$detail->has_upselling)
                                        <br><small class="text-muted">No same-day payment</small>
                                    @endif
                                </td>
                                <td class="text-right">
                                    {{ number_format($detail->tax_including_price, 2) }}
                                </td>
                                <td class="text-right">
                                    @if($detail->payment_received > 0)
                                        <span class="text-success font-weight-bold">
                                            {{ number_format($detail->payment_received, 2) }}
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($detail->actual_amount > 0)
                                        <span class="badge badge-success">
                                            {{ number_format($detail->actual_amount, 2) }}
                                        </span>
                                    @else
                                        <span class="text-muted">0.00</span>
                                    @endif
                                </td>
                                <td>
                                    {{ \Carbon\Carbon::parse($detail->created_at)->format('M d, Y') }}
                                    <br><small class="text-muted-small">{{ \Carbon\Carbon::parse($detail->created_at)->format('h:i A') }}</small>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">No services found for this doctor.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="font-weight-bold">
                        <tr>
                            <td colspan="5" class="text-right">Total Upselling:</td>
                            <td class="text-right">
                                <span class="badge badge-primary">{{ number_format($totalAmount, 2) }}</span>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <h6 class="card-title">Understanding Upselling Calculation</h6>
            <ul class="mb-0">
                <li><strong>Service Price:</strong> Original price of the service sold</li>
                <li><strong>Payment Received:</strong> Total payment made on the same day after service was added</li>
                <li><strong>Upselling Amount:</strong> Credited amount (minimum of payment or service price)</li>
                <li><strong>Green rows:</strong> Services that received same-day payment</li>
                <li><strong>Gray rows:</strong> Services without same-day payment (excluded from upselling)</li>
            </ul>
        </div>
    </div>
</div>
</div>
    </div>
</div>

@endsection