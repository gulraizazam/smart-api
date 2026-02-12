@extends('admin.layouts.master')
@section('title', 'CSR Dashboard')
@section('content')
<style>
    .csr-stat-card {
        border-radius: 8px;
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 0 20px 0 rgba(0, 0, 0, 0.05);
    }
    .csr-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 25px 0 rgba(0, 0, 0, 0.1);
    }
    .csr-stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        line-height: 1;
    }
    .csr-stat-label {
        font-size: 0.9rem;
        color: #7e8299;
        font-weight: 500;
    }
    .date-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.85rem;
    }
    .date-badge.today {
        background: linear-gradient(135deg, #1BC5BD 0%, #0BB783 100%);
        color: white;
    }
    .date-badge.future {
        background: #f3f6f9;
        color: #3f4254;
    }
    .location-card {
        border-left: 4px solid #3699FF;
        margin-bottom: 1rem;
    }
    .location-card .card-header {
        background: #f8f9fa;
        border-bottom: 1px solid #ebedf3;
        cursor: pointer;
    }
    .location-card .card-header:hover {
        background: #f1f3f6;
    }
    .appointment-row {
        border-bottom: 1px solid #ebedf3;
        padding: 0.75rem 0;
    }
    .appointment-row:last-child {
        border-bottom: none;
    }
    .appointment-time {
        font-weight: 600;
        color: #3699FF;
    }
    .summary-table th {
        background: #f3f6f9;
        font-weight: 600;
        color: #3f4254;
    }
    .summary-table td {
        vertical-align: middle;
    }
    .count-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.9rem;
    }
    .count-badge.has-appointments {
        background: #C9F7F5;
        color: #1BC5BD;
    }
    .count-badge.no-appointments {
        background: #f3f6f9;
        color: #b5b5c3;
    }
    .total-row {
        background: #f8f9fa;
        font-weight: 600;
    }
    .refresh-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 100;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        box-shadow: 0 5px 20px rgba(54, 153, 255, 0.4);
    }
</style>

<!--begin::Content-->
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'CSR Dashboard'])
    
    <!--begin::Entry-->
    <div class="d-flex flex-column-fluid">
        <!--begin::Container-->
        <div class="container">
            
            <!-- Header Stats -->
            <div class="row mb-6">
                <div class="col-12">
                    <div class="card csr-stat-card">
                        <div class="card-body py-4">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-50 symbol-light-primary mr-4">
                                        <span class="symbol-label">
                                            <i class="la la-calendar-check text-primary icon-2x"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <h3 class="font-weight-bolder text-dark mb-0">CSR Dashboard</h3>
                                        <span class="text-muted font-weight-bold">Consultations Scheduled - Next 5 Days</span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="text-right mr-8">
                                        <span class="csr-stat-number text-primary">{{ $totalAppointments }}</span>
                                        <div class="csr-stat-label">Total Consultations</div>
                                    </div>
                                    <a href="{{ route('admin.reports.csr_dashboard') }}" class="btn btn-light-primary btn-sm">
                                        <i class="la la-refresh"></i> Refresh
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Date Summary Cards -->
            <div class="row mb-6">
                @foreach($dateRange as $dateKey => $dateInfo)
                <div class="col">
                    <div class="card csr-stat-card h-100">
                        <div class="card-body text-center py-4">
                            <span class="date-badge {{ $dateInfo['is_today'] ? 'today' : 'future' }} mb-3">
                                {{ $dateInfo['is_today'] ? 'TODAY' : $dateInfo['display'] }}
                            </span>
                            <div class="csr-stat-number {{ $totalByDate[$dateKey] > 0 ? 'text-success' : 'text-muted' }}">
                                {{ $totalByDate[$dateKey] }}
                            </div>
                            <div class="csr-stat-label">Consultations</div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Summary Table -->
            <div class="card card-custom mb-6">
                <div class="card-header py-3">
                    <div class="card-title">
                        <span class="card-icon">
                            <i class="la la-building text-primary icon-lg"></i>
                        </span>
                        <h3 class="card-label">Branch-wise Summary</h3>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-head-custom table-vertical-center summary-table mb-0">
                            <thead>
                                <tr>
                                    <th class="pl-6">Branch</th>
                                    @foreach($dateRange as $dateKey => $dateInfo)
                                    <th class="text-center">
                                        @if($dateInfo['is_today'])
                                            <span class="text-success font-weight-bolder">Today</span>
                                        @else
                                            {{ $dateInfo['display'] }}
                                        @endif
                                    </th>
                                    @endforeach
                                    <th class="text-center pr-6">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($locationStats as $locationId => $stats)
                                <tr>
                                    <td class="pl-6">
                                        <span class="font-weight-bold">{{ $stats['name'] }}</span>
                                    </td>
                                    @foreach($dateRange as $dateKey => $dateInfo)
                                    <td class="text-center">
                                        <span class="count-badge {{ $stats['dates'][$dateKey] > 0 ? 'has-appointments' : 'no-appointments' }}">
                                            {{ $stats['dates'][$dateKey] }}
                                        </span>
                                    </td>
                                    @endforeach
                                    <td class="text-center pr-6">
                                        <span class="font-weight-bolder text-primary">{{ $stats['total'] }}</span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="{{ count($dateRange) + 2 }}" class="text-center py-6 text-muted">
                                        No branches found
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="total-row">
                                    <td class="pl-6 font-weight-bolder">TOTAL</td>
                                    @foreach($dateRange as $dateKey => $dateInfo)
                                    <td class="text-center">
                                        <span class="font-weight-bolder text-primary">{{ $totalByDate[$dateKey] }}</span>
                                    </td>
                                    @endforeach
                                    <td class="text-center pr-6">
                                        <span class="font-weight-bolder text-success" style="font-size: 1.1rem;">{{ $totalAppointments }}</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- CSR-wise Consultation Stats -->
            <div class="card card-custom">
                <div class="card-header py-3">
                    <div class="card-title">
                        <span class="card-icon">
                            <i class="la la-user-tie text-primary icon-lg"></i>
                        </span>
                        <h3 class="card-label">CSR-wise Consultations</h3>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-head-custom table-vertical-center summary-table mb-0">
                            <thead>
                                <tr>
                                    <th class="pl-6" rowspan="2" style="vertical-align: middle;">CSR Name</th>
                                    @foreach($dateRange as $dateKey => $dateInfo)
                                    <th class="text-center" colspan="2" style="border-bottom: none;">
                                        @if($dateInfo['is_today'])
                                            <span class="text-success font-weight-bolder">Today</span>
                                        @else
                                            {{ $dateInfo['display'] }}
                                        @endif
                                    </th>
                                    @endforeach
                                    <th class="text-center pr-6" colspan="2" style="border-bottom: none;">Total</th>
                                </tr>
                                <tr>
                                    @foreach($dateRange as $dateKey => $dateInfo)
                                    <th class="text-center" style="font-size: 0.75rem; padding-top: 0;">
                                        <span class="text-success">New</span>
                                    </th>
                                    <th class="text-center" style="font-size: 0.75rem; padding-top: 0;">
                                        <span class="text-warning">Resch.</span>
                                    </th>
                                    @endforeach
                                    <th class="text-center" style="font-size: 0.75rem; padding-top: 0;">
                                        <span class="text-success">New</span>
                                    </th>
                                    <th class="text-center pr-6" style="font-size: 0.75rem; padding-top: 0;">
                                        <span class="text-warning">Resch.</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($csrStats as $csrId => $stats)
                                <tr>
                                    <td class="pl-6">
                                        <span class="font-weight-bold">{{ $stats['name'] }}</span>
                                    </td>
                                    @foreach($dateRange as $dateKey => $dateInfo)
                                    <td class="text-center">
                                        <span class="count-badge {{ $stats['new_created'][$dateKey] > 0 ? 'has-appointments' : 'no-appointments' }}">
                                            {{ $stats['new_created'][$dateKey] }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $reschCount = $stats['rescheduled'][$dateKey];
                                        @endphp
                                        <span class="count-badge {{ $reschCount > 0 ? 'has-appointments' : 'no-appointments' }}" style="{{ $reschCount > 0 ? 'background: #FFF4DE; color: #FFA800;' : '' }}">
                                            {{ $reschCount }}
                                        </span>
                                    </td>
                                    @endforeach
                                    <td class="text-center">
                                        <span class="font-weight-bolder text-success">{{ $stats['total_new'] }}</span>
                                    </td>
                                    <td class="text-center pr-6">
                                        <span class="font-weight-bolder text-warning">{{ $stats['total_rescheduled'] }}</span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="{{ (count($dateRange) * 2) + 3 }}" class="text-center py-6 text-muted">
                                        No CSR data found
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                            @if(count($csrStats) > 0)
                            <tfoot>
                                <tr class="total-row">
                                    <td class="pl-6 font-weight-bolder">TOTAL</td>
                                    @foreach($dateRange as $dateKey => $dateInfo)
                                    @php
                                        $totalNewByDate = 0;
                                        $totalReschByDate = 0;
                                        foreach($csrStats as $stats) {
                                            $totalNewByDate += $stats['new_created'][$dateKey];
                                            $totalReschByDate += $stats['rescheduled'][$dateKey];
                                        }
                                    @endphp
                                    <td class="text-center">
                                        <span class="font-weight-bolder text-success">{{ $totalNewByDate }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="font-weight-bolder text-warning">{{ $totalReschByDate }}</span>
                                    </td>
                                    @endforeach
                                    @php
                                        $grandTotalNew = 0;
                                        $grandTotalResch = 0;
                                        foreach($csrStats as $stats) {
                                            $grandTotalNew += $stats['total_new'];
                                            $grandTotalResch += $stats['total_rescheduled'];
                                        }
                                    @endphp
                                    <td class="text-center">
                                        <span class="font-weight-bolder text-success" style="font-size: 1.1rem;">{{ $grandTotalNew }}</span>
                                    </td>
                                    <td class="text-center pr-6">
                                        <span class="font-weight-bolder text-warning" style="font-size: 1.1rem;">{{ $grandTotalResch }}</span>
                                    </td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

        </div>
        <!--end::Container-->
    </div>
    <!--end::Entry-->
</div>
<!--end::Content-->

<!-- Floating Refresh Button -->
<a href="{{ route('admin.reports.csr_dashboard') }}" class="btn btn-primary refresh-btn d-flex align-items-center justify-content-center" title="Refresh Dashboard">
    <i class="la la-refresh icon-lg"></i>
</a>

@endsection
