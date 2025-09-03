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



.btn {
    border-radius: 6px;
}
</style>
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Consultant Revenue Report'])
    <div class="d-flex flex-column-fluid">
        <div class="container">

<div id="consultant_revenue_report">
    
    <div class="alert alert-success">
        <i class="fas fa-user-md"></i>
        <strong>Consultant Revenue Report:</strong> Shows revenue attributed to doctors who <strong>performed</strong> the appointments, regardless of who sold the services.
        <br>
        <small class="text-muted">This helps evaluate which consultants generate the most upselling opportunities for their patients.</small>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Consultant (Appointment Doctor)</th>
                <th>Total Upselling Revenue</th>
                <th>Total Consumed Amount</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportData as $report)
                <tr>
                    <td>{{ $report->consultant_name ?? 'Unknown' }}</td>
                    <td>{{ number_format($report->total_consultation_revenue, 2) }}</td>
                    <td>{{ number_format($report->total_consumed_amount, 2) }}</td>
                    <td>
                        <a href="{{ route('admin.consultant.revenue.detail', $report->consultant_id) }}"
                           class="btn btn-success btn-sm">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">No data available for this location.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
</div>
    </div>
</div>

@endsection