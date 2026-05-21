<!--begin::Header Mobile-->
<div id="kt_header_mobile" class="header-mobile align-items-center header-mobile-fixed">
    <!--begin::Logo-->
    <a href="{{route('admin.home')}}">
        <img style="width: 130px;" alt="Logo" src="{{asset('logo.png')}}" />
    </a>
    <!--end::Logo-->
    <!--begin::Toolbar-->
    <div class="d-flex align-items-center">
        <!--begin::Aside Mobile Toggle (hamburger)-->
        <button class="btn p-0 burger-icon burger-icon-left" id="kt_aside_mobile_toggle">
            <span></span>
        </button>
        <!--end::Aside Mobile Toggle-->

        <!--begin::Cashflow Notifications-->
        @can('cashflow.manage')
        <div class="position-relative ml-3" id="m_cashflow_bell">
            <button class="btn btn-hover-text-primary p-0 position-relative" id="m_cashflow_toggle">
                <i class="la la-bell" style="font-size:20px;"></i>
                <span class="badge badge-danger badge-pill position-absolute" style="top:-6px;right:-8px;font-size:9px;display:none;" id="m-cf-count">0</span>
            </button>
            <div id="m_cashflow_dropdown" style="display:none;position:absolute;top:100%;right:-20px;width:300px;max-height:380px;overflow-y:auto;z-index:1060;background:#fff;border-radius:6px;box-shadow:0 5px 20px rgba(0,0,0,.2);margin-top:8px;">
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom bg-light">
                    <h6 class="mb-0 font-size-sm">Cash Flow Notifications</h6>
                    <a href="javascript:;" id="m-cf-mark-read" class="text-primary font-size-xs">Mark all read</a>
                </div>
                <div id="m-cf-list" class="p-0">
                    <div class="text-center text-muted py-4 font-size-sm">No notifications</div>
                </div>
            </div>
        </div>
        @endcan
        <!--end::Cashflow Notifications-->

        <!--begin::HR Notifications-->
        <div class="position-relative ml-3" id="m_hr_bell">
            <button class="btn btn-hover-text-primary p-0 position-relative" id="m_hr_toggle">
                <i class="la la-bell-o" style="font-size:20px;"></i>
                <span class="badge badge-warning badge-pill position-absolute" style="top:-6px;right:-8px;font-size:9px;display:none;" id="m-hr-count">0</span>
            </button>
            <div id="m_hr_dropdown" style="display:none;position:absolute;top:100%;right:-10px;width:300px;max-height:380px;overflow-y:auto;z-index:1060;background:#fff;border-radius:6px;box-shadow:0 5px 20px rgba(0,0,0,.2);margin-top:8px;">
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom bg-light">
                    <h6 class="mb-0 font-size-sm">HR Notifications</h6>
                    <a href="javascript:;" id="m-hr-mark-read" class="text-primary font-size-xs">Mark all read</a>
                </div>
                <div id="m-hr-list" class="p-0">
                    <div class="text-center text-muted py-4 font-size-sm">No notifications</div>
                </div>
            </div>
        </div>
        <!--end::HR Notifications-->

        <!--begin::User Menu-->
        <div class="position-relative ml-3" id="mobile_user_menu">
            <button class="btn btn-hover-text-primary p-0" id="mobile_user_toggle">
                <span class="svg-icon svg-icon-xl">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                            <polygon points="0 0 24 0 24 24 0 24" />
                            <path d="M12,11 C9.790861,11 8,9.209139 8,7 C8,4.790861 9.790861,3 12,3 C14.209139,3 16,4.790861 16,7 C16,9.209139 14.209139,11 12,11 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" />
                            <path d="M3.00065168,20.1992055 C3.38825852,15.4265159 7.26191235,13 11.9833413,13 C16.7712164,13 20.7048837,15.2931929 20.9979143,20.2 C21.0095879,20.3954741 20.9979143,21 20.2466999,21 C16.541124,21 11.0347247,21 3.72750223,21 C3.47671215,21 2.97953825,20.45918 3.00065168,20.1992055 Z" fill="#000000" fill-rule="nonzero" />
                        </g>
                    </svg>
                </span>
            </button>
            <div id="mobile_user_dropdown" style="display:none;position:absolute;top:100%;right:0;width:200px;z-index:1060;background:#fff;border-radius:6px;box-shadow:0 5px 20px rgba(0,0,0,.2);margin-top:8px;">
                <div class="px-4 py-3 border-bottom">
                    <div class="font-weight-bold font-size-sm">{{ auth()->user()->name ?? '' }}</div>
                    <div class="text-muted font-size-xs text-truncate">{{ auth()->user()->email ?? '' }}</div>
                </div>
                <div class="py-2">
                    <a href="{{ route('admin.hr.my.profile') }}" class="d-flex align-items-center px-4 py-2 text-dark text-hover-primary">
                        <i class="la la-user mr-3 text-muted" style="font-size:18px;"></i>My Profile
                    </a>
                    <a href="{{ route('admin.hr.my.leaves') }}" class="d-flex align-items-center px-4 py-2 text-dark text-hover-primary">
                        <i class="la la-calendar-check mr-3 text-muted" style="font-size:18px;"></i>My Leaves
                    </a>
                    <div class="border-top my-1"></div>
                    <a href="javascript:void(0);" onclick="document.getElementById('mobile-logout-form').submit();" class="d-flex align-items-center px-4 py-2 text-danger">
                        <i class="la la-sign-out mr-3" style="font-size:18px;"></i>Sign Out
                    </a>
                    <form id="mobile-logout-form" action="{{ route('logout') }}" method="post" class="d-none">@csrf</form>
                </div>
            </div>
        </div>
        <!--end::User Menu-->
    </div>
    <!--end::Toolbar-->
</div>
<!--end::Header Mobile-->
