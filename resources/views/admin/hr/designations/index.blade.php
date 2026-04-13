@extends('admin.layouts.master')
@section('title', 'Designations')
@section('content')

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

        @include('admin.partials.breadcrumb', ['module' => 'HRM', 'title' => 'Designations'])

        <div class="d-flex flex-column-fluid">
            <div class="container">
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon">
                                <span class="svg-icon svg-icon-md svg-icon-primary">
                                    <i class="la la-sitemap icon-lg text-primary"></i>
                                </span>
                            </span>
                            <h3 class="card-label">Designations</h3>
                        </div>
                        <div class="card-toolbar">
                            @can('hr_designations_manage')
                                <a href="javascript:void(0);" class="btn btn-primary" data-toggle="modal" data-target="#modal_add_designation">
                                    <i class="la la-plus"></i> Add New
                                </a>
                            @endcan
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-head-custom table-vertical-center table-head-bg" id="designations_table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($designations as $designation)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $designation->name }}</td>
                                            <td>{{ $designation->department?->name ?? '—' }}</td>
                                            <td>
                                                @if($designation->active)
                                                    <span class="label label-lg label-light-success label-inline">Active</span>
                                                @else
                                                    <span class="label label-lg label-light-danger label-inline">Inactive</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @can('hr_designations_manage')
                                                    <a href="javascript:void(0);"
                                                       class="btn btn-sm btn-clean btn-icon btn-icon-md edit-designation"
                                                       data-id="{{ $designation->id }}"
                                                       data-name="{{ $designation->name }}"
                                                       data-department-id="{{ $designation->department_id }}"
                                                       data-active="{{ $designation->active }}"
                                                       title="Edit">
                                                        <i class="la la-pencil"></i>
                                                    </a>
                                                    <a href="javascript:void(0);"
                                                       class="btn btn-sm btn-clean btn-icon btn-icon-md delete-designation"
                                                       data-url="{{ route('admin.hr.designations.destroy', $designation) }}"
                                                       title="Delete">
                                                        <i class="la la-trash text-danger"></i>
                                                    </a>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No designations found.</td>
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

    @include('admin.hr.designations.partials.modal', ['departments' => $departments])

    @push('js')
    <script>
        $(function () {
            // Initialize Select2
            $('.select2').select2();

            // Add Designation
            $('#form_add_designation').on('submit', function (e) {
                e.preventDefault();
                let form = $(this);
                let btn = form.find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');

                $.ajax({
                    url: form.attr('action'),
                    method: 'POST',
                    data: form.serialize(),
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
                            let errors = xhr.responseJSON.errors;
                            Object.values(errors).forEach(function (msgs) {
                                toastr.error(msgs[0]);
                            });
                        } else {
                            toastr.error('Something went wrong, please try again.');
                        }
                    },
                    complete: function () {
                        btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right');
                    }
                });
            });

            // Edit Designation — populate modal
            $('.edit-designation').on('click', function () {
                let id = $(this).data('id');
                let name = $(this).data('name');
                let departmentId = $(this).data('department-id');
                let active = $(this).data('active');
                let url = '{{ route("admin.hr.designations.update", ":id") }}'.replace(':id', id);

                $('#edit_designation_name').val(name);
                $('#edit_designation_department_id').val(departmentId).trigger('change');
                $('#edit_designation_active').prop('checked', active == 1);
                $('#form_edit_designation').attr('action', url);
                $('#modal_edit_designation').modal('show');
            });

            // Update Designation
            $('#form_edit_designation').on('submit', function (e) {
                e.preventDefault();
                let form = $(this);
                let btn = form.find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');

                $.ajax({
                    url: form.attr('action'),
                    method: 'POST',
                    data: form.serialize(),
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
                            let errors = xhr.responseJSON.errors;
                            Object.values(errors).forEach(function (msgs) {
                                toastr.error(msgs[0]);
                            });
                        } else {
                            toastr.error('Something went wrong, please try again.');
                        }
                    },
                    complete: function () {
                        btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right');
                    }
                });
            });

            // Delete Designation
            $('.delete-designation').on('click', function () {
                let url = $(this).data('url');
                if (!confirm('Are you sure you want to delete this designation?')) return;

                $.ajax({
                    url: url,
                    method: 'DELETE',
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
                        toastr.error('Something went wrong, please try again.');
                    }
                });
            });
        });
    </script>
    @endpush

@endsection
