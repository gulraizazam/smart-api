@extends('layouts.auth_new')

@section('content')
@if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif
    @if (count($errors) > 0)
        <div class="alert alert-danger">
            <strong>Whoops!</strong> There were problems with input:
            <br><br>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
<!--begin::Login forgot password form-->
<div class="login-forgot">
							<div class="mb-20">
								<h3 class="opacity-75 font-weight-normal">Forgotten Password ?</h3>
								<p class="opacity-75">Enter your email to reset your password</p>
							</div>
							<form class="form" id="kt_login_forgot_form" method="POST" action="{{ url('password/email') }}">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
								<div class="form-group mb-10">
									<input class="form-control h-auto text-white bg-white-o-5 rounded-pill border-0 py-4 px-8" type="email" autocomplete="off" placeholder="Email" name="email" value="{{ old('email') }}" />
								</div>
								<div class="form-group">
									<button id="kt_login_forgot_submit" class="btn btn-pill btn-primary opacity-90 px-15 py-3 m-2">Submit</button>
									<a id="kt_login_forgot_cancel" href="{{ route('auth.login') }}" class="btn btn-pill btn-outline-white opacity-90 px-15 py-3 m-2">Back</a>
								</div>
							</form>
						</div>
						<!--end::Login forgot password form-->
@endsection
