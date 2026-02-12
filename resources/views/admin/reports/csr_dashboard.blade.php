@extends('admin.layouts.master')
@section('title', 'CSR Dashboard')
@section('content')
<style>
    .csr-dashboard-wrapper {
        background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
        min-height: 100vh;
        padding-bottom: 80px;
    }
    .csr-stat-card {
        border-radius: 12px;
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }
    .csr-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    }
    .csr-stat-number {
        font-size: 2.8rem;
        font-weight: 800;
        line-height: 1;
        background: linear-gradient(135deg, #3699FF 0%, #1BC5BD 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .csr-stat-label {
        font-size: 0.85rem;
        color: #7e8299;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .date-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        position: relative;
    }
    .date-card.today-card {
        background: linear-gradient(135deg, #1BC5BD 0%, #0BB783 100%);
    }
    .date-card.today-card .date-label,
    .date-card.today-card .date-count,
    .date-card.today-card .date-sublabel {
        color: white !important;
    }
    .date-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #7e8299;
    }
    .date-count {
        font-size: 2.2rem;
        font-weight: 800;
        color: #3f4254;
    }
    .date-sublabel {
        font-size: 0.8rem;
        color: #b5b5c3;
        font-weight: 500;
    }
    .dashboard-table {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
    }
    .dashboard-table .card-header {
        background: linear-gradient(135deg, #3699FF 0%, #2d7fd3 100%);
        border: none;
        padding: 1rem 1.5rem;
    }
    .dashboard-table .card-header .card-label {
        color: white;
        font-weight: 700;
    }
    .dashboard-table .card-header .card-icon i {
        color: rgba(255,255,255,0.8);
    }
    .summary-table {
        margin: 0;
    }
    .summary-table thead th {
        background: #f8fafc;
        font-weight: 700;
        color: #3f4254;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 1rem 0.75rem;
        border-bottom: 2px solid #ebedf3;
    }
    .summary-table tbody td {
        vertical-align: middle;
        padding: 0.875rem 0.75rem;
        border-bottom: 1px solid #f3f6f9;
    }
    .summary-table tbody tr:hover {
        background: #f8fafc;
    }
    .summary-table tbody tr:last-child td {
        border-bottom: none;
    }
    .branch-name {
        font-weight: 600;
        color: #3f4254;
        font-size: 0.9rem;
    }
    .csr-name {
        font-weight: 600;
        color: #3f4254;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
    }
    .csr-name .csr-avatar {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: linear-gradient(135deg, #3699FF 0%, #1BC5BD 100%);
        color: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.75rem;
        margin-right: 10px;
        flex-shrink: 0;
    }
    .count-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 38px;
        height: 28px;
        border-radius: 14px;
        font-weight: 700;
        font-size: 0.85rem;
        transition: all 0.2s ease;
    }
    .count-pill.new-count {
        background: #E8FFF3;
        color: #1BC5BD;
    }
    .count-pill.new-count.has-value {
        background: linear-gradient(135deg, #1BC5BD 0%, #0BB783 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(27, 197, 189, 0.3);
    }
    .count-pill.resch-count {
        background: #FFF8DD;
        color: #FFA800;
    }
    .count-pill.resch-count.has-value {
        background: linear-gradient(135deg, #FFA800 0%, #FF8A00 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(255, 168, 0, 0.3);
    }
    .count-pill.zero {
        background: #f3f6f9;
        color: #b5b5c3;
    }
    .total-cell {
        font-weight: 800;
        font-size: 1rem;
    }
    .total-cell.new-total {
        color: #1BC5BD;
    }
    .total-cell.resch-total {
        color: #FFA800;
    }
    .total-row {
        background: linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
    }
    .total-row td {
        font-weight: 700;
        padding: 1rem 0.75rem !important;
        border-top: 2px solid #ebedf3;
    }
    .date-header-group {
        text-align: center;
        border-bottom: none !important;
    }
    .date-header-group .date-text {
        font-size: 0.75rem;
        font-weight: 700;
        color: #3f4254;
    }
    .date-header-group.today-header .date-text {
        color: #1BC5BD;
    }
    .sub-header {
        font-size: 0.7rem !important;
        font-weight: 600 !important;
        padding: 0.5rem 0.75rem !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .sub-header.new-header {
        color: #1BC5BD !important;
        background: #E8FFF3 !important;
    }
    .sub-header.resch-header {
        color: #FFA800 !important;
        background: #FFF8DD !important;
    }
    .refresh-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 100;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3699FF 0%, #2d7fd3 100%);
        box-shadow: 0 6px 25px rgba(54, 153, 255, 0.4);
        border: none;
        transition: all 0.3s ease;
    }
    .refresh-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 8px 30px rgba(54, 153, 255, 0.5);
    }
    .section-divider {
        height: 4px;
        background: linear-gradient(90deg, #3699FF 0%, #1BC5BD 50%, #FFA800 100%);
        border-radius: 2px;
        margin: 2rem 0;
        opacity: 0.3;
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
                    <div class="card date-card h-100 {{ $dateInfo['is_today'] ? 'today-card' : '' }}">
                        <div class="card-body text-center py-4">
                            <div class="date-label mb-2">
                                {{ $dateInfo['is_today'] ? '📅 TODAY' : $dateInfo['display'] }}
                            </div>
                            <div class="date-count">
                                {{ $totalByDate[$dateKey] }}
                            </div>
                            <div class="date-sublabel">Consultations</div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Two Column Layout for Tables -->
            @php
                $todayKey = $today->format('Y-m-d');
            @endphp
            <div class="row">
                <!-- Branch-wise Summary Table -->
                <div class="col-lg-6 col-md-12 mb-4">
                    <div class="card dashboard-table h-100">
                        <div class="card-header py-2">
                            <div class="card-title">
                                <span class="card-icon">
                                    <i class="la la-building icon-lg"></i>
                                </span>
                                <h3 class="card-label" style="font-size: 1rem;">Branch-wise Summary</h3>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table summary-table table-sm">
                                    <thead>
                                        <tr>
                                            <th class="pl-3" style="font-size: 0.9rem;">Branch</th>
                                            @foreach($dateRange as $dateKey => $dateInfo)
                                            <th class="text-center {{ $dateInfo['is_today'] ? 'today-header' : '' }}" style="font-size: 0.85rem; padding: 0.5rem 0.25rem;">
                                                @if($dateInfo['is_today'])
                                                    <span style="color: #1BC5BD;">Today</span>
                                                @else
                                                    {{ \Carbon\Carbon::parse($dateKey)->format('D') }}
                                                @endif
                                            </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($locationStats as $locationId => $stats)
                                        <tr>
                                            <td class="pl-3" style="font-size: 0.95rem; padding: 0.5rem 0.5rem;">
                                                <span class="branch-name">{{ $stats['name'] }}</span>
                                            </td>
                                            @foreach($dateRange as $dateKey => $dateInfo)
                                            <td class="text-center" style="padding: 0.5rem 0.25rem;">
                                                <span class="count-pill new-count {{ $stats['dates'][$dateKey] > 0 ? 'has-value' : 'zero' }}" style="min-width: 32px; height: 26px; font-size: 0.9rem;">
                                                    {{ $stats['dates'][$dateKey] }}
                                                </span>
                                            </td>
                                            @endforeach
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="{{ count($dateRange) + 1 }}" class="text-center py-4 text-muted">
                                                No branches found
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot>
                                        <tr class="total-row">
                                            <td class="pl-3" style="font-size: 0.95rem;"><strong>TOTAL</strong></td>
                                            @foreach($dateRange as $dateKey => $dateInfo)
                                            <td class="text-center" style="padding: 0.5rem 0.25rem;">
                                                <span class="total-cell new-total" style="font-size: 1rem;">{{ $totalByDate[$dateKey] }}</span>
                                            </td>
                                            @endforeach
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CSR-wise Consultation Stats - Today Only -->
                <div class="col-lg-6 col-md-12 mb-4">
                    <div class="card dashboard-table h-100">
                        <div class="card-header py-2" style="background: linear-gradient(135deg, #1BC5BD 0%, #0BB783 100%);">
                            <div class="card-title">
                                <span class="card-icon">
                                    <i class="la la-user-tie icon-lg"></i>
                                </span>
                                <h3 class="card-label" style="font-size: 1rem;">CSR-wise (Today)</h3>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table summary-table table-sm">
                                    <thead>
                                        <tr>
                                            <th class="pl-3" style="font-size: 0.9rem;">CSR Name</th>
                                            <th class="text-center" style="font-size: 0.85rem; padding: 0.5rem 0.25rem; background: #E8F4FD; color: #3699FF;">Target</th>
                                            <th class="text-center sub-header new-header" style="font-size: 0.85rem; padding: 0.5rem 0.25rem;">New</th>
                                            <th class="text-center sub-header resch-header" style="font-size: 0.85rem; padding: 0.5rem 0.25rem;">Resch.</th>
                                            <th class="text-center pr-3" style="font-size: 0.85rem; padding: 0.5rem 0.25rem; background: #F3F6F9; color: #7E8299; font-weight: 600;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($csrStats as $csrId => $stats)
                                        <tr>
                                            <td class="pl-3" style="padding: 0.5rem 0.5rem;">
                                                <div class="csr-name" style="font-size: 0.95rem;">
                                                    <span class="csr-avatar" style="width: 28px; height: 28px; font-size: 0.7rem; margin-right: 8px;">{{ strtoupper(substr($stats['name'], 0, 2)) }}</span>
                                                    {{ $stats['name'] }}
                                                </div>
                                            </td>
                                            <td class="text-center" style="padding: 0.5rem 0.25rem;">
                                                <span class="count-pill" style="min-width: 32px; height: 26px; font-size: 0.9rem; background: #E8F4FD; color: #3699FF;">
                                                    {{ $csrTarget }}
                                                </span>
                                            </td>
                                            <td class="text-center" style="padding: 0.5rem 0.25rem;">
                                                @php $newCount = $stats['new_created'][$todayKey]; @endphp
                                                <span class="count-pill new-count {{ $newCount >= $csrTarget ? 'has-value' : ($newCount > 0 ? 'has-value' : 'zero') }}" style="min-width: 32px; height: 26px; font-size: 0.9rem;">
                                                    {{ $newCount }}
                                                </span>
                                            </td>
                                            <td class="text-center" style="padding: 0.5rem 0.25rem;">
                                                @php $reschCount = $stats['rescheduled'][$todayKey]; @endphp
                                                <span class="count-pill resch-count {{ $reschCount > 0 ? 'has-value' : 'zero' }}" style="min-width: 32px; height: 26px; font-size: 0.9rem;">
                                                    {{ $reschCount }}
                                                </span>
                                            </td>
                                            <td class="text-center pr-3" style="padding: 0.5rem 0.25rem;">
                                                @php $totalCount = $newCount + $reschCount; @endphp
                                                <span class="count-pill {{ $totalCount >= $csrTarget ? 'has-value' : ($totalCount > 0 ? 'has-value' : 'zero') }}" style="min-width: 32px; height: 26px; font-size: 0.9rem; background: {{ $totalCount >= $csrTarget ? '#C9F7F5' : '#F3F6F9' }}; color: {{ $totalCount >= $csrTarget ? '#1BC5BD' : '#7E8299' }};">
                                                    {{ $totalCount }}
                                                </span>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                No CSR data found
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                    @if(count($csrStats) > 0)
                                    <tfoot>
                                        <tr class="total-row">
                                            <td class="pl-3" style="font-size: 0.95rem;"><strong>TOTAL</strong></td>
                                            @php
                                                $totalNewToday = 0;
                                                $totalReschToday = 0;
                                                $totalTarget = count($csrStats) * $csrTarget;
                                                foreach($csrStats as $stats) {
                                                    $totalNewToday += $stats['new_created'][$todayKey];
                                                    $totalReschToday += $stats['rescheduled'][$todayKey];
                                                }
                                            @endphp
                                            <td class="text-center" style="padding: 0.5rem 0.25rem;">
                                                <span style="font-size: 1rem; color: #3699FF; font-weight: 700;">{{ $totalTarget }}</span>
                                            </td>
                                            <td class="text-center" style="padding: 0.5rem 0.25rem;">
                                                <span class="total-cell new-total" style="font-size: 1rem;">{{ $totalNewToday }}</span>
                                            </td>
                                            <td class="text-center" style="padding: 0.5rem 0.25rem;">
                                                <span class="total-cell resch-total" style="font-size: 1rem;">{{ $totalReschToday }}</span>
                                            </td>
                                            <td class="text-center pr-3" style="padding: 0.5rem 0.25rem;">
                                                <span style="font-size: 1rem; color: #7E8299; font-weight: 700;">{{ $totalNewToday + $totalReschToday }}</span>
                                            </td>
                                        </tr>
                                    </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>
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
