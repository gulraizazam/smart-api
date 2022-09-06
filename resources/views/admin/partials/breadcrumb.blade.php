<!--begin::Subheader-->
<div class="subheader py-2 py-lg-6 subheader-solid" id="kt_subheader" style="top:0;">
    <div class="container-fluid d-flex align-items-center justify-content-between flex-wrap flex-sm-nowrap">
        <!--begin::Info-->
        <div class="d-flex align-items-center flex-wrap mr-1">


            <!--begin::Page Heading-->
            <div class="d-flex align-items-baseline flex-wrap mr-5">
                <!--begin::Page Title-->
                <h5 class="text-dark font-weight-bold my-1 mr-5">{{$module ?? ''}}</h5>
                <!--end::Page Title-->
                <!--begin::Breadcrumb-->
                <ul class="breadcrumb breadcrumb-transparent breadcrumb-dot font-weight-bold p-0 my-2 font-size-sm">
                    <li class="breadcrumb-item text-muted">
                        <a href="{{route('admin.home')}}" class="text-muted">Home</a>
                    </li>
                    <li class="breadcrumb-item text-muted">
                        <a href="javascript:void(0);" class="text-muted">{{$title ?? ''}}</a>
                    </li>
                </ul>

                <!--end::Breadcrumb-->
            </div>
            <!--end::Page Heading-->


        </div>

        <!--begin::Topbar-->
        <div style="float: right" class="topbar">

            <!--begin::User-->
            <div class="topbar-item user-setting">
                <div class="btn btn-icon btn-icon-mobile w-auto btn-clean d-flex align-items-center btn-lg px-2" id="kt_quick_user_toggle">
                    <span class="symbol symbol-lg-35 symbol-25 symbol-light-success">
                        <span class="symbol-label font-size-h5 font-weight-bold">
                            <img style="width: 40px;" src="{{asset('assets/media/logos/avatar.jpg')}}" >
                        </span>
                    </span>


                    <div class="user-popup menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-primary fw-bold py-4 fs-6" data-kt-menu="true" style="display:none; z-index: 105; position: fixed; inset: 0px 0px auto auto; margin: 0px; transform: translate(-30px, 65px); width: auto !important;" data-popper-placement="bottom-end">
                        <!--begin::Menu item-->
                        <div class="menu-item px-3">
                            <div class="menu-content d-flex align-items-center px-3">
                                <!--begin::Avatar-->
                                <div class="symbol symbol-50px me-5">
                                    <img alt="Logo" src="{{asset('assets/media/logos/avatar.jpg')}}">
                                </div>
                                <!--end::Avatar-->
                                <!--begin::Username-->
                                <div class="d-flex flex-column">
                                    <div class="fw-bolder d-flex align-items-center fs-5">{{auth()->check() ? auth()->user()->name : ''}}</div>
                                    <a href="#" class="fw-bold text-muted text-hover-primary fs-7">{{auth()->check() ? auth()->user()->email : ''}}</a>
                                </div>
                                <!--end::Username-->
                            </div>
                        </div>
                        <!--end::Menu item-->
                        <!--begin::Menu separator-->
                        <div class="separator my-2"></div>
                        <!--end::Menu separator-->
                        <!--begin::Menu item-->
                        <div class="menu-item px-5">
                            <a href="{{route('admin.change_password')}}" class="menu-link px-5">My Profile</a>
                        </div>

                    {{--<div class="menu-item px-5 my-1">
                        <a href="javascript:void(0);" class="menu-link px-5">Account Settings</a>
                    </div>--}}
                    <!--end::Menu item-->
                        <!--begin::Menu item-->
                        <div class="menu-item px-5">
                            <a href="javascript:void(0);" onclick="document.getElementById('logout-form').submit();" class="menu-link px-5">Sign Out</a>
                            <form id="logout-form" action="{{route('logout')}}" method="post" class="d-none">
                                @csrf
                            </form>
                        </div>
                        <!--end::Menu item-->
                    </div>

                </div>
            </div>
            <!--end::User-->
        </div>
        <!--end::Topbar-->
        <!--end::Info-->

    </div>
</div>
<!--end::Subheader-->
