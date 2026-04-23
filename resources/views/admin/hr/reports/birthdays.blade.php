@extends('admin.layouts.master')
@section('title', 'Employee Birthdays')
@section('content')

@php
    $today = \Carbon\CarbonImmutable::now();
    $currentYear = $today->year;
@endphp

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'HRM', 'title' => 'Birthdays'])

    <div class="d-flex flex-column-fluid">
        <div class="container">
            <div class="card card-custom">
                <div class="card-header py-3">
                    <div class="card-title">
                        <span class="card-icon">
                            <span class="svg-icon svg-icon-md svg-icon-primary">
                                <i class="la la-birthday-cake icon-lg text-primary"></i>
                            </span>
                        </span>
                        <h3 class="card-label">Employee Birthdays — {{ $months[$selectedMonth] }}</h3>
                    </div>
                    <div class="card-toolbar">
                        <a href="{{ route('admin.hr.reports.birthdays.export', ['scope' => 'month', 'month' => $selectedMonth, 'department_id' => $selectedDepartmentId]) }}"
                           class="btn btn-light-primary btn-sm mr-2">
                            <i class="la la-download"></i> Export {{ $months[$selectedMonth] }}
                        </a>
                        <a href="{{ route('admin.hr.reports.birthdays.export', ['scope' => 'all', 'department_id' => $selectedDepartmentId]) }}"
                           class="btn btn-light-success btn-sm">
                            <i class="la la-download"></i> Export All
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <form method="GET" action="{{ route('admin.hr.reports.birthdays') }}" class="mb-7">
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
                                <a href="{{ route('admin.hr.reports.birthdays') }}" class="btn btn-light">Reset</a>
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
                                    <th>Date of Birth</th>
                                    <th class="text-center">Turning</th>
                                    <th>Upcoming / Passed</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($employees as $employee)
                                    @php
                                        $dob = $employee->dob;
                                        $thisYearBirthday = $dob ? $dob->copy()->year($currentYear) : null;
                                        $turning = $dob ? $currentYear - (int) $dob->year : null;
                                        $isPast = $thisYearBirthday && $thisYearBirthday->isPast() && ! $thisYearBirthday->isToday();
                                        $isToday = $thisYearBirthday && $thisYearBirthday->isToday();
                                        $daysAway = $thisYearBirthday ? (int) $today->startOfDay()->diffInDays($thisYearBirthday->startOfDay(), false) : null;
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
                                        <td>{{ $dob?->format('d M') ?? '—' }}</td>
                                        <td class="text-center">{{ $turning !== null ? $turning : '—' }}</td>
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
                                        <td colspan="7" class="text-center text-muted">No employees with birthdays in {{ $months[$selectedMonth] }}.</td>
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
