@extends('layouts.app')
@section('title', 'Reset Password')
@section('content')

    <!--begin::Main-->
    <div class="d-flex flex-column flex-root">
        <!--begin::Authentication - Password reset -->
        <div class="d-flex flex-column flex-column-fluid bgi-position-y-bottom position-x-center bgi-no-repeat bgi-size-contain bgi-attachment-fixed" style="background-image: url(../../assets/media/logos/14.png">
            <!--begin::Content-->
            <div class="d-flex flex-center flex-column flex-column-fluid p-10 pb-lg-20">
                <!--begin::Logo-->
                <a href="/" class="mb-12">
                    <img alt="Logo" src="{{asset('assets/media/logos/smart.svg')}}" style="height: 80px; width: 269px;" />
                </a>
                <!--end::Logo-->
                <!--begin::Wrapper-->
                <div class="w-lg-500px bg-body rounded shadow-sm p-10 p-lg-15 mx-auto">
                    <!--begin::Form-->
                    <form class="form w-100" novalidate="novalidate" id="kt_password_reset_form"  method="POST"  action="{{ route('auth.password.resettoken') }}">
                    @csrf

                        <input type="hidden" name="token" value="{{ $token }}">

                    <!--begin::Heading-->
                        <div class="text-center mb-10">
                            <!--begin::Title-->
                            <h1 class="text-dark mb-3">Reset Password</h1>
                            <!--end::Title-->
                            <!--begin::Link-->
                            <div class="text-gray-400 fw-bold fs-4">Enter your new password.</div>
                            <!--end::Link-->
                        </div>

                        @if($expiry)
                            <div class="alert alert-danger" role="alert">
                                Your token has been expired.
                            </div>
                        @endif

                        @if(session()->has('success'))
                            <div class="alert alert-success" role="alert">
                                {{ session('success') }}
                            </div>
                        @endif
                        @if(session()->has('error'))
                            <div class="alert alert-danger" role="alert">
                                {{ session('error') }}
                            </div>
                    @endif

                        @if(!$expiry)
                            <!--begin::Heading-->
                            <!--begin::Input group-->
                            <div class="fv-row mb-10">
                                <label class="form-label fw-bolder text-gray-900 fs-6">Email</label>
                                <input class="form-control form-control-solid" value="{{old('email', request('email'))}}" type="email" placeholder="" name="email" autocomplete="off" />
                                @error('email')
                                <div class="alert alert-danger" role="alert">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            <div class="fv-row mb-10">
                                <label class="form-label fw-bolder text-gray-900 fs-6">Password</label>
                                <input class="form-control form-control-solid" value="{{old('password')}}" type="password" placeholder="" name="password" autocomplete="off" />
                                @error('password')
                                <div class="fv-plugins-message-container invalid-feedback" role="alert">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            <div class="fv-row mb-10">
                                <label class="form-label fw-bolder text-gray-900 fs-6">Confirm Password</label>
                                <input class="form-control form-control-solid" value="{{old('password_confirmation')}}" type="password" placeholder="" name="password_confirmation" autocomplete="off" />
                                @error('password_confirmation')
                                <div class="fv-plugins-message-container invalid-feedback" role="alert">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <!--end::Input group-->
                            <!--begin::Actions-->
                            <div class="d-flex flex-wrap justify-content-center pb-lg-0">
                                <button type="button" id="kt_password_reset_submit" class="btn btn-lg btn-primary fw-bolder me-4">
                                    <span class="indicator-label">Reset Password</span>
                                </button>
                            </div>
                            <!--end::Actions-->

                        @else
                            <div class="d-flex flex-wrap justify-content-center pb-lg-0">
                                <a href="{{route('login')}}" class="btn btn-lg btn-primary fw-bolder me-4">
                                    <span class="indicator-label">Login</span>
                                </a>
                            </div>
                        @endif
                    </form>
                    <!--end::Form-->
                </div>
                <!--end::Wrapper-->
            </div>
            <!--end::Content-->
        </div>
        <!--end::Authentication - Password reset-->
    </div>
    <!--end::Main-->

@endsection
