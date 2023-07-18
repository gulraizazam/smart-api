<!DOCTYPE html>
<html lang="en">
<!--begin::Head-->
<head>
    <title>Cutera Aesthetics | @yield('title')</title>
    <meta charset="utf-8" />
    <meta name="description" content="Smart Aesthetic" />
    <meta name="keywords" content="Smart Aesthetic" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="shortcut icon" href="{{asset('favicon.ico')}}" />
    <link rel="shortcut icon" href="https://cuteraesthetics.com/wp-content/themes/cutera/assets/img/favicon.png" />
    <!--begin::Fonts-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <!--end::Fonts-->
    <!--begin::Global Stylesheets Bundle(used by all pages)-->
    <link href="{{asset('assets/css/auth/plugins.bundle.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('assets/css/auth/style.bundle.css')}}" rel="stylesheet" type="text/css" />
    <!--end::Global Stylesheets Bundle-->
</head>
<!--end::Head-->
<!--begin::Body-->
<body id="kt_body" class="bg-body">
<!--begin::Main-->

@yield('content')

<!--end::Main-->

<!--begin::Javascript-->
<!--begin::Global Javascript Bundle(used by all pages)-->
<script src="{{asset('assets/js/auth/plugins.bundle.js')}}"></script>
<script src="{{asset('assets/js/auth/scripts.bundle.js')}}"></script>
<!--end::Global Javascript Bundle-->
<!--begin::Page Custom Javascript(used by this page)-->
<script src="{{asset('assets/js/auth/general.js')}}"></script>
<script src="{{asset('assets/js/auth/password-reset.js')}}"></script>
<!--end::Page Custom Javascript-->
<!--end::Javascript-->

@include('admin.partials.messages', ['toaster' => true])

</body>
<!--end::Body-->
</html>
