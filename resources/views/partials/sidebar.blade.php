@inject('request', 'Illuminate\Http\Request')
<div class="page-sidebar-wrapper">
    <div class="page-sidebar navbar-collapse collapse">
        <ul class="page-sidebar-menu  page-header-fixed " data-keep-expanded="false" data-auto-scroll="true" data-slide-speed="200" style="padding-top: 20px">
            <!-- DOC: To remove the sidebar toggler from the sidebar you just need to completely remove the below "sidebar-toggler-wrapper" LI element -->
            <!-- BEGIN SIDEBAR TOGGLER BUTTON -->
            <li class="sidebar-toggler-wrapper hide">
                <div class="sidebar-toggler">
                    <span></span>
                </div>
            </li>
            <!-- END SIDEBAR TOGGLER BUTTON -->
            <li class="nav-item start {{ $request->segment(2) == 'home' ? 'active' : '' }}">
                <a href="{{ url('/') }}" class="nav-link ">
                    <i class="icon-home"></i>
                    <span class="title">@lang('global.app_dashboard')</span>
                </a>
            </li>

            @if(Gate::allows('permissions_manage') || Gate::allows('roles_manage') || Gate::allows('users_manage') || Gate::allows('user_types_manage'))
            <li class="nav-item start @if(
                    $request->segment(2) == 'permissions' ||
                    $request->segment(2) == 'roles' ||
                    $request->segment(2) == 'users' ||
                    $request->segment(2) == 'user_types'

                ) active open @endif">
                <a href="javascript:;" class="nav-link nav-toggle">
                    <i class="icon-user"></i>
                    <span class="title">@lang('global.user-management.title')</span>
                    <span class="arrow"></span>
                </a>
                <ul class="sub-menu">
                    @if(Gate::allows('permissions_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'permissions' ? 'active ' : '' }}">
                        <a href="{{ route('admin.permissions.index') }}">
                            <span class="title">@lang('global.permissions.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('roles_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'roles' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.roles.index') }}">
                            <span class="title">@lang('global.roles.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('users_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'users' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.users.index') }}">
                            <span class="title">@lang('global.users.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('user_types_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'user_types' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.user_types.index') }}">
                            <span class="title">@lang('global.user_types.title')</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </li>
            @endcan

            @if(Gate::allows('patients_manage'))
            <li class="nav-item start @if($request->segment(2) == 'patients' || $request->segment(2) == 'customformfeedbackspatient' || $request->segment(2) == 'plans' || $request->segment(2) == 'finances' || $request->segment(2) == 'patients' || $request->segment(2) == 'refundpatient' || $request->segment(2) == 'invoicepatient') active open @endif">
                <a href="javascript:;" class="nav-link nav-toggle">
                    <i class="icon-users"></i>
                    <span class="title">@lang('global.patients.heading')</span>
                    <span class="arrow"></span>
                </a>
                <ul class="sub-menu">
                    <li class="nav-item start {{ ($request->segment(2) == 'patients' || $request->segment(2) == 'customformfeedbackspatient' || $request->segment(2) == 'plans' || $request->segment(2) == 'finances' || $request->segment(2) == 'refundpatient' || $request->segment(2) == 'invoicepatient') ? 'active' : '' }}">
                        <a href="{{ route('admin.patients.index') }}">
                            <span class="title">@lang('global.patients.title')</span>
                        </a>
                    </li>
                </ul>
            </li>
            @endif

            @if(Gate::allows('leads_manage') || Gate::allows('leads_junk'))
            <li class="nav-item start @if($request->segment(2) == 'leads') active open @endif">
                <a href="javascript:;" class="nav-link nav-toggle">
                    <i class="icon-briefcase"></i>
                    <span class="title">@lang('global.leads.title')</span>
                    <span class="arrow"></span>
                </a>
                <ul class="sub-menu">
                    @if(Gate::allows('leads_create'))
                    <li class="nav-item start {{ ($request->segment(2) == 'leads' && $request->segment(3) == 'create') ? 'active' : '' }}">
                        <a href="{{ route('admin.leads.create') }}">
                            <span class="title">Create Lead</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('leads_manage'))
                    <li class="nav-item start {{ ($request->segment(2) == 'leads' && $request->segment(3) != 'create' && $request->segment(3) != 'junk') ? 'active' : '' }}">
                        <a href="{{ route('admin.leads.index') }}">
                            <span class="title">@lang('global.leads.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('leads_junk'))
                    <li class="nav-item start {{ ($request->segment(2) == 'leads' && $request->segment(3) == 'junk' && $request->segment(3) != 'create') ? 'active' : '' }}">
                        <a href="{{ route('admin.leads.junk') }}">
                            <span class="title">Junk @lang('global.leads.title')</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </li>
            @endif

            @if(Gate::allows('appointments_manage') || Gate::allows('appointments_consultancy') || Gate::allows('treatments_services'))
            <li class="nav-item start @if($request->segment(2) == 'appointments' || $request->segment(2) == 'appointmentsmeasurement' || $request->segment(2) == 'appointmentsimage') active open @endif">
                <a href="javascript:;" class="nav-link nav-toggle">
                    <i class="icon-clock"></i>
                    <span class="title">@lang('global.appointments.title')</span>
                    <span class="arrow"></span>
                </a>
                <ul class="sub-menu">
                    @if(Gate::allows('appointments_consultancy'))
                    <li class="nav-item start {{ ($request->segment(2) == 'appointments' && $request->segment(3) == 'create') ? 'active' : '' }}">
                        <a href="{{ route('admin.appointments.create') }}">
                            <span class="title">Manage Consultancy</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('treatments_services'))
                    <li class="nav-item start {{ ($request->segment(2) == 'appointments' && $request->segment(3) == 'manage-services') ? 'active' : '' }}">
                        <a href="{{ route('admin.appointments.manage_services') }}">
                            <span class="title">Manage Treatment</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('appointments_manage'))
                    <li class="nav-item start {{ ( ($request->segment(2) == 'appointments' || $request->segment(2) == 'appointmentsmeasurement' || $request->segment(2) == 'appointmentsimage' ) && $request->segment(3) != 'create' && $request->segment(3) != 'manage-services') ? 'active' : '' }}">
                        <a href="{{ route('admin.appointments.index') }}">
                            <span class="title">@lang('global.appointments.title')</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </li>
            @endif

            @if(
            Gate::allows('settings_manage') ||
            Gate::allows('user_operator_settings_manage') ||
            Gate::allows('sms_templates_manage') ||
            Gate::allows('regions_manage') ||
            Gate::allows('cities_manage') ||
            Gate::allows('payment_modes_manage') ||
            Gate::allows('custom_forms_manage') ||
            Gate::allows('custom_form_feedbacks_manage') ||
            Gate::allows('locations_manage') ||
            Gate::allows('doctors_manage') ||
            Gate::allows('staff_targets_manage') ||
            Gate::allows('centre_targets_manage') ||
            Gate::allows('lead_sources_manage') ||
            Gate::allows('services_manage') ||
            Gate::allows('lead_statuses_manage') ||
            Gate::allows('appointment_statuses_manage') ||
            Gate::allows('cancellation_reasons_manage')||
            Gate::allows('resources_manage') ||
            Gate::allows('resourcerotas_manage') ||
            Gate::allows('discounts_manage') ||
            Gate::allows('logs_manage') ||
            Gate::allows('packages_manage') ||
            Gate::allows('plans_manage') ||
            Gate::allows('finances_manage') ||
            Gate::allows('invoices_manage') ||
            Gate::allows('refunds_manage') ||
            Gate::allows('pabao_records_manage') ||
            Gate::allows('machineType_manage') ||
            Gate::allows('towns_manage')


            )
            <li class="nav-item start @if(
                    $request->segment(2) == 'settings' ||
                    $request->segment(2) == 'user_operator_settings' ||
                    $request->segment(2) == 'sms_templates' ||
                    $request->segment(2) == 'regions' ||
                    $request->segment(2) == 'cities' ||
                    $request->segment(2) == 'payment_modes' ||
                    $request->segment(2) == 'custom_forms' ||
                    $request->segment(2) == 'custom_form_feedbacks' ||
                    $request->segment(2) == 'locations' ||
                    $request->segment(2) == 'doctors' ||
                    $request->segment(2) == 'staff_targets' ||
                    $request->segment(2) == 'centre_targets' ||
                    $request->segment(2) == 'lead_sources' ||
                    $request->segment(2) == 'services' ||
                    $request->segment(2) == 'lead_statuses' ||
                    $request->segment(2) == 'appointment_statuses' ||
                    $request->segment(2) == 'cancellation_reasons'||
                    $request->segment(2) == 'resource_types'||
                    $request->segment(2) == 'resources'||
                    $request->segment(2) == 'resourcerotas'||
                    $request->segment(2) == 'discounts' ||
                    $request->segment(2) == 'logs' ||
                    $request->segment(2) == 'bundles' ||
                    $request->segment(2) == 'packages' ||
                    $request->segment(2) == 'packagesadvances' ||
                    $request->segment(2) == 'invoices' ||
                    $request->segment(2) == 'refunds' ||
                    $request->segment(2) == 'pabao_records' ||
                    $request->segment(2) == 'machinetypes' ||
                    $request->segment(2) == 'towns'

                ) active open @endif">
                <a href="javascript:;" class="nav-link nav-toggle">
                    <i class="icon-settings"></i>
                    <span class="title">Admin Settings</span>
                    <span class="arrow"></span>
                </a>
                <ul class="sub-menu">
                    @if(Gate::allows('settings_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'settings' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.settings.index') }}">
                            <span class="title">@lang('global.settings.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('user_operator_settings_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'user_operator_settings' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.user_operator_settings.index') }}">
                            <span class="title">@lang('global.user_operator_settings.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('payment_modes_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'payment_modes' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.payment_modes.index') }}">
                            <span class="title">@lang('global.payment_modes.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('custom_forms_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'custom_forms' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.custom_forms.index') }}">
                            <span class="title">@lang('global.custom_forms.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('custom_form_feedbacks_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'custom_form_feedbacks' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.custom_form_feedbacks.index') }}">
                            <span class="title">@lang('global.custom_form_feedbacks.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('sms_templates_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'sms_templates' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.sms_templates.index') }}">
                            <span class="title">@lang('global.sms_templates.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('regions_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'regions' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.regions.index') }}">
                            <span class="title">@lang('global.regions.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('cities_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'cities' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.cities.index') }}">
                            <span class="title">@lang('global.cities.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('towns_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'towns' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.towns.index') }}">
                            <span class="title">@lang('global.towns.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('locations_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'locations' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.locations.index') }}">
                            <span class="title">@lang('global.locations.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('doctors_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'doctors' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.doctors.index') }}">
                            <span class="title">@lang('global.doctors.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('staff_targets_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'staff_targets' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.staff_targets.index') }}">
                            <span class="title">@lang('global.staff_targets.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('centre_targets_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'centre_targets' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.centre_targets.index') }}">
                            <span class="title">@lang('global.centre_targets.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('lead_sources_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'lead_sources' || $request->segment(2) == 'lead_sources_sort' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.lead_sources.index') }}">
                            <span class="title">@lang('global.lead_sources.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('services_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'services' || $request->segment(2) == 'services_sort' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.services.index') }}">
                            <span class="title">@lang('global.services.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('lead_statuses_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'lead_statuses' || $request->segment(2) == 'lead_status_sort' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.lead_statuses.index') }}">
                            <span class="title">@lang('global.lead_statuses.title')</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('appointment_statuses_manage'))
                    <li class="nav-item start {{ $request->segment(2) == 'appointment_statuses' || $request->segment(2) == 'appointment_status_sort' ? 'active active-sub' : '' }}">
                        <a href="{{ route('admin.appointment_statuses.index') }}">
                            <span class="title">@lang('global.appointment_statuses.title')</span>
                        </a>
                    </li>
                    @endif
                    {{--@if(Gate::allows('resource_types_manage'))
                            <li class="nav-item start {{ $request->segment(2) == 'resource_types' ? 'active active-sub' : '' }}">
                    <a href="{{ route('admin.resource_types.index') }}">
                        <span class="title">@lang('global.resource_types.title')</span>
                    </a>
            </li>
            @endif--}}
            @if(Gate::allows('machineType_manage'))
            <li class="nav-item start {{ $request->segment(2) == 'machinetypes' ? 'active active-sub' : '' }}">
                <a href="{{ route('admin.machinetypes.index') }}">
                    <span class="title">@lang('global.machinetypes.title')</span>
                </a>
            </li>
            @endif
            @if(Gate::allows('resources_manage'))
            <li class="nav-item start {{ $request->segment(2) == 'resources' ? 'active active-sub' : '' }}">
                <a href="{{ route('admin.resources.schedule') }}">
                    <span class="title">@lang('global.resources.title')</span>
                </a>
            </li>
            @endif
            @if(Gate::allows('resourcerotas_manage'))
            <li class="nav-item start {{ $request->segment(2) == 'resourcerotas' ? 'active active-sub' : '' }}">
                <a href="{{ route('admin.resourcerotas.schedule') }}">
                    <span class="title">@lang('global.resourcerotas.title')</span>
                </a>
            </li>
            @endif
            @if(Gate::allows('discounts_manage'))
            <li class="nav-item start {{ $request->segment(2) == 'discounts' ? 'active active-sub' : '' }}">
                <a href="{{ route('admin.discounts.index') }}">
                    <span class="title">@lang('global.discounts.title')</span>
                </a>
            </li>
            @endif
            @if(Gate::allows('packages_manage'))
            <li class="nav-item start {{ $request->segment(2) == 'bundles' ? 'active active-sub' : '' }}">
                <a href="{{ route('admin.bundles.index') }}">
                    <span class="title">@lang('global.bundles.title')</span>
                </a>
            </li>
            @endif
            @if(Gate::allows('plans_manage'))
            <li class="nav-item start {{ $request->segment(2) == 'packages' ? 'active active-sub' : '' }}">
                <a href="{{ route('admin.packages.index') }}">
                    <span class="title">@lang('global.packages.title')</span>
                </a>
            </li>

            @endif
            @if(Gate::allows('finances_manage'))
            <li class="nav-item start {{ $request->segment(2) == 'packagesadvances' ? 'active active-sub' : '' }}">
                <a href="{{ route('admin.packagesadvances.index') }}">
                    <span class="title">@lang('global.packagesadvances.title')</span>
                </a>
            </li>
            @endif
            @if(Gate::allows('invoices_manage'))
            <li class="nav-item start {{ $request->segment(2) == 'invoices' ? 'active active-sub' : '' }}">
                <a href="{{ route('admin.invoices.index') }}">
                    <span class="title">@lang('global.invoices.title')</span>
                </a>
            </li>
            @endif
            {{--Refunds Start--}}
            @if(Gate::allows('refunds_manage'))
            <li class="nav-item start {{ $request->segment(2) == 'refunds' ? 'active active-sub' : '' }}">
                <a href="{{ route('admin.refunds.index') }}">
                    <span class="title">@lang('global.refunds.title')</span>
                </a>
            </li>
            @endif
            @if(Gate::allows('pabao_records_manage'))
            <li class="nav-item start {{ $request->segment(2) == 'pabao_records' ? 'active active-sub' : '' }}">
                <a href="{{ route('admin.pabao_records.index') }}">
                    <span class="title">@lang('global.pabao_records.title')</span>
                </a>
            </li>
            @endif
            {{--Refunds end--}}
            <!-- @if(Gate::allows('logs_manage'))
            <li class="nav-item start {{ $request->segment(2) == 'logs' ? 'active active-sub' : '' }}">
                <a href="{{ route('admin.logs.index') }}">
                    <span class="title">@lang('global.logs.title')</span>
                </a>
            </li>
            @endif -->
        </ul>
        </li>
        @endif
        @if(
        Gate::allows('leads_reports_manage') ||
        Gate::allows('appointment_reports_manage') ||
        Gate::allows('finance_general_revenue_reports_manage') ||
        Gate::allows('finance_revenue_breakup_reports_manage') ||
        Gate::allows('finance_ledger_reports_manage') ||
        Gate::allows('centers_reports_manage') ||
        Gate::allows('marketing_reports_manage') ||
        Gate::allows('Hr_reports_manage')
        )

        <li class="nav-item start @if(
                    $request->segment(3) == 'leads_reports'||
                    $request->segment(3) == 'centers_reports'||
                    $request->segment(3) == 'appointments-general' ||
                    $request->segment(3) == 'finance_reports' ||
                    $request->segment(3) == 'revenue_reports' ||
                    $request->segment(3) == 'ledger_reports' ||
                    $request->segment(3) == 'staff_reports' ||
                    $request->segment(3) == 'marketing_reports' ||
                    $request->segment(3) == 'rbreakup_reports' ||
                    $request->segment(3) == 'operations-report' ||
                    $request->segment(3) == 'HR-report'

                ) active open @endif">
            <a href="javascript:;" class="nav-link nav-toggle">
                <i class="fa fa-file-text-o"></i>
                <span class="title">Report Management</span>
                <span class="arrow"></span>
            </a>
            <ul class="sub-menu">
                @if(Gate::allows('leads_reports_manage'))
                <li class="nav-item start {{ $request->segment(3) == 'leads_reports' ? 'active active-sub' : '' }}">
                    <a href="{{ route('admin.leads.leads_reports') }}">
                        <span class="title">@lang('global.reports.lead_report')</span>
                    </a>
                </li>
                @endif
                @if(Gate::allows('centers_reports_manage'))
                <li class="nav-item start {{ $request->segment(3) == 'centers_reports' ? 'active active-sub' : '' }}">
                    <a href="{{ route('admin.centers.centers_reports') }}">
                        <span class="title">@lang('global.reports.centers_report')</span>
                    </a>
                </li>
                @endif
                @if(Gate::allows('appointment_reports_manage'))
                <li class="nav-item start {{ $request->segment(3) == 'appointments-general' ? 'active active-sub' : '' }}">
                    <a href="{{ route('admin.reports.appointments_general') }}">
                        <span class="title">Appointment Reports</span>
                    </a>
                </li>
                @endif
                @if(Gate::allows('operations_reports_manage'))
                <li class="nav-item start {{ $request->segment(3) == 'operations-report' ? 'active active-sub' : '' }}">
                    <a href="{{ route('admin.reports.operations_report') }}">
                        <span class="title">Operation Reports</span>
                    </a>
                </li>
                @endif
           
                @if(Gate::allows('operations_reports_manage'))
                <li class="nav-item start {{ $request->segment(3) == 'operations-report' ? 'active active-sub' : '' }}">
                    <a href="{{ route('admin.reports.operations_report') }}">
                        <span class="title">Membership Report</span>
                    </a>
                </li>
                @endif
                {{--Finance Report Start--}}
                @if(Gate::allows('finance_general_revenue_reports_manage') || Gate::allows('finance_revenue_breakup_reports_manage') || Gate::allows('finance_ledger_reports_manage'))
                <li class="nav-item start @if($request->segment(2) == 'reports') active open @endif">
                    <a href="javascript:;" class="nav-link nav-toggle">
                        <span class="title">Finance Reports</span>
                        <span class="arrow"></span>
                    </a>
                    <ul class="sub-menu">
                        @if(Gate::allows('finance_general_revenue_reports_manage'))
                        <li class="nav-item start {{ ($request->segment(3) == 'revenue_reports') ? 'active' : '' }}">
                            <a href="{{ route('admin.reports.finance_reports') }}">
                                <span class="title">General Revenue Reports</span>
                            </a>
                        </li>
                        @endif
                        @if(Gate::allows('finance_revenue_breakup_reports_manage'))

                        <li class="nav-item start {{ ($request->segment(3) == 'rbreakup_reports') ? 'active' : '' }}">
                            <a href="{{ route('admin.reports.rbreakup_reports') }}">
                                <span class="title">Revenue Breakup Reports</span>
                            </a>
                        </li>
                        @endif
                        @if(Gate::allows('finance_ledger_reports_manage'))

                        <li class="nav-item start {{ ($request->segment(3) == 'ledger_reports') ? 'active' : '' }}">
                            <a href="{{ route('admin.reports.ledger_reports') }}">
                                <span class="title">Ledger Reports</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </li>
                @endif
                {{--Finance Report end--}}
                @if(Gate::allows('staff_listing_reports_manage') || Gate::allows('staff_revenue_reports_manage'))
                <li class="nav-item start @if($request->segment(2) == 'reports-staff') active open @endif">
                    <a href="javascript:;" class="nav-link nav-toggle">
                        <span class="title">@lang('global.reports.staff_report')</span>
                        <span class="arrow"></span>
                    </a>
                    <ul class="sub-menu">
                        @if(Gate::allows('staff_listing_reports_manage'))
                        <li class="nav-item start {{ ($request->segment(3) == 'staff_reports') ? 'active' : '' }}">
                            <a href="{{ route('admin.staff.reports') }}">
                                <span class="title">@lang('global.reports.staff_listing')</span>
                            </a>
                        </li>
                        @endif
                        @if(Gate::allows('staff_revenue_reports_manage'))
                        <li class="nav-item start {{ ($request->segment(3) == 'revenue_reports') ? 'active' : '' }}">
                            <a href="{{ route('admin.staff.revenue.report') }}">
                                <span class="title">@lang('global.reports.staff_revenue_report')</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </li>
                @endif
                @if(Gate::allows('marketing_reports_manage'))
                <li class="nav-item start {{ $request->segment(3) == 'marketing_reports' ? 'active active-sub' : '' }}">
                    <a href="{{ route('admin.marketing.marketing_reports') }}">
                        <span class="title">@lang('global.reports.marketing_report')</span>
                    </a>
                </li>
                @endif
                @if(Gate::allows('Hr_reports_manage'))
                <li class="nav-item start {{ $request->segment(3) == 'HR-report' ? 'active active-sub' : '' }}">
                    <a href="{{ route('admin.report.HR_reports') }}">
                        <span class="title">@lang('global.reports.hr_report')</span>
                    </a>
                </li>
                @endif
            </ul>
        </li>
        @endif
        </ul>
        <!-- END SIDEBAR MENU -->
    </div>
    <!-- END SIDEBAR -->
</div>