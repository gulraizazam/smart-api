@extends('admin.layouts.master')
@section('title', 'HRM Dashboard')
@section('content')

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'HRM', 'title' => 'Dashboard'])

        <div class="d-flex flex-column-fluid">
            <div class="container">

                {{-- Overview Cards --}}
                <div class="row mb-5">
                    <div class="col-lg-3 col-md-6 mb-5">
                        <div class="card card-custom bg-light-primary card-stretch">
                            <div class="card-body d-flex align-items-center py-5">
                                <div class="d-flex flex-column flex-grow-1">
                                    <a href="{{ route('admin.hr.employees.index') }}" class="text-dark-75 text-hover-primary font-weight-bolder font-size-lg">Active Employees</a>
                                    <span class="text-primary font-weight-bolder font-size-h1 mt-2">{{ number_format($totalEmployees) }}</span>
                                </div>
                                <span class="symbol symbol-50 symbol-light-primary">
                                    <span class="symbol-label"><i class="la la-users icon-2x text-primary"></i></span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-5">
                        <div class="card card-custom bg-light-warning card-stretch">
                            <div class="card-body d-flex align-items-center py-5">
                                <div class="d-flex flex-column flex-grow-1">
                                    <a href="{{ route('admin.hr.leave-applications.index') }}" class="text-dark-75 text-hover-warning font-weight-bolder font-size-lg">Pending Leaves</a>
                                    <span class="text-warning font-weight-bolder font-size-h1 mt-2">{{ number_format($pendingLeaves) }}</span>
                                </div>
                                <span class="symbol symbol-50 symbol-light-warning">
                                    <span class="symbol-label"><i class="la la-clock icon-2x text-warning"></i></span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-5">
                        <div class="card card-custom bg-light-success card-stretch">
                            <div class="card-body d-flex align-items-center py-5">
                                <div class="d-flex flex-column flex-grow-1">
                                    <span class="text-dark-75 font-weight-bolder font-size-lg">Approved This Month</span>
                                    <span class="text-success font-weight-bolder font-size-h1 mt-2">{{ number_format($approvedThisMonth) }}</span>
                                </div>
                                <span class="symbol symbol-50 symbol-light-success">
                                    <span class="symbol-label"><i class="la la-check-circle icon-2x text-success"></i></span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-5">
                        <div class="card card-custom bg-light-info card-stretch">
                            <div class="card-body d-flex align-items-center py-5">
                                <div class="d-flex flex-column flex-grow-1">
                                    <a href="{{ route('admin.hr.recruitment.index') }}" class="text-dark-75 text-hover-info font-weight-bolder font-size-lg">Open Candidates</a>
                                    <span class="text-info font-weight-bolder font-size-h1 mt-2">{{ number_format($openCandidates) }}</span>
                                </div>
                                <span class="symbol symbol-50 symbol-light-info">
                                    <span class="symbol-label"><i class="la la-user-plus icon-2x text-info"></i></span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Pending Leave Applications --}}
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon"><i class="la la-clock icon-lg text-warning"></i></span>
                            <h3 class="card-label">Pending Leave Requests</h3>
                        </div>
                        <div class="card-toolbar">
                            <a href="{{ route('admin.hr.leave-applications.index') }}" class="btn btn-sm btn-light-primary">
                                View All <i class="la la-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-head-custom table-vertical-center table-head-bg">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Dates</th>
                                        <th>Days</th>
                                        <th>Applied On</th>
                                        @can('hr_leave_approve')
                                        <th class="text-center" style="width: 140px;">Actions</th>
                                        @endcan
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($pendingApplications as $app)
                                        <tr>
                                            <td class="font-weight-bold">{{ $app->user?->name ?? '---' }}</td>
                                            <td>{{ $app->leaveType?->name ?? '---' }}</td>
                                            <td>
                                                {{ $app->start_date->format('d M') }}
                                                @if($app->start_date->ne($app->end_date))
                                                    &ndash; {{ $app->end_date->format('d M') }}
                                                @endif
                                                <span class="label label-sm label-light-primary label-inline ml-1">{{ $app->duration_type->label() }}</span>
                                            </td>
                                            <td>{{ $app->total_days }}</td>
                                            <td>{{ $app->created_at->format('d M Y') }}</td>
                                            @can('hr_leave_approve')
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-clean btn-icon btn-review-leave"
                                                    data-url="{{ route('admin.hr.leave-applications.approve', $app) }}"
                                                    data-action="Approve" title="Approve">
                                                    <i class="la la-check-circle text-success icon-lg"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-clean btn-icon btn-review-leave"
                                                    data-url="{{ route('admin.hr.leave-applications.reject', $app) }}"
                                                    data-action="Reject" title="Reject">
                                                    <i class="la la-times-circle text-danger icon-lg"></i>
                                                </button>
                                            </td>
                                            @endcan
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ auth()->user()->can('hr_leave_approve') ? 6 : 5 }}" class="text-center text-muted py-5">
                                                No pending leave requests.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    @include('admin.hr.leave.applications.partials.review-modal')

    @push('js')
    <script>
        $(function () {
            // Review Leave (Approve / Reject)
            $(document).on('click', '.btn-review-leave', function () {
                var url = $(this).data('url');
                var action = $(this).data('action');
                $('#review_action_url').val(url);
                $('#review_action_label').text(action);
                $('#review_submit_btn').removeClass('btn-success btn-danger')
                    .addClass(action === 'Approve' ? 'btn-success' : 'btn-danger')
                    .text(action);
                $('#review_notes').val('');
                $('#modal_review_leave').modal('show');
            });

            $('#form_review_leave').on('submit', function (e) {
                e.preventDefault();
                var btn = $(this).find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');

                $.ajax({
                    url: $('#review_action_url').val(),
                    method: 'POST',
                    data: { review_notes: $('#review_notes').val() },
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
                        btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right');
                    }
                });
            });
        });
    </script>
    @endpush

@endsection
