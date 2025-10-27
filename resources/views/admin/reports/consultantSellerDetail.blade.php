@extends('admin.layouts.master')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title">Consultant Breakdown for {{ $doctor->name }}</h3>
                        <a href="#" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Report
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Summary Info -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="info-box bg-info">
                                <span class="info-box-icon"><i class="fas fa-user-md"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Doctor/Seller</span>
                                    <span class="info-box-number">{{ $doctor->name }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-success">
                                <span class="info-box-icon"><i class="fas fa-money-bill-wave"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Upselling</span>
                                    <span class="info-box-number">{{ number_format($totalUpselling, 2) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-warning">
                                <span class="info-box-icon"><i class="fas fa-map-marker-alt"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Location</span>
                                    <span class="info-box-number">{{ $location->name ?? 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-primary">
                                <span class="info-box-icon"><i class="fas fa-calendar"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Date Range</span>
                                    <span class="info-box-number" style="font-size: 14px;">
                                        {{ date('M d, Y', strtotime($startDate)) }} - {{ date('M d, Y', strtotime($endDate)) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Alert Box -->
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Explanation:</strong> This breakdown shows how {{ $doctor->name }}'s total upselling of 
                        <strong>{{ number_format($totalUpselling, 2) }} PKR</strong> is distributed across different consultants 
                        whose appointments had services sold by this doctor.
                    </div>

                    <!-- Breakdown Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Consultant Name</th>
                                    <th>Number of Services</th>
                                    <th>Total Amount (PKR)</th>
                                    <th>Percentage (%)</th>
                                    <th>Visual</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($breakdownData as $index => $data)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <i class="fas fa-user-doctor text-primary"></i>
                                            <strong>{{ $data->consultant_name }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">{{ $data->service_count }} services</span>
                                        </td>
                                        <td>
                                            <strong class="text-success">{{ number_format($data->total_amount, 2) }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary">{{ number_format($data->percentage, 2) }}%</span>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 25px;">
                                                <div class="progress-bar bg-success" 
                                                     role="progressbar" 
                                                     style="width: {{ $data->percentage }}%"
                                                     aria-valuenow="{{ $data->percentage }}" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    {{ number_format($data->percentage, 1) }}%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            <i class="fas fa-inbox"></i> No consultant breakdown data available.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($breakdownData->count() > 0)
                                <tfoot class="thead-light">
                                    <tr>
                                        <th colspan="2">TOTAL</th>
                                        <th>
                                            <span class="badge badge-info">{{ $breakdownData->sum('service_count') }} services</span>
                                        </th>
                                        <th>
                                            <strong class="text-success">{{ number_format($totalUpselling, 2) }}</strong>
                                        </th>
                                        <th>
                                            <span class="badge badge-primary">100%</span>
                                        </th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>

                    <!-- Chart Section (Optional) -->
                    @if($breakdownData->count() > 0)
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h5>Distribution Chart</h5>
                                <canvas id="consultantChart" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if($breakdownData->count() > 0)
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('consultantChart').getContext('2d');
    const chartData = {
        labels: [
            @foreach($breakdownData as $data)
                '{{ $data->consultant_name }}',
            @endforeach
        ],
        datasets: [{
            label: 'Amount (PKR)',
            data: [
                @foreach($breakdownData as $data)
                    {{ $data->total_amount }},
                @endforeach
            ],
            backgroundColor: [
                'rgba(54, 162, 235, 0.6)',
                'rgba(75, 192, 192, 0.6)',
                'rgba(255, 206, 86, 0.6)',
                'rgba(153, 102, 255, 0.6)',
                'rgba(255, 159, 64, 0.6)',
                'rgba(255, 99, 132, 0.6)',
                'rgba(201, 203, 207, 0.6)'
            ],
            borderColor: [
                'rgba(54, 162, 235, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(153, 102, 255, 1)',
                'rgba(255, 159, 64, 1)',
                'rgba(255, 99, 132, 1)',
                'rgba(201, 203, 207, 1)'
            ],
            borderWidth: 2
        }]
    };

    const config = {
        type: 'bar',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'PKR ' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'PKR ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    };

    new Chart(ctx, config);
</script>
@endpush
@endif

@endsection