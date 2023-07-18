@extends('admin.layouts.master')
@section('title', 'Profile')
@section('content')

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        <!--begin::Subheader-->
        @include('admin.partials.breadcrumb', ['module' => 'Profile', 'title' => 'Change Password'])

    <!--end::Subheader-->
        <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">
                <!--begin::Profile Change Password-->
                <div class="d-flex flex-row">
                    <!--begin::Aside-->
                    <div class="flex-row-auto offcanvas-mobile w-250px w-xxl-350px" id="kt_profile_aside">
                        <!--begin::Profile Card-->
                        <div class="card card-custom card-stretch">
                            <!--begin::Body-->
                            <div class="card-body pt-4">

                                <!--begin::User-->
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-60 symbol-xxl-100 mr-5 align-self-start align-self-xxl-center">
                                        <div class="symbol-label" style="background-image:url('../assets/media/logos/avatar.jpg')"></div>
                                        <i class="symbol-badge bg-success"></i>
                                    </div>
                                    <div>
                                        <a href="#" class="font-weight-bolder font-size-h5 text-dark-75 text-hover-primary">{{auth()->user()->name ?? ''}}</a>
                                        <div class="text-muted">{{auth()->user()->getRoles()}}</div>
                                    </div>
                                </div>
                                <!--end::User-->
                                <!--begin::Contact-->
                                <div class="py-9">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="font-weight-bold mr-2">Email:</span>
                                        <a href="#" class="text-muted text-hover-primary">{{auth()->user()->email ?? ''}}</a>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="font-weight-bold mr-2">Phone:</span>
                                        <span class="text-muted">{{auth()->user()->phone ?? ''}}</span>
                                    </div>

                                </div>
                                <!--end::Contact-->

                            </div>
                            <!--end::Body-->
                        </div>
                        <!--end::Profile Card-->
                    </div>
                    <!--end::Aside-->
                    <!--begin::Content-->
                    <div class="flex-row-fluid ml-lg-8">
                        <!--begin::Card-->
                        <form id="kt_form_1" class="form" method="post" action="{{route('admin.update_password')}}">
                            <div class="card card-custom">

                                <!--begin::Header-->
                                <div class="card-header py-3">
                                    <div class="card-title align-items-start flex-column">
                                        <h3 class="card-label font-weight-bolder text-dark">Change Password</h3>
                                        <span class="text-muted font-weight-bold font-size-sm mt-1">Change your account password</span>
                                    </div>
                                    <div class="card-toolbar">
                                        <button type="submit" class="btn btn-success mr-2">Save Changes</button>
                                        <a href="{{route('admin.home')}}" class="btn btn-secondary">Cancel</a>
                                    </div>
                                </div>
                                <!--end::Header-->
                                <!--begin::Form-->

                                <div class="card-body">

                                    <!--begin::Alert-->
                                    <div class="alert alert-custom alert-light-danger fade show mb-10 profile-message d-none" role="alert">
                                        <div class="alert-icon">
                                            <span class="svg-icon svg-icon-3x svg-icon-danger">
                                                <!--begin::Svg Icon | path:assets/media/svg/icons/Code/Info-circle.svg-->
                                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
																		<rect x="0" y="0" width="24" height="24" />
																		<circle fill="#000000" opacity="0.3" cx="12" cy="12" r="10" />
																		<rect fill="#000000" x="11" y="10" width="2" height="7" rx="1" />
																		<rect fill="#000000" x="11" y="7" width="2" height="2" rx="1" />
																	</g>
                                                </svg>
                                                <!--end::Svg Icon-->
                                            </span>
                                        </div>
                                        <div class="alert-text font-weight-bold message-body"></div>
                                        <div class="alert-close">
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">
                                                    <i class="ki ki-close"></i>
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                    <!--end::Alert-->

                                    <div class="form-group row">
                                        <label class="col-xl-3 col-lg-3 col-form-label text-alert">Current Password</label>
                                        <div class="col-lg-9 col-xl-6">
                                            <input type="password" name="current_password" class="form-control form-control-lg form-control-solid mb-2" value="" placeholder="Current password" />
                                            {{--<a href="#" class="text-sm font-weight-bold">Forgot password ?</a>--}}
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-xl-3 col-lg-3 col-form-label text-alert">New Password</label>
                                        <div class="col-lg-9 col-xl-6">
                                            <input type="password" name="new_password" class="form-control form-control-lg form-control-solid" value="" placeholder="New password" />
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-xl-3 col-lg-3 col-form-label text-alert">Verify Password</label>
                                        <div class="col-lg-9 col-xl-6">
                                            <input type="password" name="new_password_confirmation" class="form-control form-control-lg form-control-solid" value="" placeholder="Verify password" />
                                        </div>
                                    </div>
                                </div>

                            <!--end::Form-->
                            </div>
                        </form>
                    </div>
                    <!--end::Content-->
                </div>
                <!--end::Profile Change Password-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->

    @push('js')
        <script src="{{asset('assets/js/pages/crud/forms/validation/form-controls.js')}}"></script>
    @endpush

@endsection
