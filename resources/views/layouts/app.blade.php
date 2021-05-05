<!DOCTYPE html>
<!--[if IE 8]> <html lang="en" class="ie8 no-js"> <![endif]-->
<!--[if IE 9]> <html lang="en" class="ie9 no-js"> <![endif]-->
<!--[if !IE]><!-->
<html lang="en">
<!--<![endif]-->
<!-- BEGIN HEAD -->
<head>
    @include('partials.head')
</head>
<!-- END HEAD -->

<body class="page-header-fixed page-sidebar-closed-hide-logo page-container-bg-solid">
<div class="page-wrapper">
    <!-- BEGIN HEADER -->
    @include('partials.topbar')
    <!-- END HEADER -->
    <!-- BEGIN HEADER & CONTENT DIVIDER -->
    <div class="clearfix"> </div>
    <!-- END HEADER & CONTENT DIVIDER -->
    <!-- BEGIN CONTAINER -->
    <div class="page-container">
        <!-- BEGIN SIDEBAR -->
        @include('partials.sidebar')
        <!-- END SIDEBAR -->
        <!-- BEGIN CONTENT -->
        <div class="page-content-wrapper">
            @if(session('success'))
                <div class="alert alert-success display-hide"><button class="close" data-close="alert"></button> {{ session('success') }}</div>
            @endif
            <!-- BEGIN CONTENT BODY -->
            <div class="page-content">
                @yield('title')

                @if (count($errors) > 0)
                    <div class="alert alert-danger">
                        <button class="close" data-close="alert"></button> <strong>Whoops!</strong> There were problems with input:
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @include('flash::message')

                @yield('content')
            </div>
            <!-- END CONTENT BODY -->
        </div>
        <!-- END CONTENT -->
    </div>
    <!-- END CONTAINER -->
    <!-- BEGIN FOOTER -->
    @include('partials.footer')
    <!-- END FOOTER -->
</div>
<div class="quick-nav-overlay"></div>
<!-- END QUICK NAV -->
    @include('partials.javascripts')
<script type="text/javascript">
    $(document).ready(function () {
        setTimeout(function() {
            $('.alert-success').slideUp('fast');
        }, 2000);

        setTimeout(function() {
            $('.alert-warning').slideUp('fast');

        }, 15000);
    });
function phoneReset(sel){
    $("#"+sel).val('')
    $("#"+sel).attr("type","number");
    $("#"+sel).attr("readonly",false);
}
</script>

</body>

</html>