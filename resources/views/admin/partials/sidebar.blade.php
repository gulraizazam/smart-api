@php
    use Illuminate\Support\Facades\Gate;

    $sidebarHasAny = static fn (array $perms): bool => collect($perms)->contains(static fn ($p) => Gate::allows($p));

    $sidebarOpsPerms = ['patients_manage','leads_manage','consultations_manage','treatments_manage','resourcerotas_manage','business_closures_manage','feedbacks_manage'];
    $sidebarSalesPerms = ['plans_manage','refunds_manage','services_manage','packages_manage','discounts_manage','vouchers_manage','voucher_types_manage','memberships_manage','membershiptypes_manage'];
    $sidebarHrPerms = ['hr_dashboard_view','hr_employees_view','hr_departments_view','hr_designations_view','hr_leave_view','hr_leave_approve','hr_recruitment_view'];
    $sidebarReportsPerms = [
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
    $sidebarAdminPerms = [
        'users_manage','permissions_manage','roles_manage','user_types_manage','doctors_manage',
        'settings_manage','user_operator_settings_manage','sms_templates_manage','regions_manage','cities_manage',
        'payment_modes_manage','custom_forms_manage','custom_form_feedbacks_manage','locations_manage','staff_targets_manage',
        'centre_targets_manage','lead_sources_manage','lead_statuses_manage','appointment_statuses_manage','cancellation_reasons_manage',
        'resources_manage','logs_manage','finances_manage','invoices_manage','machineType_manage','towns_manage',
    ];

    $sidebarShowOps = $sidebarHasAny($sidebarOpsPerms);
    $sidebarShowSales = $sidebarHasAny($sidebarSalesPerms);
    $sidebarShowHr = $sidebarHasAny($sidebarHrPerms);
    $sidebarShowReports = $sidebarHasAny($sidebarReportsPerms);
    $sidebarShowAdmin = $sidebarHasAny($sidebarAdminPerms);
@endphp
<!--begin::Aside-->
<div class="aside aside-left aside-fixed d-flex flex-column flex-row-auto" id="kt_aside">
    <!--begin::Brand-->
    <div class="brand flex-column-auto" id="kt_brand">
        <!--begin::Logo-->
        <a href="{{ route('admin.home') }}" class="brand-logo">
            <span style="font-size:22px;font-weight:700;color:#ffffff;margin-left:25px;letter-spacing:1px;">Allura</span>
        </a>
        <!--end::Logo-->
        <!--begin::Toggle-->
        <button class="brand-toggle btn btn-sm px-0" id="kt_aside_toggle">
            <span class="svg-icon svg-icon svg-icon-xl">
                <!--begin::Svg Icon | path:/metronic/theme/html/demo1/dist/assets/media/svg/icons/Navigation/Angle-double-left.svg-->
                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                        <polygon points="0 0 24 0 24 24 0 24" />
                        <path d="M5.29288961,6.70710318 C4.90236532,6.31657888 4.90236532,5.68341391 5.29288961,5.29288961 C5.68341391,4.90236532 6.31657888,4.90236532 6.70710318,5.29288961 L12.7071032,11.2928896 C13.0856821,11.6714686 13.0989277,12.281055 12.7371505,12.675721 L7.23715054,18.675721 C6.86395813,19.08284 6.23139076,19.1103429 5.82427177,18.7371505 C5.41715278,18.3639581 5.38964985,17.7313908 5.76284226,17.3242718 L10.6158586,12.0300721 L5.29288961,6.70710318 Z" fill="#000000" fill-rule="nonzero" transform="translate(8.999997, 11.999999) scale(-1, 1) translate(-8.999997, -11.999999)" />
                        <path d="M10.7071009,15.7071068 C10.3165766,16.0976311 9.68341162,16.0976311 9.29288733,15.7071068 C8.90236304,15.3165825 8.90236304,14.6834175 9.29288733,14.2928932 L15.2928873,8.29289322 C15.6714663,7.91431428 16.2810527,7.90106866 16.6757187,8.26284586 L22.6757187,13.7628459 C23.0828377,14.1360383 23.1103407,14.7686056 22.7371482,15.1757246 C22.3639558,15.5828436 21.7313885,15.6103465 21.3242695,15.2371541 L16.0300699,10.3841378 L10.7071009,15.7071068 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" transform="translate(15.999997, 11.999999) scale(-1, 1) rotate(-270.000000) translate(-15.999997, -11.999999)" />
                    </g>
                </svg>
                <!--end::Svg Icon-->
            </span>
        </button>
        <!--end::Toolbar-->
    </div>
    <!--end::Brand-->
    <!--begin::Aside Menu-->
    <div class="aside-menu-wrapper flex-column-fluid" id="kt_aside_menu_wrapper">
        <!--begin::Menu Container-->
        <div id="kt_aside_menu" class="aside-menu my-4" data-menu-vertical="1" data-menu-scroll="1" data-menu-dropdown-timeout="500">
            <!--begin::Brand-->
            <div class="flex-column-auto d-lg-none pt-4 pb-7" id="kt_brand" style="border-bottom: 1px solid #032f58;">
                <!--begin::Logo-->
                <a class="brand-logo">
                    <span style="font-size:22px;font-weight:700;color:#ffffff;margin-left:25px;letter-spacing:1px;">Allura</span>
                </a>
                <!--end::Logo-->
            </div>
            <!--end::Brand-->
            <!--begin::Menu Nav-->
            <ul class="menu-nav">

                {{-- ───────────────── Dashboards ───────────────── --}}
                <li class="menu-section">
                    <h4 class="menu-text">Dashboards</h4>
                    <i class="menu-section-icon flaticon-more-v2"></i>
                </li>

                <li class="menu-item {{ activeMenu('admin.home') }}" aria-haspopup="true">
                    <a href="{{ route('admin.home') }}" class="menu-link">
                        <span class="svg-icon menu-icon">
                            <i class="font-icon la la-home"></i>
                        </span>
                        <span class="menu-text">Dashboard</span>
                    </a>
                </li>

                @can('management_dashboard.view')
                <li class="menu-item {{ activeMenu('admin.management_dashboard.overview') }} {{ activeMenu('admin.management_dashboard.people') }}" aria-haspopup="true">
                    <a href="{{ route('admin.management_dashboard.overview') }}" class="menu-link">
                        <span class="svg-icon menu-icon">
                            <i class="font-icon la la-chart-pie"></i>
                        </span>
                        <span class="menu-text">Management Dashboard</span>
                    </a>
                </li>
                @endcan

                {{-- ───────────────── Operations ───────────────── --}}
                @if ($sidebarShowOps)
                <li class="menu-section">
                    <h4 class="menu-text">Operations</h4>
                    <i class="menu-section-icon flaticon-more-v2"></i>
                </li>
                @endif

                @if (Gate::allows('patients_manage'))
                <li class="menu-item {{ activeMenu('admin.patients.index') }} {{ activeMenu('admin.patients.preview') }}" aria-haspopup="true">
                    <a href="{{ route('admin.patients.index') }}" class="menu-link">
                        <span class="svg-icon menu-icon">
                            <i class="font-icon la la-users"></i>
                        </span>
                        <span class="menu-text">Patients</span>
                    </a>
                </li>
                @endif

                @if (Gate::allows('leads_manage'))
                <li class="menu-item menu-item-submenu {{ openMenu(['admin.leads.index']) }}" aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon">
                            <i class="font-icon la la-briefcase"></i>
                        </span>
                        <span class="menu-text">Leads</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">

                            @can('leads_create')
                            <li class="menu-item {{ isActive(url('admin/leads?create=create'), 'create') }}" aria-haspopup="true">
                                <a href="{{ route('admin.leads.index', ['create' => 'create']) }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Create Lead </span>
                                </a>
                            </li>
                            @endcan

                            @can('leads_manage')
                            <li class="menu-item {{ isActive(url('admin/leads'), 'other') }}" aria-haspopup="true">
                                <a href="{{ route('admin.leads.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Leads </span>
                                </a>
                            </li>
                            @endcan

                            @can('leads_junk')
                            <li class="menu-item {{ isActive(url('admin/leads?type=junk'), 'junk') }}" aria-haspopup="true">
                                <a href="{{ route('admin.leads.index', ['type' => 'junk']) }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Junk Leads</span>
                                </a>
                            </li>
                            @endcan

                        </ul>
                    </div>
                </li>
                @endif

                @if (Gate::allows('consultations_manage') || Gate::allows('treatments_manage'))
                <li class="menu-item menu-item-submenu {{ openMenu(['admin.consultancy.index']) }} {{ openMenu(['admin.treatment.index']) }} {{ openMenu(['admin.appointments.index']) }}" aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon fa_icon">
                            <i class="font-icon la la-clock-o"></i>
                        </span>
                        <span class="menu-text">Appointments</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">

                            @if(Gate::allows('consultations_manage'))
                            <li class="menu-item manage-consultancy {{ activeMenu('admin.consultancy.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.consultancy.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Consultancies</span>
                                </a>
                            </li>
                            @endif

                            @can('treatments_manage')
                            <li class="menu-item manage-treatment {{ activeMenu('admin.treatment.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.treatment.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Treatments</span>
                                </a>
                            </li>
                            @endcan

                        </ul>
                    </div>
                </li>
                @endif

                @if (Gate::allows('resourcerotas_manage') || Gate::allows('business_closures_manage'))
                <li class="menu-item menu-item-submenu {{ openMenu(['admin.resourcerotas.schedule', 'admin.business-closures.index']) }}" aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon"><i class="font-icon la la-calendar-alt"></i></span>
                        <span class="menu-text">Schedule</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            @if (Gate::allows('resourcerotas_manage'))
                            <li class="menu-item {{ activeMenu('admin.resourcerotas.schedule') }} {{ activeMenu('admin.resourcerotas.repeating-shifts') }}" aria-haspopup="true">
                                <a href="{{ route('admin.resourcerotas.schedule') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Scheduling Shifts</span>
                                </a>
                            </li>
                            @endif

                            <li class="menu-item {{ activeMenu('admin.business-closures.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.business-closures.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Business Closed Periods</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                @endif

                @if (Gate::allows('feedbacks_manage'))
                <li class="menu-item {{ activeMenu('admin.feedbacks.index') }} " aria-haspopup="true">
                    <a href="{{ route('admin.feedbacks.index') }}" class="menu-link">
                        <span class="svg-icon menu-icon"><i class="font-icon la la-file"></i></span>
                        <span class="menu-text">Doctor Ratings</span>
                    </a>
                </li>
                @endif

                {{-- ───────────────── Sales & Services ───────────────── --}}
                @if ($sidebarShowSales)
                <li class="menu-section">
                    <h4 class="menu-text">Sales &amp; Services</h4>
                    <i class="menu-section-icon flaticon-more-v2"></i>
                </li>
                @endif

                @if (Gate::allows('plans_manage'))
                <li class="menu-item {{ activeMenu('admin.packages.index') }}" aria-haspopup="true">
                    <a href="{{ route('admin.packages.index') }}" class="menu-link">
                        <span class="svg-icon menu-icon"><i class="font-icon la la-cog"></i></span>
                        <span class="menu-text">@lang('global.packages.title')</span>
                    </a>
                </li>
                @endif

                @if (Gate::allows('services_manage') || Gate::allows('packages_manage') || Gate::allows('discounts_manage'))
                <li class="menu-item menu-item-submenu {{ openMenu(['admin.services.index']) }} {{ openMenu(['admin.service-bundles.index']) }} {{ openMenu(['admin.bundles.index']) }} {{ openMenu(['admin.discounts.index']) }}" aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon fa_icon">
                            <i class="font-icon la la-clock-o"></i>
                        </span>
                        <span class="menu-text">Services</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">

                            @can('services_manage')
                            <li class="menu-item manage-consultancy {{ activeMenu('admin.services.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.services.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Services</span>
                                </a>
                            </li>
                            @endcan

                            @can('packages_manage')
                            <li class="menu-item {{ activeMenu('admin.service-bundles.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.service-bundles.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Bundles</span>
                                </a>
                            </li>
                            @endcan

                            @can('packages_manage')
                            <li class="menu-item manage-treatment {{ activeMenu('admin.bundles.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.bundles.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Packages</span>
                                </a>
                            </li>
                            @endcan

                            @can('discounts_manage')
                            <li class="menu-item manage-treatment {{ activeMenu('admin.discounts.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.discounts.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Discounts</span>
                                </a>
                            </li>
                            @endcan

                        </ul>
                    </div>
                </li>
                @endif

                @if (Gate::allows('vouchers_manage') || Gate::allows('voucher_types_manage'))
                <li class="menu-item menu-item-submenu {{ openMenu(['admin.voucherTypes.index', 'admin.vouchers.index']) }} " aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon">
                            <i class="font-icon la la-ticket"></i>
                        </span>
                        <span class="menu-text">Vouchers</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">

                            @can('voucher_types_manage')
                            <li class="menu-item {{ activeMenu('admin.voucherTypes.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.voucherTypes.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Voucher Types</span>
                                </a>
                            </li>
                            @endcan

                            @can('vouchers_manage')
                            <li class="menu-item {{ activeMenu('admin.vouchers.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.vouchers.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Vouchers</span>
                                </a>
                            </li>
                            @endcan

                        </ul>
                    </div>
                </li>
                @endif

                @if (Gate::allows('memberships_manage') || Gate::allows('membershiptypes_manage'))
                <li class="menu-item menu-item-submenu {{ openMenu(['admin.membershiptypes.index', 'admin.memberships.index']) }} " aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon">
                            <i class="font-icon la la-id-card"></i>
                        </span>
                        <span class="menu-text">Memberships</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">

                            @can('membershiptypes_manage')
                            <li class="menu-item {{ activeMenu('admin.membershiptypes.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.membershiptypes.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Membership Types</span>
                                </a>
                            </li>
                            @endcan

                            @can('memberships_manage')
                            <li class="menu-item {{ activeMenu('admin.memberships.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.memberships.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Memberships</span>
                                </a>
                            </li>
                            @endcan

                        </ul>
                    </div>
                </li>
                @endif

                @if (Gate::allows('refunds_manage'))
                <li class="menu-item {{ activeMenu('admin.refunds.index') }}" aria-haspopup="true">
                    <a href="{{ route('admin.refunds.index') }}" class="menu-link">
                        <span class="svg-icon menu-icon"><i class="font-icon la la-refresh"></i></span>
                        <span class="menu-text"> Refunds </span>
                    </a>
                </li>
                @endif

                {{-- ───────────────── Inventory ───────────────── --}}
                @if (Gate::allows('inventory_manage'))
                <li class="menu-section">
                    <h4 class="menu-text">Inventory</h4>
                    <i class="menu-section-icon flaticon-more-v2"></i>
                </li>

                @can('inventory_manage')
                <li class="menu-item menu-item-submenu {{ openMenu(['admin.warehouse.index']) }} {{ openMenu(['admin.brands.index']) }} {{ openMenu(['admin.products.index']) }} {{ openMenu(['admin.products.logs']) }} {{ openMenu(['admin.products.stock']) }} {{ openMenu(['admin.transfer_product.index']) }} {{ openMenu(['admin.orders.index']) }} {{ openMenu(['admin.order.refunds.index']) }}" aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon fa_icon">
                            <i class="la la-warehouse"></i>
                        </span>
                        <span class="menu-text">Inventory</span>
                        <i class="menu-arrow"></i>
                    </a>

                    @can('brand_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.brands.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.brands.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Brand</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('product_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.products.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.products.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Product</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('product_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.transfer_product.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.transfer_product.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Transfer</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('order_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.orders.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.orders.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Order</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan
                </li>
                @endcan
                @endif

                {{-- ───────────────── Finance ───────────────── --}}
                @can('cashflow_manage')
                <li class="menu-section">
                    <h4 class="menu-text">Finance</h4>
                    <i class="menu-section-icon flaticon-more-v2"></i>
                </li>

                <li class="menu-item menu-item-submenu {{ openMenu(['admin.cashflow.dashboard','admin.cashflow.expenses','admin.cashflow.transfers','admin.cashflow.vendors','admin.cashflow.staff','admin.cashflow.fdm','admin.cashflow.reports','admin.cashflow.settings']) }}" aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:;" class="menu-link menu-toggle">
                        <i class="menu-icon la la-money-bill-wave"></i>
                        <span class="menu-text">Cash Flow</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item menu-item-parent" aria-haspopup="true">
                                <span class="menu-link"><span class="menu-text">Cash Flow</span></span>
                            </li>
                            @can('cashflow_dashboard')
                            <li class="menu-item {{ activeMenu('admin.cashflow.dashboard') }}" aria-haspopup="true">
                                <a href="{{ route('admin.cashflow.dashboard') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">Dashboard</span>
                                </a>
                            </li>
                            @endcan
                            @canany(['cashflow_expense_view', 'cashflow_expense_create', 'cashflow_expense_approve', 'cashflow_expense_reject', 'cashflow_expense_edit', 'cashflow_expense_void'])
                            <li class="menu-item {{ activeMenu('admin.cashflow.expenses') }}" aria-haspopup="true">
                                <a href="{{ route('admin.cashflow.expenses') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">Expenses</span>
                                </a>
                            </li>
                            @endcanany
                            @canany(['cashflow_transfer_view', 'cashflow_transfer_create', 'cashflow_transfer_edit', 'cashflow_transfer_void'])
                            <li class="menu-item {{ activeMenu('admin.cashflow.transfers') }}" aria-haspopup="true">
                                <a href="{{ route('admin.cashflow.transfers') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">Transfers</span>
                                </a>
                            </li>
                            @endcanany
                            @canany(['cashflow_vendor_view', 'cashflow_vendor_create', 'cashflow_vendor_edit', 'cashflow_vendor_manage', 'cashflow_vendor_ledger_view', 'cashflow_vendor_transaction'])
                            <li class="menu-item {{ activeMenu('admin.cashflow.vendors') }}" aria-haspopup="true">
                                <a href="{{ route('admin.cashflow.vendors') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">Vendors</span>
                                </a>
                            </li>
                            @endcanany
                            @canany(['cashflow_staff_advance_view', 'cashflow_staff_advance_create', 'cashflow_staff_advance_edit', 'cashflow_staff_advance_void', 'cashflow_staff_return_create', 'cashflow_staff_return_void'])
                            <li class="menu-item {{ activeMenu('admin.cashflow.staff') }}" aria-haspopup="true">
                                <a href="{{ route('admin.cashflow.staff') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">Staff Advances</span>
                                </a>
                            </li>
                            @endcanany
                            @can('cashflow_fdm_view')
                            <li class="menu-item {{ activeMenu('admin.cashflow.fdm') }}" aria-haspopup="true">
                                <a href="{{ route('admin.cashflow.fdm') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">FDM View</span>
                                </a>
                            </li>
                            @endcan
                            @can('cashflow_reports')
                            <li class="menu-item {{ activeMenu('admin.cashflow.reports') }}" aria-haspopup="true">
                                <a href="{{ route('admin.cashflow.reports') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">Reports</span>
                                </a>
                            </li>
                            @endcan
                            @can('cashflow_settings')
                            <li class="menu-item {{ activeMenu('admin.cashflow.settings') }}" aria-haspopup="true">
                                <a href="{{ route('admin.cashflow.settings') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">Settings</span>
                                </a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcan

                {{-- ───────────────── Human Resources ───────────────── --}}
                @if ($sidebarShowHr)
                <li class="menu-section">
                    <h4 class="menu-text">Human Resources</h4>
                    <i class="menu-section-icon flaticon-more-v2"></i>
                </li>

                <li class="menu-item menu-item-submenu {{ openMenu(['admin.hr.dashboard', 'admin.hr.employees.index', 'admin.hr.employees.show', 'admin.hr.departments.index', 'admin.hr.designations.index', 'admin.hr.leave-types.index', 'admin.hr.leave-balances.index', 'admin.hr.leave-applications.index', 'admin.hr.leave-applications.calendar', 'admin.hr.recruitment.index', 'admin.hr.recruitment.show', 'admin.hr.reports.celebrations']) }}" aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon">
                            <i class="font-icon la la-users"></i>
                        </span>
                        <span class="menu-text">HRM</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            @can('hr_dashboard_view')
                            <li class="menu-item {{ activeMenu('admin.hr.dashboard') }}" aria-haspopup="true">
                                <a href="{{ route('admin.hr.dashboard') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">Dashboard</span>
                                </a>
                            </li>
                            @endcan
                            @can('hr_employees_view')
                            <li class="menu-item {{ activeMenu('admin.hr.employees.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.hr.employees.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">Employees</span>
                                </a>
                            </li>
                            @endcan
                            @can('hr_departments_view')
                            <li class="menu-item {{ activeMenu('admin.hr.departments.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.hr.departments.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">Departments</span>
                                </a>
                            </li>
                            @endcan
                            @can('hr_designations_view')
                            <li class="menu-item {{ activeMenu('admin.hr.designations.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.hr.designations.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">Designations</span>
                                </a>
                            </li>
                            @endcan
                            @can('hr_leave_view')
                            <li class="menu-item {{ activeMenu('admin.hr.leave-types.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.hr.leave-types.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">Leave Types</span>
                                </a>
                            </li>
                            <li class="menu-item {{ activeMenu('admin.hr.leave-balances.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.hr.leave-balances.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">Leave Balances</span>
                                </a>
                            </li>
                            <li class="menu-item {{ activeMenu('admin.hr.leave-applications.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.hr.leave-applications.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">Leave Applications</span>
                                </a>
                            </li>
                            @endcan
                            @can('hr_leave_view')
                            <li class="menu-item {{ activeMenu('admin.hr.leave-applications.calendar') }}" aria-haspopup="true">
                                <a href="{{ route('admin.hr.leave-applications.calendar') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">Leave Calendar</span>
                                </a>
                            </li>
                            @endcan
                            @can('hr_recruitment_view')
                            <li class="menu-item {{ activeMenu('admin.hr.recruitment.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.hr.recruitment.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">Recruitment</span>
                                </a>
                            </li>
                            @endcan
                            @can('hr_employees_view')
                            <li class="menu-item {{ activeMenu('admin.hr.reports.celebrations') }}" aria-haspopup="true">
                                <a href="{{ route('admin.hr.reports.celebrations') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot"><span></span></i>
                                    <span class="menu-text">Celebrations</span>
                                </a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endif

                {{-- ───────────────── Reports ───────────────── --}}
                @if ($sidebarShowReports)
                <li class="menu-section">
                    <h4 class="menu-text">Reports</h4>
                    <i class="menu-section-icon flaticon-more-v2"></i>
                </li>

                <li class="menu-item menu-item-submenu {{ openMenu(['admin.reports.finance_reports', 'admin.reports.operations_report', 'admin.reports.inventory_report']) }}" aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon">
                            <i class="font-icon la la-file-text-o"></i>
                        </span>
                        <span class="menu-text">Reports Management</span>
                        <i class="menu-arrow"></i>
                    </a>

                    @can('finance_general_revenue_reports_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.finance_reports') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.finance_reports') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">General Sales Report</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('operations_reports_operations_tax_calculation_report')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.tax_calculation_report') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.tax_calculation_report') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Tax Calculation Report</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('operations_reports_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.operations_report') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.operations_report') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Operation Reports</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('operations_reports_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.membership-reports') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.membership-reports') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Membership Reports</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('operations_reports_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.doctorWiseConversion') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.doctorWiseConversion') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Doctor incentive Report</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('appointment_reports_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.appointmentsReport') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.appointmentsReport') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Appointments Report</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('csr_dashboard_report')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.csr_dashboard') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.csr_dashboard') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">CSR Dashboard</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('non_converted_customers_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.arrived_not_converted') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.arrived_not_converted') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Non-Converted Customer Report </span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('conversion_report_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.conversion') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.conversion') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Conversion Report</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('activity_logs_report')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.activity_logs') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.activity_logs') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Activity Logs </span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('staff_wise_arrival_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.staff_wise_arrival') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.staff_wise_arrival') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Staff Wise Arrival Report </span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('follow_up_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.follow_up') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.follow_up') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Follow Up Report </span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('inventory_report_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.inventory_report') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.inventory_report') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Inventory Report </span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('feedbacks_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.feedback_report') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.feedback_report') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Doctor Ratings Report </span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('followuppatient_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.future_treatments') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.future_treatments') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Future Treatments Report</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('upselling_report')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.upselling') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.upselling') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Doctors Upselling Report </span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('consultant_revenue_report')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.consultant_revenue') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.consultant_revenue') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Consultants Revenue Report </span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('consultant_revenue_report')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.reports.doctor_revenue') }}" aria-haspopup="true">
                                <a href="{{ route('admin.reports.doctor_revenue') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Doctor Revenue Report</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan
                </li>
                @endif

                {{-- ───────────────── Administration ───────────────── --}}
                @if ($sidebarShowAdmin)
                <li class="menu-section">
                    <h4 class="menu-text">Administration</h4>
                    <i class="menu-section-icon flaticon-more-v2"></i>
                </li>
                @endif

                @can('users_manage')
                <li class="menu-item {{ activeMenu('admin.user_branches.index') }}" aria-haspopup="true">
                    <a href="{{ route('admin.user_branches.index') }}" class="menu-link">
                        <span class="svg-icon menu-icon">
                            <i class="font-icon la la-map-marker"></i>
                        </span>
                        <span class="menu-text">Branch Assignments</span>
                    </a>
                </li>
                @endcan

                @if (Gate::allows('permissions_manage') ||
                Gate::allows('roles_manage') ||
                Gate::allows('users_manage') ||
                Gate::allows('user_types_manage') ||
                Gate::allows('doctors_manage'))
                <li class="menu-item menu-item-submenu {{ openMenu(['admin.permissions.index', 'admin.users.index', 'admin.roles.index', 'admin.roles.edit', 'admin.users.index', 'admin.doctors.index', 'admin.user_types.index']) }}" aria-haspopup="true" data-menu-toggle="hover">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon">
                            <i class="font-icon la la-user"></i>
                        </span>
                        <span class="menu-text">User Management</span>
                        <i class="menu-arrow"></i>
                    </a>
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">

                            @can('permissions_manage')
                            <li class="menu-item {{ activeMenu('admin.permissions.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.permissions.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Permissions</span>
                                </a>
                            </li>
                            @endcan

                            @can('roles_manage')
                            <li class="menu-item {{ activeMenu('admin.roles.index') }} {{ activeMenu('admin.roles.edit') }}" aria-haspopup="true">
                                <a href="{{ route('admin.roles.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Roles</span>
                                </a>
                            </li>
                            @endcan

                            @can('users_manage')
                            <li class="menu-item {{ activeMenu('admin.users.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.users.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Application Users</span>
                                </a>
                            </li>
                            @endcan
                            @can('doctors_manage')
                            <li class="menu-item {{ activeMenu('admin.doctors.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.doctors.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Doctors</span>
                                </a>
                            </li>
                            @endcan
                            @can('user_types_manage')
                            <li class="menu-item {{ activeMenu('admin.user_types.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.user_types.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">User Types</span>
                                </a>
                            </li>
                            @endcan

                        </ul>
                    </div>
                </li>
                @endif

                @if (Gate::allows('settings_manage') ||
                Gate::allows('user_operator_settings_manage') ||
                Gate::allows('sms_templates_manage') ||
                Gate::allows('regions_manage') ||
                Gate::allows('cities_manage') ||
                Gate::allows('payment_modes_manage') ||
                Gate::allows('custom_forms_manage') ||
                Gate::allows('custom_form_feedbacks_manage') ||
                Gate::allows('locations_manage') ||
                Gate::allows('staff_targets_manage') ||
                Gate::allows('centre_targets_manage') ||
                Gate::allows('lead_sources_manage') ||
                Gate::allows('lead_statuses_manage') ||
                Gate::allows('appointment_statuses_manage') ||
                Gate::allows('cancellation_reasons_manage') ||
                Gate::allows('resources_manage') ||
                Gate::allows('logs_manage') ||
                Gate::allows('finances_manage') ||
                Gate::allows('invoices_manage') ||
                Gate::allows('machineType_manage') ||
                Gate::allows('towns_manage'))

                <li class="menu-item menu-item-submenu {{ openMenu([
                        'admin.settings.index',
                        'admin.user_operator_settings.index',
                        'admin.payment_modes.index',
                        'admin.payment_modes.sort',
                        'admin.regions.index',
                        'admin.regions.sort',
                        'admin.cities.index',
                        'admin.cities.sort',
                        'admin.lead_sources.index',
                        'admin.lead_sources.sort',
                        'admin.towns.index',
                        'admin.lead_statuses.index',
                        'admin.lead_statuses.sort',
                        'admin.appointment_statuses.index',
                        'admin.locations.index',
                        'admin.machine_types.index',
                        'admin.resources.index',
                        'admin.logs.index',
                        'admin.sms_templates.index',
                        'admin.centre_targets.index',
                        'admin.google_reviews.index',
                        'admin.packagesadvances.index',
                        'admin.resourcerotas.calender-view',
                        'admin.invoices.index',
                        'admin.packages.log',
                        'admin.custom_form_feedbacks.index',
                        'admin.custom_form_feedbacks.edit',
                        'admin.custom_form_feedbacks.filled_preview',
                        'admin.custom_forms.index',
                        'admin.custom_forms.edit',
                        'admin.custom_form_feedbacks.preview_form',
                        'admin.custom_form_feedbacks.fill_form',
                    ]) }}" aria-haspopup="true" data-menu-toggle="hover">

                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <span class="svg-icon menu-icon">
                            <i class="font-icon la la-cog"></i>
                        </span>
                        <span class="menu-text">Admin Settings</span>
                        <i class="menu-arrow"></i>
                    </a>

                    @can('settings_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.settings.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.settings.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Global Settings</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('user_operator_settings_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.user_operator_settings.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.user_operator_settings.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Operator Settings</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('payment_modes_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ openMenu(['admin.payment_modes.index', 'admin.payment_modes.sort'], 'menu-item-active') }}" aria-haspopup="true">
                                <a href="{{ route('admin.payment_modes.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Payment Modes</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('regions_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ openMenu(['admin.regions.index', 'admin.regions.sort'], 'menu-item-active') }}" aria-haspopup="true">
                                <a href="{{ route('admin.regions.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Regions</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('cities_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ openMenu(['admin.cities.index', 'admin.cities.sort'], 'menu-item-active') }}" aria-haspopup="true">
                                <a href="{{ route('admin.cities.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Cities</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('towns_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.towns.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.towns.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Towns</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('lead_sources_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ openMenu(['admin.lead_sources.index', 'admin.lead_sources.sort'], 'menu-item-active') }}" aria-haspopup="true">
                                <a href="{{ route('admin.lead_sources.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Lead Sources</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('lead_statuses_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ openMenu(['admin.lead_statuses.index', 'admin.lead_statuses.sort'], 'menu-item-active') }}" aria-haspopup="true">
                                <a href="{{ route('admin.lead_statuses.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Lead Statuses</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('locations_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.locations.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.locations.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Centres</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('appointment_statuses_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.appointment_statuses.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.appointment_statuses.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Appointment Statuses</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('machineType_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.machine_types.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.machine_types.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Machine Type</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('resources_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.resources.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.resources.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Resource</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('sms_templates_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.sms_templates.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.sms_templates.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">SMS Templates</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('centre_targets_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.centre_targets.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.centre_targets.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Centre Targets</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('google_reviews_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.google_reviews.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.google_reviews.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Google Reviews</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('finances_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.packagesadvances.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.packagesadvances.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Finances</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('invoices_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.invoices.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.invoices.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Invoices</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('custom_form_feedbacks_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.custom_form_feedbacks.index') }} {{ activeMenu('admin.custom_form_feedbacks.filled_preview') }}" aria-haspopup="true">
                                <a href="{{ route('admin.custom_form_feedbacks.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Custom Form Feedbacks</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    @can('custom_forms_manage')
                    <div class="menu-submenu">
                        <i class="menu-arrow"></i>
                        <ul class="menu-subnav">
                            <li class="menu-item {{ activeMenu('admin.custom_forms.index') }}" aria-haspopup="true">
                                <a href="{{ route('admin.custom_forms.index') }}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Custom Form</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endcan
                </li>
                @endif

            </ul>
            <!--end::Menu Nav-->
        </div>
        <!--end::Menu Container-->
    </div>
    <!--end::Aside Menu-->
</div>
<!--end::Aside-->
