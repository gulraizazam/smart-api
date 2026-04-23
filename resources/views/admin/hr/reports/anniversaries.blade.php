@extends('admin.layouts.master')
@section('title', 'Work Anniversaries')
@section('content')

@php
    $today = \Carbon\CarbonImmutable::now();
    $currentYear = $today->year;
@endphp

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'HRM', 'title' => 'Anniversaries'])

    <div class="d-flex flex-column-fluid">
        <div class="container">
            <div class="card card-custom">
                <div class="card-header py-3">
                    <div class="card-title">
                        <span class="card-icon">
                            <span class="svg-icon svg-icon-md svg-icon-primary">
                                <i class="la la-trophy icon-lg text-primary"></i>
                            </span>
                        </span>
                        <h3 class="card-label">Work Anniversaries — {{ $months[$selectedMonth] }}</h3>
                    </div>
                </div>

                <div class="card-body">
                    <form method="GET" action="{{ route('admin.hr.reports.anniversaries') }}" class="mb-7">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="fw-bold">Month</label>
                                <select name="month" class="form-control select2">
                                    @foreach($months as $value => $label)
                                        <option value="{{ $value }}" {{ $selectedMonth === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="fw-bold">Department</label>
                                <select name="department_id" class="form-control select2">
                                    <option value="">All Departments</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ $selectedDepartmentId === $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary mr-2"><i class="la la-search"></i> Apply</button>
                                <a href="{{ route('admin.hr.reports.anniversaries') }}" class="btn btn-light">Reset</a>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-head-custom table-vertical-center table-head-bg">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Designation</th>
                                    <th>Hire Date</th>
                                    <th class="text-center">Years Completing</th>
                                    <th>Upcoming / Passed</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($employees as $employee)
                                    @php
                                        $hireDate = $employee->employeeDetail?->hire_date;
                                        $thisYearAnniversary = $hireDate ? $hireDate->copy()->year($currentYear) : null;
                                        $yearsCompleting = $hireDate ? $currentYear - (int) $hireDate->year : null;
                                        $isPast = $thisYearAnniversary && $thisYearAnniversary->isPast() && ! $thisYearAnniversary->isToday();
                                        $isToday = $thisYearAnniversary && $thisYearAnniversary->isToday();
                                        $daysAway = $thisYearAnniversary ? (int) $today->startOfDay()->diffInDays($thisYearAnniversary->startOfDay(), false) : null;
                                    @endphp
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            <a href="{{ route('admin.hr.employees.show', $employee) }}" class="text-dark font-weight-bolder text-hover-primary">
                                                {{ $employee->name }}
                                            </a>
                                        </td>
                                        <td>{{ $employee->employeeDetail?->department?->name ?? '—' }}</td>
                                        <td>{{ $employee->employeeDetail?->designation?->name ?? '—' }}</td>
                                        <td>{{ $hireDate?->format('d M Y') ?? '—' }}</td>
                                        <td class="text-center">
                                            @if($yearsCompleting !== null && $yearsCompleting > 0)
                                                {{ $yearsCompleting }} {{ $yearsCompleting === 1 ? 'year' : 'years' }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if($isToday)
                                                <span class="label label-lg label-light-success label-inline">Today</span>
                                            @elseif($isPast)
                                                <span class="text-muted">{{ abs($daysAway) }} day{{ abs($daysAway) === 1 ? '' : 's' }} ago</span>
                                            @elseif($daysAway !== null)
                                                <span class="text-primary">in {{ $daysAway }} day{{ $daysAway === 1 ? '' : 's' }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No work anniversaries in {{ $months[$selectedMonth] }}.</td>
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

@push('js')
<script>
    $(function () {
        $('.select2').select2();
    });
</script>
@endpush

@endsection
