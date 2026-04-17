@extends('admin.layouts.master')
@section('title', 'My Leaves')
@section('content')

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'My HR', 'title' => 'My Leaves'])

        <div class="d-flex flex-column-fluid">
            <div class="container">

                {{-- Balance Cards --}}
                <div class="row mb-5">
                    @foreach($leaveTypes as $type)
                        @php
                            $balance = $balances->get($type->id);
                            $allocated = $balance ? (float) $balance->allocated : 0;
                            $used = $balance ? (float) $balance->used : 0;
                            $remaining = $allocated - $used;
                        @endphp
                        <div class="col-6 col-md-3 mb-4">
                            <div class="card card-custom card-stretch">
                                <div class="card-body text-center py-4 px-2">
                                    <span class="font-weight-bold text-muted d-block mb-1 font-size-sm">{{ $type->name }}</span>
                                    <span class="font-weight-bolder font-size-h2 {{ $remaining < 0 ? 'text-danger' : 'text-primary' }}">{{ $remaining }}</span>
                                    <span class="d-block text-muted font-size-xs mt-1">{{ $used }} used / {{ $allocated }} total</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Apply Button (mobile: full width sticky feel) --}}
                <div class="mb-5 d-md-none">
                    <button type="button" class="btn btn-primary btn-block" data-toggle="modal" data-target="#modal_apply_leave">
                        <i class="la la-plus"></i> Apply for Leave
                    </button>
                </div>

                {{-- Leave History --}}
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label">Leave History ({{ $fiscalYear }})</h3>
                        </div>
                        <div class="card-toolbar d-none d-md-block">
                            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modal_apply_leave">
                                <i class="la la-plus"></i> Apply for Leave
                            </button>
                        </div>
                    </div>
                    <div class="card-body">

                        {{-- Desktop table --}}
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-head-custom table-vertical-center table-head-bg">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Dates</th>
                                        <th>Days</th>
                                        <th>Duration</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Applied On</th>
                                        <th class="text-center" style="width: 80px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($applications as $app)
                                        <tr>
                                            <td>{{ $app->leaveType?->name ?? '---' }}</td>
                                            <td>
                                                {{ $app->start_date->format('d M') }}
                                                @if($app->start_date->ne($app->end_date))
                                                    &ndash; {{ $app->end_date->format('d M Y') }}
                                                @else
                                                    , {{ $app->start_date->format('Y') }}
                                                @endif
                                            </td>
                                            <td>
                                                {{ $app->total_days }}
                                                @if($app->duration_type->value === 'custom' && $app->custom_hours)
                                                    <span class="text-muted font-size-xs d-block">{{ rtrim(rtrim((string) $app->custom_hours, '0'), '.') }} hrs</span>
                                                @elseif(in_array($app->duration_type->value, ['half','short'], true) && $app->shift_hours_snapshot)
                                                    <span class="text-muted font-size-xs d-block">{{ rtrim(rtrim(number_format((float) $app->total_days * (float) $app->shift_hours_snapshot, 2), '0'), '.') }} hrs</span>
                                                @endif
                                            </td>
                                            <td><span class="label label-light-primary label-inline">{{ $app->duration_type->label() }}</span></td>
                                            <td class="text-truncate" style="max-width:180px;" title="{{ $app->reason }}">{{ $app->reason }}</td>
                                            <td><span class="label {{ $app->status->badgeClass() }} label-inline">{{ $app->status->label() }}</span></td>
                                            <td>{{ $app->created_at->format('d M Y') }}</td>
                                            <td class="text-center text-nowrap">
                                                @if($app->status->value === 'pending')
                                                    <button type="button" class="btn btn-sm btn-clean btn-icon btn-edit-leave"
                                                        data-id="{{ $app->id }}"
                                                        data-leave-type="{{ $app->leave_type_id }}"
                                                        data-duration="{{ $app->duration_type->value }}"
                                                        data-custom-hours="{{ $app->custom_hours }}"
                                                        data-start="{{ $app->start_date->format('Y-m-d') }}"
                                                        data-end="{{ $app->end_date->format('Y-m-d') }}"
                                                        data-reason="{{ e($app->reason) }}"
                                                        title="Edit">
                                                        <i class="la la-pencil text-primary"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-clean btn-icon btn-cancel-leave"
                                                        data-url="{{ route('admin.hr.my.leaves.cancel', $app) }}" title="Cancel">
                                                        <i class="la la-times-circle text-danger"></i>
                                                    </button>
                                                @else
                                                    ---
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-5">No leave applications this fiscal year.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Mobile cards --}}
                        <div class="d-md-none">
                            @forelse($applications as $app)
                                <div class="border rounded p-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span class="font-weight-bolder">{{ $app->leaveType?->name ?? '---' }}</span>
                                            <span class="label label-light-primary label-inline font-size-xs ml-2">{{ $app->duration_type->label() }}</span>
                                        </div>
                                        <span class="label {{ $app->status->badgeClass() }} label-inline">{{ $app->status->label() }}</span>
                                    </div>
                                    <div class="text-muted font-size-sm mb-1">
                                        {{ $app->start_date->format('d M') }}
                                        @if($app->start_date->ne($app->end_date))
                                            &ndash; {{ $app->end_date->format('d M Y') }}
                                        @else
                                            , {{ $app->start_date->format('Y') }}
                                        @endif
                                        <span class="mx-1">&middot;</span>
                                        {{ $app->total_days }} {{ $app->total_days == 1 ? 'day' : 'days' }}
                                        @if($app->duration_type->value === 'custom' && $app->custom_hours)
                                            <span class="text-muted">({{ rtrim(rtrim((string) $app->custom_hours, '0'), '.') }} hrs)</span>
                                        @elseif(in_array($app->duration_type->value, ['half','short'], true) && $app->shift_hours_snapshot)
                                            <span class="text-muted">({{ rtrim(rtrim(number_format((float) $app->total_days * (float) $app->shift_hours_snapshot, 2), '0'), '.') }} hrs)</span>
                                        @endif
                                    </div>
                                    @if($app->reason)
                                        <div class="font-size-sm text-dark-50 mb-2">{{ Str::limit($app->reason, 100) }}</div>
                                    @endif
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted font-size-xs">Applied {{ $app->created_at->format('d M Y') }}</span>
                                        @if($app->status->value === 'pending')
                                            <div>
                                                <button type="button" class="btn btn-xs btn-light-primary btn-edit-leave mr-1"
                                                    data-id="{{ $app->id }}"
                                                    data-leave-type="{{ $app->leave_type_id }}"
                                                    data-duration="{{ $app->duration_type->value }}"
                                                    data-custom-hours="{{ $app->custom_hours }}"
                                                    data-start="{{ $app->start_date->format('Y-m-d') }}"
                                                    data-end="{{ $app->end_date->format('Y-m-d') }}"
                                                    data-reason="{{ e($app->reason) }}">
                                                    <i class="la la-pencil mr-1"></i>Edit
                                                </button>
                                                <button type="button" class="btn btn-xs btn-light-danger btn-cancel-leave"
                                                    data-url="{{ route('admin.hr.my.leaves.cancel', $app) }}">
                                                    <i class="la la-times-circle mr-1"></i>Cancel
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted py-5">No leave applications this fiscal year.</div>
                            @endforelse
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- Apply Leave Modal --}}
    <div class="modal fade" id="modal_apply_leave" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header py-3">
                    <h5 class="modal-title font-weight-bolder" id="leave_modal_title">Apply for Leave</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><i class="la la-times"></i></button>
                </div>
                <div class="modal-body">
                    <form id="form_apply_leave" method="post" action="{{ route('admin.hr.my.leaves.apply') }}"
                          data-apply-url="{{ route('admin.hr.my.leaves.apply') }}"
                          data-update-url="{{ route('admin.hr.my.leaves.update', ':id') }}"
                          data-shift-hours="{{ $shiftHours ?? '' }}">
                        @csrf
                        <input type="hidden" id="leave_edit_id" value="">
                        <div class="form-group">
                            <label class="font-weight-bold font-size-sm mb-1">Leave Type <span class="text-danger">*</span></label>
                            <select name="leave_type_id" class="form-control select2" style="width:100%;" required>
                                <option value="">--- Select ---</option>
                                @foreach($leaveTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold font-size-sm mb-2">Duration <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap" style="gap:8px;">
                                <label class="btn btn-outline-primary btn-sm mb-0 leave-dur-btn active">
                                    <input type="radio" name="duration_type" value="full" checked class="d-none"> Full Day
                                </label>
                                <label class="btn btn-outline-primary btn-sm mb-0 leave-dur-btn">
                                    <input type="radio" name="duration_type" value="half" class="d-none"> Half Day
                                </label>
                                <label class="btn btn-outline-primary btn-sm mb-0 leave-dur-btn">
                                    <input type="radio" name="duration_type" value="short" class="d-none"> Short Leave
                                </label>
                                @if(!empty($shiftHours))
                                <label class="btn btn-outline-primary btn-sm mb-0 leave-dur-btn">
                                    <input type="radio" name="duration_type" value="custom" class="d-none"> Custom Hours
                                </label>
                                @endif
                            </div>
                            @if(!empty($shiftHours))
                                <span class="form-text text-muted font-size-xs mt-1">Your shift is {{ rtrim(rtrim((string) $shiftHours, '0'), '.') }} hours.</span>
                            @endif
                        </div>
                        <div class="form-group" id="custom_hours_group" style="display:none;">
                            <label class="font-weight-bold font-size-sm mb-1">Hours <span class="text-danger">*</span></label>
                            <input type="number" step="0.25" min="0.25" name="custom_hours" id="leave_custom_hours" class="form-control" placeholder="e.g. 3">
                            <span class="form-text text-muted font-size-xs">Less than your shift hours ({{ rtrim(rtrim((string) ($shiftHours ?? ''), '0'), '.') }}). Single day only.</span>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold font-size-sm mb-1">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="leave_start_date" class="form-control" required>
                        </div>
                        <div class="form-group" id="end_date_group">
                            <label class="font-weight-bold font-size-sm mb-1">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="leave_end_date" class="form-control" required>
                        </div>
                        <div id="total_days_row" class="form-group">
                            <label class="font-weight-bold font-size-sm mb-1">Total Days</label>
                            <input type="text" id="total_days_display" class="form-control bg-light" readonly value="0">
                        </div>
                        <div class="form-group mb-4">
                            <label class="font-weight-bold font-size-sm mb-1">Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="2" maxlength="1000" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block" id="leave_submit_btn">Submit Application</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('css')
    <style>
        .leave-dur-btn { cursor: pointer; }
        .leave-dur-btn.active { background-color: #3699FF; color: #fff; border-color: #3699FF; }
        @media (max-width: 575.98px) {
            #modal_apply_leave .modal-dialog {
                margin: 0;
                max-width: 100%;
                min-height: 100vh;
            }
            #modal_apply_leave .modal-content {
                min-height: 100vh;
                border-radius: 0;
            }
        }
    </style>
    @endpush

    @push('js')
    <script>
        $(function () {
            $('.select2').select2({ dropdownParent: $('#modal_apply_leave') });

            // Duration toggle (button-style)
            $('.leave-dur-btn').on('click', function () {
                $('.leave-dur-btn').removeClass('active');
                $(this).addClass('active');
                $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
            });

            var shiftHours = parseFloat($('#form_apply_leave').data('shift-hours')) || 0;

            $('input[name="duration_type"]').on('change', function () {
                var val = $(this).val();
                if (val === 'full') {
                    $('#end_date_group').show();
                    $('#leave_end_date').prop('required', true);
                    $('#total_days_row').show();
                    $('#custom_hours_group').hide();
                    $('#leave_custom_hours').prop('required', false);
                } else {
                    $('#end_date_group').hide();
                    $('#leave_end_date').prop('required', false).val('');
                    $('#total_days_row').show();
                    if (val === 'custom') {
                        $('#custom_hours_group').show();
                        $('#leave_custom_hours').prop('required', true);
                    } else {
                        $('#custom_hours_group').hide();
                        $('#leave_custom_hours').prop('required', false);
                    }
                }
                calcDays();
            });

            function formatDays(d) {
                return (Math.round(d * 100) / 100).toString();
            }

            function calcDays() {
                var durationType = $('input[name="duration_type"]:checked').val();
                if (durationType === 'half') {
                    var hrs = shiftHours ? ' (' + formatDays(shiftHours * 0.5) + ' hrs)' : '';
                    $('#total_days_display').val('0.5' + hrs);
                    return;
                }
                if (durationType === 'short') {
                    var hrsS = shiftHours ? ' (' + formatDays(shiftHours * 0.25) + ' hrs)' : '';
                    $('#total_days_display').val('0.25' + hrsS);
                    return;
                }
                if (durationType === 'custom') {
                    var h = parseFloat($('#leave_custom_hours').val());
                    if (shiftHours > 0 && h > 0) {
                        $('#total_days_display').val(formatDays(h / shiftHours) + ' (' + h + ' hrs)');
                    } else {
                        $('#total_days_display').val('0');
                    }
                    return;
                }

                var start = $('#leave_start_date').val();
                var end = $('#leave_end_date').val();
                if (start && end) {
                    var s = new Date(start), e = new Date(end);
                    var diff = Math.ceil((e - s) / (1000 * 60 * 60 * 24)) + 1;
                    $('#total_days_display').val(diff > 0 ? diff : 0);
                } else {
                    $('#total_days_display').val('0');
                }
            }

            $('#leave_start_date, #leave_end_date, #leave_custom_hours').on('change input', calcDays);

            // Edit leave — populate modal
            $(document).on('click', '.btn-edit-leave', function () {
                var btn = $(this);
                var form = $('#form_apply_leave');
                $('#leave_edit_id').val(btn.data('id'));
                $('#leave_modal_title').text('Edit Leave Application');
                $('#leave_submit_btn').text('Update Application');

                // Set leave type
                form.find('select[name="leave_type_id"]').val(btn.data('leave-type')).trigger('change');

                // Set duration
                var dur = btn.data('duration');
                $('input[name="duration_type"]').each(function () {
                    if ($(this).val() === dur) {
                        $(this).prop('checked', true);
                        $(this).closest('.leave-dur-btn').addClass('active');
                    } else {
                        $(this).closest('.leave-dur-btn').removeClass('active');
                    }
                });

                // Show/hide end date + custom hours based on duration
                if (dur === 'full') {
                    $('#end_date_group').show();
                    $('#leave_end_date').prop('required', true);
                    $('#total_days_row').show();
                    $('#custom_hours_group').hide();
                    $('#leave_custom_hours').prop('required', false).val('');
                } else {
                    $('#end_date_group').hide();
                    $('#leave_end_date').prop('required', false);
                    $('#total_days_row').show();
                    if (dur === 'custom') {
                        $('#custom_hours_group').show();
                        $('#leave_custom_hours').prop('required', true).val(btn.data('custom-hours') || '');
                    } else {
                        $('#custom_hours_group').hide();
                        $('#leave_custom_hours').prop('required', false).val('');
                    }
                }

                // Set dates and reason
                $('#leave_start_date').val(btn.data('start'));
                $('#leave_end_date').val(btn.data('end'));
                form.find('textarea[name="reason"]').val(btn.data('reason'));

                calcDays();
                $('#modal_apply_leave').modal('show');
            });

            // Reset modal to "Apply" mode when opened via Apply button or closed
            $('[data-target="#modal_apply_leave"]').on('click', function () {
                var form = $('#form_apply_leave');
                $('#leave_edit_id').val('');
                $('#leave_modal_title').text('Apply for Leave');
                $('#leave_submit_btn').text('Submit Application');
                form[0].reset();
                form.find('select[name="leave_type_id"]').val('').trigger('change');
                $('.leave-dur-btn').removeClass('active').first().addClass('active');
                $('input[name="duration_type"][value="full"]').prop('checked', true);
                $('#end_date_group').show();
                $('#leave_end_date').prop('required', true);
                $('#total_days_row').show();
                $('#custom_hours_group').hide();
                $('#leave_custom_hours').prop('required', false).val('');
                $('#total_days_display').val('0');
            });

            // Submit — inject end_date = start_date for half/short
            $('#form_apply_leave').on('submit', function (e) {
                e.preventDefault();
                var form = $(this);
                var btn = form.find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');

                var editId = $('#leave_edit_id').val();
                var isEdit = editId !== '';
                var url = isEdit
                    ? form.data('update-url').replace(':id', editId)
                    : form.data('apply-url');
                var method = isEdit ? 'PUT' : 'POST';

                var data = form.serializeArray();
                var durationType = data.find(function (d) { return d.name === 'duration_type'; });
                if (durationType && ['half', 'short', 'custom'].indexOf(durationType.value) !== -1) {
                    var startDate = data.find(function (d) { return d.name === 'start_date'; });
                    var endDate = data.find(function (d) { return d.name === 'end_date'; });
                    if (endDate) {
                        endDate.value = startDate ? startDate.value : '';
                    } else {
                        data.push({ name: 'end_date', value: startDate ? startDate.value : '' });
                    }
                }

                $.ajax({
                    url: url,
                    method: method,
                    data: $.param(data),
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) {
                        if (res.status) {
                            toastr.success(res.message);
                            location.reload();
                        } else {
                            toastr.error(res.message);
                        }
                    },
                    error: function (xhr) {
                        if (xhr.status === 422) {
                            Object.values(xhr.responseJSON.errors).forEach(function (m) { toastr.error(m[0]); });
                        } else {
                            toastr.error('Something went wrong.');
                        }
                    },
                    complete: function () {
                        btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right');
                    }
                });
            });

            // Cancel leave
            $(document).on('click', '.btn-cancel-leave', function () {
                if (!confirm('Cancel this leave application?')) return;
                var url = $(this).data('url');
                var btn = $(this);
                btn.attr('disabled', true);

                $.ajax({
                    url: url,
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) {
                        if (res.status) {
                            toastr.success(res.message);
                            location.reload();
                        } else {
                            toastr.error(res.message);
                        }
                    },
                    error: function () {
                        toastr.error('Something went wrong.');
                    },
                    complete: function () {
                        btn.attr('disabled', false);
                    }
                });
            });
        });
    </script>
    @endpush

@endsection
