@extends('admin.layouts.master')
@section('title', 'Doctor Consultant Breakdown')
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

.stats-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.percentage-bar {
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
    height: 8px;
    overflow: hidden;
}

.percentage-fill {
    background: #fff;
    height: 100%;
    border-radius: 10px;
    transition: width 0.3s ease;
}
</style>
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Doctor Consultant Breakdown'])
    <div class="d-flex flex-column-fluid">
        <div class="container">

<div id="doctor_consultant_breakdown">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Consultant Breakdown for {{ $sellerName }}</h4>
        <a href="{{ url()->previous() }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Report
        </a>
    </div>

    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        <strong>Breakdown Analysis:</strong> Shows which consultants' appointments generated the upselling revenue for <strong>{{ $sellerName }}</strong>.
        <br>
        <small class="text-muted">Upselling is calculated based on same-day payments received after services were added to packages.</small>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Upselling Amount</h5>
                    <h3>{{ number_format($totalSoldAmount, 2) }}</h3>
                    <small>by {{ $sellerName }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Packages</h5>
                    <h3>{{ $totalPackages }}</h3>
                    <small>packages with upselling</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Consultants</h5>
                    <h3>{{ $totalConsultants }}</h3>
                    <small>contributing consultants</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Consultant Breakdown Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Consultant Contribution Breakdown</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Consultant Name</th>
                            <th class="text-right">Upselling Amount</th>
                            <th class="text-center">Packages</th>
                            <th>Contribution %</th>
                           
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($consultantBreakdown as $consultant)
                            @php
                                $contributionPercentage = $totalSoldAmount > 0 ? ($consultant->total_amount / $totalSoldAmount) * 100 : 0;
                            @endphp
                            <tr>
                                <td class="font-weight-bold">
                                    <i class="fas fa-user-md text-primary mr-2"></i>
                                    {{ $consultant->consultant_name }}
                                </td>
                                <td class="text-right">
                                    <strong class="text-success">{{ number_format($consultant->total_amount, 2) }}</strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-info">{{ $consultant->total_packages }}</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="mr-2 font-weight-bold" style="min-width: 50px;">{{ number_format($contributionPercentage, 1) }}%</span>
                                        <div class="percentage-bar flex-grow-1">
                                            <div class="percentage-fill" style="width: {{ $contributionPercentage }}%"></div>
                                        </div>
                                    </div>
                                </td>
                               
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="fas fa-exclamation-triangle mb-2"></i><br>
                                    No consultant breakdown data found for {{ $sellerName }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="font-weight-bold bg-light">
                        <tr>
                            <td>TOTAL</td>
                            <td class="text-right">{{ number_format($totalSoldAmount, 2) }}</td>
                            <td class="text-center">{{ $totalPackages }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    @if($consultantBreakdown->isNotEmpty())
    <!-- Additional Insights -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-trophy"></i> Top Contributing Consultant</h6>
                </div>
                <div class="card-body">
                    @php $topConsultant = $consultantBreakdown->first(); @endphp
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="h5">{{ $topConsultant->consultant_name }}</strong>
                            <br>
                            <small class="text-muted">{{ $topConsultant->total_packages }} packages</small>
                        </div>
                        <div class="text-right">
                            <span class="h4 text-success">{{ number_format($topConsultant->total_amount, 2) }}</span>
                            <br>
                            <small class="text-muted">{{ number_format(($topConsultant->total_amount / $totalSoldAmount) * 100, 1) }}% of total</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-chart-pie"></i> Average per Consultant</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="h5">Avg. Upselling</strong>
                            <br>
                            <small class="text-muted">per consultant</small>
                        </div>
                        <div class="text-right">
                            <span class="h4 text-info">{{ number_format($totalSoldAmount / max($totalConsultants, 1), 2) }}</span>
                            <br>
                            <small class="text-muted">{{ number_format($totalPackages / max($totalConsultants, 1), 1) }} packages avg.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
</div>
    </div>
</div>

@endsection