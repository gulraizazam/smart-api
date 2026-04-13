@extends('admin.layouts.master')
@section('title', 'Leave Calendar')
@section('content')

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'HRM', 'title' => 'Leave Calendar'])

        <div class="d-flex flex-column-fluid">
            <div class="container">
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon"><i class="la la-calendar icon-lg text-primary"></i></span>
                            <h3 class="card-label">Leave Calendar</h3>
                        </div>
                        <div class="card-toolbar">
                            <button class="btn btn-light-primary btn-sm mr-2" id="btn-prev"><i class="la la-chevron-left"></i> Previous</button>
                            <span class="font-weight-bold font-size-lg mx-3" id="current-month-label"></span>
                            <button class="btn btn-light-primary btn-sm" id="btn-next">Next <i class="la la-chevron-right"></i></button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="leave-timeline" style="min-height: 400px;"></div>
                        <div class="mt-3">
                            <span class="label label-light-success label-inline mr-2">Approved</span>
                            <span class="label label-light-warning label-inline">Pending</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/vis-timeline/7.7.3/vis-timeline-graph2d.min.css" />
    <style>
        .vis-item.bg-success { background-color: #1BC5BD !important; border-color: #1BC5BD !important; color: #fff !important; }
        .vis-item.bg-warning { background-color: #FFA800 !important; border-color: #FFA800 !important; color: #fff !important; }
        .vis-label { font-size: 13px; font-weight: 500; }
    </style>
    @endpush

    @push('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/vis-timeline/7.7.3/vis-timeline-graph2d.min.js"></script>
    <script>
        $(function () {
            var currentDate = new Date();
            var currentMonth = currentDate.getMonth() + 1;
            var currentYear = currentDate.getFullYear();
            var timeline;

            function loadCalendar(month, year) {
                var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                $('#current-month-label').text(monthNames[month - 1] + ' ' + year);

                $.ajax({
                    url: '{{ route("admin.hr.leave-applications.calendar-data") }}',
                    method: 'GET',
                    data: { month: month, year: year },
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) {
                        if (!res.data) return;

                        var groups = new vis.DataSet(res.data.groups);
                        var items = new vis.DataSet(res.data.items);

                        var start = new Date(year, month - 1, 1);
                        var end = new Date(year, month, 0);

                        var options = {
                            start: start,
                            end: end,
                            orientation: 'top',
                            stack: true,
                            showCurrentTime: true,
                            zoomMin: 1000 * 60 * 60 * 24 * 7,
                            zoomMax: 1000 * 60 * 60 * 24 * 60,
                            tooltip: { followMouse: true },
                        };

                        if (timeline) {
                            timeline.destroy();
                        }

                        var container = document.getElementById('leave-timeline');
                        timeline = new vis.Timeline(container, items, groups, options);
                    }
                });
            }

            $('#btn-prev').on('click', function () {
                currentMonth--;
                if (currentMonth < 1) { currentMonth = 12; currentYear--; }
                loadCalendar(currentMonth, currentYear);
            });

            $('#btn-next').on('click', function () {
                currentMonth++;
                if (currentMonth > 12) { currentMonth = 1; currentYear++; }
                loadCalendar(currentMonth, currentYear);
            });

            loadCalendar(currentMonth, currentYear);
        });
    </script>
    @endpush

@endsection
