@php
    /**
     * Sidebar v2 — one group per section.
     *
     * 8 rows at rest: Dashboard + 7 collapsible groups. Current route's
     * group auto-expands on load; otherwise the last user-toggled group
     * is restored from localStorage. Every route() and every @can /
     * Gate::allows guard in this file is a verbatim copy of the legacy
     * sidebar — nothing is renamed, added, or removed.
     */
    $routeGroupMap = [
        // Clinic
        'admin.patients.index' => 'clinic',
        'admin.patients.preview' => 'clinic',
        'admin.leads.index' => 'clinic',
        'admin.consultancy.index' => 'clinic',
        'admin.treatment.index' => 'clinic',
        'admin.appointments.index' => 'clinic',
        'admin.resourcerotas.schedule' => 'clinic',
        'admin.resourcerotas.repeating-shifts' => 'clinic',
        'admin.business-closures.index' => 'clinic',

        // Catalog
        'admin.services.index' => 'catalog',
        'admin.service-bundles.index' => 'catalog',
        'admin.bundles.index' => 'catalog',
        'admin.packages.index' => 'catalog',
        'admin.discounts.index' => 'catalog',
        'admin.voucherTypes.index' => 'catalog',
        'admin.vouchers.index' => 'catalog',
        'admin.membershiptypes.index' => 'catalog',
        'admin.memberships.index' => 'catalog',

        // Finance
        'admin.invoices.index' => 'finance',
        'admin.refunds.index' => 'finance',
        'admin.packagesadvances.index' => 'finance',
        'admin.payment_modes.index' => 'finance',
        'admin.payment_modes.sort' => 'finance',
        'admin.cashflow.dashboard' => 'finance',
        'admin.cashflow.expenses' => 'finance',
        'admin.cashflow.transfers' => 'finance',
        'admin.cashflow.vendors' => 'finance',
        'admin.cashflow.staff' => 'finance',
        'admin.cashflow.fdm' => 'finance',
        'admin.cashflow.reports' => 'finance',
        'admin.cashflow.settings' => 'finance',

        // Inventory
        'admin.warehouse.index' => 'inventory',
        'admin.brands.index' => 'inventory',
        'admin.products.index' => 'inventory',
        'admin.products.logs' => 'inventory',
        'admin.products.stock' => 'inventory',
        'admin.transfer_product.index' => 'inventory',
        'admin.orders.index' => 'inventory',
        'admin.order.refunds.index' => 'inventory',

        // Reports
        'admin.management_dashboard.overview' => 'reports',
        'admin.management_dashboard.people' => 'reports',
        'admin.feedbacks.index' => 'reports',
        'admin.reports.finance_reports' => 'reports',
        'admin.reports.tax_calculation_report' => 'reports',
        'admin.reports.operations_report' => 'reports',
        'admin.reports.membership-reports' => 'reports',
        'admin.reports.doctorWiseConversion' => 'reports',
        'admin.reports.appointmentsReport' => 'reports',
        'admin.reports.csr_dashboard' => 'reports',
        'admin.reports.arrived_not_converted' => 'reports',
        'admin.reports.conversion' => 'reports',
        'admin.reports.activity_logs' => 'reports',
        'admin.reports.staff_wise_arrival' => 'reports',
        'admin.reports.follow_up' => 'reports',
        'admin.reports.inventory_report' => 'reports',
        'admin.reports.feedback_report' => 'reports',
        'admin.reports.future_treatments' => 'reports',
        'admin.reports.upselling' => 'reports',
        'admin.reports.consultant_revenue' => 'reports',
        'admin.reports.doctor_revenue' => 'reports',

        // People (HR + Access folded together)
        'admin.hr.dashboard' => 'people',
        'admin.hr.employees.index' => 'people',
        'admin.hr.employees.show' => 'people',
        'admin.hr.departments.index' => 'people',
        'admin.hr.designations.index' => 'people',
        'admin.hr.leave-types.index' => 'people',
        'admin.hr.leave-balances.index' => 'people',
        'admin.hr.leave-applications.index' => 'people',
        'admin.hr.leave-applications.calendar' => 'people',
        'admin.hr.recruitment.index' => 'people',
        'admin.hr.recruitment.show' => 'people',
        'admin.hr.reports.celebrations' => 'people',
        'admin.permissions.index' => 'people',
        'admin.roles.index' => 'people',
        'admin.roles.edit' => 'people',
        'admin.users.index' => 'people',
        'admin.doctors.index' => 'people',
        'admin.user_types.index' => 'people',
        'admin.centre_targets.index' => 'people',

        // Settings (General + Locations + Taxonomies + Forms)
        'admin.settings.index' => 'settings',
        'admin.user_operator_settings.index' => 'settings',
        'admin.sms_templates.index' => 'settings',
        'admin.google_reviews.index' => 'settings',
        'admin.regions.index' => 'settings',
        'admin.regions.sort' => 'settings',
        'admin.cities.index' => 'settings',
        'admin.cities.sort' => 'settings',
        'admin.towns.index' => 'settings',
        'admin.locations.index' => 'settings',
        'admin.lead_sources.index' => 'settings',
        'admin.lead_sources.sort' => 'settings',
        'admin.lead_statuses.index' => 'settings',
        'admin.lead_statuses.sort' => 'settings',
        'admin.appointment_statuses.index' => 'settings',
        'admin.machine_types.index' => 'settings',
        'admin.resources.index' => 'settings',
        'admin.custom_forms.index' => 'settings',
        'admin.custom_forms.edit' => 'settings',
        'admin.custom_form_feedbacks.index' => 'settings',
        'admin.custom_form_feedbacks.edit' => 'settings',
        'admin.custom_form_feedbacks.filled_preview' => 'settings',
        'admin.custom_form_feedbacks.preview_form' => 'settings',
        'admin.custom_form_feedbacks.fill_form' => 'settings',
    ];

    $currentRoute = request()->route()?->getName() ?? '';
    $activeGroup = $routeGroupMap[$currentRoute] ?? '';

    $reportsMenuPermissions = [
        'finance_general_revenue_reports_manage',
        'operations_reports_operations_tax_calculation_report',
        'operations_reports_manage',
        'appointment_reports_manage',
        'csr_dashboard_report',
        'non_converted_customers_manage',
        'conversion_report_manage',
        'staff_wise_arrival_manage',
        'follow_up_manage',
        'inventory_report_manage',
        'feedbacks_manage',
        'followuppatient_manage',
        'upselling_report',
        'consultant_revenue_report',
    ];
    $canSeeReportsMenu = collect($reportsMenuPermissions)
        ->contains(fn ($p) => \Illuminate\Support\Facades\Gate::allows($p));

    $isActive = fn (array $routes): string =>
        in_array(request()->route()?->getName(), $routes, true) ? 'is-active' : '';
@endphp

{{-- Styles inline because master emits @stack('css') before this partial is
     included in <body>; @push from here would never reach the head. --}}
<style>
    .cs-sidebar {
        position: fixed;
        inset-block: 0;
        inset-inline-start: 0;
        width: 265px;
        display: flex;
        flex-direction: column;
        background: linear-gradient(180deg, #0B1121 0%, #0F1629 100%);
        color: #94A3B8;
        font-family: 'Poppins', system-ui, -apple-system, sans-serif;
        z-index: 98;
        border-right: 1px solid rgba(255, 255, 255, 0.04);
        transition: width 0.25s ease;
    }

    .cs-brand {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px 14px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }
    .cs-brand-logo img { width: 120px; display: block; }
    .cs-brand-toggle {
        width: 32px; height: 32px;
        border-radius: 8px; border: 0;
        background: rgba(255, 255, 255, 0.04);
        color: #94A3B8;
        display: inline-flex; align-items: center; justify-content: center;
        cursor: pointer;
        transition: background 0.15s, color 0.15s;
    }
    .cs-brand-toggle:hover { background: rgba(255, 255, 255, 0.1); color: #fff; }
    .cs-brand-toggle:focus-visible { outline: 2px solid #3B82F6; outline-offset: 2px; }

    .cs-search {
        flex: 0 0 auto;
        padding: 10px 14px 4px;
    }
    .cs-search-field {
        display: flex; align-items: center; gap: 8px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 8px;
        padding: 8px 10px;
        transition: border-color 0.15s, background 0.15s;
    }
    .cs-search-field:focus-within {
        border-color: rgba(59, 130, 246, 0.5);
        background: rgba(255, 255, 255, 0.07);
    }
    .cs-search-icon { font-size: 14px; color: #64748B; flex-shrink: 0; }
    .cs-search input {
        background: transparent; border: 0; outline: 0;
        color: #E2E8F0; font-family: inherit; font-size: 13px;
        width: 100%; min-width: 0;
    }
    .cs-search input::placeholder { color: #64748B; }
    .cs-kbd {
        font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace;
        font-size: 10px; color: #94A3B8;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 4px; padding: 1px 5px; flex-shrink: 0;
    }

    .cs-menu {
        flex: 1 1 auto;
        overflow-y: auto; overflow-x: hidden;
        padding: 8px 10px 16px;
        scrollbar-width: thin;
        scrollbar-color: rgba(255, 255, 255, 0.12) transparent;
    }
    .cs-menu::-webkit-scrollbar { width: 4px; }
    .cs-menu::-webkit-scrollbar-track { background: transparent; }
    .cs-menu::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.1); border-radius: 2px;
    }
    .cs-menu::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.18); }

    /* In-group sub-section divider (used inside People and Settings) */
    .cs-subsection {
        padding: 10px 14px 4px 24px;
        font-size: 9.5px;
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #475569;
        user-select: none;
    }
    .cs-group-body .cs-subsection:first-child { padding-top: 6px; }

    .cs-link, .cs-group-btn {
        display: flex; align-items: center; width: 100%;
        padding: 8px 12px; margin: 1px 0;
        border-radius: 8px; border: 0;
        background: transparent;
        color: #94A3B8;
        font-family: inherit; font-size: 13px; font-weight: 400;
        text-align: start; text-decoration: none;
        cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .cs-link:hover, .cs-group-btn:hover {
        background: rgba(255, 255, 255, 0.04);
        color: #F1F5F9; text-decoration: none;
    }
    .cs-link:focus-visible, .cs-group-btn:focus-visible {
        outline: 2px solid #3B82F6; outline-offset: 2px;
    }
    .cs-group-btn[aria-expanded="true"] { color: #F1F5F9; }
    .cs-link.is-active {
        background: linear-gradient(90deg, rgba(59, 130, 246, 0.18) 0%, rgba(59, 130, 246, 0.06) 100%);
        color: #fff; font-weight: 500;
        box-shadow: inset 3px 0 0 #3B82F6;
    }
    .cs-link.is-active .cs-icon { color: #60A5FA; }

    .cs-icon {
        display: inline-flex; align-items: center; justify-content: center;
        width: 20px; height: 20px;
        margin-inline-end: 12px;
        font-size: 17px; flex-shrink: 0;
        color: inherit;
    }
    .cs-label {
        flex: 1 1 auto; min-width: 0;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .cs-chev {
        font-size: 10px; opacity: 0.55;
        transition: transform 0.2s ease;
        margin-inline-start: 6px;
    }
    .cs-group-btn[aria-expanded="true"] .cs-chev {
        transform: rotate(90deg); opacity: 0.9;
    }

    .cs-group-body {
        overflow: hidden;
        max-height: 0;
        transition: max-height 0.3s ease;
    }
    .cs-group-body.is-open { max-height: 2200px; }

    .cs-sub {
        display: flex; align-items: center;
        padding: 6px 12px 6px 44px;
        margin: 1px 0;
        border-radius: 8px;
        font-size: 12.5px;
        color: #94A3B8;
        text-decoration: none;
        position: relative;
        transition: background 0.15s, color 0.15s;
    }
    .cs-sub::before {
        content: "";
        position: absolute;
        inset-inline-start: 24px;
        top: 50%; transform: translateY(-50%);
        width: 4px; height: 4px;
        border-radius: 50%;
        background: currentColor; opacity: 0.4;
    }
    .cs-sub:hover {
        background: rgba(255, 255, 255, 0.04);
        color: #F1F5F9; text-decoration: none;
    }
    .cs-sub.is-active {
        background: rgba(59, 130, 246, 0.14);
        color: #fff; font-weight: 500;
    }
    .cs-sub.is-active::before {
        opacity: 1; background: #3B82F6;
        box-shadow: 0 0 6px rgba(59, 130, 246, 0.6);
    }
    .cs-sub:focus-visible { outline: 2px solid #3B82F6; outline-offset: 2px; }

    .cs-user {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 14px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }
    .cs-avatar {
        width: 34px; height: 34px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3B82F6, #8B5CF6);
        color: #fff;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 600;
        flex-shrink: 0;
    }
    .cs-user-meta { flex: 1; min-width: 0; }
    .cs-user-name {
        font-size: 12.5px; font-weight: 500; color: #E2E8F0;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .cs-user-role {
        font-size: 10.5px; color: #64748B;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    @media (max-width: 768px) {
        .cs-link, .cs-group-btn, .cs-sub { min-height: 44px; }
        .cs-brand-toggle { width: 44px; height: 44px; }
    }

    /* Desktop offset — v2 owns this, Metronic's aside-minimize is ignored.
       At ≥992px, .wrapper/.subheader/.header always sit at 265px from the
       left (or 72px when collapsed). Mobile (<992px) is handled below
       with a slide-in drawer; no offset needed there. */
    @media (min-width: 992px) {
        body.aside-minimize .cs-sidebar { width: 265px; }
        body .wrapper { padding-left: 265px !important; }
        body .subheader { left: 265px !important; }
        body .header { left: 265px !important; }
        body .footer-fixed .footer { left: 265px !important; }

        body.cs-collapsed .cs-sidebar { width: 72px; }
        body.cs-collapsed .wrapper { padding-left: 72px !important; }
        body.cs-collapsed .subheader { left: 72px !important; }
        body.cs-collapsed .header { left: 72px !important; }
        body.cs-collapsed .footer-fixed .footer { left: 72px !important; }
    }
    body.cs-collapsed .cs-label,
    body.cs-collapsed .cs-chev,
    body.cs-collapsed .cs-group-body,
    body.cs-collapsed .cs-search,
    body.cs-collapsed .cs-kbd,
    body.cs-collapsed .cs-brand-logo,
    body.cs-collapsed .cs-user-meta,
    body.cs-collapsed .cs-subsection {
        display: none;
    }
    body.cs-collapsed .cs-link,
    body.cs-collapsed .cs-group-btn {
        justify-content: center; padding: 12px;
    }
    body.cs-collapsed .cs-icon { margin-inline-end: 0; }
    body.cs-collapsed .cs-brand { justify-content: center; padding: 20px 10px; }
    body.cs-collapsed .cs-brand-toggle svg { transform: rotate(180deg); }
    body.cs-collapsed .cs-user { justify-content: center; padding: 12px 8px; }

    /* Mobile drawer — sidebar slides off-canvas, content stays flush left */
    @media (max-width: 991.98px) {
        .cs-sidebar {
            transform: translate3d(-100%, 0, 0);
            transition: transform 0.25s ease;
            box-shadow: 8px 0 32px rgba(0, 0, 0, 0.35);
        }
        body.aside-on .cs-sidebar { transform: translate3d(0, 0, 0); }
        body.cs-collapsed .cs-sidebar { width: 265px; }
        body .wrapper { padding-left: 0 !important; }
        body .subheader { left: 0 !important; }
        body .header { left: 0 !important; }
    }

    @media (prefers-reduced-motion: reduce) {
        .cs-sidebar, .cs-group-body, .cs-chev,
        .cs-link, .cs-group-btn, .cs-sub { transition: none; }
    }
</style>

<!--begin::Sidebar v2-->
<aside x-data="sidebarV2({ activeGroup: @js($activeGroup) })"
       class="cs-sidebar"
       id="kt_aside"
       aria-label="Primary navigation">

    {{-- ── Brand ─────────────────────────────────────────────────────── --}}
    <div class="cs-brand">
        <a href="{{ route('admin.home') }}" class="cs-brand-logo" aria-label="Cutera home">
            <img alt="Cutera" src="{{ asset('logo.png') }}" />
        </a>
        <button type="button"
                class="cs-brand-toggle"
                id="kt_aside_toggle"
                :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                :aria-pressed="collapsed"
                @click.prevent.stop="setCollapsed(!collapsed)">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>
    </div>

    {{-- ── Search (⌘K) ───────────────────────────────────────────────── --}}
    <div class="cs-search">
        <label class="cs-search-field" aria-label="Search menu">
            <i class="la la-search cs-search-icon" aria-hidden="true"></i>
            <input type="search"
                   placeholder="Search menu…"
                   x-ref="search"
                   @input.debounce.100="filter($event.target.value)"
                   @keydown.escape="$event.target.value=''; filter('')" />
            <span class="cs-kbd" aria-hidden="true">⌘K</span>
        </label>
    </div>

    {{-- ── Menu ──────────────────────────────────────────────────────── --}}
    <nav class="cs-menu" id="kt_aside_menu" aria-label="Main menu">

        {{-- Dashboard — only always-visible destination --}}
        <a href="{{ route('admin.home') }}" class="cs-link {{ $isActive(['admin.home']) }}" title="Dashboard">
            <span class="cs-icon"><i class="la la-home"></i></span>
            <span class="cs-label">Dashboard</span>
        </a>

        {{-- ══════════════════════════════════════════════════════════════
             CLINIC — patient journey
             ══════════════════════════════════════════════════════════════ --}}
        @if (Gate::allows('patients.list.view')
            || Gate::allows('leads.list.view')
            || Gate::allows('consultations_manage')
            || Gate::allows('treatments_manage')
            || Gate::allows('scheduling_shifts.list.view')
            || Gate::allows('business_closures.list.view'))
            <div>
                <button type="button"
                        class="cs-group-btn"
                        :aria-expanded="openGroup === 'clinic'"
                        @click="toggle('clinic')"
                        title="Clinic">
                    <span class="cs-icon"><i class="la la-user-md"></i></span>
                    <span class="cs-label">Clinic</span>
                    <i class="la la-angle-right cs-chev" aria-hidden="true"></i>
                </button>
                <div class="cs-group-body" :class="{ 'is-open': openGroup === 'clinic' }">
                    @can('patients.list.view')
                        <a href="{{ route('admin.patients.index') }}"
                           class="cs-sub {{ $isActive(['admin.patients.index', 'admin.patients.preview']) }}"><span>Patients</span></a>
                    @endcan
                    @can('leads.list.view')
                        {{-- Single Leads entry — Create is reachable from the leads
                             page, and Junk is a status filter on the same list. --}}
                        <a href="{{ route('admin.leads.index') }}"
                           class="cs-sub {{ $isActive(['admin.leads.index']) }}"><span>Leads</span></a>
                    @endcan
                    @can('consultations_manage')
                        <a href="{{ route('admin.consultancy.index') }}"
                           class="cs-sub {{ $isActive(['admin.consultancy.index']) }}"><span>Consultancies</span></a>
                    @endcan
                    @can('treatments_manage')
                        <a href="{{ route('admin.treatment.index') }}"
                           class="cs-sub {{ $isActive(['admin.treatment.index']) }}"><span>Treatments</span></a>
                    @endcan
                    @can('scheduling_shifts.list.view')
                        <a href="{{ route('admin.resourcerotas.schedule') }}"
                           class="cs-sub {{ $isActive(['admin.resourcerotas.schedule', 'admin.resourcerotas.repeating-shifts']) }}"><span>Scheduling Shifts</span></a>
                    @endcan
                    @can('business_closures.list.view')
                        <a href="{{ route('admin.business-closures.index') }}"
                           class="cs-sub {{ $isActive(['admin.business-closures.index']) }}"><span>Business Closed Periods</span></a>
                    @endcan
                </div>
            </div>
        @endif

        {{-- ══════════════════════════════════════════════════════════════
             CATALOG — what you sell
             ══════════════════════════════════════════════════════════════ --}}
        @if (Gate::allows('services.list.view')
            || Gate::allows('packages.list.view')
            || Gate::allows('bundles.list.view')
            || Gate::allows('discounts_manage')
            || Gate::allows('plans.list.view')
            || Gate::allows('voucher_types_manage')
            || Gate::allows('vouchers_manage')
            || Gate::allows('memberships_manage')
            || Gate::allows('membershiptypes_manage'))
            <div>
                <button type="button"
                        class="cs-group-btn"
                        :aria-expanded="openGroup === 'catalog'"
                        @click="toggle('catalog')"
                        title="Catalog">
                    <span class="cs-icon"><i class="la la-layer-group"></i></span>
                    <span class="cs-label">Catalog</span>
                    <i class="la la-angle-right cs-chev" aria-hidden="true"></i>
                </button>
                <div class="cs-group-body" :class="{ 'is-open': openGroup === 'catalog' }">
                    @can('services.list.view')
                        <a href="{{ route('admin.services.index') }}"
                           class="cs-sub {{ $isActive(['admin.services.index']) }}"><span>Services</span></a>
                    @endcan
                    @can('bundles.list.view')
                        <a href="{{ route('admin.service-bundles.index') }}"
                           class="cs-sub {{ $isActive(['admin.service-bundles.index']) }}"><span>Bundles</span></a>
                    @endcan
                    @can('packages.list.view')
                        <a href="{{ route('admin.bundles.index') }}"
                           class="cs-sub {{ $isActive(['admin.bundles.index']) }}"><span>Packages</span></a>
                    @endcan
                    @can('plans.list.view')
                        <a href="{{ route('admin.packages.index') }}"
                           class="cs-sub {{ $isActive(['admin.packages.index']) }}"><span>@lang('global.packages.title')</span></a>
                    @endcan
                    @can('discounts_manage')
                        <a href="{{ route('admin.discounts.index') }}"
                           class="cs-sub {{ $isActive(['admin.discounts.index']) }}"><span>Discounts</span></a>
                    @endcan
                    @can('voucher_types_manage')
                        <a href="{{ route('admin.voucherTypes.index') }}"
                           class="cs-sub {{ $isActive(['admin.voucherTypes.index']) }}"><span>Voucher Types</span></a>
                    @endcan
                    @can('vouchers_manage')
                        <a href="{{ route('admin.vouchers.index') }}"
                           class="cs-sub {{ $isActive(['admin.vouchers.index']) }}"><span>Vouchers</span></a>
                    @endcan
                    @can('membershiptypes_manage')
                        <a href="{{ route('admin.membershiptypes.index') }}"
                           class="cs-sub {{ $isActive(['admin.membershiptypes.index']) }}"><span>Membership Types</span></a>
                    @endcan
                    @can('memberships_manage')
                        <a href="{{ route('admin.memberships.index') }}"
                           class="cs-sub {{ $isActive(['admin.memberships.index']) }}"><span>Memberships</span></a>
                    @endcan
                </div>
            </div>
        @endif

        {{-- ══════════════════════════════════════════════════════════════
             FINANCE — invoices, refunds, advances, payment modes, cash flow
             ══════════════════════════════════════════════════════════════ --}}
        @if (Gate::allows('invoices_manage')
            || Gate::allows('refunds_manage')
            || Gate::allows('finances_manage')
            || Gate::allows('payment_modes_manage')
            || Gate::allows('cashflow_manage'))
            <div>
                <button type="button"
                        class="cs-group-btn"
                        :aria-expanded="openGroup === 'finance'"
                        @click="toggle('finance')"
                        title="Finance">
                    <span class="cs-icon"><i class="la la-wallet"></i></span>
                    <span class="cs-label">Finance</span>
                    <i class="la la-angle-right cs-chev" aria-hidden="true"></i>
                </button>
                <div class="cs-group-body" :class="{ 'is-open': openGroup === 'finance' }">
                    @can('invoices_manage')
                        <a href="{{ route('admin.invoices.index') }}"
                           class="cs-sub {{ $isActive(['admin.invoices.index']) }}"><span>Invoices</span></a>
                    @endcan
                    @can('refunds_manage')
                        <a href="{{ route('admin.refunds.index') }}"
                           class="cs-sub {{ $isActive(['admin.refunds.index']) }}"><span>Refunds</span></a>
                    @endcan
                    @can('finances_manage')
                        <a href="{{ route('admin.packagesadvances.index') }}"
                           class="cs-sub {{ $isActive(['admin.packagesadvances.index']) }}"><span>Advances</span></a>
                    @endcan
                    @can('payment_modes_manage')
                        <a href="{{ route('admin.payment_modes.index') }}"
                           class="cs-sub {{ $isActive(['admin.payment_modes.index', 'admin.payment_modes.sort']) }}"><span>Payment Modes</span></a>
                    @endcan
                    @can('cashflow_manage')
                        <div class="cs-subsection">Cash Flow</div>
                        @can('cashflow_dashboard')
                            <a href="{{ route('admin.cashflow.dashboard') }}"
                               class="cs-sub {{ $isActive(['admin.cashflow.dashboard']) }}"><span>Dashboard</span></a>
                        @endcan
                        @canany(['cashflow_expense_view', 'cashflow_expense_create', 'cashflow_expense_approve', 'cashflow_expense_reject', 'cashflow_expense_edit', 'cashflow_expense_void'])
                            <a href="{{ route('admin.cashflow.expenses') }}"
                               class="cs-sub {{ $isActive(['admin.cashflow.expenses']) }}"><span>Expenses</span></a>
                        @endcanany
                        @canany(['cashflow_transfer_view', 'cashflow_transfer_create', 'cashflow_transfer_edit', 'cashflow_transfer_void'])
                            <a href="{{ route('admin.cashflow.transfers') }}"
                               class="cs-sub {{ $isActive(['admin.cashflow.transfers']) }}"><span>Transfers</span></a>
                        @endcanany
                        @canany(['cashflow_vendor_view', 'cashflow_vendor_create', 'cashflow_vendor_edit', 'cashflow_vendor_manage', 'cashflow_vendor_ledger_view', 'cashflow_vendor_transaction'])
                            <a href="{{ route('admin.cashflow.vendors') }}"
                               class="cs-sub {{ $isActive(['admin.cashflow.vendors']) }}"><span>Vendors</span></a>
                        @endcanany
                        @canany(['cashflow_staff_advance_view', 'cashflow_staff_advance_create', 'cashflow_staff_advance_edit', 'cashflow_staff_advance_void', 'cashflow_staff_return_create', 'cashflow_staff_return_void'])
                            <a href="{{ route('admin.cashflow.staff') }}"
                               class="cs-sub {{ $isActive(['admin.cashflow.staff']) }}"><span>Staff Advances</span></a>
                        @endcanany
                        @can('cashflow_fdm_view')
                            <a href="{{ route('admin.cashflow.fdm') }}"
                               class="cs-sub {{ $isActive(['admin.cashflow.fdm']) }}"><span>FDM View</span></a>
                        @endcan
                        @can('cashflow_reports')
                            <a href="{{ route('admin.cashflow.reports') }}"
                               class="cs-sub {{ $isActive(['admin.cashflow.reports']) }}"><span>Reports</span></a>
                        @endcan
                        @can('cashflow_settings')
                            <a href="{{ route('admin.cashflow.settings') }}"
                               class="cs-sub {{ $isActive(['admin.cashflow.settings']) }}"><span>Settings</span></a>
                        @endcan
                    @endcan
                </div>
            </div>
        @endif

        {{-- ══════════════════════════════════════════════════════════════
             INVENTORY
             ══════════════════════════════════════════════════════════════ --}}
        @if (Gate::allows('inventory_manage'))
            <div>
                <button type="button"
                        class="cs-group-btn"
                        :aria-expanded="openGroup === 'inventory'"
                        @click="toggle('inventory')"
                        title="Inventory">
                    <span class="cs-icon"><i class="la la-warehouse"></i></span>
                    <span class="cs-label">Inventory</span>
                    <i class="la la-angle-right cs-chev" aria-hidden="true"></i>
                </button>
                <div class="cs-group-body" :class="{ 'is-open': openGroup === 'inventory' }">
                    @can('brand_manage')
                        <a href="{{ route('admin.brands.index') }}"
                           class="cs-sub {{ $isActive(['admin.brands.index']) }}"><span>Brand</span></a>
                    @endcan
                    @can('product_manage')
                        <a href="{{ route('admin.products.index') }}"
                           class="cs-sub {{ $isActive(['admin.products.index']) }}"><span>Product</span></a>
                    @endcan
                    @can('product_manage')
                        <a href="{{ route('admin.transfer_product.index') }}"
                           class="cs-sub {{ $isActive(['admin.transfer_product.index']) }}"><span>Transfer</span></a>
                    @endcan
                    @can('order_manage')
                        <a href="{{ route('admin.orders.index') }}"
                           class="cs-sub {{ $isActive(['admin.orders.index']) }}"><span>Order</span></a>
                    @endcan
                </div>
            </div>
        @endif

        {{-- ══════════════════════════════════════════════════════════════
             REPORTS — analytics hub + management dashboard + doctor ratings
             ══════════════════════════════════════════════════════════════ --}}
        @if ($canSeeReportsMenu
            || Gate::allows('management_dashboard.view')
            || Gate::allows('feedbacks_manage'))
            <div>
                <button type="button"
                        class="cs-group-btn"
                        :aria-expanded="openGroup === 'reports'"
                        @click="toggle('reports')"
                        title="Reports">
                    <span class="cs-icon"><i class="la la-chart-bar"></i></span>
                    <span class="cs-label">Reports</span>
                    <i class="la la-angle-right cs-chev" aria-hidden="true"></i>
                </button>
                <div class="cs-group-body" :class="{ 'is-open': openGroup === 'reports' }">
                    @can('management_dashboard.view')
                        <a href="{{ route('admin.management_dashboard.overview') }}"
                           class="cs-sub {{ $isActive(['admin.management_dashboard.overview', 'admin.management_dashboard.people']) }}"><span>Management Dashboard</span></a>
                    @endcan
                    @can('feedbacks_manage')
                        <a href="{{ route('admin.feedbacks.index') }}"
                           class="cs-sub {{ $isActive(['admin.feedbacks.index']) }}"><span>Doctor Ratings</span></a>
                    @endcan
                    @if ($canSeeReportsMenu)
                        @can('finance_general_revenue_reports_manage')
                            <a href="{{ route('admin.reports.finance_reports') }}"
                               class="cs-sub {{ $isActive(['admin.reports.finance_reports']) }}"><span>General Sales Report</span></a>
                        @endcan
                        @can('operations_reports_operations_tax_calculation_report')
                            <a href="{{ route('admin.reports.tax_calculation_report') }}"
                               class="cs-sub {{ $isActive(['admin.reports.tax_calculation_report']) }}"><span>Tax Calculation Report</span></a>
                        @endcan
                        @can('operations_reports_manage')
                            <a href="{{ route('admin.reports.operations_report') }}"
                               class="cs-sub {{ $isActive(['admin.reports.operations_report']) }}"><span>Operation Reports</span></a>
                        @endcan
                        @can('operations_reports_manage')
                            <a href="{{ route('admin.reports.membership-reports') }}"
                               class="cs-sub {{ $isActive(['admin.reports.membership-reports']) }}"><span>Membership Reports</span></a>
                        @endcan
                        @can('operations_reports_manage')
                            <a href="{{ route('admin.reports.doctorWiseConversion') }}"
                               class="cs-sub {{ $isActive(['admin.reports.doctorWiseConversion']) }}"><span>Doctor Incentive Report</span></a>
                        @endcan
                        @can('appointment_reports_manage')
                            <a href="{{ route('admin.reports.appointmentsReport') }}"
                               class="cs-sub {{ $isActive(['admin.reports.appointmentsReport']) }}"><span>Appointments Report</span></a>
                        @endcan
                        @can('csr_dashboard_report')
                            <a href="{{ route('admin.reports.csr_dashboard') }}"
                               class="cs-sub {{ $isActive(['admin.reports.csr_dashboard']) }}"><span>CSR Dashboard</span></a>
                        @endcan
                        @can('non_converted_customers_manage')
                            <a href="{{ route('admin.reports.arrived_not_converted') }}"
                               class="cs-sub {{ $isActive(['admin.reports.arrived_not_converted']) }}"><span>Non-Converted Customer Report</span></a>
                        @endcan
                        @can('conversion_report_manage')
                            <a href="{{ route('admin.reports.conversion') }}"
                               class="cs-sub {{ $isActive(['admin.reports.conversion']) }}"><span>Conversion Report</span></a>
                        @endcan
                        @can('appointment_reports_manage')
                            <a href="{{ route('admin.reports.activity_logs') }}"
                               class="cs-sub {{ $isActive(['admin.reports.activity_logs']) }}"><span>Activity Logs</span></a>
                        @endcan
                        @can('staff_wise_arrival_manage')
                            <a href="{{ route('admin.reports.staff_wise_arrival') }}"
                               class="cs-sub {{ $isActive(['admin.reports.staff_wise_arrival']) }}"><span>Staff Wise Arrival Report</span></a>
                        @endcan
                        @can('follow_up_manage')
                            <a href="{{ route('admin.reports.follow_up') }}"
                               class="cs-sub {{ $isActive(['admin.reports.follow_up']) }}"><span>Follow Up Report</span></a>
                        @endcan
                        @can('inventory_report_manage')
                            <a href="{{ route('admin.reports.inventory_report') }}"
                               class="cs-sub {{ $isActive(['admin.reports.inventory_report']) }}"><span>Inventory Report</span></a>
                        @endcan
                        @can('feedbacks_manage')
                            <a href="{{ route('admin.reports.feedback_report') }}"
                               class="cs-sub {{ $isActive(['admin.reports.feedback_report']) }}"><span>Doctor Ratings Report</span></a>
                        @endcan
                        @can('followuppatient_manage')
                            <a href="{{ route('admin.reports.future_treatments') }}"
                               class="cs-sub {{ $isActive(['admin.reports.future_treatments']) }}"><span>Future Treatments Report</span></a>
                        @endcan
                        @can('upselling_report')
                            <a href="{{ route('admin.reports.upselling') }}"
                               class="cs-sub {{ $isActive(['admin.reports.upselling']) }}"><span>Doctors Upselling Report</span></a>
                        @endcan
                        @can('consultant_revenue_report')
                            <a href="{{ route('admin.reports.consultant_revenue') }}"
                               class="cs-sub {{ $isActive(['admin.reports.consultant_revenue']) }}"><span>Consultants Revenue Report</span></a>
                        @endcan
                        @can('consultant_revenue_report')
                            <a href="{{ route('admin.reports.doctor_revenue') }}"
                               class="cs-sub {{ $isActive(['admin.reports.doctor_revenue']) }}"><span>Doctor Revenue Report</span></a>
                        @endcan
                    @endif
                </div>
            </div>
        @endif

        {{-- ══════════════════════════════════════════════════════════════
             PEOPLE — HR + Access in one group with sub-section dividers
             ══════════════════════════════════════════════════════════════ --}}
        @if (Gate::allows('hr_dashboard_view')
            || Gate::allows('hr_employees_view')
            || Gate::allows('hr_departments_view')
            || Gate::allows('hr_designations_view')
            || Gate::allows('hr_leave_view')
            || Gate::allows('hr_leave_approve')
            || Gate::allows('hr_recruitment_view')
            || Gate::allows('permissions_manage')
            || Gate::allows('roles_manage')
            || Gate::allows('users_manage')
            || Gate::allows('user_types_manage')
            || Gate::allows('doctors_manage')
            || Gate::allows('centre_targets_manage'))
            <div>
                <button type="button"
                        class="cs-group-btn"
                        :aria-expanded="openGroup === 'people'"
                        @click="toggle('people')"
                        title="People">
                    <span class="cs-icon"><i class="la la-users"></i></span>
                    <span class="cs-label">People</span>
                    <i class="la la-angle-right cs-chev" aria-hidden="true"></i>
                </button>
                <div class="cs-group-body" :class="{ 'is-open': openGroup === 'people' }">
                    @if (Gate::allows('hr_dashboard_view')
                        || Gate::allows('hr_employees_view')
                        || Gate::allows('hr_departments_view')
                        || Gate::allows('hr_designations_view')
                        || Gate::allows('hr_leave_view')
                        || Gate::allows('hr_leave_approve')
                        || Gate::allows('hr_recruitment_view'))
                        <div class="cs-subsection">HR</div>
                        @can('hr_dashboard_view')
                            <a href="{{ route('admin.hr.dashboard') }}"
                               class="cs-sub {{ $isActive(['admin.hr.dashboard']) }}"><span>Dashboard</span></a>
                        @endcan
                        @can('hr_employees_view')
                            <a href="{{ route('admin.hr.employees.index') }}"
                               class="cs-sub {{ $isActive(['admin.hr.employees.index']) }}"><span>Employees</span></a>
                        @endcan
                        @can('hr_departments_view')
                            <a href="{{ route('admin.hr.departments.index') }}"
                               class="cs-sub {{ $isActive(['admin.hr.departments.index']) }}"><span>Departments</span></a>
                        @endcan
                        @can('hr_designations_view')
                            <a href="{{ route('admin.hr.designations.index') }}"
                               class="cs-sub {{ $isActive(['admin.hr.designations.index']) }}"><span>Designations</span></a>
                        @endcan
                        @can('hr_leave_view')
                            <a href="{{ route('admin.hr.leave-types.index') }}"
                               class="cs-sub {{ $isActive(['admin.hr.leave-types.index']) }}"><span>Leave Types</span></a>
                            <a href="{{ route('admin.hr.leave-balances.index') }}"
                               class="cs-sub {{ $isActive(['admin.hr.leave-balances.index']) }}"><span>Leave Balances</span></a>
                            <a href="{{ route('admin.hr.leave-applications.index') }}"
                               class="cs-sub {{ $isActive(['admin.hr.leave-applications.index']) }}"><span>Leave Applications</span></a>
                        @endcan
                        @can('hr_leave_view')
                            <a href="{{ route('admin.hr.leave-applications.calendar') }}"
                               class="cs-sub {{ $isActive(['admin.hr.leave-applications.calendar']) }}"><span>Leave Calendar</span></a>
                        @endcan
                        @can('hr_recruitment_view')
                            <a href="{{ route('admin.hr.recruitment.index') }}"
                               class="cs-sub {{ $isActive(['admin.hr.recruitment.index']) }}"><span>Recruitment</span></a>
                        @endcan
                        @can('hr_employees_view')
                            <a href="{{ route('admin.hr.reports.celebrations') }}"
                               class="cs-sub {{ $isActive(['admin.hr.reports.celebrations']) }}"><span>Celebrations</span></a>
                        @endcan
                    @endif

                    @if (Gate::allows('permissions_manage')
                        || Gate::allows('roles_manage')
                        || Gate::allows('users_manage')
                        || Gate::allows('user_types_manage')
                        || Gate::allows('doctors_manage')
                        || Gate::allows('centre_targets_manage'))
                        <div class="cs-subsection">Access</div>
                        @can('users_manage')
                            <a href="{{ route('admin.users.index') }}"
                               class="cs-sub {{ $isActive(['admin.users.index']) }}"><span>Application Users</span></a>
                        @endcan
                        @can('roles_manage')
                            <a href="{{ route('admin.roles.index') }}"
                               class="cs-sub {{ $isActive(['admin.roles.index', 'admin.roles.edit']) }}"><span>Roles</span></a>
                        @endcan
                        @can('permissions_manage')
                            <a href="{{ route('admin.permissions.index') }}"
                               class="cs-sub {{ $isActive(['admin.permissions.index']) }}"><span>Permissions</span></a>
                        @endcan
                        @can('user_types_manage')
                            <a href="{{ route('admin.user_types.index') }}"
                               class="cs-sub {{ $isActive(['admin.user_types.index']) }}"><span>User Types</span></a>
                        @endcan
                        @can('doctors_manage')
                            <a href="{{ route('admin.doctors.index') }}"
                               class="cs-sub {{ $isActive(['admin.doctors.index']) }}"><span>Doctors</span></a>
                        @endcan
                        @can('centre_targets_manage')
                            <a href="{{ route('admin.centre_targets.index') }}"
                               class="cs-sub {{ $isActive(['admin.centre_targets.index']) }}"><span>Centre Targets</span></a>
                        @endcan
                    @endif
                </div>
            </div>
        @endif

        {{-- ══════════════════════════════════════════════════════════════
             SETTINGS — General / Locations / Taxonomies / Forms
             ══════════════════════════════════════════════════════════════ --}}
        @if (Gate::allows('settings_manage')
            || Gate::allows('user_operator_settings_manage')
            || Gate::allows('sms_templates_manage')
            || Gate::allows('regions_manage')
            || Gate::allows('cities_manage')
            || Gate::allows('custom_forms_manage')
            || Gate::allows('custom_form_feedbacks_manage')
            || Gate::allows('locations_manage')
            || Gate::allows('lead_sources_manage')
            || Gate::allows('lead_statuses_manage')
            || Gate::allows('appointment_statuses_manage')
            || Gate::allows('cancellation_reasons_manage')
            || Gate::allows('resources_manage')
            || Gate::allows('logs_manage')
            || Gate::allows('machineType_manage')
            || Gate::allows('towns_manage')
            || Gate::allows('google_reviews_manage'))
            <div>
                <button type="button"
                        class="cs-group-btn"
                        :aria-expanded="openGroup === 'settings'"
                        @click="toggle('settings')"
                        title="Settings">
                    <span class="cs-icon"><i class="la la-cog"></i></span>
                    <span class="cs-label">Settings</span>
                    <i class="la la-angle-right cs-chev" aria-hidden="true"></i>
                </button>
                <div class="cs-group-body" :class="{ 'is-open': openGroup === 'settings' }">
                    @if (Gate::allows('settings_manage')
                        || Gate::allows('user_operator_settings_manage')
                        || Gate::allows('sms_templates_manage')
                        || Gate::allows('google_reviews_manage'))
                        <div class="cs-subsection">General</div>
                        @can('settings_manage')
                            <a href="{{ route('admin.settings.index') }}"
                               class="cs-sub {{ $isActive(['admin.settings.index']) }}"><span>Global Settings</span></a>
                        @endcan
                        @can('user_operator_settings_manage')
                            <a href="{{ route('admin.user_operator_settings.index') }}"
                               class="cs-sub {{ $isActive(['admin.user_operator_settings.index']) }}"><span>Operator Settings</span></a>
                        @endcan
                        @can('sms_templates_manage')
                            <a href="{{ route('admin.sms_templates.index') }}"
                               class="cs-sub {{ $isActive(['admin.sms_templates.index']) }}"><span>SMS Templates</span></a>
                        @endcan
                        @can('google_reviews_manage')
                            <a href="{{ route('admin.google_reviews.index') }}"
                               class="cs-sub {{ $isActive(['admin.google_reviews.index']) }}"><span>Google Reviews</span></a>
                        @endcan
                    @endif

                    @if (Gate::allows('regions_manage')
                        || Gate::allows('cities_manage')
                        || Gate::allows('towns_manage')
                        || Gate::allows('locations_manage'))
                        <div class="cs-subsection">Locations</div>
                        @can('regions_manage')
                            <a href="{{ route('admin.regions.index') }}"
                               class="cs-sub {{ $isActive(['admin.regions.index', 'admin.regions.sort']) }}"><span>Regions</span></a>
                        @endcan
                        @can('cities_manage')
                            <a href="{{ route('admin.cities.index') }}"
                               class="cs-sub {{ $isActive(['admin.cities.index', 'admin.cities.sort']) }}"><span>Cities</span></a>
                        @endcan
                        @can('towns_manage')
                            <a href="{{ route('admin.towns.index') }}"
                               class="cs-sub {{ $isActive(['admin.towns.index']) }}"><span>Towns</span></a>
                        @endcan
                        @can('locations_manage')
                            <a href="{{ route('admin.locations.index') }}"
                               class="cs-sub {{ $isActive(['admin.locations.index']) }}"><span>Centres</span></a>
                        @endcan
                    @endif

                    @if (Gate::allows('lead_sources_manage')
                        || Gate::allows('lead_statuses_manage')
                        || Gate::allows('appointment_statuses_manage')
                        || Gate::allows('machineType_manage')
                        || Gate::allows('resources_manage'))
                        <div class="cs-subsection">Taxonomies</div>
                        @can('lead_sources_manage')
                            <a href="{{ route('admin.lead_sources.index') }}"
                               class="cs-sub {{ $isActive(['admin.lead_sources.index', 'admin.lead_sources.sort']) }}"><span>Lead Sources</span></a>
                        @endcan
                        @can('lead_statuses_manage')
                            <a href="{{ route('admin.lead_statuses.index') }}"
                               class="cs-sub {{ $isActive(['admin.lead_statuses.index', 'admin.lead_statuses.sort']) }}"><span>Lead Statuses</span></a>
                        @endcan
                        @can('appointment_statuses_manage')
                            <a href="{{ route('admin.appointment_statuses.index') }}"
                               class="cs-sub {{ $isActive(['admin.appointment_statuses.index']) }}"><span>Appointment Statuses</span></a>
                        @endcan
                        @can('machineType_manage')
                            <a href="{{ route('admin.machine_types.index') }}"
                               class="cs-sub {{ $isActive(['admin.machine_types.index']) }}"><span>Machine Type</span></a>
                        @endcan
                        @can('resources_manage')
                            <a href="{{ route('admin.resources.index') }}"
                               class="cs-sub {{ $isActive(['admin.resources.index']) }}"><span>Resource</span></a>
                        @endcan
                    @endif

                    @if (Gate::allows('custom_forms_manage') || Gate::allows('custom_form_feedbacks_manage'))
                        <div class="cs-subsection">Forms</div>
                        @can('custom_forms_manage')
                            <a href="{{ route('admin.custom_forms.index') }}"
                               class="cs-sub {{ $isActive(['admin.custom_forms.index']) }}"><span>Custom Forms</span></a>
                        @endcan
                        @can('custom_form_feedbacks_manage')
                            <a href="{{ route('admin.custom_form_feedbacks.index') }}"
                               class="cs-sub {{ $isActive(['admin.custom_form_feedbacks.index', 'admin.custom_form_feedbacks.filled_preview']) }}"><span>Custom Form Feedbacks</span></a>
                        @endcan
                    @endif
                </div>
            </div>
        @endif

    </nav>

    @auth
        @php
            $authUser = auth()->user();
            $initials = collect(explode(' ', trim($authUser->name ?? '')))
                ->filter()
                ->take(2)
                ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                ->implode('') ?: 'U';
            $roleLabel = method_exists($authUser, 'getRoleNames')
                ? ($authUser->getRoleNames()->first() ?? 'Member')
                : 'Member';
        @endphp
        <div class="cs-user">
            <div class="cs-avatar" aria-hidden="true">{{ $initials }}</div>
            <div class="cs-user-meta">
                <div class="cs-user-name">{{ $authUser->name ?? 'User' }}</div>
                <div class="cs-user-role">{{ $roleLabel }}</div>
            </div>
        </div>
    @endauth
</aside>
<!--end::Sidebar v2-->

@push('js')
{{-- Alpine.js CDN — CUTERA-REVIEW: self-host with SRI before prod enable. --}}
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" crossorigin="anonymous"></script>
<script>
    window.sidebarV2 = function (opts) {
        const activeGroupFromRoute = (opts && opts.activeGroup) ? opts.activeGroup : '';
        return {
            openGroup: activeGroupFromRoute,
            collapsed: false,
            setCollapsed(val) {
                this.collapsed = !!val;
                document.body.classList.toggle('cs-collapsed', this.collapsed);
                try {
                    localStorage.setItem('sv2.collapsed', this.collapsed ? '1' : '0');
                } catch (e) { /* ignore */ }
            },
            init() {
                // Start with every group CLOSED on fresh visits. Only auto-open
                // the group that owns the current route (deep-linking should
                // reveal the parent so the user sees where they are).
                if (!activeGroupFromRoute) {
                    this.openGroup = '';
                }
                try {
                    if (localStorage.getItem('sv2.collapsed') === '1') {
                        this.setCollapsed(true);
                    }
                } catch (e) { /* ignore */ }
                this._onKey = (e) => {
                    const key = (e.key || '').toLowerCase();
                    if ((e.metaKey || e.ctrlKey) && key === 'k') {
                        e.preventDefault();
                        this.$refs.search?.focus();
                    }
                };
                window.addEventListener('keydown', this._onKey);
            },
            destroy() {
                if (this._onKey) window.removeEventListener('keydown', this._onKey);
            },
            toggle(key) {
                this.openGroup = this.openGroup === key ? '' : key;
            },
            filter(raw) {
                const q = (raw || '').toLowerCase().trim();
                const root = this.$root;

                root.querySelectorAll('.cs-link, .cs-sub').forEach((el) => {
                    const txt = (el.textContent || '').toLowerCase();
                    el.style.display = !q || txt.includes(q) ? '' : 'none';
                });

                root.querySelectorAll('.cs-group-btn').forEach((btn) => {
                    const body = btn.nextElementSibling;
                    const visible = body ? body.querySelectorAll('.cs-sub:not([style*="none"])') : [];
                    btn.style.display = !q || visible.length > 0 ? '' : 'none';
                    if (body) {
                        if (q && visible.length > 0) body.classList.add('is-open');
                        else if (!q) body.classList.remove('is-open');
                    }
                });

                // Hide subsection labels whose items are all filtered out.
                root.querySelectorAll('.cs-subsection').forEach((sub) => {
                    if (!q) { sub.style.display = ''; return; }
                    let el = sub.nextElementSibling;
                    let any = false;
                    while (el && !el.classList.contains('cs-subsection')) {
                        if (el.matches && el.matches('a.cs-sub') && el.style.display !== 'none') {
                            any = true; break;
                        }
                        el = el.nextElementSibling;
                    }
                    sub.style.display = any ? '' : 'none';
                });

                if (!q) {
                    this.openGroup = activeGroupFromRoute || '';
                }
            },
        };
    };
</script>
@endpush
