<meta charset="utf-8"/>
<title>{{ trans('global.global_title_smart') }}</title>
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta content="width=device-width, initial-scale=1" name="viewport"/>
<meta content="Smart Aesthetic is a Medical Spa offering more than 60 treatment for skin rejuvenation and body contouring" name="description"/>
<meta content="Hasan Akhtar" name="author"/>
<meta name="csrf-token" content="{{ csrf_token() }}">
<!--begin::Fonts-->
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
<!--end::Fonts-->
<!--begin::Page Custom Styles(used by this page)-->
<link href="{{ url('assets/css/pages/login/classic/login-5.css') }}" rel="stylesheet" type="text/css" />
<!--end::Page Custom Styles-->
<!-- BEGIN GLOBAL MANDATORY STYLES -->
<link href="{{ url('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/plugins/custom/prismjs/prismjs.bundle.css') }}" rel="stylesheet" type="text/css" />
<!-- END GLOBAL MANDATORY STYLES -->
<!-- BEGIN THEME GLOBAL STYLES -->
<link href="{{ url('assets/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
<!-- END THEME GLOBAL STYLES -->
<!-- BEGIN THEME LAYOUT STYLES -->
<link href="{{ url('assets/css/themes/layout/header/base/light.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/css/themes/layout/header/menu/light.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/css/themes/layout/brand/dark.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/css/themes/layout/aside/dark.css') }}" rel="stylesheet" type="text/css" />
<!-- END THEME LAYOUT STYLES -->
<link rel="shortcut icon" href="favicon.ico"/>

@yield('stylesheets')
