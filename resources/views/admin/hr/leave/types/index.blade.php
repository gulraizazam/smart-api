@extends('admin.layouts.master')
@section('title', 'Leave Types')
@section('content')

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'HRM', 'title' => 'Leave Types'])

        <div class="d-flex flex-column-fluid">
            <div class="container">
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon"><i class="la la-calendar-check icon-lg text-primary"></i></span>
                            <h3 class="card-label">Leave Types</h3>
                        </div>
                        <div class="card-toolbar">
                            @can('hr_leave_manage')
                                <a href="javascript:void(0);" class="btn btn-primary" data-toggle="modal" data-target="#modal_add_leave_type">
                                    <i class="la la-plus"></i> Add New
                                </a>
                            @endcan
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-head-custom table-vertical-center table-head-bg">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Paid/Unpaid</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($leaveTypes as $lt)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $lt->name }}</td>
                                            <td>
                                                @if($lt->is_paid)
                                                    <span class="label label-light-success label-inline">Paid</span>
                                                @else
                                                    <span class="label label-light-warning label-inline">Unpaid</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($lt->active)
                                                    <span class="label label-light-success label-inline">Active</span>
                                                @else
                                                    <span class="label label-light-danger label-inline">Inactive</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @can('hr_leave_manage')
                                                    <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon edit-leave-type"
                                                       data-id="{{ $lt->id }}" data-name="{{ $lt->name }}" data-is-paid="{{ $lt->is_paid }}" data-active="{{ $lt->active }}" title="Edit">
                                                        <i class="la la-pencil"></i>
                                                    </a>
                                                    <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon delete-leave-type"
                                                       data-url="{{ route('admin.hr.leave-types.destroy', $lt) }}" title="Delete">
                                                        <i class="la la-trash text-danger"></i>
                                                    </a>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted">No leave types found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.hr.leave.types.partials.modal')

    @push('js')
    <script>
        $(function () {
            // Add
            $('#form_add_leave_type').on('submit', function (e) {
                e.preventDefault();
                let form = $(this), btn = form.find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');
                $.ajax({
                    url: form.attr('action'), method: 'POST', data: form.serialize(),
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) { if (res.status) { toastr.success(res.message); location.reload(); } else { toastr.error(res.message); } },
                    error: function (xhr) { if (xhr.status === 422) { Object.values(xhr.responseJSON.errors).forEach(function (m) { toastr.error(m[0]); }); } else { toastr.error('Something went wrong.'); } },
                    complete: function () { btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right'); }
                });
            });

            // Edit — populate
            $('.edit-leave-type').on('click', function () {
                let id = $(this).data('id');
                let url = '{{ route("admin.hr.leave-types.update", ":id") }}'.replace(':id', id);
                $('#edit_leave_type_name').val($(this).data('name'));
                $('#edit_leave_type_is_paid').prop('checked', $(this).data('is-paid') == 1);
                $('#edit_leave_type_active').prop('checked', $(this).data('active') == 1);
                $('#form_edit_leave_type').attr('action', url);
                $('#modal_edit_leave_type').modal('show');
            });

            // Update
            $('#form_edit_leave_type').on('submit', function (e) {
                e.preventDefault();
                let form = $(this), btn = form.find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');
                $.ajax({
                    url: form.attr('action'), method: 'POST', data: form.serialize(),
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) { if (res.status) { toastr.success(res.message); location.reload(); } else { toastr.error(res.message); } },
                    error: function (xhr) { if (xhr.status === 422) { Object.values(xhr.responseJSON.errors).forEach(function (m) { toastr.error(m[0]); }); } else { toastr.error('Something went wrong.'); } },
                    complete: function () { btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right'); }
                });
            });

            // Delete
            $('.delete-leave-type').on('click', function () {
                let url = $(this).data('url');
                if (!confirm('Are you sure?')) return;
                $.ajax({
                    url: url, method: 'DELETE',
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) { if (res.status) { toastr.success(res.message); location.reload(); } else { toastr.error(res.message); } },
                    error: function () { toastr.error('Something went wrong.'); }
                });
            });
        });
    </script>
    @endpush

@endsection
