@extends('admin.layouts.master')
@section('title', 'Leave Applications')
@section('content')

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'HRM', 'title' => 'Leave Applications'])

        <div class="d-flex flex-column-fluid">
            <div class="container">
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon"><i class="la la-file-alt icon-lg text-primary"></i></span>
                            <h3 class="card-label">Leave Applications</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        {{-- Filters --}}
                        <div class="mt-2 mb-7">
                            <div class="row mb-6">
                                <div class="col-lg-2 mb-lg-0 mb-6">
                                    <label>Employee:</label>
                                    <input type="text" class="form-control" placeholder="Name" id="search_employee_name" />
                                </div>
                                <div class="col-lg-2 mb-lg-0 mb-6">
                                    <label>Status:</label>
                                    <select class="form-control select2" id="search_status">
                                        <option value="">All</option>
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-lg-2 mb-lg-0 mb-6">
                                    <label>Leave Type:</label>
                                    <select class="form-control select2" id="search_leave_type">
                                        <option value="">All</option>
                                        @foreach($leaveTypes as $lt)
                                            <option value="{{ $lt->id }}">{{ $lt->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-2 mb-lg-0 mb-6">
                                    <label>Date From:</label>
                                    <input type="date" class="form-control" id="search_date_from" />
                                </div>
                                <div class="col-lg-2 mb-lg-0 mb-6">
                                    <label>Date To:</label>
                                    <input type="date" class="form-control" id="search_date_to" />
                                </div>
                                <div class="col-lg-2 mb-lg-0 mb-6 mt-8">
                                    @include('admin.partials.filter-buttons')
                                </div>
                            </div>
                        </div>

                        <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Review Modal --}}
    @include('admin.hr.leave.applications.partials.review-modal')

    {{-- Cancel Approved Leave Modal --}}
    <div class="modal fade" id="modal_cancel_leave" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered form-popup">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="fw-bolder">Cancel Approved Leave</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-dismiss="modal">
                        <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                    </div>
                </div>
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <p class="text-muted mb-4">This will cancel the approved leave and restore the employee's leave balance.</p>
                    <form id="form_cancel_leave">
                        <input type="hidden" id="cancel_leave_url" value="">
                        <div class="form-group">
                            <div class="fv-row">
                                <label class="fw-bold fs-6 mb-2">Reason for cancellation</label>
                                <textarea id="cancel_leave_notes" name="review_notes" class="form-control form-control-solid" rows="3" placeholder="Enter reason..." required></textarea>
                            </div>
                        </div>
                        <hr>
                        <div class="text-center">
                            <button type="reset" class="btn btn-light me-3" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-danger">Cancel Leave</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('datatable-js')
    <script>
        var table_url = '{{ route("admin.api.hr.leave-applications.datatable") }}';

        var table_columns = [
            { field: 'employee_name', title: 'Employee', width: 'auto', sortable: false },
            { field: 'leave_type', title: 'Type', width: 100, sortable: false },
            { field: 'start_date', title: 'From', width: 100, sortable: false },
            { field: 'end_date', title: 'To', width: 100, sortable: false },
            {
                field: 'total_days', title: 'Days', width: 80, sortable: false,
                template: function (data) {
                    var html = data.total_days;
                    if (data.hours_display) {
                        html += '<span class="d-block text-muted font-size-xs">' + data.hours_display + '</span>';
                    }
                    return html;
                }
            },
            { field: 'duration_type', title: 'Duration', width: 80, sortable: false },
            {
                field: 'status', title: 'Status', width: 90, sortable: false,
                template: function (data) {
                    var badges = { pending: 'label-light-warning', approved: 'label-light-success', rejected: 'label-light-danger', cancelled: 'label-secondary' };
                    return '<span class="label ' + (badges[data.status] || 'label-light-primary') + ' label-inline">' + data.status.charAt(0).toUpperCase() + data.status.slice(1) + '</span>';
                }
            },
            { field: 'applied_on', title: 'Applied', width: 100, sortable: false },
            {
                field: 'actions', title: 'Actions', sortable: false, width: 120, overflow: 'visible', autoHide: false,
                template: function (data) {
                    var html = '';
                    if (data.status === 'pending' && permissions.approve) {
                        html += '<a href="javascript:void(0);" onclick="reviewLeave(' + data.id + ', \'approve\')" class="btn btn-sm btn-light-success btn-icon mr-1" title="Approve"><i class="la la-check"></i></a>';
                        html += '<a href="javascript:void(0);" onclick="reviewLeave(' + data.id + ', \'reject\')" class="btn btn-sm btn-light-danger btn-icon" title="Reject"><i class="la la-times"></i></a>';
                    }
                    if (data.status === 'approved' && permissions.approve) {
                        html += '<a href="javascript:void(0);" onclick="cancelApprovedLeave(' + data.id + ')" class="btn btn-sm btn-light-dark btn-icon" title="Cancel Leave"><i class="la la-ban"></i></a>';
                    }
                    return html;
                }
            }
        ];

        var cancelUrl = '{{ route("admin.hr.leave-applications.cancel", ":id") }}';
        function cancelApprovedLeave(id) {
            $('#cancel_leave_url').val(cancelUrl.replace(':id', id));
            $('#cancel_leave_notes').val('');
            $('#modal_cancel_leave').modal('show');
        }

        function reviewLeave(id, action) {
            var url = action === 'approve'
                ? '{{ route("admin.hr.leave-applications.approve", ":id") }}'.replace(':id', id)
                : '{{ route("admin.hr.leave-applications.reject", ":id") }}'.replace(':id', id);
            $('#review_action_url').val(url);
            $('#review_action_label').text(action === 'approve' ? 'Approve' : 'Reject');
            $('#review_submit_btn').removeClass('btn-success btn-danger').addClass(action === 'approve' ? 'btn-success' : 'btn-danger');
            $('#review_notes').val('');
            $('#modal_review_leave').modal('show');
        }

        function applyFilters(datatable) {
            $('#apply-filters').on('click', function () {
                datatable.search({
                    employee_name: $('#search_employee_name').val(),
                    status: $('#search_status').val(),
                    leave_type_id: $('#search_leave_type').val(),
                    date_from: $('#search_date_from').val(),
                    date_to: $('#search_date_to').val(),
                    filter: 'filter',
                }, 'search');
            });
        }

        function resetAllFilters(datatable) {
            $('#reset-filters').on('click', function () {
                $('#search_employee_name').val('');
                $('#search_status').val('').trigger('change');
                $('#search_leave_type').val('').trigger('change');
                $('#search_date_from').val('');
                $('#search_date_to').val('');
                datatable.search({ filter: 'filter_cancel' }, 'search');
            });
        }

        function setFilters(filter_values, active_filters) {
            if (active_filters.employee_name) $('#search_employee_name').val(active_filters.employee_name);
            if (active_filters.status) $('#search_status').val(active_filters.status).trigger('change');
            if (active_filters.leave_type_id) $('#search_leave_type').val(active_filters.leave_type_id).trigger('change');
        }
    </script>
    @endpush

    @push('js')
    <script>
        $(function () {
            $('.select2').select2();

            // Submit cancel approved leave
            $('#form_cancel_leave').on('submit', function (e) {
                e.preventDefault();
                let url = $('#cancel_leave_url').val();
                let btn = $(this).find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');
                $.ajax({
                    url: url, method: 'POST',
                    data: { review_notes: $('#cancel_leave_notes').val() },
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) {
                        if (res.status) { toastr.success(res.message); $('#modal_cancel_leave').modal('hide'); reInitTable(); }
                        else { toastr.error(res.message); }
                    },
                    error: function () { toastr.error('Something went wrong.'); },
                    complete: function () { btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right'); }
                });
            });

            // Submit review
            $('#form_review_leave').on('submit', function (e) {
                e.preventDefault();
                let url = $('#review_action_url').val();
                let notes = $('#review_notes').val();
                let btn = $(this).find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');
                $.ajax({
                    url: url, method: 'POST',
                    data: { review_notes: notes },
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) {
                        if (res.status) { toastr.success(res.message); $('#modal_review_leave').modal('hide'); reInitTable(); }
                        else { toastr.error(res.message); }
                    },
                    error: function () { toastr.error('Something went wrong.'); },
                    complete: function () { btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right'); }
                });
            });
        });
    </script>
    @endpush

@endsection
