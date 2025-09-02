@extends('admin.layouts.master')
@section('title', 'Consultant Revenue Report')
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
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Consultant Revenue Detail Report'])
        <div class="d-flex flex-column-fluid">
            <div class="container">

<div id="consultant_detail_report">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Consultation Revenue Details - {{ $consultantName }}</h4>
        <a href="{{ url()->previous() }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Summary
        </a>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Total Consultation Revenue</h5>
                    <h3 class="text-primary">{{ number_format($totalAmount, 2) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Total Consultations</h5>
                    <h3 class="text-success">{{ $uniqueConsultations }}</h3>
                    <small class="text-muted">{{ $detailData->count() }} service records</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Consultation Services Details</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Package ID</th>
                            <th>Patient ID</th>
                            <th>Patient Name</th>
                            <th>Service Name</th>
                            <th>Price</th>
                            <th>Appointment Date</th>
                            <th>Sold Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($detailData as $detail)
                            <tr>
                                <td>
                                    <span class="badge badge-info">{{ $detail->package_id }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-secondary">{{ $detail->patient_id }}</span>
                                </td>
                                <td>{{ $detail->patient_name ?? 'N/A' }}</td>
                                <td>{{ $detail->service_name }}</td>
                                <td>{{ number_format($detail->actual_amount, 2) }}</td>
                                <td>{{ \Carbon\Carbon::parse($detail->scheduled_date)->format('M d, Y h:i A') }}</td>
                                <td>{{ \Carbon\Carbon::parse($detail->created_at)->format('M d, Y h:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">No consultation services found for this consultant.</td>
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