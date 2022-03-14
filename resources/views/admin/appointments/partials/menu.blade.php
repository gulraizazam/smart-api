{{--Menu--}}
<div class="card mb-8" style="width: 100%">

    <div class="card-body menu-card">
        <ul class="horizontal-nav-bar list-unstyled mb-0 appointment-menu">

            <li class="horizontal-nav-bar-li">

                <a href="javascript:void(0);" class="change-tab personal-info navi-link py-4 active">
                     <span class="text-muted mb-2 fa_icon">
                    <i class="la la-file-export"></i>
                    </span>
                    <p class="navi-text ">Export</p>
                </a>
            </li>
            @can('patients_appointment_manage')
                <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0)" class="change-tab appointment-form-tab navi-link py-4">
                         <span class="text-muted mb-2 fa_icon">
                             <i class="la la-clock"></i>
                        </span>
                        <p class="navi-text">Manage Consultancy</p>
                    </a>
                </li>
            @endcan

            @can("patients_customform_manage")
                <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0)" class="change-tab custom-form-tab navi-link py-4">

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
