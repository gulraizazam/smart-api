@extends('admin.layouts.master')
@section('title', 'Activity Logs')
@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/activity-log.css') }}">
    @push('css')
        <style>
            /* Activity log page — filter bar */
            .al-filter-card { background: #fff; border: 1px solid #ebedf3; border-radius: 8px; padding: 16px 20px; margin-bottom: 16px; }
            .al-preset-row { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
            .al-preset-btn {
                padding: 5px 14px;
                border-radius: 999px;
                border: 1px solid #e4e6ef;
                background: #f3f6f9;
                color: #3f4254;
                font-size: 0.85rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.15s;
            }
            .al-preset-btn:hover { background: #e4e6ef; }
            .al-preset-btn.active { background: #3699ff; color: #fff; border-color: #3699ff; }

            .al-filter-row { display: grid; grid-template-columns: 2fr 1.5fr 1.5fr 1.5fr; gap: 12px; margin-bottom: 12px; }
            @media (max-width: 992px) {
                .al-filter-row { grid-template-columns: 1fr 1fr; }
            }
            @media (max-width: 600px) {
                .al-filter-row { grid-template-columns: 1fr; }
            }

            .al-field-label { font-size: 0.78rem; font-weight: 600; color: #7e8299; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 4px; }

            .al-search-wrap { position: relative; }
            .al-search-wrap .fa-search { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #b5b5c3; pointer-events: none; }
            .al-search-input { padding-left: 36px; }

            .al-tag-picker { display: flex; flex-wrap: wrap; gap: 6px; padding: 8px; border: 1px solid #e4e6ef; border-radius: 6px; background: #f9fafc; max-height: 120px; overflow-y: auto; }
            .al-tag-option { cursor: pointer; user-select: none; }
            .al-tag-option input { display: none; }
            .al-tag-option .act-tag { opacity: 0.45; transition: opacity 0.15s; }
            .al-tag-option input:checked + .act-tag { opacity: 1; box-shadow: 0 0 0 2px #3699ff; }

            .al-more-toggle { color: #3699ff; cursor: pointer; font-weight: 600; font-size: 0.9rem; display: inline-block; margin: 8px 0; }
            .al-more-toggle:hover { text-decoration: underline; }
            .al-more-wrap { display: none; }
            .al-more-wrap.open { display: block; }

            .al-active-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
            .al-active-chip {
                display: inline-flex;
                align-items: center;
                padding: 3px 4px 3px 10px;
                background: #eef3f8;
                border-radius: 999px;
                font-size: 0.78rem;
                color: #3f4254;
            }
            .al-active-chip-remove {
                margin-left: 6px;
                width: 18px; height: 18px;
                display: inline-flex; align-items: center; justify-content: center;
                border-radius: 50%;
                background: #d7e1eb;
                cursor: pointer;
                font-size: 0.7rem;
                line-height: 1;
            }
            .al-active-chip-remove:hover { background: #b5c3d1; }

            .al-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 12px; }
            .al-actions .al-total { margin-left: auto; color: #7e8299; font-size: 0.9rem; }

            /* Activity log highlight styles (legacy renderer fallback) */
            .highlight { color: #3699FF; font-weight: 600; }
            .highlight-orange { color: #FFA800; font-weight: 600; }
            .highlight-green { color: #1BC5BD; font-weight: 600; }
            .highlight-purple { color: #8950FC; font-weight: 600; }

            /* Mobile filter drawer */
            @media (max-width: 768px) {
                .al-filter-card { padding: 12px; }
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

                            {{-- Date preset pills --}}
                            <div class="al-preset-row" id="al_date_presets">
                                <button type="button" class="al-preset-btn" data-preset="today">Today</button>
                                <button type="button" class="al-preset-btn" data-preset="yesterday">Yesterday</button>
                                <button type="button" class="al-preset-btn active" data-preset="last7">Last 7 days</button>
                                <button type="button" class="al-preset-btn" data-preset="last30">Last 30 days</button>
                                <button type="button" class="al-preset-btn" data-preset="thisMonth">This month</button>
                                <button type="button" class="al-preset-btn" data-preset="lastMonth">Last month</button>
                                <button type="button" class="al-preset-btn" data-preset="custom">Custom…</button>
                            </div>

                            {{-- Primary filter row: date / search / centre / actor --}}
                            <div class="al-filter-row">
                                <div>
                                    <div class="al-field-label">Date range</div>
                                    <div class="al-search-wrap">
                                        {!! Form::text('date_range', null, ['id' => 'activity_date_range', 'class' => 'form-control', 'readonly' => true]) !!}
                                    </div>
                                </div>
                                <div>
                                    <div class="al-field-label">Search</div>
                                    <div class="al-search-wrap">
                                        <i class="fa fa-search"></i>
                                        <input type="text" id="al_search" class="form-control al-search-input" placeholder="Patient name, plan #, ...">
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
                            </div>

                            {{-- Tag family multi-select --}}
                            <div>
                                <div class="al-field-label">Event type (filter by tag family)</div>
                                <div class="al-tag-picker" id="al_tag_picker">
                                    @foreach($tags as $tag)
                                        @php($color = $tagColors[$tag] ?? 'zinc')
                                        <label class="al-tag-option" title="{{ $tag }}">
                                            <input type="checkbox" value="{{ $tag }}" class="al-tag-checkbox">
                                            <span class="act-tag act-tag--{{ $color }}">{{ $tag }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            {{-- More filters (collapsible) --}}
                            <span class="al-more-toggle" id="al_more_toggle">▸ More filters</span>
                            <div class="al-more-wrap" id="al_more_wrap">
                                <div class="al-filter-row">
                                    <div>
                                        <div class="al-field-label">Activity type</div>
                                        <select class="form-control" id="activity_type">
                                            <option value="all">All types</option>
                                            <option value="Consultancy">Consultancy</option>
                                            <option value="Plan">Plan</option>
                                        </select>
                                    </div>
                                    <div>
                                        <div class="al-field-label">Patient ID</div>
                                        <input type="number" id="al_patient_id" class="form-control" placeholder="e.g. 42018" min="1">
                                    </div>
                                    <div>
                                        <div class="al-field-label">Min Rs.</div>
                                        <input type="number" id="al_amount_min" class="form-control" placeholder="0" min="0">
                                    </div>
                                    <div>
                                        <div class="al-field-label">Max Rs.</div>
                                        <input type="number" id="al_amount_max" class="form-control" placeholder="No limit" min="0">
                                    </div>
                                </div>
                            </div>

                            {{-- Active filter chips --}}
                            <div class="al-active-chips" id="al_active_chips"></div>

                            {{-- Action buttons --}}
                            <div class="al-actions">
                                <button type="button" id="al_load_btn" class="btn btn-success spinner-button">
                                    <i class="fa fa-sync-alt mr-1"></i> Load Report
                                </button>
                                <button type="button" id="al_reset_btn" class="btn btn-light">
                                    <i class="fa fa-undo mr-1"></i> Reset
                                </button>
                                <button type="button" id="al_export_btn" class="btn btn-light-primary">
                                    <i class="fa fa-download mr-1"></i> Export CSV
                                </button>
                                <span class="al-total" id="activity_total_wrap" style="display:none;">
                                    Total: <strong><span id="activity_total">0</span></strong> records
                                </span>
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
                            <input type="hidden" name="activity_type" id="al_export_type">
                            <input type="hidden" name="search" id="al_export_search">
                            <input type="hidden" name="patient_id" id="al_export_patient">
                            <input type="hidden" name="amount_min" id="al_export_amount_min">
                            <input type="hidden" name="amount_max" id="al_export_amount_max">
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
                // Date range picker
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

                // Preset button clicks
                $('#al_date_presets').on('click', '.al-preset-btn', function () {
                    var preset = $(this).data('preset');
                    $('.al-preset-btn').removeClass('active');
                    $(this).addClass('active');
                    if (preset !== 'custom' && PRESETS[preset]) {
                        var range = PRESETS[preset]();
                        $dr.data('daterangepicker').setStartDate(range[0]);
                        $dr.data('daterangepicker').setEndDate(range[1]);
                        loadReport();
                    } else if (preset === 'custom') {
                        $dr.focus().trigger('click');
                    }
                });

                // When custom range is changed, mark Custom preset active
                $dr.on('apply.daterangepicker', function () {
                    // A true preset click already sets active — this fires for manual picks
                    var d = $dr.data('daterangepicker');
                    var start = d.startDate.format('YYYY-MM-DD');
                    var end = d.endDate.format('YYYY-MM-DD');
                    var matched = null;
                    Object.keys(PRESETS).forEach(function (k) {
                        var r = PRESETS[k]();
                        if (r[0].format('YYYY-MM-DD') === start && r[1].format('YYYY-MM-DD') === end) {
                            matched = k;
                        }
                    });
                    $('.al-preset-btn').removeClass('active');
                    if (matched) {
                        $('.al-preset-btn[data-preset="' + matched + '"]').addClass('active');
                    } else {
                        $('.al-preset-btn[data-preset="custom"]').addClass('active');
                    }
                    loadReport();
                });

                // More filters toggle
                $('#al_more_toggle').on('click', function () {
                    var $wrap = $('#al_more_wrap');
                    $wrap.toggleClass('open');
                    $(this).text($wrap.hasClass('open') ? '▾ Fewer filters' : '▸ More filters');
                });

                // Debounced search
                var searchTimer = null;
                $('#al_search').on('input', function () {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(loadReport, 500);
                });

                // Tag picker changes
                $('#al_tag_picker').on('change', '.al-tag-checkbox', function () {
                    loadReport();
                });

                // More-filter inputs — reload on blur (not per-keystroke to avoid query spam)
                $('#activity_type, #location_id, #doctor_id').on('change', function () {
                    loadReport();
                });
                $('#al_patient_id, #al_amount_min, #al_amount_max').on('blur', function () {
                    if ($(this).val() !== '') { loadReport(); }
                });

                // Reset button
                $('#al_reset_btn').on('click', function () {
                    $('#al_search').val('');
                    $('#activity_type').val('all');
                    $('#location_id').val('').trigger('change');
                    $('#doctor_id').val('').trigger('change');
                    $('#al_patient_id').val('');
                    $('#al_amount_min').val('');
                    $('#al_amount_max').val('');
                    $('.al-tag-checkbox').prop('checked', false);

                    // Reset to "last 7 days"
                    var range = PRESETS.last7();
                    $dr.data('daterangepicker').setStartDate(range[0]);
                    $dr.data('daterangepicker').setEndDate(range[1]);
                    $('.al-preset-btn').removeClass('active');
                    $('.al-preset-btn[data-preset="last7"]').addClass('active');
                    loadReport();
                });

                // Load button
                $('#al_load_btn').on('click', function () { loadReport(); });

                // Export CSV
                $('#al_export_btn').on('click', function () {
                    var p = collectParams();
                    $('#al_export_start').val(p.startDate);
                    $('#al_export_end').val(p.endDate);
                    $('#al_export_location').val(p.location_id || '');
                    $('#al_export_user').val(p.user_id || '');
                    $('#al_export_type').val(p.activity_type || '');
                    $('#al_export_search').val(p.search || '');
                    $('#al_export_patient').val(p.patient_id || '');
                    $('#al_export_amount_min').val(p.amount_min || '');
                    $('#al_export_amount_max').val(p.amount_max || '');
                    var $tagsBox = $('#al_export_tags').empty();
                    (p.tags || []).forEach(function (t) {
                        $tagsBox.append('<input type="hidden" name="tags[]" value="' + t + '">');
                    });
                    $('#al_export_form').submit();
                });

                function collectParams() {
                    var d = $dr.data('daterangepicker');
                    var tags = [];
                    $('.al-tag-checkbox:checked').each(function () { tags.push($(this).val()); });
                    return {
                        startDate: d.startDate.format('YYYY-MM-DD'),
                        endDate: d.endDate.format('YYYY-MM-DD'),
                        location_id: $('#location_id').val(),
                        user_id: $('#doctor_id').val(),
                        activity_type: $('#activity_type').val(),
                        search: $.trim($('#al_search').val()),
                        patient_id: $('#al_patient_id').val(),
                        amount_min: $('#al_amount_min').val(),
                        amount_max: $('#al_amount_max').val(),
                        tags: tags
                    };
                }

                function renderActiveChips() {
                    var p = collectParams();
                    var chips = [];
                    chips.push({ label: p.startDate + ' → ' + p.endDate, key: 'date' });
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
                    if (p.patient_id) chips.push({ label: 'Patient #' + p.patient_id, key: 'patient' });
                    if (p.amount_min) chips.push({ label: '≥ Rs. ' + p.amount_min, key: 'amount_min' });
                    if (p.amount_max) chips.push({ label: '≤ Rs. ' + p.amount_max, key: 'amount_max' });

                    var $box = $('#al_active_chips').empty();
                    chips.forEach(function (c) {
                        $box.append(
                            '<span class="al-active-chip" data-key="' + c.key + '">' +
                                c.label +
                                '<span class="al-active-chip-remove">&times;</span>' +
                            '</span>'
                        );
                    });
                }

                $('#al_active_chips').on('click', '.al-active-chip-remove', function () {
                    var key = $(this).parent().data('key');
                    if (key === 'search') $('#al_search').val('');
                    else if (key === 'patient') $('#al_patient_id').val('');
                    else if (key === 'amount_min') $('#al_amount_min').val('');
                    else if (key === 'amount_max') $('#al_amount_max').val('');
                    else if (key === 'location') $('#location_id').val('').trigger('change');
                    else if (key === 'user') $('#doctor_id').val('').trigger('change');
                    else if (key && key.indexOf('tag:') === 0) {
                        $('.al-tag-checkbox[value="' + key.substr(4) + '"]').prop('checked', false);
                    }
                    loadReport();
                });

                // ===================== fetch / pagination =====================
                var activityNextCursor = null;
                var activityNextOffset = 0;

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
                        type: 'POST',
                        dataType: 'json',
                        data: p
                    }).done(function (response) {
                        if (!response || !response.success) {
                            if (typeof toastr !== 'undefined') toastr.error('Could not load activity logs. Please try again.');
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
