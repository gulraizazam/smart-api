@extends('admin.layouts.master')
@section('title', 'HR Celebrations')
@section('content')

@php
    $isBirthdays = $tab === 'birthdays';
    // Theme-native palette (see assets/css/style.bundle.css :root).
    //   info    #8950FC — Birthdays (celebratory)
    //   primary #3699FF — Anniversaries (professional)
    $accent      = $isBirthdays ? '#8950FC' : '#3699FF';
    $accentSoft  = $isBirthdays ? '#F4EDFF' : '#E1F0FF';
    $accentDeep  = $isBirthdays ? '#6C3DEB' : '#1A7BE0';
    $weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $today = \Carbon\CarbonImmutable::now();
    $totalCells = $calendar['leadingBlanks'] + $calendar['daysInMonth'];
    $trailingBlanks = (7 - ($totalCells % 7)) % 7;
    $exportRoute = $isBirthdays ? 'admin.hr.reports.birthdays.export' : 'admin.hr.reports.anniversaries.export';
@endphp

<style>
    .hrc-wrap {
        --accent: {{ $accent }};
        --accent-soft: {{ $accentSoft }};
        --accent-deep: {{ $accentDeep }};
        --accent-gradient: linear-gradient(135deg, var(--accent) 0%, var(--accent-deep) 100%);
    }

    /* Tabs */
    .hrc-tabs { display: inline-flex; background: #F3F6F9; padding: 4px; border-radius: 10px; gap: 4px; }
    .hrc-tab { padding: 8px 18px; border-radius: 8px; font-weight: 600; color: #7E8299; text-decoration: none; transition: all .15s; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; }
    .hrc-tab:hover { color: #181C32; text-decoration: none; }
    .hrc-tab.is-active { background: #ffffff; color: #181C32; box-shadow: 0 1px 2px rgba(24,28,50,.08); }
    .hrc-tab.is-active i { color: var(--accent); }

    /* Calendar */
    .hrc-calendar { background: #ffffff; border: 1px solid #E4E6EF; border-radius: 12px; overflow: hidden; }
    .hrc-cal-head { display: grid; grid-template-columns: repeat(7, 1fr); background: #F8F9FB; border-bottom: 1px solid #E4E6EF; }
    .hrc-cal-head > div { padding: 10px 8px; font-size: 12px; font-weight: 600; letter-spacing: .04em; color: #7E8299; text-transform: uppercase; text-align: center; }
    .hrc-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); }
    .hrc-cal-cell { min-height: 86px; padding: 8px; border-right: 1px solid #EEF0F6; border-bottom: 1px solid #EEF0F6; background: #ffffff; display: flex; flex-direction: column; gap: 4px; transition: background .15s; }
    .hrc-cal-cell:nth-child(7n) { border-right: none; }
    .hrc-cal-cell.is-blank { background: #FAFBFC; }
    .hrc-cal-cell.has-events { cursor: pointer; }
    .hrc-cal-cell.has-events:hover { background: var(--accent-soft); }
    .hrc-cal-cell.is-today { background: var(--accent-soft); }
    .hrc-cal-day { font-size: 12px; font-weight: 600; color: #3F4254; display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; }
    .hrc-cal-cell.is-today .hrc-cal-day { background: var(--accent-gradient); color: #ffffff; box-shadow: 0 2px 6px color-mix(in srgb, var(--accent) 35%, transparent); }
    .hrc-cal-pill { font-size: 11px; padding: 3px 8px; border-radius: 6px; background: #ffffff; color: var(--accent-deep); border: 1px solid color-mix(in srgb, var(--accent) 30%, #E4E6EF); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hrc-cal-more { font-size: 11px; color: var(--accent-deep); padding: 2px 6px; font-weight: 500; }

    /* Card grid */
    .hrc-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }

    /* Card */
    .hrc-card {
        position: relative;
        background: #ffffff;
        border: 1px solid #E4E6EF;
        border-radius: 14px;
        padding: 18px 18px 18px 22px;
        display: flex;
        gap: 14px;
        align-items: center;
        transition: box-shadow .2s ease, transform .2s ease, border-color .2s ease;
        overflow: hidden;
    }
    /* Left accent ribbon */
    .hrc-card::before {
        content: "";
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 4px;
        background: var(--accent-gradient);
        border-radius: 14px 0 0 14px;
    }
    .hrc-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 28px rgba(24,28,50,.08);
        border-color: color-mix(in srgb, var(--accent) 35%, #E4E6EF);
    }

    /* Avatar with gradient ring */
    .hrc-avatar {
        position: relative;
        width: 56px; height: 56px; flex: 0 0 56px;
        border-radius: 50%;
        padding: 2px;
        background: var(--accent-gradient);
    }
    .hrc-avatar-inner {
        width: 100%; height: 100%;
        border-radius: 50%;
        background: #ffffff;
        overflow: hidden;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 20px;
        color: var(--accent-deep);
    }
    .hrc-avatar-inner img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }

    /* Body */
    .hrc-card-body { min-width: 0; flex: 1; }
    .hrc-card-name { font-weight: 700; font-size: 15px; color: #181C32; margin: 0 0 2px; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hrc-card-name a { color: inherit; text-decoration: none; }
    .hrc-card-name a:hover { color: var(--accent-deep); }
    .hrc-card-meta { font-size: 12px; color: #7E8299; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hrc-card-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; }
    .hrc-chip { font-size: 11px; padding: 3px 9px; border-radius: 999px; background: #F3F6F9; color: #3F4254; font-weight: 600; letter-spacing: .01em; }
    .hrc-chip.hrc-chip-accent { background: var(--accent-soft); color: var(--accent-deep); }
    .hrc-chip.hrc-chip-past { background: #F3F6F9; color: #7E8299; }

    /* Right date badge — stacked day + month, like a torn calendar page */
    .hrc-date-badge {
        flex: 0 0 auto;
        width: 58px;
        border-radius: 10px;
        overflow: hidden;
        background: #ffffff;
        border: 1px solid #E4E6EF;
        box-shadow: 0 1px 2px rgba(24,28,50,.04);
        text-align: center;
        line-height: 1;
    }
    .hrc-date-badge .hrc-date-month {
        font-size: 10px;
        letter-spacing: .08em;
        text-transform: uppercase;
        font-weight: 700;
        color: #ffffff;
        background: var(--accent-gradient);
        padding: 4px 0;
    }
    .hrc-date-badge .hrc-date-day {
        font-size: 22px;
        font-weight: 700;
        color: #181C32;
        padding: 6px 0 4px;
    }
    .hrc-date-badge .hrc-date-sub {
        font-size: 10px;
        color: #7E8299;
        padding: 0 0 6px;
        font-weight: 600;
    }

    /* Today card — fully filled gradient */
    .hrc-card.is-today {
        background: var(--accent-gradient);
        border-color: transparent;
        color: #ffffff;
        box-shadow: 0 14px 34px color-mix(in srgb, var(--accent) 28%, transparent);
    }
    .hrc-card.is-today::before { display: none; }
    .hrc-card.is-today .hrc-card-name { color: #ffffff; }
    .hrc-card.is-today .hrc-card-name a { color: #ffffff; }
    .hrc-card.is-today .hrc-card-meta { color: rgba(255,255,255,.82); }
    .hrc-card.is-today .hrc-chip { background: rgba(255,255,255,.18); color: #ffffff; backdrop-filter: blur(4px); }
    .hrc-card.is-today .hrc-chip.hrc-chip-today { background: #ffffff; color: var(--accent-deep); }
    .hrc-card.is-today .hrc-avatar { background: rgba(255,255,255,.35); }
    .hrc-card.is-today .hrc-avatar-inner { color: var(--accent-deep); }
    .hrc-card.is-today .hrc-date-badge { background: rgba(255,255,255,.18); border-color: rgba(255,255,255,.25); }
    .hrc-card.is-today .hrc-date-badge .hrc-date-month { background: #ffffff; color: var(--accent-deep); }
    .hrc-card.is-today .hrc-date-badge .hrc-date-day { color: #ffffff; }
    .hrc-card.is-today .hrc-date-badge .hrc-date-sub { color: rgba(255,255,255,.82); }

    /* Empty */
    .hrc-empty { text-align: center; padding: 56px 16px; color: #7E8299; background: #ffffff; border: 1px dashed #E4E6EF; border-radius: 12px; }
    .hrc-empty i { font-size: 40px; color: var(--accent); opacity: .85; margin-bottom: 10px; }

    /* Toolbar */
    .hrc-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; }
    .hrc-toolbar .hrc-exports { display: flex; gap: 8px; flex-wrap: wrap; }

    @media (max-width: 640px) {
        .hrc-cal-cell { min-height: 62px; padding: 4px; }
        .hrc-cal-pill { display: none; }
        .hrc-card { padding: 14px 14px 14px 18px; gap: 10px; }
        .hrc-avatar { width: 48px; height: 48px; flex-basis: 48px; }
        .hrc-date-badge { width: 50px; }
        .hrc-date-badge .hrc-date-day { font-size: 18px; }
    }
</style>

<div class="content d-flex flex-column flex-column-fluid hrc-wrap" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'HRM', 'title' => 'Celebrations'])

    <div class="d-flex flex-column-fluid">
        <div class="container">

            {{-- Tabs + Export --}}
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
                                        $badgeSub = $magnitude !== null ? $magnitude . ' yrs' : null;
                                    } else {
                                        $eventDate = $employee->employeeDetail?->hire_date;
                                        $magnitude = $eventDate ? $currentYear - (int) $eventDate->year : null;
                                        $magnitudeLabel = ($magnitude !== null && $magnitude > 0)
                                            ? $magnitude . ' ' . ($magnitude === 1 ? 'year' : 'years')
                                            : null;
                                        $badgeSub = ($magnitude !== null && $magnitude > 0) ? ($magnitude . ($magnitude === 1 ? ' yr' : ' yrs')) : null;
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
                                        <div class="hrc-avatar-inner">
                                            @if($avatar)
                                                <img src="{{ $avatar }}" alt="{{ $employee->name }}">
                                            @else
                                                {{ $initial }}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="hrc-card-body">
                                        <h4 class="hrc-card-name">
                                            <a href="{{ route('admin.hr.employees.show', $employee) }}">{{ $employee->name }}</a>
                                        </h4>
                                        <p class="hrc-card-meta">
                                            {{ $designation ?? '—' }}@if($dept) · {{ $dept }} @endif
                                        </p>
                                        <div class="hrc-card-chips">
                                            @if($magnitudeLabel)
                                                <span class="hrc-chip hrc-chip-accent">{{ $magnitudeLabel }}</span>
                                            @endif
                                            @if($isEventToday)
                                                <span class="hrc-chip hrc-chip-today">🎉 Today</span>
                                            @elseif($daysAway !== null && ! $isEventPast)
                                                <span class="hrc-chip">in {{ $daysAway }} {{ $daysAway === 1 ? 'day' : 'days' }}</span>
                                            @elseif($daysAway !== null && $isEventPast)
                                                <span class="hrc-chip hrc-chip-past">{{ abs($daysAway) }} {{ abs($daysAway) === 1 ? 'day' : 'days' }} ago</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="hrc-date-badge" aria-hidden="true">
                                        <div class="hrc-date-month">{{ $eventDate?->format('M') ?? '—' }}</div>
                                        <div class="hrc-date-day">{{ $eventDate?->format('d') ?? '—' }}</div>
                                        @if($badgeSub)
                                            <div class="hrc-date-sub">{{ $badgeSub }}</div>
                                        @endif
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
