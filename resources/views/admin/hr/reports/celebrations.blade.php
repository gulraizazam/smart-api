@extends('admin.layouts.master')
@section('title', 'HR Celebrations')
@section('content')

@php
    $isBirthdays = $tab === 'birthdays';
    $accent = $isBirthdays ? '#ec4899' : '#10b981';
    $accentSoft = $isBirthdays ? '#fdf2f8' : '#ecfdf5';
    $weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $today = \Carbon\CarbonImmutable::now();
    $totalCells = $calendar['leadingBlanks'] + $calendar['daysInMonth'];
    $trailingBlanks = (7 - ($totalCells % 7)) % 7;
    $exportRoute = $isBirthdays ? 'admin.hr.reports.birthdays.export' : 'admin.hr.reports.anniversaries.export';
    $exportLabel = $isBirthdays ? 'Birthdays' : 'Anniversaries';
@endphp

<style>
    .hrc-wrap { --accent: {{ $accent }}; --accent-soft: {{ $accentSoft }}; }
    .hrc-tabs { display: inline-flex; background: #f1f5f9; padding: 4px; border-radius: 10px; gap: 4px; }
    .hrc-tab { padding: 8px 18px; border-radius: 8px; font-weight: 600; color: #64748b; text-decoration: none; transition: all .15s; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; }
    .hrc-tab:hover { color: #0f172a; text-decoration: none; }
    .hrc-tab.is-active { background: #ffffff; color: #0f172a; box-shadow: 0 1px 2px rgba(15,23,42,.08); }
    .hrc-tab.is-active i { color: var(--accent); }

    .hrc-calendar { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
    .hrc-cal-head { display: grid; grid-template-columns: repeat(7, 1fr); background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .hrc-cal-head > div { padding: 10px 8px; font-size: 12px; font-weight: 600; letter-spacing: .04em; color: #64748b; text-transform: uppercase; text-align: center; }
    .hrc-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); }
    .hrc-cal-cell { min-height: 84px; padding: 8px; border-right: 1px solid #eef2f7; border-bottom: 1px solid #eef2f7; background: #ffffff; display: flex; flex-direction: column; gap: 4px; }
    .hrc-cal-cell:nth-child(7n) { border-right: none; }
    .hrc-cal-cell.is-blank { background: #fafafa; }
    .hrc-cal-cell.is-today { background: var(--accent-soft); }
    .hrc-cal-cell.has-events { cursor: pointer; }
    .hrc-cal-day { font-size: 12px; font-weight: 600; color: #334155; display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; }
    .hrc-cal-cell.is-today .hrc-cal-day { background: var(--accent); color: #ffffff; }
    .hrc-cal-pill { font-size: 11px; padding: 3px 6px; border-radius: 6px; background: var(--accent-soft); color: #0f172a; border: 1px solid color-mix(in srgb, var(--accent) 25%, transparent); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hrc-cal-more { font-size: 11px; color: #64748b; padding: 2px 6px; }

    .hrc-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
    .hrc-card { position: relative; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; gap: 12px; align-items: center; transition: box-shadow .15s, transform .15s, border-color .15s; }
    .hrc-card:hover { box-shadow: 0 6px 20px rgba(15,23,42,.06); border-color: color-mix(in srgb, var(--accent) 30%, #e2e8f0); transform: translateY(-1px); }
    .hrc-card.is-today { border-color: var(--accent); box-shadow: 0 4px 12px color-mix(in srgb, var(--accent) 18%, transparent); }
    .hrc-avatar { width: 52px; height: 52px; flex: 0 0 52px; border-radius: 50%; background: var(--accent-soft); color: var(--accent); font-weight: 700; font-size: 20px; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; }
    .hrc-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .hrc-card-body { min-width: 0; flex: 1; }
    .hrc-card-name { font-weight: 700; font-size: 15px; color: #0f172a; margin: 0 0 2px; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hrc-card-name a { color: inherit; text-decoration: none; }
    .hrc-card-name a:hover { color: var(--accent); }
    .hrc-card-meta { font-size: 12px; color: #64748b; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hrc-card-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
    .hrc-chip { font-size: 11px; padding: 3px 8px; border-radius: 999px; background: #f1f5f9; color: #334155; font-weight: 500; }
    .hrc-chip.hrc-chip-accent { background: var(--accent-soft); color: var(--accent); }
    .hrc-chip.hrc-chip-today { background: var(--accent); color: #ffffff; }

    .hrc-empty { text-align: center; padding: 48px 16px; color: #64748b; background: #ffffff; border: 1px dashed #e2e8f0; border-radius: 12px; }
    .hrc-empty i { font-size: 36px; color: var(--accent); opacity: .7; margin-bottom: 8px; }

    .hrc-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; }
    .hrc-toolbar .hrc-exports { display: flex; gap: 8px; flex-wrap: wrap; }

    @media (max-width: 640px) {
        .hrc-cal-cell { min-height: 64px; padding: 4px; }
        .hrc-cal-pill { display: none; }
    }
</style>

<div class="content d-flex flex-column flex-column-fluid hrc-wrap" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'HRM', 'title' => 'Celebrations'])

    <div class="d-flex flex-column-fluid">
        <div class="container">

            {{-- Tabs --}}
            <div class="mb-5 hrc-toolbar">
                <div class="hrc-tabs" role="tablist">
                    <a href="{{ route('admin.hr.reports.celebrations', ['tab' => 'birthdays', 'month' => $selectedMonth, 'department_id' => $selectedDepartmentId]) }}"
                       class="hrc-tab {{ $isBirthdays ? 'is-active' : '' }}">
                        <i class="la la-birthday-cake"></i> Birthdays
                    </a>
                    <a href="{{ route('admin.hr.reports.celebrations', ['tab' => 'anniversaries', 'month' => $selectedMonth, 'department_id' => $selectedDepartmentId]) }}"
                       class="hrc-tab {{ ! $isBirthdays ? 'is-active' : '' }}">
                        <i class="la la-trophy"></i> Anniversaries
                    </a>
                </div>
                <div class="hrc-exports">
                    <a href="{{ route($exportRoute, ['scope' => 'month', 'month' => $selectedMonth, 'department_id' => $selectedDepartmentId]) }}"
                       class="btn btn-light-primary btn-sm">
                        <i class="la la-download"></i> Export {{ $months[$selectedMonth] }}
                    </a>
                    <a href="{{ route($exportRoute, ['scope' => 'all', 'department_id' => $selectedDepartmentId]) }}"
                       class="btn btn-light-success btn-sm">
                        <i class="la la-download"></i> Export All
                    </a>
                </div>
            </div>

            {{-- Filters --}}
            <div class="card card-custom mb-5">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.hr.reports.celebrations') }}">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <div class="row align-items-end">
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
                            <div class="col-md-3 mb-3">
                                <button type="submit" class="btn btn-primary mr-2"><i class="la la-search"></i> Apply</button>
                                <a href="{{ route('admin.hr.reports.celebrations', ['tab' => $tab]) }}" class="btn btn-light">Reset</a>
                            </div>
                            <div class="col-md-3 mb-3 text-md-right">
                                <span class="text-muted">{{ $employees->count() }} {{ Str::plural('employee', $employees->count()) }}</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Calendar --}}
            <div class="card card-custom mb-5">
                <div class="card-header py-3">
                    <div class="card-title">
                        <h3 class="card-label">
                            <i class="la la-calendar mr-2" style="color: var(--accent);"></i>
                            {{ $months[$selectedMonth] }} {{ $currentYear }}
                        </h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="hrc-calendar">
                        <div class="hrc-cal-head">
                            @foreach($weekDays as $wd)
                                <div>{{ $wd }}</div>
                            @endforeach
                        </div>
                        <div class="hrc-cal-grid">
                            @for($b = 0; $b < $calendar['leadingBlanks']; $b++)
                                <div class="hrc-cal-cell is-blank"></div>
                            @endfor

                            @for($d = 1; $d <= $calendar['daysInMonth']; $d++)
                                @php
                                    $events = $eventsByDay[$d] ?? [];
                                    $isToday = $calendar['today'] === $d;
                                    $hasEvents = count($events) > 0;
                                    $cellClasses = 'hrc-cal-cell';
                                    if ($isToday) $cellClasses .= ' is-today';
                                    if ($hasEvents) $cellClasses .= ' has-events';
                                @endphp
                                <div class="{{ $cellClasses }}" @if($hasEvents) data-day="{{ $d }}" title="{{ count($events) }} {{ Str::plural('event', count($events)) }}" @endif>
                                    <span class="hrc-cal-day">{{ $d }}</span>
                                    @foreach(array_slice($events, 0, 2) as $event)
                                        <span class="hrc-cal-pill">{{ $event['name'] }}</span>
                                    @endforeach
                                    @if(count($events) > 2)
                                        <span class="hrc-cal-more">+{{ count($events) - 2 }} more</span>
                                    @endif
                                </div>
                            @endfor

                            @for($t = 0; $t < $trailingBlanks; $t++)
                                <div class="hrc-cal-cell is-blank"></div>
                            @endfor
                        </div>
                    </div>
                </div>
            </div>

            {{-- Cards --}}
            <div class="card card-custom mb-5">
                <div class="card-header py-3">
                    <div class="card-title">
                        <h3 class="card-label">
                            <i class="la {{ $isBirthdays ? 'la-birthday-cake' : 'la-trophy' }} mr-2" style="color: var(--accent);"></i>
                            {{ $isBirthdays ? 'Birthdays' : 'Anniversaries' }} this month
                        </h3>
                    </div>
                </div>
                <div class="card-body">
                    @if($employees->isEmpty())
                        <div class="hrc-empty">
                            <div><i class="la {{ $isBirthdays ? 'la-birthday-cake' : 'la-trophy' }}"></i></div>
                            No {{ $isBirthdays ? 'birthdays' : 'anniversaries' }} in {{ $months[$selectedMonth] }}.
                        </div>
                    @else
                        <div class="hrc-cards">
                            @foreach($employees as $employee)
                                @php
                                    if ($isBirthdays) {
                                        $eventDate = $employee->dob;
                                        $magnitude = $eventDate ? $currentYear - (int) $eventDate->year : null;
                                        $magnitudeLabel = $magnitude !== null ? 'Turning ' . $magnitude : null;
                                    } else {
                                        $eventDate = $employee->employeeDetail?->hire_date;
                                        $magnitude = $eventDate ? $currentYear - (int) $eventDate->year : null;
                                        $magnitudeLabel = ($magnitude !== null && $magnitude > 0)
                                            ? $magnitude . ' ' . ($magnitude === 1 ? 'year' : 'years')
                                            : null;
                                    }
                                    $thisYearEvent = $eventDate?->copy()->year($currentYear);
                                    $isEventToday = $thisYearEvent && $thisYearEvent->isSameDay($today);
                                    $isEventPast = $thisYearEvent && $thisYearEvent->isPast() && ! $isEventToday;
                                    $daysAway = $thisYearEvent ? (int) $today->startOfDay()->diffInDays($thisYearEvent->startOfDay(), false) : null;
                                    $initial = mb_strtoupper(mb_substr(trim($employee->name ?? '?'), 0, 1));
                                    $avatar = $employee->image_src ? asset($employee->image_src) : null;
                                    $dept = $employee->employeeDetail?->department?->name;
                                    $designation = $employee->employeeDetail?->designation?->name;
                                @endphp
                                <div class="hrc-card {{ $isEventToday ? 'is-today' : '' }}">
                                    <div class="hrc-avatar">
                                        @if($avatar)
                                            <img src="{{ $avatar }}" alt="{{ $employee->name }}">
                                        @else
                                            {{ $initial }}
                                        @endif
                                    </div>
                                    <div class="hrc-card-body">
                                        <h4 class="hrc-card-name">
                                            <a href="{{ route('admin.hr.employees.show', $employee) }}">{{ $employee->name }}</a>
                                        </h4>
                                        <p class="hrc-card-meta">
                                            {{ $designation ?? '—' }}@if($dept) · {{ $dept }} @endif
                                        </p>
                                        <div class="hrc-card-chips">
                                            <span class="hrc-chip hrc-chip-accent">{{ $eventDate?->format('d M') ?? '—' }}</span>
                                            @if($magnitudeLabel)
                                                <span class="hrc-chip">{{ $magnitudeLabel }}</span>
                                            @endif
                                            @if($isEventToday)
                                                <span class="hrc-chip hrc-chip-today">Today</span>
                                            @elseif($daysAway !== null && ! $isEventPast)
                                                <span class="hrc-chip">in {{ $daysAway }}d</span>
                                            @elseif($daysAway !== null && $isEventPast)
                                                <span class="hrc-chip">{{ abs($daysAway) }}d ago</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>

@push('js')
<script>
    $(function () {
        $('.select2').select2();

        // Clicking a calendar cell with events scrolls to the cards grid.
        $('.hrc-cal-cell.has-events').on('click', function () {
            document.querySelector('.hrc-cards')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
</script>
@endpush

@endsection
