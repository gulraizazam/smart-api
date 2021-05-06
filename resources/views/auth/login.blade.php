@extends('layouts.auth_new')

@section('content')
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
<!--begin::Login Sign in form-->
<div class="login-signin">
							<div class="mb-20">
								<h3 class="opacity-70 font-weight-normal">Sign In</h3>
								<p class="opacity-70">Enter your details to login to your account:</p>
							</div>
                    <form class="form"  method="POST"  action="{{ url('login') }}" id="kt_login_signin_form">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
								<div class="form-group">
									<input class="form-control h-auto text-white bg-white-o-5 rounded-pill border-0 py-4 px-8" type="email" autocomplete="off" placeholder="Email" name="email" value="{{ old('email') }}" />
								</div>
								<div class="form-group">
									<input class="form-control h-auto text-white bg-white-o-5 rounded-pill border-0 py-4 px-8" type="password" autocomplete="off" placeholder="Password" name="password" />
								</div>
								<div class="form-group d-flex flex-wrap justify-content-between align-items-center px-8 opacity-80">
									<div class="checkbox-inline">
										<label class="checkbox checkbox-outline checkbox-white text-white m-0">
										<input type="checkbox" name="remember" />
										<span></span>Remember me</label>
									</div>
									<a href="javascript:void(0)" id="kt_login_forgot" class="text-white font-weight-bold">Forget Password ?</a>
								</div>
								<div class="form-group text-center mt-10">
									<button id="kt_login_signin_submit" class="btn btn-pill btn-primary opacity-90 px-15 py-3">Sign In</button>
								</div>
							</form>	
		</div>	
@endsection
