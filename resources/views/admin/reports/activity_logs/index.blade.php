@extends('admin.layouts.master')
@section('title', 'Activity Logs')
@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/activity-log.css') }}">
    @push('css')
        <style>
            /* Activity log page — filter bar */
            .al-filter-card { background:#fff; border:1px solid #ebedf3; border-radius:8px; padding:16px 20px; margin-bottom:16px; }

            /* Row 1: date preset pills + export on the right */
            .al-preset-row {
                display:flex; flex-wrap:wrap; gap:6px; margin-bottom:12px;
                align-items:center;
            }
            .al-preset-btn {
                padding:5px 14px; border-radius:999px;
                border:1px solid #e4e6ef; background:#f3f6f9; color:#3f4254;
                font-size:0.85rem; font-weight:500; cursor:pointer; transition:all .15s;
            }
            .al-preset-btn:hover { background:#e4e6ef; }
            .al-preset-btn.active { background:#3699ff; color:#fff; border-color:#3699ff; }
            .al-preset-row .al-export-btn { margin-left:auto; }

            /* Row 2: filters + action buttons in a single line */
            .al-filter-row {
                display:grid;
                grid-template-columns: 1.6fr 1fr 1fr 1fr 40px 40px;
                gap:10px;
                align-items:end;
            }
            @media (max-width: 992px) {
                .al-filter-row { grid-template-columns: 1fr 1fr 40px 40px; }
            }
            @media (max-width: 600px) {
                .al-filter-row { grid-template-columns: 1fr 1fr; }
                .al-filter-row .al-icon-btn { grid-column: span 1; }
            }

            /* Icon-only action buttons */
            .al-icon-btn {
                width:40px; height:38px;
                display:inline-flex; align-items:center; justify-content:center;
                padding:0; border-radius:4px; cursor:pointer;
                font-size:0.95rem;
            }
            .al-icon-btn:focus { outline:none; box-shadow:0 0 0 2px rgba(54,153,255,0.3); }

            .al-field-label {
                font-size:0.72rem; font-weight:600; color:#7e8299;
                text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px;
            }

            /* Search with icon */
            .al-search-wrap { position:relative; }
            .al-search-wrap .fa-search {
                position:absolute; left:12px; top:50%; transform:translateY(-50%);
                color:#b5b5c3; pointer-events:none;
            }
            .al-search-input { padding-left:34px; }

            /* Event-type multi-select as a button-styled dropdown */
            .al-tag-dd { position:relative; }
            .al-tag-dd-btn {
                width:100%; padding:7px 28px 7px 12px;
                text-align:left; background:#fff; border:1px solid #e4e6ef; border-radius:4px;
                cursor:pointer; font-size:0.95rem; color:#3f4254;
                white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
                position:relative;
            }
            .al-tag-dd-btn::after {
                content:""; position:absolute; right:10px; top:50%;
                width:0; height:0; transform:translateY(-50%);
                border-left:4px solid transparent; border-right:4px solid transparent;
                border-top:5px solid #a4a7b5;
            }
            .al-tag-dd-btn:hover { border-color:#b5b5c3; }
            .al-tag-dd-panel {
                display:none; position:absolute; top:calc(100% + 4px); left:0;
                z-index:50; min-width:260px; max-width:340px;
                background:#fff; border:1px solid #e4e6ef; border-radius:6px;
                box-shadow:0 4px 20px rgba(0,0,0,0.08);
                padding:10px; max-height:280px; overflow-y:auto;
            }
            .al-tag-dd.open .al-tag-dd-panel { display:block; }
            .al-tag-dd-list { display:flex; flex-wrap:wrap; gap:5px; }
            .al-tag-option { cursor:pointer; user-select:none; }
            .al-tag-option input { display:none; }
            .al-tag-option .act-tag { opacity:0.45; transition:opacity .15s; }
            .al-tag-option input:checked + .act-tag {
                opacity:1; box-shadow:0 0 0 2px #3699ff;
            }
            .al-tag-dd-footer {
                margin-top:8px; padding-top:8px; border-top:1px solid #ebedf3;
                display:flex; justify-content:space-between; font-size:0.82rem;
            }
            .al-tag-dd-footer a { color:#3699ff; cursor:pointer; }

            /* Active filter chips */
            .al-active-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:12px; }
            .al-active-chip {
                display:inline-flex; align-items:center;
                padding:3px 4px 3px 10px; background:#eef3f8; border-radius:999px;
                font-size:0.78rem; color:#3f4254;
            }
            .al-active-chip-remove {
                margin-left:6px; width:18px; height:18px;
                display:inline-flex; align-items:center; justify-content:center;
                border-radius:50%; background:#d7e1eb; cursor:pointer;
                font-size:0.7rem; line-height:1;
            }
            .al-active-chip-remove:hover { background:#b5c3d1; }

            /* Total indicator — lives below the chips */
            .al-total-wrap {
                margin-top:10px; text-align:right;
                color:#7e8299; font-size:0.9rem;
            }

            /* Legacy highlight classes (fallback renderer) */
            .highlight { color:#3699FF; font-weight:600; }
            .highlight-orange { color:#FFA800; font-weight:600; }
            .highlight-green { color:#1BC5BD; font-weight:600; }
            .highlight-purple { color:#8950FC; font-weight:600; }

            /* Hidden custom-range picker input */
            #activity_date_range { position:absolute; left:-9999px; opacity:0; }

            @media (max-width: 768px) {
                .al-filter-card { padding:12px; }
                .al-preset-row .al-export-btn { margin-left:0; margin-top:6px; width:100%; }
            }
        </style>
    @endpush
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Activity Logs Reports'])
        <div class="d-flex flex-column-fluid">
            <div class="container">
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label">Activity Logs</h3>
                        </div>
                    </div>
                    <div class="card-body">

                        {{-- === Filter card === --}}
                        <div class="al-filter-card">

                            {{-- Row 1: date preset pills + Export CSV on the right --}}
                            <div class="al-preset-row" id="al_date_presets">
                                <button type="button" class="al-preset-btn" data-preset="today">Today</button>
                                <button type="button" class="al-preset-btn" data-preset="yesterday">Yesterday</button>
                                <button type="button" class="al-preset-btn active" data-preset="last7">Last 7 days</button>
                                <button type="button" class="al-preset-btn" data-preset="last30">Last 30 days</button>
                                <button type="button" class="al-preset-btn" data-preset="thisMonth">This month</button>
                                <button type="button" class="al-preset-btn" data-preset="lastMonth">Last month</button>
                                <button type="button" class="al-preset-btn" data-preset="custom">Custom…</button>

                                <button type="button" id="al_export_btn" class="btn btn-light-primary al-export-btn" title="Download current view as CSV">
                                    <i class="fa fa-download mr-1"></i> Export CSV
                                </button>

                                {{-- Hidden daterangepicker target — opened by Custom… button --}}
                                {!! Form::text('date_range', null, ['id' => 'activity_date_range']) !!}
                            </div>

                            {{-- Row 2: search + event type + centre + actor on one line --}}
                            <div class="al-filter-row">
                                <div>
                                    <div class="al-field-label">Search</div>
                                    <div class="al-search-wrap">
                                        <i class="fa fa-search"></i>
                                        <input type="text" id="al_search" class="form-control al-search-input" placeholder="Patient name, plan #, description…">
                                    </div>
                                </div>

                                <div>
                                    <div class="al-field-label">Event type</div>
                                    <div class="al-tag-dd" id="al_tag_dd">
                                        <button type="button" class="al-tag-dd-btn" id="al_tag_dd_btn">All events</button>
                                        <div class="al-tag-dd-panel">
                                            <div class="al-tag-dd-list">
                                                @foreach($tags as $tag)
                                                    @php($color = $tagColors[$tag] ?? 'zinc')
                                                    <label class="al-tag-option" title="{{ $tag }}">
                                                        <input type="checkbox" value="{{ $tag }}" class="al-tag-checkbox">
                                                        <span class="act-tag act-tag--{{ $color }}">{{ $tag }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <div class="al-tag-dd-footer">
                                                <a id="al_tag_clear">Clear</a>
                                                <a id="al_tag_done">Done</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <div class="al-field-label">Centre</div>
                                    {!! Form::select('location_id', $locations, (Auth::user()->hasRole('FDM')) ? array_keys($locations->toArray()) : null, ['id' => 'location_id', 'class' => 'form-control select2']) !!}
                                </div>

                                <div>
                                    <div class="al-field-label">Actor</div>
                                    {!! Form::select('doctor_id', $operators, null, ['id' => 'doctor_id', 'class' => 'form-control select2']) !!}
                                </div>

                                {{-- Inline action buttons: Search + Reset --}}
                                <button type="button" id="al_load_btn" class="btn btn-success al-icon-btn spinner-button" title="Search / Reload">
                                    <i class="fa fa-search"></i>
                                </button>
                                <button type="button" id="al_reset_btn" class="btn btn-light al-icon-btn" title="Reset filters">
                                    <i class="fa fa-undo"></i>
                                </button>
                            </div>

                            {{-- Active filter chips --}}
                            <div class="al-active-chips" id="al_active_chips"></div>

                            {{-- Total records (right-aligned under chips) --}}
                            <div class="al-total-wrap" id="activity_total_wrap" style="display:none;">
                                Total: <strong><span id="activity_total">0</span></strong> records
                            </div>
                        </div>

                        {{-- === Activity feed === --}}
                        <div class="row" id="content">
                            <div class="col-12 pb-0">
                                <div class="timeline custom_timeline timeline-6 mt-3" id="activity_timeline"></div>
                                <div class="text-center mt-4 mb-4" id="activity_load_more_wrap" style="display:none;">
                                    <button type="button" class="btn btn-light-primary" id="activity_load_more">Load more</button>
                                </div>
                            </div>
                        </div>

                        {{-- Hidden form for CSV export POST --}}
                        <form id="al_export_form" method="POST" action="{{ route('admin.reports.activity_logs_export') }}" style="display:none;">
                            @csrf
                            <input type="hidden" name="startDate" id="al_export_start">
                            <input type="hidden" name="endDate" id="al_export_end">
                            <input type="hidden" name="location_id" id="al_export_location">
                            <input type="hidden" name="user_id" id="al_export_user">
                            <input type="hidden" name="search" id="al_export_search">
                            <div id="al_export_tags"></div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('admin.settings.edit')
    @push('js')
        <script>
            (function () {
                var $dr = $('#activity_date_range');
                $dr.daterangepicker({
                    locale: { format: 'MM/DD/YYYY' },
                    startDate: moment().subtract(6, 'days'),
                    endDate: moment()
                });

                var PRESETS = {
                    today:     function () { return [moment(), moment()]; },
                    yesterday: function () { return [moment().subtract(1, 'days'), moment().subtract(1, 'days')]; },
                    last7:     function () { return [moment().subtract(6, 'days'), moment()]; },
                    last30:    function () { return [moment().subtract(29, 'days'), moment()]; },
                    thisMonth: function () { return [moment().startOf('month'), moment().endOf('month')]; },
                    lastMonth: function () { return [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]; }
                };

                // Preset click
                $('#al_date_presets').on('click', '.al-preset-btn', function () {
                    var preset = $(this).data('preset');
                    $('.al-preset-btn').removeClass('active');
                    $(this).addClass('active');
                    if (preset !== 'custom' && PRESETS[preset]) {
                        var r = PRESETS[preset]();
                        $dr.data('daterangepicker').setStartDate(r[0]);
                        $dr.data('daterangepicker').setEndDate(r[1]);
                        loadReport();
                    } else if (preset === 'custom') {
                        // Open the picker anchored to the Custom button
                        $dr.data('daterangepicker').show();
                    }
                });

                // Reposition the picker container to appear under the Custom button
                $dr.on('show.daterangepicker', function (ev, picker) {
                    var btn = $('.al-preset-btn[data-preset="custom"]');
                    var offset = btn.offset();
                    picker.container.css({
                        top: offset.top + btn.outerHeight() + 4,
                        left: offset.left,
                        right: 'auto'
                    });
                });

                $dr.on('apply.daterangepicker', function () {
                    var d = $dr.data('daterangepicker');
                    var start = d.startDate.format('YYYY-MM-DD'), end = d.endDate.format('YYYY-MM-DD');
                    var matched = null;
                    Object.keys(PRESETS).forEach(function (k) {
                        var r = PRESETS[k]();
                        if (r[0].format('YYYY-MM-DD') === start && r[1].format('YYYY-MM-DD') === end) matched = k;
                    });
                    $('.al-preset-btn').removeClass('active');
                    $('.al-preset-btn[data-preset="' + (matched || 'custom') + '"]').addClass('active');
                    loadReport();
                });

                // Event-type dropdown open/close
                $('#al_tag_dd_btn').on('click', function (e) {
                    e.stopPropagation();
                    $('#al_tag_dd').toggleClass('open');
                });
                $(document).on('click', function (e) {
                    if (!$(e.target).closest('#al_tag_dd').length) $('#al_tag_dd').removeClass('open');
                });
                $('#al_tag_done').on('click', function () { $('#al_tag_dd').removeClass('open'); });
                $('#al_tag_clear').on('click', function () {
                    $('.al-tag-checkbox').prop('checked', false);
                    updateTagButtonLabel();
                    loadReport();
                });

                $('#al_tag_dd').on('change', '.al-tag-checkbox', function () {
                    updateTagButtonLabel();
                    loadReport();
                });

                function updateTagButtonLabel() {
                    var selected = $('.al-tag-checkbox:checked').map(function () { return this.value; }).get();
                    var $btn = $('#al_tag_dd_btn');
                    if (selected.length === 0) $btn.text('All events');
                    else if (selected.length <= 2) $btn.text(selected.join(', '));
                    else $btn.text(selected.length + ' selected');
                }

                // Debounced search
                var searchTimer = null;
                $('#al_search').on('input', function () {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(loadReport, 500);
                });

                // Centre / Actor change
                $('#location_id, #doctor_id').on('change', function () { loadReport(); });

                // Reset
                $('#al_reset_btn').on('click', function () {
                    $('#al_search').val('');
                    $('#location_id').val('').trigger('change.select2').trigger('change');
                    $('#doctor_id').val('').trigger('change.select2').trigger('change');
                    $('.al-tag-checkbox').prop('checked', false);
                    updateTagButtonLabel();
                    var r = PRESETS.last7();
                    $dr.data('daterangepicker').setStartDate(r[0]);
                    $dr.data('daterangepicker').setEndDate(r[1]);
                    $('.al-preset-btn').removeClass('active');
                    $('.al-preset-btn[data-preset="last7"]').addClass('active');
                    loadReport();
                });

                $('#al_load_btn').on('click', loadReport);

                // Export CSV
                $('#al_export_btn').on('click', function () {
                    var p = collectParams();
                    $('#al_export_start').val(p.startDate);
                    $('#al_export_end').val(p.endDate);
                    $('#al_export_location').val(p.location_id || '');
                    $('#al_export_user').val(p.user_id || '');
                    $('#al_export_search').val(p.search || '');
                    var $tagsBox = $('#al_export_tags').empty();
                    (p.tags || []).forEach(function (t) {
                        $tagsBox.append('<input type="hidden" name="tags[]" value="' + t + '">');
                    });
                    $('#al_export_form').submit();
                });

                function collectParams() {
                    var d = $dr.data('daterangepicker');
                    var tags = $('.al-tag-checkbox:checked').map(function () { return this.value; }).get();
                    return {
                        startDate: d.startDate.format('YYYY-MM-DD'),
                        endDate: d.endDate.format('YYYY-MM-DD'),
                        location_id: $('#location_id').val(),
                        user_id: $('#doctor_id').val(),
                        search: $.trim($('#al_search').val()),
                        tags: tags
                    };
                }

                function renderActiveChips() {
                    var p = collectParams();
                    var chips = [{ label: p.startDate + ' → ' + p.endDate, key: 'date' }];
                    if (p.search) chips.push({ label: '🔍 ' + p.search, key: 'search' });
                    (p.tags || []).forEach(function (t) { chips.push({ label: t, key: 'tag:' + t }); });
                    if (p.location_id) {
                        var cn = $('#location_id option:selected').text();
                        if (cn && cn !== 'All') chips.push({ label: 'Centre: ' + cn, key: 'location' });
                    }
                    if (p.user_id) {
                        var un = $('#doctor_id option:selected').text();
                        if (un && un !== 'All') chips.push({ label: 'Actor: ' + un, key: 'user' });
                    }
                    var $box = $('#al_active_chips').empty();
                    chips.forEach(function (c) {
                        $box.append(
                            '<span class="al-active-chip" data-key="' + c.key + '">' +
                                c.label +
                                (c.key !== 'date' ? '<span class="al-active-chip-remove">&times;</span>' : '') +
                            '</span>'
                        );
                    });
                }

                $('#al_active_chips').on('click', '.al-active-chip-remove', function () {
                    var key = $(this).parent().data('key');
                    if (key === 'search') $('#al_search').val('');
                    else if (key === 'location') $('#location_id').val('').trigger('change');
                    else if (key === 'user') $('#doctor_id').val('').trigger('change');
                    else if (key && key.indexOf('tag:') === 0) {
                        $('.al-tag-checkbox[value="' + key.substr(4) + '"]').prop('checked', false);
                        updateTagButtonLabel();
                    }
                    loadReport();
                });

                // Fetch / pagination
                var activityNextCursor = null, activityNextOffset = 0;

                function fetchActivityPage(cursor) {
                    var isFirstPage = !cursor;
                    showSpinner();
                    $('#activity_load_more').prop('disabled', true);
                    var p = collectParams();
                    p.cursor = cursor || '';
                    p.cursor_offset = activityNextOffset;

                    return $.ajax({
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        url: route('admin.reports.load_activity_report'),
                        type: 'POST', dataType: 'json', data: p
                    }).done(function (response) {
                        if (!response || !response.success) {
                            if (typeof toastr !== 'undefined') toastr.error('Could not load activity logs.');
                            return;
                        }
                        var payload = response.data;
                        if (isFirstPage) {
                            $('#activity_timeline').html(payload.html);
                            if (payload.total !== null && typeof payload.total !== 'undefined') {
                                $('#activity_total').text(Number(payload.total).toLocaleString());
                                $('#activity_total_wrap').show();
                            } else {
                                $('#activity_total_wrap').hide();
                            }
                        } else {
                            $('#activity_timeline').append(payload.html);
                        }
                        activityNextCursor = payload.next_cursor || null;
                        activityNextOffset = payload.next_offset || 0;
                        $('#activity_load_more_wrap').toggle(!!activityNextCursor);
                    }).fail(function (xhr) {
                        if (typeof toastr !== 'undefined') {
                            var msg = 'Failed to load activity logs.';
                            if (xhr && xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                                msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
                            } else if (xhr && xhr.status >= 500) {
                                msg = 'Server error. Try a shorter date range.';
                            }
                            toastr.error(msg);
                        }
                    }).always(function () {
                        hideSpinner();
                        $('#activity_load_more').prop('disabled', false);
                    });
                }

                function loadReport() {
                    activityNextCursor = null;
                    activityNextOffset = 0;
                    $('#activity_timeline').empty();
                    $('#activity_load_more_wrap').hide();
                    renderActiveChips();
                    fetchActivityPage(null);
                }

                $(document).on('click', '#activity_load_more', function () {
                    if (!activityNextCursor) return;
                    fetchActivityPage(activityNextCursor);
                });

                $(document).ready(function () { loadReport(); });
            })();
        </script>
    @endpush
@endsection
