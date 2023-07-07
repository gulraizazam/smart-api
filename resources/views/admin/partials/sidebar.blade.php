
    <!--begin::Aside-->
    <div class="aside aside-left aside-fixed d-flex flex-column flex-row-auto" id="kt_aside">
        <!--begin::Brand-->
        <div class="brand flex-column-auto" id="kt_brand">
            <!--begin::Logo-->
            <a href="{{route('admin.home')}}" class="brand-logo">
                <img style="width: 120px;margin-left:25px" alt="Logo" src="{{asset('logo.png')}}" />
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
                        <img style="width: 130px;margin-left:25px; display:block;" alt="Logo" src="{{asset('logo.png')}}" />
                    </a>
                    <!--end::Logo-->
                </div>
                <!--end::Brand-->
                <!--begin::Menu Nav-->
                <ul class="menu-nav">
                    <li class="menu-item {{activeMenu('admin.home')}}" aria-haspopup="true">
                        <a href="{{route('admin.home')}}" class="menu-link">
                                    <span class="svg-icon menu-icon">

                                       {{-- <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                <polygon points="0 0 24 0 24 24 0 24" />
                                                <path d="M12.9336061,16.072447 L19.36,10.9564761 L19.5181585,10.8312381 C20.1676248,10.3169571 20.2772143,9.3735535 19.7629333,8.72408713 C19.6917232,8.63415859 19.6104327,8.55269514 19.5206557,8.48129411 L12.9336854,3.24257445 C12.3871201,2.80788259 11.6128799,2.80788259 11.0663146,3.24257445 L4.47482784,8.48488609 C3.82645598,9.00054628 3.71887192,9.94418071 4.23453211,10.5925526 C4.30500305,10.6811601 4.38527899,10.7615046 4.47382636,10.8320511 L4.63,10.9564761 L11.0659024,16.0730648 C11.6126744,16.5077525 12.3871218,16.5074963 12.9336061,16.072447 Z" fill="#000000" fill-rule="nonzero" />
                                                <path d="M11.0563554,18.6706981 L5.33593024,14.122919 C4.94553994,13.8125559 4.37746707,13.8774308 4.06710397,14.2678211 C4.06471678,14.2708238 4.06234874,14.2738418 4.06,14.2768747 L4.06,14.2768747 C3.75257288,14.6738539 3.82516916,15.244888 4.22214834,15.5523151 C4.22358765,15.5534297 4.2250303,15.55454 4.22647627,15.555646 L11.0872776,20.8031356 C11.6250734,21.2144692 12.371757,21.2145375 12.909628,20.8033023 L19.7677785,15.559828 C20.1693192,15.2528257 20.2459576,14.6784381 19.9389553,14.2768974 C19.9376429,14.2751809 19.9363245,14.2734691 19.935,14.2717619 L19.935,14.2717619 C19.6266937,13.8743807 19.0546209,13.8021712 18.6572397,14.1104775 C18.654352,14.112718 18.6514778,14.1149757 18.6486172,14.1172508 L12.9235044,18.6705218 C12.377022,19.1051477 11.6029199,19.1052208 11.0563554,18.6706981 Z" fill="#000000" opacity="0.3" />
                                            </g>
                                        </svg>--}}
                                        <i class="font-icon la la-home"></i>

                                    </span>
                            <span class="menu-text">Dashboard</span>
                        </a>
                    </li>

                    @if(Gate::allows('permissions_manage') || Gate::allows('roles_manage') || Gate::allows('users_manage') || Gate::allows('user_types_manage'))
                        <li class="menu-item menu-item-submenu {{openMenu(['admin.permissions.index', 'admin.users.index', 'admin.roles.index', 'admin.roles.edit', 'admin.users.index', 'admin.user_types.index'])}}" aria-haspopup="true" data-menu-toggle="hover">
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

                                    <li class="menu-item {{activeMenu('admin.permissions.index')}}" aria-haspopup="true">
                                        <a href="{{route('admin.permissions.index')}}" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Permissions</span>
                                        </a>
                                    </li>
                                    @endcan

                                    @can('roles_manage')
                                        <li class="menu-item {{activeMenu('admin.roles.index')}} {{activeMenu('admin.roles.edit')}}" aria-haspopup="true">
                                            <a href="{{route('admin.roles.index')}}" class="menu-link">
                                                <i class="menu-bullet menu-bullet-dot">
                                                    <span></span>
                                                </i>
                                                <span class="menu-text">Roles</span>
                                            </a>
                                        </li>
                                    @endcan

                                    @can('users_manage')
                                        <li class="menu-item {{activeMenu('admin.users.index')}}" aria-haspopup="true">
                                            <a href="{{route('admin.users.index')}}" class="menu-link">
                                                <i class="menu-bullet menu-bullet-dot">
                                                    <span></span>
                                                </i>
                                                <span class="menu-text">Users</span>
                                            </a>
                                        </li>
                                    @endcan

                                    @can('user_types_manage')
                                        <li class="menu-item {{activeMenu('admin.user_types.index')}}" aria-haspopup="true">
                                            <a href="{{route('admin.user_types.index')}}" class="menu-link">
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


                    <!--Patient menu-->

                    @if(Gate::allows('patients_manage'))

                        <li class="menu-item menu-item-submenu {{openMenu(['admin.patients.index', 'admin.patients.preview'])}}" aria-haspopup="true" data-menu-toggle="hover">

                            <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <span class="svg-icon menu-icon">
                            <i class="font-icon la la-users"></i>
                            </span>
                                <span class="menu-text">Patients Management</span>
                                <i class="menu-arrow"></i>
                            </a>
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    @can('patients_manage')
                                        <li class="menu-item {{activeMenu('admin.patients.index')}} {{activeMenu('admin.patients.preview')}}" aria-haspopup="true">
                                            <a href="{{route('admin.patients.index')}}" class="menu-link">
                                                <i class="menu-bullet menu-bullet-dot">
                                                    <span></span>
                                                </i>
                                                <span class="menu-text">Patients</span>
                                            </a>
                                        </li>
                                    @endcan

                                </ul>
                            </div>

                        </li>

                    @endif

                    <!-- Leads menu -->

                    @if(Gate::allows('leads_manage'))

                    <li class="menu-item menu-item-submenu {{openMenu(['admin.leads.index'])}}" aria-haspopup="true" data-menu-toggle="hover">

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
                                    <li class="menu-item {{isActive(url('admin/leads?create=create'), 'create')}}" aria-haspopup="true">
                                        <a href="{{route('admin.leads.index', ['create' => 'create'])}}" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Create Lead </span>
                                        </a>
                                    </li>
                                @endcan

                            @can('leads_manage')
                                <li class="menu-item {{isActive(url('admin/leads'), 'other')}}" aria-haspopup="true">
                                    <a href="{{route('admin.leads.index')}}" class="menu-link">
                                        <i class="menu-bullet menu-bullet-dot">
                                            <span></span>
                                        </i>
                                        <span class="menu-text">Leads </span>
                                    </a>
                                </li>
                            @endcan

                                @can('leads_junk')
                                    <li class="menu-item {{isActive(url('admin/leads?type=junk'), 'junk')}}" aria-haspopup="true">
                                        <a href="{{route('admin.leads.index', ['type' => 'junk'])}}" class="menu-link">
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

                    <!-- End leads menu -->

                    <!-- Appointment menu -->

                    @if(Gate::allows('appointments_manage'))

                        <li class="menu-item menu-item-submenu {{openMenu(['admin.consultancy.index'])}} {{openMenu(['admin.treatment.index'])}} {{openMenu(['admin.appointments.index'])}}" aria-haspopup="true" data-menu-toggle="hover">

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

                                    @can('appointments_consultancy')
                                        <li class="menu-item manage-consultancy {{activeMenu('admin.consultancy.index')}}" aria-haspopup="true">
                                            <a href="{{route('admin.consultancy.index')}}" class="menu-link">
                                                <i class="menu-bullet menu-bullet-dot">
                                                    <span></span>
                                                </i>
                                                <span class="menu-text">Consultancies</span>
                                            </a>
                                        </li>
                                    @endcan

                                    @can('appointments_services')
                                        <li class="menu-item manage-treatment {{activeMenu('admin.treatment.index')}}" aria-haspopup="true">
                                            <a href="{{route('admin.treatment.index')}}" class="menu-link">
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

                <!-- End Appointment menu -->

                @if(Gate::allows('plans_manage'))
                    <li class="menu-item {{activeMenu('admin.packages.index')}}" aria-haspopup="true">
                        <a href="{{route('admin.packages.index')}}" class="menu-link">
                            <span class="svg-icon menu-icon"><i class="font-icon la la-cog"></i></span>
                            <span class="menu-text">@lang('global.packages.title')</span>
                        </a>
                    </li>
                @endif

                @if( Gate::allows('services_manage') || Gate::allows('packages_manage') || Gate::allows('discounts_manage'))

                    <li class="menu-item menu-item-submenu {{openMenu(['admin.services.index'])}} {{openMenu(['admin.bundles.index'])}} {{openMenu(['admin.discounts.index'])}}" aria-haspopup="true" data-menu-toggle="hover">

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
                                    <li class="menu-item manage-consultancy {{activeMenu('admin.services.index')}}" aria-haspopup="true">
                                        <a href="{{route('admin.services.index')}}" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Services</span>
                                        </a>
                                    </li>
                                @endcan

                                @can('packages_manage')
                                    <li class="menu-item manage-treatment {{activeMenu('admin.bundles.index')}}" aria-haspopup="true">
                                        <a href="{{route('admin.bundles.index')}}" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Packages</span>
                                        </a>
                                    </li>
                                @endcan

                                @can('discounts_manage')
                                    <li class="menu-item manage-treatment {{activeMenu('admin.discounts.index')}}" aria-haspopup="true">
                                        <a href="{{route('admin.discounts.index')}}" class="menu-link">
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

                @if(Gate::allows('resourcerotas_manage'))
                    <li class="menu-item {{activeMenu('admin.resourcerotas.index')}} {{activeMenu('admin.resourcerotas.calender-view')}}" aria-haspopup="true">
                        <a href="{{route('admin.resourcerotas.index')}}" class="menu-link">
                            <span class="svg-icon menu-icon"><i class="font-icon la la-cog"></i></span>
                            <span class="menu-text">Rota Management</span>
                        </a>
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
                        Gate::allows('lead_statuses_manage') ||
                        Gate::allows('appointment_statuses_manage') ||
                        Gate::allows('cancellation_reasons_manage')||
                        Gate::allows('resources_manage') ||
                        Gate::allows('logs_manage') ||
                        Gate::allows('finances_manage') ||
                        Gate::allows('invoices_manage') ||
                        Gate::allows('refunds_manage') ||
                        Gate::allows('pabao_records_manage') ||
                        Gate::allows('machineType_manage') ||
                        Gate::allows('towns_manage')

                    )

                    <li class="menu-item menu-item-submenu {{openMenu([
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
                        'admin.refunds.index',
                        'admin.sms_templates.index',
                        'admin.centre_targets.index',
                        'admin.doctors.index',
                        'admin.packagesadvances.index',
                        'admin.resourcerotas.calender-view',
                        'admin.invoices.index',
                        'admin.packages.log',
                        'admin.nonplansrefunds.index',
                        'admin.custom_form_feedbacks.index',
                        'admin.custom_form_feedbacks.edit',
                        'admin.custom_form_feedbacks.filled_preview',
                        'admin.custom_forms.index',
                        'admin.custom_forms.edit',
                        'admin.custom_form_feedbacks.preview_form',
                        'admin.custom_form_feedbacks.fill_form',
                        ])}}" aria-haspopup="true" data-menu-toggle="hover">

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
                                <li class="menu-item {{activeMenu('admin.settings.index')}}" aria-haspopup="true">
                                    <a href="{{route('admin.settings.index')}}" class="menu-link">
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
                                    <li class="menu-item {{activeMenu('admin.user_operator_settings.index')}}" aria-haspopup="true">
                                        <a href="{{route('admin.user_operator_settings.index')}}" class="menu-link">
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
                                    <li class="menu-item {{openMenu(['admin.payment_modes.index','admin.payment_modes.sort'],'menu-item-active')}}" aria-haspopup="true">
                                        <a href="{{route('admin.payment_modes.index')}}" class="menu-link">
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
                                    <li class="menu-item {{openMenu(['admin.regions.index','admin.regions.sort'],'menu-item-active')}}" aria-haspopup="true">
                                        <a href="{{route('admin.regions.index')}}" class="menu-link">
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
                                    <li class="menu-item {{openMenu(['admin.cities.index','admin.cities.sort'],'menu-item-active')}}" aria-haspopup="true">
                                        <a href="{{route('admin.cities.index')}}" class="menu-link">
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
                                <li class="menu-item {{activeMenu('admin.towns.index')}}" aria-haspopup="true">
                                    <a href="{{route('admin.towns.index')}}" class="menu-link">
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
                                    <li class="menu-item {{openMenu(['admin.lead_sources.index','admin.lead_sources.sort'],'menu-item-active')}}" aria-haspopup="true">
                                        <a href="{{route('admin.lead_sources.index')}}" class="menu-link">
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
                                    <li class="menu-item {{openMenu(['admin.lead_statuses.index','admin.lead_statuses.sort'],'menu-item-active')}}" aria-haspopup="true">
                                        <a href="{{route('admin.lead_statuses.index')}}" class="menu-link">
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
                                    <li class="menu-item {{activeMenu('admin.locations.index')}}" aria-haspopup="true">
                                        <a href="{{route('admin.locations.index')}}" class="menu-link">
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
                                    <li class="menu-item {{activeMenu('admin.appointment_statuses.index')}}" aria-haspopup="true">
                                        <a href="{{route('admin.appointment_statuses.index')}}" class="menu-link">
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
                                    <li class="menu-item {{activeMenu('admin.machine_types.index')}}" aria-haspopup="true">
                                        <a href="{{route('admin.machine_types.index')}}" class="menu-link">
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
                                    <li class="menu-item {{activeMenu('admin.resources.index')}}" aria-haspopup="true">
                                        <a href="{{route('admin.resources.index')}}" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Resource</span>
                                        </a>
                                    </li>

                                </ul>
                            </div>

                        @endcan

                        @can('logs_manage')
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item {{activeMenu('admin.logs.index')}}" aria-haspopup="true">
                                        <a href="{{route('admin.logs.index')}}" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Logs</span>

                                        </a>
                                    </li>

                                </ul>
                            </div>
                        @endcan

                        @can('sms_templates_manage')
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item {{activeMenu('admin.sms_templates.index')}}" aria-haspopup="true">
                                        <a href="{{route('admin.sms_templates.index')}}" class="menu-link">
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
                                    <li class="menu-item {{activeMenu('admin.centre_targets.index')}}" aria-haspopup="true">
                                        <a href="{{route('admin.centre_targets.index')}}" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Centre Targets</span>

                                        </a>
                                    </li>

                                </ul>
                            </div>
                        @endcan

                        @can('doctors_manage')
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                            <li class="menu-item {{activeMenu('admin.doctors.index')}}" aria-haspopup="true">
                                <a href="{{route('admin.doctors.index')}}" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Doctors</span>
                                </a>
                            </li>

                        </ul>
                            </div>
                        @endcan

                        @can('finances_manage')
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                            <li class="menu-item {{activeMenu('admin.packagesadvances.index')}}" aria-haspopup="true">
                                <a href="{{route('admin.packagesadvances.index')}}" class="menu-link">
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
                                    <li class="menu-item {{activeMenu('admin.invoices.index')}}" aria-haspopup="true">
                                        <a href="{{route('admin.invoices.index')}}" class="menu-link">
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
                                    <li class="menu-item {{activeMenu('admin.custom_form_feedbacks.index')}} {{activeMenu('admin.custom_form_feedbacks.filled_preview')}}" aria-haspopup="true">
                                        <a href="{{route('admin.custom_form_feedbacks.index')}}" class="menu-link">
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
                                    <li class="menu-item {{activeMenu('admin.custom_forms.index')}}" aria-haspopup="true">
                                        <a href="{{route('admin.custom_forms.index')}}" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Custom Form</span>
                                        </a>
                                    </li>

                                </ul>
                            </div>
                        @endcan

                        @can('refunds_manage')
                         <div class="menu-submenu">
                            <i class="menu-arrow"></i>
                            <ul class="menu-subnav">
                                <li class="menu-item  {{openMenu(['admin.refunds.index'])}} {{openMenu(['admin.nonplansrefunds.index'])}}" aria-haspopup="true" data-menu-toggle="hover">

                                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                                <span class="svg-icon menu-icon">
                                <i class="fas fa-paper-plane"></i>
                                </span>
                                        <span class="menu-text">Refunds</span>
                                        <i class="menu-arrow"></i>
                                    </a>
                                    <div class="menu-submenu">
                                        <i class="menu-arrow"></i>
                                        <ul class="menu-subnav">


                                                <li class="menu-item {{activeMenu('admin.refunds.index')}}" aria-haspopup="true">
                                                    <a href="{{route('admin.refunds.index')}}" class="menu-link">
                                                        <i class="menu-bullet menu-bullet-dot">
                                                            <span></span>
                                                        </i>
                                                        <span class="menu-text">Plan Refunds </span>
                                                    </a>
                                                </li>


                                                <li class="menu-item {{activeMenu('admin.nonplansrefunds.index')}}" aria-haspopup="true">
                                                    <a href="{{route('admin.nonplansrefunds.index')}}" class="menu-link">
                                                        <i class="menu-bullet menu-bullet-dot">
                                                            <span></span>
                                                        </i>
                                                        <span class="menu-text">Non Plan Refunds </span>
                                                    </a>
                                                </li>


                                        </ul>
                                    </div>

                                </li>
                            </ul>
                        </div>
                        @endcan

                    </li>

                    @endif


                     <!-- Inventory menu -->

                     @if(Gate::allows('inventory_manage'))
                        @can('inventory_manage')
                            <li class="menu-item menu-item-submenu {{openMenu([
                                'admin.brands.index'
                                ])}} {{openMenu([
                                'admin.products.index'
                                ])}} {{openMenu([
                                'admin.orders.index'
                                ])}} {{openMenu([
                                'admin.order.refunds.index'
                                ])}}"  aria-haspopup="true" data-menu-toggle="hover">

                                <a href="javascript:void(0);" class="menu-link menu-toggle">
                                <span class="svg-icon menu-icon fa_icon">
                                    <i class="la la-clock-o"></i>
                                </span>
                                    <span class="menu-text">Inventory</span>
                                    <i class="menu-arrow"></i>
                                </a>
                               @can('brand_manage')
                                    <div class="menu-submenu">
                                        <i class="menu-arrow"></i>
                                        <ul class="menu-subnav">
                                            <li class="menu-item {{activeMenu('admin.brands.index')}}" aria-haspopup="true">
                                                <a href="{{route('admin.brands.index')}}" class="menu-link">
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
                                            <li class="menu-item {{activeMenu('admin.products.index')}}" aria-haspopup="true">
                                                <a href="{{route('admin.products.index')}}" class="menu-link">
                                                    <i class="menu-bullet menu-bullet-dot">
                                                        <span></span>
                                                    </i>
                                                    <span class="menu-text">Product</span>
                                                </a>
                                            </li>

                                        </ul>
                                    </div>

                                @endcan
                                @can('order_manage')
                                    <div class="menu-submenu">
                                        <i class="menu-arrow"></i>
                                        <ul class="menu-subnav">
                                            <li class="menu-item {{activeMenu('admin.orders.index')}}" aria-haspopup="true">
                                                <a href="{{route('admin.orders.index')}}" class="menu-link">
                                                    <i class="menu-bullet menu-bullet-dot">
                                                        <span></span>
                                                    </i>
                                                    <span class="menu-text">Order</span>
                                                </a>
                                            </li>

                                        </ul>
                                    </div>

                                @endcan
                                @can('refund_manage')
                                    <div class="menu-submenu">
                                        <i class="menu-arrow"></i>
                                        <ul class="menu-subnav">
                                            <li class="menu-item {{activeMenu('admin.order.refunds.index')}}" aria-haspopup="true">
                                                <a href="{{route('admin.order.refunds.index')}}" class="menu-link">
                                                    <i class="menu-bullet menu-bullet-dot">
                                                        <span></span>
                                                    </i>
                                                    <span class="menu-text">Refund</span>
                                                </a>
                                            </li>

                                        </ul>
                                    </div>

                                @endcan
                            </li>
                        @endcan
                    @endif



                    <!-- End Inventory menu -->

                   
                        <li class="menu-item menu-item-submenu {{openMenu([
                            'admin.reports.finance_reports',
                            'admin.reports.operations_report'

                            ])}}" aria-haspopup="true" data-menu-toggle="hover">

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
                                        <li class="menu-item {{activeMenu('admin.reports.finance_reports')}}" aria-haspopup="true">
                                            <a href="{{route('admin.reports.finance_reports')}}" class="menu-link">
                                                <i class="menu-bullet menu-bullet-dot">
                                                    <span></span>
                                                </i>
                                                <span class="menu-text">General Revenue Report</span>
                                            </a>
                                        </li>

                                    </ul>
                                </div>
                            @endcan

                            @can('operations_reports_manage')
                                <div class="menu-submenu">
                                    <i class="menu-arrow"></i>
                                    <ul class="menu-subnav">
                                        <li class="menu-item {{activeMenu('admin.reports.operations_report')}}" aria-haspopup="true">
                                            <a href="{{route('admin.reports.operations_report')}}" class="menu-link">
                                                <i class="menu-bullet menu-bullet-dot">
                                                    <span></span>
                                                </i>
                                                <span class="menu-text">Operation Reports</span>
                                            </a>
                                        </li>

                                    </ul>
                                </div>
                                
                            @endcan
                            @can('non_converted_customers_manage')
                            <div class="menu-submenu">
                                     <i class="menu-arrow"></i>
                                     <ul class="menu-subnav">
                                         <li class="menu-item {{activeMenu('admin.reports.arrived_not_converted')}}" aria-haspopup="true">
                                             <a href="{{route('admin.reports.arrived_not_converted')}}" class="menu-link">
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
                                         <li class="menu-item {{activeMenu('admin.reports.conversion')}}" aria-haspopup="true">
                                             <a href="{{route('admin.reports.conversion')}}" class="menu-link">
                                                 <i class="menu-bullet menu-bullet-dot">
                                                     <span></span>
                                                 </i>
                                                 <span class="menu-text">Conversion Report </span>
                                             </a>
                                         </li>
 
                                     </ul>
                                 </div>
                                @endcan
                                @can('staff_wise_arrival_manage')
                                 <div class="menu-submenu">
                                    <i class="menu-arrow"></i>
                                    <ul class="menu-subnav">
                                        <li class="menu-item {{activeMenu('admin.reports.staff_wise_arrival')}}" aria-haspopup="true">
                                            <a href="{{route('admin.reports.staff_wise_arrival')}}" class="menu-link">
                                                <i class="menu-bullet menu-bullet-dot">
                                                    <span></span>
                                                </i>
                                                <span class="menu-text">Staff Wise Arrival Report </span>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                @endcan
                        </li>
                    

                </ul>
                <!--end::Menu Nav-->
            </div>
            <!--end::Menu Container-->
        </div>
        <!--end::Aside Menu-->
    </div>
    <!--end::Aside-->

