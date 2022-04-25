{{--Menu--}}
<div class="card mb-8 menu_section" style="width: 100%">

    <div class="card-body menu-card">
        <ul class="horizontal-nav-bar list-unstyled mb-0 appointment-menu">

            @can('appointments_manage')
                <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0)" onclick="toggleSection($(this), 'appointment');" class="change-tab appointment-tab navi-link py-4">
                         <span class="text-muted mb-2 fa_icon">
                             <i class="la la-handshake-o"></i>
                        </span>
                        <p class="navi-text">Appointments</p>
                    </a>
                </li>
            @endcan

            @can('appointments_consultancy')
                <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0)" onclick="toggleSection($(this), 'consultancy');" class="change-tab consultancy-tab navi-link py-4">
                         <span class="text-muted mb-2 fa_icon">
                             <i class="la la-clock"></i>
                        </span>
                        <p class="navi-text">Manage Consultancy</p>
                    </a>
                </li>
            @endcan

            @can("appointments_services")
                <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0)" onclick="toggleSection($(this), 'treatment');" class="change-tab treatment-tab navi-link py-4">

                        <span class="text-muted mb-2 fa_icon">
                            <i class="la la-medkit"></i>
                        </span>
                        <p class="navi-text font-size-lg">Manage Treatment</p>

                    </a>
                </li>
            @endcan

        </ul>
    </div>

</div>
{{--End Menu--}}
