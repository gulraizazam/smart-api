<!DOCTYPE html>
<html lang="en">
<!--begin::Head-->
<head>
    <meta charset="utf-8" />
    <title>Allura Aesthetics | @yield('title')
    </title>
    <meta
        content="Allura Aesthetics is a Medical Spa offering more than 60 treatments for skin rejuvenation and body contouring"
        name="description" />
    <meta content="Red Signal" name="author" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link rel="icon" type="image/png" href="{{ asset('favicon2.png') }}" />
    <link rel="shortcut icon" type="image/png" href="{{ asset('favicon2.png') }}" />
    <link rel="apple-touch-icon" href="{{ asset('favicon2.png') }}" />
    <!--begin::Fonts-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <!--end::Fonts-->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!--begin::Global Theme Styles(used by all pages)-->
    <link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/plugins/custom/prismjs/prismjs.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
    <!--end::Global Theme Styles-->
    <!--begin::Layout Themes(used by all pages)-->
    <link href="{{ asset('assets/css/themes/layout/header/base/light.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/themes/layout/header/menu/light.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/themes/layout/brand/dark.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/themes/layout/aside/dark.css') }}" rel="stylesheet" type="text/css" />
    <!--end::Layout Themes-->
    <link href="{{ asset('assets/css/custom.css') }}?v={{ @filemtime(public_path('assets/css/custom.css')) ?: time() }}" rel="stylesheet" type="text/css" />
    @stack('css')
</head>
<!--end::Head-->
<!--begin::Body-->

<body id="kt_body"
    class="header-fixed header-mobile-fixed subheader-enabled subheader-fixed aside-enabled aside-fixed aside-minimize-hoverable page-loading">
    <div class="page-loader page-loader-base">
        <div class="blockui">
            <span>Please wait...</span>
            <span>
                <div class="spinner spinner-primary"></div>
            </span>
        </div>
    </div>
    <div class="d-flex flex-column flex-root">
        <!--begin::Page-->
        <div class="d-flex flex-row flex-column-fluid page">
            @include('admin.partials.mobile-header')
            @include('admin.partials.sidebar')
            <!--begin::Wrapper-->
            <div class="d-flex flex-column flex-row-fluid wrapper" id="kt_wrapper">
                @yield('content')
                @include('admin.partials.footer')
            </div>
        </div>
        <!--end::Page-->
    </div>
    <!--end::Main-->
    @routes
    <script>
        var HOST_URL = "/metronic/theme/html/tools/preview";
        var base_route = "{{ url('/') }}";
        var asset_url = "{{ asset('/') }}";
    </script>
    <!--begin::Global Config(global config for global JS scripts)-->
    <script>
        var KTAppSettings = {
            "breakpoints": {
                "sm": 576,
                "md": 768,
                "lg": 992,
                "xl": 1200,
                "xxl": 1400
            },
            "colors": {
                "theme": {
                    "base": {
                        "white": "#ffffff",
                        "primary": "#3699FF",
                        "secondary": "#E5EAEE",
                        "success": "#1BC5BD",
                        "info": "#8950FC",
                        "warning": "#FFA800",
                        "danger": "#F64E60",
                        "light": "#E4E6EF",
                        "dark": "#181C32"
                    },
                    "light": {
                        "white": "#ffffff",
                        "primary": "#E1F0FF",
                        "secondary": "#EBEDF3",
                        "success": "#C9F7F5",
                        "info": "#EEE5FF",
                        "warning": "#FFF4DE",
                        "danger": "#FFE2E5",
                        "light": "#F3F6F9",
                        "dark": "#D6D6E0"
                    },
                    "inverse": {
                        "white": "#ffffff",
                        "primary": "#ffffff",
                        "secondary": "#3F4254",
                        "success": "#ffffff",
                        "info": "#ffffff",
                        "warning": "#ffffff",
                        "danger": "#ffffff",
                        "light": "#464E5F",
                        "dark": "#ffffff"
                    }
                },
                "gray": {
                    "gray-100": "#F3F6F9",
                    "gray-200": "#EBEDF3",
                    "gray-300": "#E4E6EF",
                    "gray-400": "#D1D3E0",
                    "gray-500": "#B5B5C3",
                    "gray-600": "#7E8299",
                    "gray-700": "#5E6278",
                    "gray-800": "#3F4254",
                    "gray-900": "#181C32"
                }
            },
            "font-family": "Poppins"
        };
    </script>
    <!--end::Global Config-->
    <!--begin::Global Theme Bundle(used by all pages)-->
    <script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('assets/plugins/custom/prismjs/prismjs.bundle.js') }}"></script>
    <script src="{{ asset('assets/js/scripts.bundle.js') }}"></script>
    <!--end::Global Theme Bundle-->
    <!--begin::ApexCharts Latest (overrides bundled v3.25.0)-->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.1/dist/apexcharts.min.js"></script>
    <!--end::ApexCharts Latest-->
    <script src="{{ asset('assets/js/pages/features/custom/spinners.js') }}"></script>
    <script src="{{ asset('assets/js/pages/widgets.js') }}"></script>
    <script>
        const debug = "{{ config('app.debug') }}";
    </script>
    <script src="{{ asset('assets/js/custom.js') }}"></script>
    @stack('datatable-js')
    <script src="{{ asset('assets/js/pages/crud/ktdatatable/advanced/row-details.js') }}"></script>
    <script src="{{ asset('assets/js/pages/crud/forms/widgets/select2.js') }}"></script>
    <script src="{{ asset('assets/js/reports/centre-multiselect.js') }}"></script>
    <!--end::Page Scripts-->
    @include('admin.partials.messages', ['toastr' => true])
    @stack('js')
    <script>
        var width = (window.innerWidth > 0) ? window.innerWidth : screen.width;

        if (width < 1536) {
            $("#kt_aside_toggle").addClass("active");
            $("#kt_body").addClass("aside-minimize");
        }

        $(function() {
            // Desktop user dropdown
            $(".user-setting").click(function() {
                $(".user-popup").slideToggle();
            });

            $(document).on('click', function(e) {
                var container = $(".user-setting");
                if (!$(e.target).closest(container).length) {
                    $(".user-popup").hide();
                }
            });

            // Mobile dropdowns — close any open one when another opens
            function closeMobileDropdowns(except) {
                ['#mobile_user_dropdown', '#m_cashflow_dropdown', '#m_hr_dropdown'].forEach(function(sel) {
                    if (sel !== except) $(sel).hide();
                });
            }

            $('#mobile_user_toggle').on('click', function(e) {
                e.stopPropagation();
                closeMobileDropdowns('#mobile_user_dropdown');
                $('#mobile_user_dropdown').slideToggle(150);
            });

            $('#m_cashflow_toggle').on('click', function(e) {
                e.stopPropagation();
                closeMobileDropdowns('#m_cashflow_dropdown');
                var wasVisible = $('#m_cashflow_dropdown').is(':visible');
                $('#m_cashflow_dropdown').slideToggle(150);
                if (!wasVisible) loadMobileCfNotifications();
            });

            $('#m_hr_toggle').on('click', function(e) {
                e.stopPropagation();
                closeMobileDropdowns('#m_hr_dropdown');
                var wasVisible = $('#m_hr_dropdown').is(':visible');
                $('#m_hr_dropdown').slideToggle(150);
                if (!wasVisible) loadMobileHrNotifications();
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('#mobile_user_menu, #m_cashflow_bell, #m_hr_bell').length) {
                    closeMobileDropdowns();
                }
            });

            // Mobile cashflow notifications
            function pollMobileCfCount() {
                $.ajax({
                    url: '/api/cashflow/notifications', type: 'GET', data: { per_page: 1 },
                    success: function(res) {
                        var $c = $('#m-cf-count');
                        if (res.success && res.unread_count > 0) { $c.text(res.unread_count).show(); } else { $c.hide(); }
                    }
                });
            }
            function loadMobileCfNotifications() {
                $.ajax({
                    url: '/api/cashflow/notifications', type: 'GET', data: { per_page: 15 },
                    success: function(res) {
                        if (!res.success) return;
                        var $list = $('#m-cf-list').empty();
                        if (!res.data || !res.data.length) { $list.html('<div class="text-center text-muted py-4 font-size-sm">No notifications</div>'); return; }
                        $.each(res.data, function(i, n) {
                            $list.append('<div class="px-3 py-2 border-bottom ' + (n.read_at ? '' : 'bg-light-primary') + '"><div class="font-weight-bold font-size-sm">' + (n.title||'') + '</div><div class="text-muted font-size-xs">' + (n.message||'') + '</div></div>');
                        });
                    }
                });
            }
            $('#m-cf-mark-read').on('click', function() {
                $.ajax({ url: '/api/cashflow/notifications/mark-read', type: 'POST', data: { all: true },
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function() { $('#m-cf-count').hide(); loadMobileCfNotifications(); }
                });
            });
            if ($('#m_cashflow_bell').length) { pollMobileCfCount(); setInterval(pollMobileCfCount, 60000); }

            // Mobile HR notifications
            function pollMobileHrCount() {
                $.ajax({
                    url: '/api/hr/notifications', type: 'GET',
                    success: function(res) {
                        var $c = $('#m-hr-count');
                        if (res.status && res.unread_count > 0) { $c.text(res.unread_count).show(); } else { $c.hide(); }
                    }
                });
            }
            function loadMobileHrNotifications() {
                $.ajax({
                    url: '/api/hr/notifications', type: 'GET',
                    success: function(res) {
                        if (!res.status) return;
                        var $list = $('#m-hr-list').empty();
                        if (!res.data || !res.data.length) { $list.html('<div class="text-center text-muted py-4 font-size-sm">No notifications</div>'); return; }
                        $.each(res.data, function(i, n) {
                            var link = n.url || 'javascript:;';
                            $list.append('<a href="' + link + '" class="d-block px-3 py-2 border-bottom text-dark ' + (n.is_read ? '' : 'bg-light-warning') + ' m-hr-notif-item" data-id="' + n.id + '"><div class="' + (n.is_read ? '' : 'font-weight-bold') + ' font-size-sm">' + n.title + '</div><div class="text-muted font-size-xs">' + n.message + '</div><div class="text-muted font-size-xs mt-1">' + n.time_ago + '</div></a>');
                        });
                    }
                });
            }
            $(document).on('click', '.m-hr-notif-item', function() {
                $.ajax({ url: '/api/hr/notifications/' + $(this).data('id') + '/read', type: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
                });
            });
            $('#m-hr-mark-read').on('click', function() {
                $.ajax({ url: '/api/hr/notifications/mark-all-read', type: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function() { $('#m-hr-count').hide(); loadMobileHrNotifications(); }
                });
            });
            pollMobileHrCount();
            setInterval(pollMobileHrCount, 60000);
        });

        // Cashflow Notification Bell
        (function() {
            var $bell = $('#cashflow_notification_toggle');
            var $dropdown = $('#cashflow-notif-dropdown');
            var $count = $('#cashflow-notif-count');
            if (!$bell.length) return;

            // Toggle dropdown
            $bell.on('click', function(e) {
                e.stopPropagation();
                var visible = $dropdown.is(':visible');
                $dropdown.toggle(!visible);
                if (!visible) loadNotifications();
            });
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#cashflow-notification-bell').length) $dropdown.hide();
            });

            // Poll unread count
            function pollCount() {
                $.ajax({
                    url: '/api/cashflow/notifications',
                    type: 'GET',
                    data: { per_page: 1 },
                    success: function(res) {
                        if (res.success && res.unread_count > 0) {
                            $count.text(res.unread_count).show();
                        } else {
                            $count.hide();
                        }
                    }
                });
            }
            pollCount();
            setInterval(pollCount, 60000);

            // Load notifications
            function loadNotifications() {
                $.ajax({
                    url: '/api/cashflow/notifications',
                    type: 'GET',
                    data: { per_page: 15 },
                    success: function(res) {
                        if (!res.success) return;
                        var $list = $('#cashflow-notif-list').empty();
                        if (!res.data || res.data.length === 0) {
                            $list.html('<div class="text-center text-muted py-4 font-size-sm">No notifications</div>');
                            return;
                        }
                        $.each(res.data, function(i, n) {
                            var bg = n.read_at ? '' : 'bg-light-primary';
                            $list.append(
                                '<div class="d-flex align-items-start px-3 py-2 border-bottom ' + bg + '">' +
                                '<div class="flex-grow-1">' +
                                '<div class="font-weight-bold font-size-sm">' + (n.title || '') + '</div>' +
                                '<div class="text-muted font-size-xs">' + (n.message || '') + '</div>' +
                                '<div class="text-muted font-size-xs mt-1">' + timeAgo(n.created_at) + '</div>' +
                                '</div></div>'
                            );
                        });
                    }
                });
            }

            // Mark all read
            $('#cashflow-mark-all-read').on('click', function() {
                $.ajax({
                    url: '/api/cashflow/notifications/mark-read',
                    type: 'POST',
                    data: { all: true },
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function() { $count.hide(); loadNotifications(); }
                });
            });

            function timeAgo(dt) {
                if (!dt) return '';
                var diff = Math.floor((Date.now() - new Date(dt).getTime()) / 1000);
                if (diff < 60) return 'just now';
                if (diff < 3600) return Math.floor(diff/60) + 'm ago';
                if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
                return Math.floor(diff/86400) + 'd ago';
            }
        })();

        // HR Notification Bell
        (function() {
            var $bell = $('#hr_notification_toggle');
            var $dropdown = $('#hr-notif-dropdown');
            var $count = $('#hr-notif-count');
            if (!$bell.length) return;

            $bell.on('click', function(e) {
                e.stopPropagation();
                var visible = $dropdown.is(':visible');
                $dropdown.toggle(!visible);
                if (!visible) loadHrNotifications();
            });
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#hr-notification-bell').length) $dropdown.hide();
            });

            function pollHrCount() {
                $.ajax({
                    url: '/api/hr/notifications',
                    type: 'GET',
                    success: function(res) {
                        if (res.status && res.unread_count > 0) {
                            $count.text(res.unread_count).show();
                        } else {
                            $count.hide();
                        }
                    }
                });
            }
            pollHrCount();
            setInterval(pollHrCount, 60000);

            function loadHrNotifications() {
                $.ajax({
                    url: '/api/hr/notifications',
                    type: 'GET',
                    success: function(res) {
                        if (!res.status) return;
                        var $list = $('#hr-notif-list').empty();
                        if (!res.data || res.data.length === 0) {
                            $list.html('<div class="text-center text-muted py-4 font-size-sm">No notifications</div>');
                            return;
                        }
                        $.each(res.data, function(i, n) {
                            var bg = n.is_read ? '' : 'bg-light-warning';
                            var fw = n.is_read ? '' : 'font-weight-bold';
                            var link = n.url ? n.url : 'javascript:;';
                            $list.append(
                                '<a href="' + link + '" class="d-flex align-items-start px-3 py-2 border-bottom text-dark ' + bg + ' hr-notif-item" data-id="' + n.id + '">' +
                                '<div class="flex-grow-1">' +
                                '<div class="' + fw + ' font-size-sm">' + n.title + '</div>' +
                                '<div class="text-muted font-size-xs">' + n.message + '</div>' +
                                '<div class="text-muted font-size-xs mt-1">' + n.time_ago + '</div>' +
                                '</div></a>'
                            );
                        });
                    }
                });
            }

            $(document).on('click', '.hr-notif-item', function() {
                var id = $(this).data('id');
                $.ajax({
                    url: '/api/hr/notifications/' + id + '/read',
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
                });
            });

            $('#hr-mark-all-read').on('click', function() {
                $.ajax({
                    url: '/api/hr/notifications/mark-all-read',
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function() { $count.hide(); loadHrNotifications(); }
                });
            });
        })();
    </script>

    @if(request()->is('*/cashflow*'))
    <script src="{{ asset('assets/js/pages/cashflow/keyboard-shortcuts.js') }}"></script>
    @endif

</body>
<!--end::Body-->

</html>
