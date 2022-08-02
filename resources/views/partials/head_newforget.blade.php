<meta charset="utf-8"/>
<title>{{ trans('global.global_title_smart') }}</title>
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta content="width=device-width, initial-scale=1" name="viewport"/>
<meta content="Smart Aesthetic is a Medical Spa offering more than 60 treatment for skin rejuvenation and body contouring" name="description"/>
<meta content="Engr. Mustafa Mughal" name="author"/>
<meta name="csrf-token" content="{{ csrf_token() }}">
<!-- BEGIN GLOBAL MANDATORY STYLES -->
<link href="//fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet"
      type="text/css"/>
<link href="{{ url('metronic/assets/global/plugins/font-awesome/css/font-awesome.min.css') }}" rel="stylesheet"
      type="text/css"/>
<link href="{{ url('metronic/assets/global/plugins/simple-line-icons/simple-line-icons.min.css') }}" rel="stylesheet"
      type="text/css"/>
<link href="{{ url('metronic/assets/global/plugins/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet"
      type="text/css"/>
<link href="{{ url('metronic/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css') }}" rel="stylesheet"
      type="text/css"/>
<!-- END GLOBAL MANDATORY STYLES -->
<link href="{{ url('metronic/assets/global/plugins/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css"/>
<link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet"
      type="text/css"/>
<!-- BEGIN THEME GLOBAL STYLES -->
<link href="{{ url('metronic/assets/global/css/components.min.css') }}" rel="stylesheet" id="style_components"
      type="text/css"/>
<link href="{{ url('metronic/assets/global/css/plugins.min.css') }}" rel="stylesheet" type="text/css"/>
<!-- END THEME GLOBAL STYLES -->
<link href="{{ url('metronic/assets/pages/css/login-4.min.css') }}" rel="stylesheet" type="text/css"/>
<!-- BEGIN THEME LAYOUT STYLES -->
<link href="{{ url('metronic/assets/layouts/layout/css/layout.min.css') }}" rel="stylesheet" type="text/css"/>
<link href="{{ url('metronic/assets/layouts/layout/css/themes/darkblue.min.css') }}" rel="stylesheet" type="text/css"
      id="style_color"/>
<link href="{{ url('metronic/assets/layouts/layout/css/custom.min.css') }}" rel="stylesheet" type="text/css"/>
<!-- END THEME LAYOUT STYLES -->
<link rel="shortcut icon" href="favicon.ico"/>

@yield('stylesheets')
