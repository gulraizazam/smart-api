
    <!--begin::Aside-->
    <div class="aside aside-left aside-fixed d-flex flex-column flex-row-auto" id="kt_aside">
        <!--begin::Brand-->
        <div class="brand flex-column-auto" id="kt_brand">
            <!--begin::Logo-->
            <a href="<?php echo e(route('admin.home')); ?>" class="brand-logo">
                <img style="width: 120px;margin-left:25px" alt="Logo" src="<?php echo e(asset('logo.png')); ?>" />
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
                <!--begin::Menu Nav-->
                <ul class="menu-nav">
                    <li class="menu-item <?php echo e(activeMenu('admin.home')); ?>" aria-haspopup="true">
                        <a href="<?php echo e(route('admin.home')); ?>" class="menu-link">
                                    <span class="svg-icon menu-icon">

                                       
                                        <i class="font-icon la la-home"></i>

                                    </span>
                            <span class="menu-text">Dashboard</span>
                        </a>
                    </li>

                    <?php if(Gate::allows('permissions_manage') || Gate::allows('roles_manage') || Gate::allows('users_manage') || Gate::allows('user_types_manage')): ?>
                        <li class="menu-item menu-item-submenu <?php echo e(openMenu(['admin.permissions.index', 'admin.users.index', 'admin.roles.index', 'admin.roles.edit', 'admin.users.index', 'admin.user_types.index'])); ?>" aria-haspopup="true" data-menu-toggle="hover">
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

                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('permissions_manage')): ?>

                                    <li class="menu-item <?php echo e(activeMenu('admin.permissions.index')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.permissions.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Permissions</span>
                                        </a>
                                    </li>
                                    <?php endif; ?>

                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('roles_manage')): ?>
                                        <li class="menu-item <?php echo e(activeMenu('admin.roles.index')); ?> <?php echo e(activeMenu('admin.roles.edit')); ?>" aria-haspopup="true">
                                            <a href="<?php echo e(route('admin.roles.index')); ?>" class="menu-link">
                                                <i class="menu-bullet menu-bullet-dot">
                                                    <span></span>
                                                </i>
                                                <span class="menu-text">Roles</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('users_manage')): ?>
                                        <li class="menu-item <?php echo e(activeMenu('admin.users.index')); ?>" aria-haspopup="true">
                                            <a href="<?php echo e(route('admin.users.index')); ?>" class="menu-link">
                                                <i class="menu-bullet menu-bullet-dot">
                                                    <span></span>
                                                </i>
                                                <span class="menu-text">Users</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('user_types_manage')): ?>
                                        <li class="menu-item <?php echo e(activeMenu('admin.user_types.index')); ?>" aria-haspopup="true">
                                            <a href="<?php echo e(route('admin.user_types.index')); ?>" class="menu-link">
                                                <i class="menu-bullet menu-bullet-dot">
                                                    <span></span>
                                                </i>
                                                <span class="menu-text">User Types</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                </ul>
                            </div>
                        </li>
                    <?php endif; ?>


                    <!--Patient menu-->

                    <?php if(Gate::allows('patients_manage')): ?>

                        <li class="menu-item menu-item-submenu <?php echo e(openMenu(['admin.patients.index', 'admin.patients.preview'])); ?>" aria-haspopup="true" data-menu-toggle="hover">

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
                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('patients_manage')): ?>
                                        <li class="menu-item <?php echo e(activeMenu('admin.patients.index')); ?> <?php echo e(activeMenu('admin.patients.preview')); ?>" aria-haspopup="true">
                                            <a href="<?php echo e(route('admin.patients.index')); ?>" class="menu-link">
                                                <i class="menu-bullet menu-bullet-dot">
                                                    <span></span>
                                                </i>
                                                <span class="menu-text">Patients</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                </ul>
                            </div>

                        </li>

                    <?php endif; ?>

                    <!-- Leads menu -->

                    <?php if(Gate::allows('leads_manage')): ?>

                    <li class="menu-item menu-item-submenu <?php echo e(openMenu(['admin.leads.index'])); ?>" aria-haspopup="true" data-menu-toggle="hover">

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

                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('leads_create')): ?>
                                    <li class="menu-item <?php echo e(isActive(url('admin/leads?create=create'), 'create')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.leads.index', ['create' => 'create'])); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Create Lead </span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('leads_manage')): ?>
                                <li class="menu-item <?php echo e(isActive(url('admin/leads'), 'other')); ?>" aria-haspopup="true">
                                    <a href="<?php echo e(route('admin.leads.index')); ?>" class="menu-link">
                                        <i class="menu-bullet menu-bullet-dot">
                                            <span></span>
                                        </i>
                                        <span class="menu-text">Leads </span>
                                    </a>
                                </li>
                            <?php endif; ?>

                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('leads_junk')): ?>
                                    <li class="menu-item <?php echo e(isActive(url('admin/leads?type=junk'), 'junk')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.leads.index', ['type' => 'junk'])); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Junk Leads</span>
                                        </a>
                                    </li>
                                <?php endif; ?>


                            </ul>
                        </div>

                        </li>

                    <?php endif; ?>

                    <!-- End leads menu -->

                    <!-- Appointment menu -->

                    <?php if(Gate::allows('appointments_manage')): ?>

                        <li class="menu-item menu-item-submenu <?php echo e(openMenu(['admin.consultancy.index'])); ?> <?php echo e(openMenu(['admin.treatment.index'])); ?> <?php echo e(openMenu(['admin.appointments.index'])); ?>" aria-haspopup="true" data-menu-toggle="hover">

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

                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('appointments_consultancy')): ?>
                                        <li class="menu-item manage-consultancy <?php echo e(activeMenu('admin.consultancy.index')); ?>" aria-haspopup="true">
                                            <a href="<?php echo e(route('admin.consultancy.index')); ?>" class="menu-link">
                                                <i class="menu-bullet menu-bullet-dot">
                                                    <span></span>
                                                </i>
                                                <span class="menu-text">Consultancies</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('appointments_services')): ?>
                                        <li class="menu-item manage-treatment <?php echo e(activeMenu('admin.treatment.index')); ?>" aria-haspopup="true">
                                            <a href="<?php echo e(route('admin.treatment.index')); ?>" class="menu-link">
                                                <i class="menu-bullet menu-bullet-dot">
                                                    <span></span>
                                                </i>
                                                <span class="menu-text">Treatments</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                </ul>
                            </div>

                        </li>

                    <?php endif; ?>

                <!-- End Appointment menu -->

                <?php if(Gate::allows('plans_manage')): ?>
                    <li class="menu-item <?php echo e(activeMenu('admin.packages.index')); ?>" aria-haspopup="true">
                        <a href="<?php echo e(route('admin.packages.index')); ?>" class="menu-link">
                            <span class="svg-icon menu-icon"><i class="font-icon la la-cog"></i></span>
                            <span class="menu-text"><?php echo app('translator')->get('global.packages.title'); ?></span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if( Gate::allows('services_manage') || Gate::allows('packages_manage') || Gate::allows('discounts_manage')): ?>

                    <li class="menu-item menu-item-submenu <?php echo e(openMenu(['admin.services.index'])); ?> <?php echo e(openMenu(['admin.bundles.index'])); ?> <?php echo e(openMenu(['admin.discounts.index'])); ?>" aria-haspopup="true" data-menu-toggle="hover">

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

                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('services_manage')): ?>
                                    <li class="menu-item manage-consultancy <?php echo e(activeMenu('admin.services.index')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.services.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Services</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('packages_manage')): ?>
                                    <li class="menu-item manage-treatment <?php echo e(activeMenu('admin.bundles.index')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.bundles.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Packages</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('discounts_manage')): ?>
                                    <li class="menu-item manage-treatment <?php echo e(activeMenu('admin.discounts.index')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.discounts.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Discounts</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                            </ul>
                        </div>

                    </li>

                <?php endif; ?>

                <?php if(Gate::allows('resourcerotas_manage')): ?>
                    <li class="menu-item <?php echo e(activeMenu('admin.resourcerotas.index')); ?> <?php echo e(activeMenu('admin.resourcerotas.calender-view')); ?>" aria-haspopup="true">
                        <a href="<?php echo e(route('admin.resourcerotas.index')); ?>" class="menu-link">
                            <span class="svg-icon menu-icon"><i class="font-icon la la-cog"></i></span>
                            <span class="menu-text">Rota Management</span>
                        </a>
                    </li>
                <?php endif; ?>

                    <?php if(
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

                    ): ?>

                    <li class="menu-item menu-item-submenu <?php echo e(openMenu([
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
                        ])); ?>" aria-haspopup="true" data-menu-toggle="hover">

                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <span class="svg-icon menu-icon">
                                <i class="font-icon la la-cog"></i>
                            </span>
                            <span class="menu-text">Admin Settings</span>
                            <i class="menu-arrow"></i>
                        </a>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('settings_manage')): ?>
                            <div class="menu-submenu">
                            <i class="menu-arrow"></i>
                            <ul class="menu-subnav">
                                <li class="menu-item <?php echo e(activeMenu('admin.settings.index')); ?>" aria-haspopup="true">
                                    <a href="<?php echo e(route('admin.settings.index')); ?>" class="menu-link">
                                        <i class="menu-bullet menu-bullet-dot">
                                            <span></span>
                                        </i>
                                        <span class="menu-text">Global Settings</span>
                                    </a>
                                </li>

                            </ul>
                        </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('user_operator_settings_manage')): ?>
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item <?php echo e(activeMenu('admin.user_operator_settings.index')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.user_operator_settings.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Operator Settings</span>
                                        </a>
                                    </li>

                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('payment_modes_manage')): ?>
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item <?php echo e(openMenu(['admin.payment_modes.index','admin.payment_modes.sort'],'menu-item-active')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.payment_modes.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Payment Modes</span>
                                        </a>
                                    </li>

                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('regions_manage')): ?>
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item <?php echo e(openMenu(['admin.regions.index','admin.regions.sort'],'menu-item-active')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.regions.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Regions</span>
                                        </a>
                                    </li>

                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('cities_manage')): ?>
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item <?php echo e(openMenu(['admin.cities.index','admin.cities.sort'],'menu-item-active')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.cities.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Cities</span>
                                        </a>
                                    </li>

                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('towns_manage')): ?>
                            <div class="menu-submenu">
                            <i class="menu-arrow"></i>
                            <ul class="menu-subnav">
                                <li class="menu-item <?php echo e(activeMenu('admin.towns.index')); ?>" aria-haspopup="true">
                                    <a href="<?php echo e(route('admin.towns.index')); ?>" class="menu-link">
                                        <i class="menu-bullet menu-bullet-dot">
                                            <span></span>
                                        </i>
                                        <span class="menu-text">Towns</span>
                                    </a>
                                </li>

                            </ul>
                        </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('lead_sources_manage')): ?>

                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item <?php echo e(openMenu(['admin.lead_sources.index','admin.lead_sources.sort'],'menu-item-active')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.lead_sources.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Lead Sources</span>
                                        </a>
                                    </li>

                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('lead_statuses_manage')): ?>
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item <?php echo e(openMenu(['admin.lead_statuses.index','admin.lead_statuses.sort'],'menu-item-active')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.lead_statuses.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Lead Statuses</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('locations_manage')): ?>

                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item <?php echo e(activeMenu('admin.locations.index')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.locations.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Centres</span>
                                        </a>
                                    </li>

                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('appointment_statuses_manage')): ?>
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item <?php echo e(activeMenu('admin.appointment_statuses.index')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.appointment_statuses.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Appointment Statuses</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('machineType_manage')): ?>
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item <?php echo e(activeMenu('admin.machine_types.index')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.machine_types.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Machine Type</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('resources_manage')): ?>

                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item <?php echo e(activeMenu('admin.resources.index')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.resources.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Resource</span>
                                        </a>
                                    </li>

                                </ul>
                            </div>

                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('logs_manage')): ?>
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item <?php echo e(activeMenu('admin.logs.index')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.logs.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Logs</span>

                                        </a>
                                    </li>

                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('sms_templates_manage')): ?>
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item <?php echo e(activeMenu('admin.sms_templates.index')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.sms_templates.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">SMS Templates</span>
                                        </a>
                                    </li>

                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('centre_targets_manage')): ?>
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item <?php echo e(activeMenu('admin.centre_targets.index')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.centre_targets.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Centre Targets</span>

                                        </a>
                                    </li>

                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('doctors_manage')): ?>
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                            <li class="menu-item <?php echo e(activeMenu('admin.doctors.index')); ?>" aria-haspopup="true">
                                <a href="<?php echo e(route('admin.doctors.index')); ?>" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Doctors</span>
                                </a>
                            </li>

                        </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('finances_manage')): ?>
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                            <li class="menu-item <?php echo e(activeMenu('admin.packagesadvances.index')); ?>" aria-haspopup="true">
                                <a href="<?php echo e(route('admin.packagesadvances.index')); ?>" class="menu-link">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Finances</span>
                                </a>
                            </li>

                        </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('invoices_manage')): ?>
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item <?php echo e(activeMenu('admin.invoices.index')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.invoices.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Invoices</span>
                                        </a>
                                    </li>

                                </ul>
                            </div>
                        <?php endif; ?>


                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('custom_form_feedbacks_manage')): ?>
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item <?php echo e(activeMenu('admin.custom_form_feedbacks.index')); ?> <?php echo e(activeMenu('admin.custom_form_feedbacks.filled_preview')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.custom_form_feedbacks.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Custom Form Feedbacks</span>
                                        </a>
                                    </li>

                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('custom_forms_manage')): ?>
                            <div class="menu-submenu">
                                <i class="menu-arrow"></i>
                                <ul class="menu-subnav">
                                    <li class="menu-item <?php echo e(activeMenu('admin.custom_forms.index')); ?>" aria-haspopup="true">
                                        <a href="<?php echo e(route('admin.custom_forms.index')); ?>" class="menu-link">
                                            <i class="menu-bullet menu-bullet-dot">
                                                <span></span>
                                            </i>
                                            <span class="menu-text">Custom Form</span>
                                        </a>
                                    </li>

                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('refunds_manage')): ?>
                         <div class="menu-submenu">
                            <i class="menu-arrow"></i>
                            <ul class="menu-subnav">
                                <li class="menu-item  <?php echo e(openMenu(['admin.refunds.index'])); ?> <?php echo e(openMenu(['admin.nonplansrefunds.index'])); ?>" aria-haspopup="true" data-menu-toggle="hover">

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


                                                <li class="menu-item <?php echo e(activeMenu('admin.refunds.index')); ?>" aria-haspopup="true">
                                                    <a href="<?php echo e(route('admin.refunds.index')); ?>" class="menu-link">
                                                        <i class="menu-bullet menu-bullet-dot">
                                                            <span></span>
                                                        </i>
                                                        <span class="menu-text">Plan Refunds </span>
                                                    </a>
                                                </li>


                                                <li class="menu-item <?php echo e(activeMenu('admin.nonplansrefunds.index')); ?>" aria-haspopup="true">
                                                    <a href="<?php echo e(route('admin.nonplansrefunds.index')); ?>" class="menu-link">
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
                        <?php endif; ?>

                    </li>

                    <?php endif; ?>


                     <!-- Inventory menu -->

                     <?php if(Gate::allows('inventory_manage')): ?>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('inventory_manage')): ?>
                            <li class="menu-item menu-item-submenu <?php echo e(openMenu([
                                'admin.brands.index'
                                ])); ?> <?php echo e(openMenu([
                                'admin.products.index'
                                ])); ?> <?php echo e(openMenu([
                                'admin.orders.index'
                                ])); ?> <?php echo e(openMenu([
                                'admin.order.refunds.index'
                                ])); ?>"  aria-haspopup="true" data-menu-toggle="hover">

                                <a href="javascript:void(0);" class="menu-link menu-toggle">
                                <span class="svg-icon menu-icon fa_icon">
                                    <i class="la la-clock-o"></i>
                                </span>
                                    <span class="menu-text">Inventory</span>
                                    <i class="menu-arrow"></i>
                                </a>
                               <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('brand_manage')): ?>
                                    <div class="menu-submenu">
                                        <i class="menu-arrow"></i>
                                        <ul class="menu-subnav">
                                            <li class="menu-item <?php echo e(activeMenu('admin.brands.index')); ?>" aria-haspopup="true">
                                                <a href="<?php echo e(route('admin.brands.index')); ?>" class="menu-link">
                                                    <i class="menu-bullet menu-bullet-dot">
                                                        <span></span>
                                                    </i>
                                                    <span class="menu-text">Brand</span>
                                                </a>
                                            </li>

                                        </ul>
                                    </div>
                                            
                                <?php endif; ?>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('product_manage')): ?>
                                    <div class="menu-submenu">
                                        <i class="menu-arrow"></i>
                                        <ul class="menu-subnav">
                                            <li class="menu-item <?php echo e(activeMenu('admin.products.index')); ?>" aria-haspopup="true">
                                                <a href="<?php echo e(route('admin.products.index')); ?>" class="menu-link">
                                                    <i class="menu-bullet menu-bullet-dot">
                                                        <span></span>
                                                    </i>
                                                    <span class="menu-text">Product</span>
                                                </a>
                                            </li>

                                        </ul>
                                    </div>
                                            
                                <?php endif; ?>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('order_manage')): ?>
                                    <div class="menu-submenu">
                                        <i class="menu-arrow"></i>
                                        <ul class="menu-subnav">
                                            <li class="menu-item <?php echo e(activeMenu('admin.orders.index')); ?>" aria-haspopup="true">
                                                <a href="<?php echo e(route('admin.orders.index')); ?>" class="menu-link">
                                                    <i class="menu-bullet menu-bullet-dot">
                                                        <span></span>
                                                    </i>
                                                    <span class="menu-text">Order</span>
                                                </a>
                                            </li>

                                        </ul>
                                    </div>
                                            
                                <?php endif; ?>    
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('refund_manage')): ?>
                                    <div class="menu-submenu">
                                        <i class="menu-arrow"></i>
                                        <ul class="menu-subnav">
                                            <li class="menu-item <?php echo e(activeMenu('admin.order.refunds.index')); ?>" aria-haspopup="true">
                                                <a href="<?php echo e(route('admin.order.refunds.index')); ?>" class="menu-link">
                                                    <i class="menu-bullet menu-bullet-dot">
                                                        <span></span>
                                                    </i>
                                                    <span class="menu-text">Refund</span>
                                                </a>
                                            </li>

                                        </ul>
                                    </div>
                                            
                                <?php endif; ?>        
                            </li>            
                        <?php endif; ?>
                    <?php endif; ?>

                   

                    <!-- End Inventory menu -->

                    <?php if(
                       Gate::allows('finance_general_revenue_reports_manage')
                    ): ?>
                        <li class="menu-item menu-item-submenu <?php echo e(openMenu([
                            'admin.reports.finance_reports',
                            'admin.reports.operations_report'

                            ])); ?>" aria-haspopup="true" data-menu-toggle="hover">

                            <a href="javascript:void(0);" class="menu-link menu-toggle">
                                <span class="svg-icon menu-icon">
                                    <i class="font-icon la la-file-text-o"></i>
                                </span>
                                <span class="menu-text">Reports Management</span>
                                <i class="menu-arrow"></i>
                            </a>

                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('finance_general_revenue_reports_manage')): ?>
                                <div class="menu-submenu">
                                    <i class="menu-arrow"></i>
                                    <ul class="menu-subnav">
                                        <li class="menu-item <?php echo e(activeMenu('admin.reports.finance_reports')); ?>" aria-haspopup="true">
                                            <a href="<?php echo e(route('admin.reports.finance_reports')); ?>" class="menu-link">
                                                <i class="menu-bullet menu-bullet-dot">
                                                    <span></span>
                                                </i>
                                                <span class="menu-text">General Revenue Report</span>
                                            </a>
                                        </li>

                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('operations_reports_manage')): ?>
                                <div class="menu-submenu">
                                    <i class="menu-arrow"></i>
                                    <ul class="menu-subnav">
                                        <li class="menu-item <?php echo e(activeMenu('admin.reports.operations_report')); ?>" aria-haspopup="true">
                                            <a href="<?php echo e(route('admin.reports.operations_report')); ?>" class="menu-link">
                                                <i class="menu-bullet menu-bullet-dot">
                                                    <span></span>
                                                </i>
                                                <span class="menu-text">Operation Reports</span>
                                            </a>
                                        </li>

                                    </ul>
                                </div>
                            <?php endif; ?>

                        </li>
                    <?php endif; ?>

                </ul>
                <!--end::Menu Nav-->
            </div>
            <!--end::Menu Container-->
        </div>
        <!--end::Aside Menu-->
    </div>
    <!--end::Aside-->

<?php /**PATH /var/www/html/resources/views/admin/partials/sidebar.blade.php ENDPATH**/ ?>