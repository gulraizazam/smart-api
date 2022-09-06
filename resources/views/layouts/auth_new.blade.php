<!DOCTYPE html>
<html lang="en">

<head>
    @include('partials.head_new')
</head>

<!--begin::Body-->
<body id="kt_body" class="header-fixed header-mobile-fixed subheader-enabled subheader-fixed aside-enabled aside-fixed aside-minimize-hoverable page-loading">
		<!--begin::Main-->
		<div class="d-flex flex-column flex-root">
			<!--begin::Login-->
			<div class="login login-5 login-signin-on d-flex flex-row-fluid" id="kt_login">
				<div class="d-flex flex-center bgi-size-cover bgi-no-repeat flex-row-fluid" style="background-image: url(../assets/media/bg/bg-2.jpg);">
					<div class="login-form text-center text-white p-7 position-relative overflow-hidden">
						<!--begin::Login Header-->
						<div class="d-flex flex-center mb-15">
							<a href="#">
								<img src="{{asset('assets/media/logos/smart.svg')}}" width="240px" class="max-h-75px" alt="" />
							</a>
						</div>
						
						
							@yield('content')					
						
						
                    </div>
				</div>
			</div>
			<!--end::Login-->
		</div>
		<!--end::Main-->
	@include('partials.javascripts_new')
</body>
	<!--end::Body-->
</html>