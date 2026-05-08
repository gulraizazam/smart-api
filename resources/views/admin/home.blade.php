@extends('admin.layouts.master')
@section('title', 'Dashboard')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/home.css') }}">

<style>
    /* ─────────────────────────────────────────────────────────────────
       Allura dashboard — modern shell. All rules scoped under .allura-dash
       so the existing admin chrome (sidebar/header) is unaffected.
       ───────────────────────────────────────────────────────────────── */
    .allura-dash {
        --ad-bg: #f5f5f7;
        --ad-card: #ffffff;
        --ad-fg: #0a0a0a;
        --ad-muted: #6b7280;
        --ad-line: #e5e7eb;
        --ad-line-strong: #d4d4d8;
        --ad-accent: #0a0a0a;
        --ad-success: #16a34a;
        --ad-success-soft: #dcfce7;
        --ad-warning: #d97706;
        --ad-warning-soft: #fef3c7;
        --ad-info: #2563eb;
        --ad-info-soft: #dbeafe;
        --ad-danger: #dc2626;
        --ad-danger-soft: #fee2e2;
        --ad-radius: 14px;
        --ad-shadow: 0 1px 2px rgba(0,0,0,.04), 0 8px 24px rgba(0,0,0,.04);

        font-family: 'Poppins', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        color: var(--ad-fg);
        background: var(--ad-bg);
        padding-block: 16px 32px;
        padding-inline: clamp(12px, 2vw, 24px);
        container-type: inline-size;
    }

    .allura-dash * { box-sizing: border-box; }

    .allura-dash__container {
        max-width: 1600px;
        margin-inline: auto;
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    /* ─── Page header ─── */
    .allura-dash__header {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        margin-block-end: 2px;
    }
    .allura-dash__eyebrow {
        margin: 0 0 4px;
        font-size: 0.78rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: var(--ad-muted);
    }
    .allura-dash__title {
        margin: 0;
        font-size: clamp(1.5rem, 1.4vw + 1rem, 2rem);
        font-weight: 700;
        letter-spacing: -0.02em;
    }
    .allura-dash__subtitle {
        margin: 6px 0 0;
        color: var(--ad-muted);
        font-size: 0.9rem;
    }
    .allura-dash__chips {
        display: inline-flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .allura-dash__chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 600;
        background: #fff;
        border: 1px solid var(--ad-line);
        color: var(--ad-fg);
    }
    .allura-dash__chip--admin {
        background: var(--ad-fg);
        color: #fff;
        border-color: var(--ad-fg);
    }
    .allura-dash__chip::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: var(--ad-success);
    }
    .allura-dash__chip--admin::before { background: #fff; }

    /* ─── Card primitive ─── */
    .allura-dash__card {
        background: var(--ad-card);
        border: 1px solid var(--ad-line);
        border-radius: var(--ad-radius);
        box-shadow: var(--ad-shadow);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .allura-dash__card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        padding: 16px 20px;
        border-bottom: 1px solid var(--ad-line);
        background: #fff;
    }
    .allura-dash__card-title {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 700;
        letter-spacing: 0.01em;
        text-transform: none;
        color: var(--ad-fg);
        display: inline-flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }
    .allura-dash__card-title .ad-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: var(--ad-fg);
        flex: 0 0 auto;
    }
    .allura-dash__card-tools {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .allura-dash__card-body {
        position: relative;
        padding: 16px 20px 20px;
        flex: 1 1 auto;
        min-height: 0;
    }
    .allura-dash__card-body--flush { padding: 0; }
    .allura-dash__card--scroll-body .allura-dash__card-body {
        max-height: 540px;
        overflow-y: auto;
    }

    /* Re-skin form-control selects inside dashboard card heads */
    .allura-dash .allura-dash__card-tools .form-control,
    .allura-dash .allura-dash__card-tools select.form-control,
    .allura-dash .allura-dash__card-tools select {
        appearance: none;
        -webkit-appearance: none;
        height: 36px;
        min-height: 36px;
        padding: 0 32px 0 12px;
        font-size: 0.85rem;
        font-weight: 500;
        border: 1px solid var(--ad-line-strong);
        border-radius: 10px;
        background-color: #fff;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%236b7280'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z' clip-rule='evenodd'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        background-size: 16px;
        color: var(--ad-fg);
        cursor: pointer;
        line-height: 1.2;
        box-shadow: none;
    }
    .allura-dash .allura-dash__card-tools .form-control:focus,
    .allura-dash .allura-dash__card-tools select.form-control:focus,
    .allura-dash .allura-dash__card-tools select:focus {
        outline: none;
        border-color: var(--ad-fg);
        box-shadow: 0 0 0 3px rgba(0,0,0,.10);
    }

    /* "View Report" / "Download" link buttons in card heads */
    .allura-dash .allura-dash__card-tools .btndropdown {
        height: 36px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0 12px;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 600;
        text-decoration: none;
        background: #fff;
        color: var(--ad-fg);
        border: 1px solid var(--ad-line-strong);
        white-space: nowrap;
    }
    .allura-dash .allura-dash__card-tools .btndropdown:hover {
        background: #fafafa;
        border-color: var(--ad-fg);
    }

    /* ─── Top row: KPI card + Activities ─── */
    .allura-dash__top {
        display: grid;
        grid-template-columns: 1fr;
        gap: 18px;
    }

    /* KPI card */
    .allura-dash__kpi-card .allura-dash__sparkline {
        height: 90px;
        margin-block: -8px 4px;
    }
    .allura-dash__kpi-card .allura-dash__sparkline #kt_mixed_widget_1_chart {
        height: 90px !important;
    }
    .allura-dash__kpi-card .allura-dash__card-body {
        padding-top: 12px;
    }
    .allura-dash__kpis {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .allura-dash__kpi {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 18px;
        border: 1px solid var(--ad-line);
        border-radius: 12px;
        background: #fff;
        min-height: 116px;
        transition: box-shadow .15s ease, transform .15s ease, border-color .15s ease;
    }
    .allura-dash__kpi:hover {
        border-color: var(--ad-line-strong);
        box-shadow: 0 4px 14px rgba(0,0,0,.04);
    }
    .allura-dash__kpi-icon {
        width: 36px;
        height: 36px;
        display: inline-grid;
        place-items: center;
        border-radius: 10px;
        font-size: 18px;
        margin-bottom: 4px;
    }
    .allura-dash__kpi--success .allura-dash__kpi-icon { background: var(--ad-success-soft); color: var(--ad-success); }
    .allura-dash__kpi--warning .allura-dash__kpi-icon { background: var(--ad-warning-soft); color: var(--ad-warning); }
    .allura-dash__kpi--info .allura-dash__kpi-icon { background: var(--ad-info-soft); color: var(--ad-info); }
    .allura-dash__kpi--danger .allura-dash__kpi-icon { background: var(--ad-danger-soft); color: var(--ad-danger); }
    .allura-dash__kpi-value,
    .allura-dash .dashboard-counter {
        font-size: 1.35rem;
        font-weight: 700;
        letter-spacing: -0.01em;
        line-height: 1.2;
        color: var(--ad-fg);
        word-break: break-word;
        display: block;
    }
    .allura-dash__kpi-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--ad-muted);
        text-decoration: none;
        margin-top: 2px;
    }
    .allura-dash__kpi-label:hover { color: var(--ad-fg); text-decoration: underline; text-underline-offset: 3px; }
    .allura-dash .skeleton-loader {
        display: inline-block;
        background: linear-gradient(90deg, #f1f1f1, #f7f7f7, #f1f1f1);
        background-size: 200% 100%;
        animation: ad-shimmer 1.6s linear infinite;
        color: transparent;
        border-radius: 6px;
        padding: 0 8px;
    }
    @keyframes ad-shimmer {
        0% { background-position: 100% 0; }
        100% { background-position: -100% 0; }
    }
    @media (prefers-reduced-motion: reduce) {
        .allura-dash .skeleton-loader { animation: none; }
    }

    /* Activities feed */
    .allura-dash__activities #activities-container {
        height: auto !important;
        max-height: none;
        overflow: visible !important;
    }
    .allura-dash__activities-body {
        padding: 4px 20px 20px;
        max-height: 540px;
        overflow-y: auto;
    }
    .allura-dash__activities-body #activities-loader { padding: 24px 0; }
    .allura-dash__activities-body #activities-empty,
    .allura-dash__activities-body #activities-unauthorized {
        padding: 60px 0 40px;
        text-align: center;
        color: var(--ad-muted);
    }
    .allura-dash__activities-body .timeline { padding-top: 8px; }

    /* ─── Pair grid ─── */
    .allura-dash__grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 18px;
    }

    /* ─── Card-internal common skin ─── */
    .allura-dash .table {
        margin: 0;
        font-size: 0.875rem;
        color: var(--ad-fg);
    }
    .allura-dash .table thead th,
    .allura-dash .table .table-cols {
        background: #fafafa;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--ad-muted);
        border-top: 0;
        border-bottom: 1px solid var(--ad-line);
        padding: 10px 12px;
        position: sticky;
        top: 0;
        z-index: 1;
    }
    .allura-dash .table tbody td {
        padding: 12px;
        border-top: 1px solid var(--ad-line);
        vertical-align: middle;
    }
    .allura-dash .table tbody tr:hover td { background: #fafafa; }

    .allura-dash .custom_loader {
        display: block;
        margin: 30px auto;
        width: 50px;
        opacity: .85;
    }

    /* Hide the legacy "card-spacer2" + "nav nav-tabs" wrappers we replace.
       We keep them inside markup for backward CSS compatibility but suppress
       only the ones with no children inside .allura-dash. */
    .allura-dash .card-spacer2 { padding: 0; }

    /* Container queries — refine layout at component level */
    @container (min-width: 720px) {
        .allura-dash__kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .allura-dash__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @container (min-width: 1200px) {
        .allura-dash__top {
            grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .allura-dash__kpi { transition: none; }
    }

    /* Tighter scrollbars inside cards */
    .allura-dash__card *::-webkit-scrollbar { width: 8px; height: 8px; }
    .allura-dash__card *::-webkit-scrollbar-track { background: transparent; }
    .allura-dash__card *::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 999px; }
    .allura-dash__card *::-webkit-scrollbar-thumb:hover { background: #d4d4d8; }
</style>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb')

    <div class="allura-dash">
        <div class="allura-dash__container">

            {{-- ─────────────── Page header ─────────────── --}}
            <header class="allura-dash__header">
                <div>
                    <p class="allura-dash__eyebrow">Welcome back</p>
                    <h1 class="allura-dash__title">Dashboard</h1>
                    <p class="allura-dash__subtitle">
                        @if (!empty($today)){{ \Illuminate\Support\Carbon::parse($today)->format('l, j F Y') }}@endif
                        @if (!empty($currentTime)) · {{ $currentTime }}@endif
                    </p>
                </div>
                <div class="allura-dash__chips">
                    @if ($isAdmin)
                        <span class="allura-dash__chip allura-dash__chip--admin">Admin</span>
                    @endif
                    @if ($isCSRRole)
                        <span class="allura-dash__chip">CSR</span>
                    @endif
                </div>
            </header>

            {{-- ─────────────── Top row: KPI + Activities ─────────────── --}}
            <div class="allura-dash__top">

                {{-- KPI card --}}
                <section class="allura-dash__card allura-dash__kpi-card">
                    <header class="allura-dash__card-head">
                        <h2 class="allura-dash__card-title"><span class="ad-dot"></span>Performance</h2>
                        <div class="allura-dash__card-tools">
                            <select class="form-control" name="type" onchange="changeDate();" id="recordfilter">
                                <option value="today" {{ (request('type')=='today' || !request('type')) ? 'selected' : '' }}>Today</option>
                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This Week</option>
                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This Month</option>
                            </select>
                        </div>
                    </header>
                    <div class="allura-dash__card-body">
                        <div class="allura-dash__sparkline">
                            <div id="kt_mixed_widget_1_chart" style="height: 90px"></div>
                        </div>

                        <div class="allura-dash__kpis">
                            <div class="allura-dash__kpi allura-dash__kpi--success">
                                <span class="allura-dash__kpi-icon" aria-hidden="true">
                                    <i class="la la-shopping-cart"></i>
                                </span>
                                <span class="dashboard-counter" id="allleads"><span class="skeleton-loader">Loading...</span></span>
                                <a href="javascript:void(0);" class="allura-dash__kpi-label">Sales</a>
                            </div>
                            <div class="allura-dash__kpi allura-dash__kpi--warning">
                                <span class="allura-dash__kpi-icon" aria-hidden="true">
                                    <i class="la la-coins"></i>
                                </span>
                                <span class="dashboard-counter" id="allrevenue"><span class="skeleton-loader">Loading...</span></span>
                                <a href="javascript:void(0);" class="allura-dash__kpi-label">Revenue Consumed</a>
                            </div>
                            <div class="allura-dash__kpi allura-dash__kpi--info">
                                <span class="allura-dash__kpi-icon" aria-hidden="true">
                                    <i class="la la-stethoscope"></i>
                                </span>
                                <span class="dashboard-counter" id="allconsult"><span class="skeleton-loader">Loading...</span></span>
                                <a id="allconsultantdate" href="javascript:void(0);" class="allura-dash__kpi-label">Consultancies</a>
                            </div>
                            <div class="allura-dash__kpi allura-dash__kpi--danger">
                                <span class="allura-dash__kpi-icon" aria-hidden="true">
                                    <i class="la la-medkit"></i>
                                </span>
                                <span class="dashboard-counter" id="alltreat"><span class="skeleton-loader">Loading...</span></span>
                                <a id="alltreatmentdate" href="javascript:void(0);" class="allura-dash__kpi-label">Treatments</a>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Activities --}}
                <section class="allura-dash__card allura-dash__activities" id="activitydiv">
                    <div id="activities-container">
                        <header class="allura-dash__card-head">
                            <h2 class="allura-dash__card-title">
                                <span class="ad-dot" style="background:#16a34a;"></span>
                                Today's Activities
                            </h2>
                            <span class="allura-dash__chip" id="totalactivities">0 activities</span>
                        </header>
                        <div class="allura-dash__activities-body" id="activities-body">
                            <div class="text-center" id="activities-loader">
                                <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" style="width: 50px;">
                            </div>
                            <div class="timeline timeline-6 mt-3" id="activities-timeline" style="display: none;"></div>
                            <div class="text-center" id="activities-empty" style="display: none;">
                                <span style="display:block;">No activity found</span>
                            </div>
                            <div class="text-center" id="activities-unauthorized" style="display: none;">
                                <span>You are not authorized</span>
                            </div>
                            <div class="text-center py-3" id="load-more-container" style="display: none;">
                                <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading more" id="load-more-spinner" style="width: 30px; display: none;">
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- ─────────────── Modals (preserved verbatim) ─────────────── --}}
            <div class="modal fade" id="modal_change_appointment_status" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered form-popup" id="appointment_status_change">
                    @include('admin.appointments.appointment-forms.change-status')
                </div>
            </div>
            <div class="modal fade" id="modal_change_appointment_schedule" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered form-popup" id="appointment_schedule_change">
                    @include('admin.appointments.appointment-forms.schedule')
                </div>
            </div>

            {{-- ─────────────── Paired widgets ─────────────── --}}
            <div class="allura-dash__grid">

                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_collection_by_centre'))
                <section class="allura-dash__card custom_tabs_style" id="collection-by-centre-section">
                    <header class="allura-dash__card-head">
                        <h2 class="allura-dash__card-title"><span class="ad-dot" style="background:#2563eb;"></span>Collection by Centre</h2>
                        <div class="allura-dash__card-tools">
                            <select id="collection_centre" class="form-control collection_centre" name="type">
                                <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today</option>
                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This Week</option>
                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This Month</option>
                            </select>
                        </div>
                    </header>
                    <div class="allura-dash__card-body" id="collectionbycenter">
                        <div class="d-none flex-column text-right">
                            <span class="text-dark-75 font-weight-bolder font-size-h3 total-pie-chart"></span>
                            <span class="text-muted font-weight-bold mt-2 pie-income-title">Weekly Income</span>
                        </div>
                        <div id="collection-by-centre"></div>
                        <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                    </div>
                </section>
                @endif

                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_unattended_report'))
                <section class="allura-dash__card custom_tabs_style" id="patient-followup-section">
                    <header class="allura-dash__card-head">
                        <h2 class="allura-dash__card-title"><span class="ad-dot" style="background:#d97706;"></span>Unattended Payments</h2>
                        <div class="allura-dash__card-tools">
                            <a class="btndropdown btn_Report dashboard_unattended_report" href="{{ route('admin.reports.follow_up') }}">View Report <i class="fa fa-angle-right"></i></a>
                            <a class="btndropdown btn_Report dashboard_unattended_report" href="{{ route('admin.follow_up.download') }}">Download <i class="fa fa-download ml-2"></i></a>
                        </div>
                    </header>
                    <div class="allura-dash__card-body allura-dash__card-body--flush">
                        <div class="card-spacer2" id="unattended-payments-scroll" style="max-height: 460px; overflow-y: auto;">
                            <div class='table-responsive'>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th class='table-cols'>ID</th>
                                            <th class='table-cols'>Name</th>
                                            <th class='table-cols'>Treatment</th>
                                            <th class='table-cols'>Balance</th>
                                            <th class='table-cols'>Conversion Date</th>
                                        </tr>
                                    </thead>
                                    <tbody id="patient-follow-up"></tbody>
                                </table>
                                <div class="text-center py-2" id="unattended-loader" style="display: none;">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                </div>
                                <img src="{{ asset('assets/media/loader.gif') }}" class="custom_loader loader-img-unattended" style="width: 50px; display: block; margin: 50px auto;">
                            </div>
                        </div>
                    </div>
                </section>
                @endif

                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_revenue_by_centre'))
                <section class="allura-dash__card custom_tabs_style" id="revenue-by-centre-section">
                    <header class="allura-dash__card-head">
                        <h2 class="allura-dash__card-title"><span class="ad-dot" style="background:#16a34a;"></span>Revenue by Centre</h2>
                        <div class="allura-dash__card-tools">
                            <select id="revenue_centre" class="form-control" name="type">
                                <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today</option>
                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This Week</option>
                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This Month</option>
                            </select>
                        </div>
                    </header>
                    <div class="allura-dash__card-body" id="revenue_by_centre">
                        <div class="d-none flex-column text-right">
                            <span class="text-dark-75 font-weight-bolder font-size-h3 total-centre"></span>
                            <span class="text-muted font-weight-bold mt-2 revenue-centre-title">Today Revenue</span>
                        </div>
                        <div id="revenue-centre"></div>
                        <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                    </div>
                </section>
                @endif

                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_overdue_treatments'))
                <section class="allura-dash__card custom_tabs_style" id="patient-followup-onemonth-section">
                    <header class="allura-dash__card-head">
                        <h2 class="allura-dash__card-title"><span class="ad-dot" style="background:#dc2626;"></span>Overdue Treatments</h2>
                        <div class="allura-dash__card-tools">
                            <a class="btndropdown btn_Report dashboard_overdue_treatments" href="{{ route('admin.reports.follow_up') }}">View Report <i class="fa fa-angle-right"></i></a>
                            <a class="btndropdown btn_Report dashboard_overdue_treatments" href="{{ route('admin.monthly_follow_up.download') }}">Download <i class="fa fa-download ml-2"></i></a>
                        </div>
                    </header>
                    <div class="allura-dash__card-body allura-dash__card-body--flush">
                        <div class="card-spacer2" id="overdue-treatments-scroll" style="max-height: 460px; overflow-y: auto;">
                            <div class='table-responsive'>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th class='table-cols'>ID</th>
                                            <th class='table-cols'>Name</th>
                                            <th class='table-cols'>Balance</th>
                                            <th class='table-cols'>Last Arrived</th>
                                        </tr>
                                    </thead>
                                    <tbody id="patient-follow-up-one-month"></tbody>
                                </table>
                                <div class="text-center py-2" id="overdue-loader" style="display: none;">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                </div>
                                <img src="{{ asset('assets/media/loader.gif') }}" class="custom_loader loader-img-overdue" style="width: 50px; display: block; margin: 50px auto;">
                            </div>
                        </div>
                    </div>
                </section>
                @endif

                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_revenue_by_service'))
                <section class="allura-dash__card" id="revenue-service-category-section">
                    <header class="allura-dash__card-head">
                        <h2 class="allura-dash__card-title"><span class="ad-dot" style="background:#7c3aed;"></span>Revenue by Service Category</h2>
                        <div class="allura-dash__card-tools">
                            <select id="revenue_service_cate" class="form-control" name="type">
                                <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today</option>
                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This Week</option>
                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This Month</option>
                            </select>
                        </div>
                    </header>
                    <div class="allura-dash__card-body" id="revenue_by_service_category">
                        <div class="d-none flex-column text-right">
                            <span class="text-dark-75 font-weight-bolder font-size-h3 total-category-service"></span>
                            <span class="text-muted font-weight-bold mt-2 service-category-title"></span>
                        </div>
                        <div id="revenue-service-category"></div>
                        <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                    </div>
                </section>

                <section class="allura-dash__card custom_tabs_style" id="revenue-service-section">
                    <header class="allura-dash__card-head">
                        <h2 class="allura-dash__card-title"><span class="ad-dot" style="background:#0891b2;"></span>Revenue by Service</h2>
                        <div class="allura-dash__card-tools">
                            <select id="revenue_service" class="form-control" name="type">
                                <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today</option>
                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This Week</option>
                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This Month</option>
                            </select>
                        </div>
                    </header>
                    <div class="allura-dash__card-body" id="revenue_by_service">
                        <div class="d-none flex-column text-right">
                            <span class="text-dark-75 font-weight-bolder font-size-h3 total-service"></span>
                            <span class="text-muted font-weight-bold mt-2 service-title"></span>
                        </div>
                        <div id="revenue-service"></div>
                        <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                    </div>
                </section>
                @endif

                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_appointment_by_status'))
                <section class="allura-dash__card custom_tabs_style" id="consultancy-status-section">
                    <header class="allura-dash__card-head">
                        <h2 class="allura-dash__card-title"><span class="ad-dot" style="background:#2563eb;"></span>Consultancy by Status</h2>
                        <div class="allura-dash__card-tools">
                            <select id="consultancy_status" class="form-control" name="type">
                                <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today</option>
                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This Week</option>
                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This Month</option>
                            </select>
                        </div>
                    </header>
                    <div class="allura-dash__card-body" id="consultancy_status1">
                        <div class="d-none flex-column text-right">
                            <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointment-by-status"></span>
                            <span class="text-muted font-weight-bold mt-2 appointment-by-status-title"></span>
                        </div>
                        <div id="consultancy_by_status"></div>
                        <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                    </div>
                </section>
                @endif

                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_appointment_by_type'))
                <section class="allura-dash__card custom_tabs_style" id="treatment-status-section">
                    <header class="allura-dash__card-head">
                        <h2 class="allura-dash__card-title"><span class="ad-dot" style="background:#dc2626;"></span>Treatment by Status</h2>
                        <div class="allura-dash__card-tools">
                            <select id="treatment_status" class="form-control" name="type">
                                <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today</option>
                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This Week</option>
                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This Month</option>
                            </select>
                        </div>
                    </header>
                    <div class="allura-dash__card-body" id="treatment_status1">
                        <div class="d-none flex-column text-right">
                            <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointment-by-status"></span>
                            <span class="text-muted font-weight-bold mt-2 appointment-by-status-title"></span>
                        </div>
                        <div id="treatment_by_status"></div>
                        <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                    </div>
                </section>
                @endif

            </div>

            {{-- ─────────────── Full-width sections ─────────────── --}}

            @if (\Illuminate\Support\Facades\Gate::allows('dashboard_staff_wise_arrival'))
            <section class="allura-dash__card custom_tabs_style" id="staff_wise_arrival">
                <header class="allura-dash__card-head">
                    <h2 class="allura-dash__card-title"><span class="ad-dot" style="background:#16a34a;"></span>Centre Wise Arrival</h2>
                    <div class="allura-dash__card-tools wise_arrival_ul">
                        @if ($isAdmin)
                            <select class="form-control centre_name_ul" id="centervise_center">
                                <option data-period="thismonth" value="All">All Centres</option>
                                @foreach ($centres as $centre)
                                    <option data-period="thismonth" value="{{ $centre->id }}">{{ $centre->name }}</option>
                                @endforeach
                            </select>
                        @elseif ($isCSRRole)
                            <select class="form-control" id="userwise_arrival">
                                <option onclick="initUserWiseArrival('thismonth', 'All')" value="">All</option>
                                @foreach ($csrUsers as $user)
                                    <option onclick="initUserWiseArrival('thismonth', {{ $user->id }})" value="{{ $user->id }}" data-period="thismonth">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <select class="form-control" id="centervise_center">
                                <option value="" class="btndropdown btn_Report centre_name arrivalbtn">
                                    {{ $firstCentre ? $firstCentre->name : 'No Centre Assigned' }}
                                </option>
                            </select>
                        @endif

                        @if ($isCSRRole)
                            <select id="center_wise_arrival" class="form-control" name="type">
                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This Week</option>
                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This Month</option>
                                <option value="lastmonth" {{ request('type')=='lastmonth' ? 'selected' : '' }}>Last Month</option>
                            </select>
                        @else
                            <select id="initCentreWiseArrival" class="form-control" name="type">
                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This Week</option>
                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This Month</option>
                                <option value="lastmonth" {{ request('type')=='lastmonth' ? 'selected' : '' }}>Last Month</option>
                            </select>
                        @endif
                    </div>
                </header>
                <div class="allura-dash__card-body" style="position: relative;">
                    <div class="d-none flex-column text-right">
                        <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointment-by-status"></span>
                        <span class="text-muted font-weight-bold mt-2 appointment-by-status-title"></span>
                    </div>
                    <div class="row">
                        <div class="col-12 col-lg-7">
                            <div id="centre_wise_arrival"></div>
                        </div>
                        <div class="col-12 col-lg-5 centre_wise_arrival_wrap">
                            <div class="row" id="centre_wise_arrival_02">
                                <div class='table-responsive' style="overflow-y: auto; height: 475px; width: 100%;">
                                    <table class='table'>
                                        <thead>
                                            @if ($isCSRRole)
                                                <tr>
                                                    <th class='table-cols'>CSR Name</th>
                                                    <th class='table-cols'>Arrived</th>
                                                    <th class='table-cols'>Percentage</th>
                                                </tr>
                                            @else
                                                <tr>
                                                    <th class='table-cols'></th>
                                                    <th class='table-cols'>Arrived</th>
                                                    <th class='table-cols'>WalkIn</th>
                                                    <th class='table-cols'>Percentage</th>
                                                </tr>
                                            @endif
                                        </thead>
                                        <tbody id="table-body"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                </div>
            </section>
            @endif

            @if (\Illuminate\Support\Facades\Gate::allows('dashboard_doctor_wise_conversion'))
            <section class="allura-dash__card custom_tabs_style" id="doctor_wise_conversion_section">
                <header class="allura-dash__card-head">
                    <h2 class="allura-dash__card-title"><span class="ad-dot" style="background:#7c3aed;"></span>Doctor Wise Conversion</h2>
                    <div class="allura-dash__card-tools doc_wise_arrival_ul">
                        <select class="form-control btndropdown btn_Report doctorwiseconversion selectcenter"
                                data-placeholder="Select Centre" data-dropdown-css-class="select2-dropdown">
                            @if ($hasMultipleCentres)
                                <option value="all" data-period="thismonth">All Centres</option>
                            @endif
                            @foreach ($centres as $centre)
                                <option value="{{ $centre->id }}" data-period="thismonth">{{ $centre->name }}</option>
                            @endforeach
                        </select>
                        <select class="form-control btndropdown btn_Report doctorname" data-dropdown-css-class="select2-dropdown" id="doc_nav"></select>
                        <select id="dr_wise_con" class="form-control" name="type">
                            <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today</option>
                            <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                            <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                            <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This Week</option>
                            <option value="thismonth" {{ (request('type')=='thismonth' || !request('type')) ? 'selected' : '' }}>This Month</option>
                        </select>
                    </div>
                </header>
                <div class="allura-dash__card-body" style="position: relative;">
                    <div class="d-none flex-column text-right">
                        <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointment-by-status"></span>
                        <span class="text-muted font-weight-bold mt-2 appointment-by-status-title"></span>
                    </div>
                    <div class="row">
                        <div class="col-12 col-lg-7" style="overflow-x: auto;">
                            <div id="doc_wise_conversion"></div>
                        </div>
                        <div class="col-12 col-lg-5 appenddoctorlist" id="centre_wise_arrival_02">
                            <div class='table-responsive' style="overflow-y: auto; height: 475px; width: 100%;">
                                <table class='table'>
                                    <thead>
                                        <tr>
                                            <th class='table-cols'></th>
                                            <th class='table-cols'>Con. Ratio</th>
                                            <th class='table-cols'>% avg</th>
                                            <th class='table-cols'>Avg Value</th>
                                        </tr>
                                    </thead>
                                    <tbody id="categories-table-body"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                </div>
            </section>
            @endif

            @if (\Illuminate\Support\Facades\Gate::allows('dashboard_doctor_wise_feedback'))
            <section class="allura-dash__card custom_tabs_style" id="doctor_wise_feedback_section">
                <header class="allura-dash__card-head">
                    <h2 class="allura-dash__card-title"><span class="ad-dot" style="background:#0891b2;"></span>Doctor Ratings — Based on Client Insight</h2>
                    <div class="allura-dash__card-tools doc_feedback_ul">
                        <select class="form-control btndropdown btn_Report doctorwisefeedback selectcenterfeedback"
                                data-placeholder="Select Centre" data-dropdown-css-class="select2-dropdown">
                            @if ($hasMultipleCentres)
                                <option value="all" data-period="thismonth">All Centres</option>
                            @endif
                            @foreach ($centres as $centre)
                                <option value="{{ $centre->id }}" data-period="thismonth">{{ $centre->name }}</option>
                            @endforeach
                        </select>
                        <select id="dr_wise_fed" class="form-control" name="type">
                            <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                            <option value="last7days" {{ request('type')=='last7days' || request('type')=='' ? 'selected' : '' }}>Last 7 Days</option>
                            <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This Week</option>
                            <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This Month</option>
                            <option value="lastmonth" {{ request('type')=='lastmonth' ? 'selected' : '' }}>Last Month</option>
                            <option value="all" {{ request('type')=='all' ? 'selected' : '' }}>Life Time</option>
                        </select>
                    </div>
                </header>
                <div class="allura-dash__card-body" style="position: relative;">
                    <div class="d-none flex-column text-right">
                        <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointment-by-status"></span>
                        <span class="text-muted font-weight-bold mt-2 appointment-by-status-title"></span>
                    </div>
                    <div class="row">
                        <div class="col-12" style="overflow-x: auto; overflow-y: hidden;">
                            <div id="doc_wise_feedback_data"></div>
                        </div>
                    </div>
                    <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                </div>
            </section>
            @endif

            @if (\Illuminate\Support\Facades\Gate::allows('dashboard_upselling_report'))
            <section class="allura-dash__card custom_tabs_style" id="doctor_upselling_section">
                <header class="allura-dash__card-head">
                    <h2 class="allura-dash__card-title"><span class="ad-dot" style="background:#d97706;"></span>Doctor Upselling</h2>
                    <div class="allura-dash__card-tools doc_upselling_ul">
                        <select class="form-control btndropdown btn_Report doctorUpselling selectcenterupselling"
                                id="doctor_upselling_centre_select"
                                data-placeholder="Select Centre" data-dropdown-css-class="select2-dropdown">
                            @if ($hasMultipleCentres)
                                <option value="all" data-period="thismonth" selected>All Centres</option>
                            @endif
                            @foreach ($centres as $centre)
                                <option value="{{ $centre->id }}" data-period="thismonth" {{ (!$hasMultipleCentres && count($centres) == 1) ? 'selected' : '' }}>{{ $centre->name }}</option>
                            @endforeach
                        </select>
                        <select id="dr_wise_upselling_period" class="form-control" name="type">
                            <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today</option>
                            <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                            <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                            <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This Week</option>
                            <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This Month</option>
                            <option value="lastmonth" {{ request('type')=='lastmonth' ? 'selected' : '' }}>Last Month</option>
                        </select>
                    </div>
                </header>
                <div class="allura-dash__card-body" style="position: relative;">
                    <div class="d-none flex-column text-right">
                        <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointment-by-status"></span>
                        <span class="text-muted font-weight-bold mt-2 appointment-by-status-title"></span>
                    </div>
                    <div class="row">
                        <div class="col-12 col-lg-7">
                            <div id="doctor_upselling_chart" style="min-height: 400px;">
                                <div class="d-flex align-items-center justify-content-center" style="height: 400px;" id="doctor_upselling_placeholder">
                                    <div class="text-center text-muted">
                                        <i class="fas fa-info-circle mb-2"></i><br>
                                        Select a centre to view doctor upselling data
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-5">
                            <div class="table-responsive" style="overflow-y: auto; height: 475px;">
                                <table class="table" id="doctor_upselling_table">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="text-left">Doctor Name</th>
                                            <th class="text-right">Upselling Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody id="doctor_upselling_tbody">
                                        <tr id="no_data_row">
                                            <td colspan="3" class="text-center text-muted py-5">
                                                <i class="fas fa-info-circle mb-2"></i><br>
                                                Select a centre to view data
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot id="doctor_upselling_tfoot" style="display: none;">
                                        <tr class="font-weight-bold bg-light">
                                            <td>Total</td>
                                            <td class="text-right" id="total_upselling_amount">0.00</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-upselling" style="height: 60px;">
                </div>
            </section>
            @endif

        </div>
    </div>
</div>

@push('datatable-js')
<script src="{{ asset('assets/js/pages/crud/forms/validation/appointment/validation.js') }}"></script>
<script src="{{ asset('assets/js/pages/dashboard/datatable.js') }}"></script>
<script src="{{ asset('assets/js/jsapi.js') }}"></script>
<script src="{{ asset('assets/js/pie.js') }}"></script>
<script src="{{ asset('assets/js/home.js') }}"></script>
<script>
// Dashboard configuration for lazy loading and routes
window.dashboardConfig = {
    requestType: '{{ $requestType ?? 'thismonth' }}',
    locationIds: {!! json_encode($location_id) !!},
    startDate: '{{ $today ?? '' }}',
    endDate: '{{ $today ?? '' }}',
    isCSR: {{ auth()->user()->hasRole('CSR') ? 'true' : 'false' }},
    isCSRSupervisor: {{ auth()->user()->hasRole('CSR Supervisor') ? 'true' : 'false' }},
    isSocialLead: {{ auth()->user()->hasRole('Social Lead') ? 'true' : 'false' }},
    routes: {
        doctorUpsellingData: '/api/dashboard/doctor-upselling-data'
    }
};
</script>
<script src="{{ asset('assets/js/dashboard.js') }}"></script>
<script src="{{ asset('assets/js/dashboard-charts.js') }}"></script>
@endpush
@endsection
