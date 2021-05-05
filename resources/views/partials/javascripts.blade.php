<!--[if lt IE 9]>
<script src="{{ url('metronic/assets/global/plugins/respond.min.js') }}"></script>
<script src="{{ url('metronic/assets/global/plugins/excanvas.min.js') }}"></script>
<script src="{{ url('metronic/assets/global/plugins/ie8.fix.min.js') }}"></script>
<![endif]-->
<!-- BEGIN CORE PLUGINS -->
<script src="{{ url('metronic/assets/global/plugins/jquery.min.js') }}" type="text/javascript"></script>
<script src="{{ url('metronic/assets/global/plugins/bootstrap/js/bootstrap.min.js') }}" type="text/javascript"></script>
<script src="{{ url('metronic/assets/global/plugins/js.cookie.min.js') }}" type="text/javascript"></script>
<script src="{{ url('metronic/assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js') }}" type="text/javascript"></script>
<script src="{{ url('metronic/assets/global/plugins/jquery.blockui.min.js') }}" type="text/javascript"></script>
<script src="{{ url('metronic/assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js') }}" type="text/javascript"></script>
<!-- END CORE PLUGINS -->
<!-- BEGIN THEME GLOBAL SCRIPTS -->
<script src="{{ url('metronic/assets/global/scripts/app.min.js') }}" type="text/javascript"></script>
<!-- END THEME GLOBAL SCRIPTS -->
<!-- BEGIN THEME LAYOUT SCRIPTS -->
<script src="{{ url('metronic/assets/layouts/layout/scripts/layout.min.js') }}" type="text/javascript"></script>
<script src="{{ url('metronic/assets/layouts/layout/scripts/demo.min.js') }}" type="text/javascript"></script>
<script src="{{ url('metronic/assets/layouts/global/scripts/quick-sidebar.min.js') }}" type="text/javascript"></script>
<script src="{{ url("metronic/assets/layouts/global/scripts/quick-nav.min.js") }}" type="text/javascript"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js" type="text/javascript"></script>
<script src="{{ url('js/Utils.js') }}" type="text/javascript"></script>
<!-- END THEME LAYOUT SCRIPTS -->
<script>
    window._token = '{{ csrf_token() }}';
</script>

@yield('javascript')

<script>
    $(document).ready(function () {
        $('#clickmewow').click(function () {
            $('#radio1003').attr('checked', 'checked');
        });
    })
</script>
@if (Auth::user())
    <script>
        $(function() {
            console.log('I called');
            setInterval(function checkSession() {
                $.get(route('check_session'), function(data) {
                    // if session was expired
                    if (data.guest) {
                        // or, may be better, just reload page
                        location.reload();
                    }
                });
            }, parseInt({{ (Config('session.lifetime') * 60000) + 10000 }})) // as per session
        });
    </script>
    <!-- Ziggy Routes Start -->
    @routes
    <!-- Ziggy Routes End -->
@endif