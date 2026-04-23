@extends('admin.layouts.master')
@section('title', $employee->name . ' — Employee Profile')
@section('content')

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

        @include('admin.partials.breadcrumb', ['module' => 'HRM', 'title' => $employee->name])

        <div class="d-flex flex-column-fluid">
            <div class="container">

                {{-- Personal Info Card --}}
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-user text-primary mr-2"></i>Personal Information</h3>
                        </div>
                        @can('hr_employees_manage')
                        <div class="card-toolbar">
                            <a href="javascript:void(0);" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modal_edit_employee">
                                <i class="la la-pencil"></i> Edit HR Details
                            </a>
                        </div>
                        @endcan
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 text-center mb-4">
                                <div class="symbol symbol-100 symbol-circle">
                                    @if($employee->image_src)
                                        <img src="{{ asset($employee->image_src) }}" alt="{{ $employee->name }}">
                                    @else
                                        <img src="{{ asset('assets/media/logos/avatar.jpg') }}" alt="{{ $employee->name }}">
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-5">
                                <table class="table table-borderless table-sm mb-0">
                                    <tr><td class="font-weight-bold text-muted" style="width:140px">Name</td><td>{{ $employee->name }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Email</td><td>{{ $employee->email ?? '—' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Phone</td><td>{{ $employee->phone ?? '—' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Gender</td><td>{{ $employee->gender == 1 ? 'Male' : ($employee->gender == 2 ? 'Female' : '—') }}</td></tr>
                                </table>
                            </div>
                            <div class="col-md-5">
                                <table class="table table-borderless table-sm mb-0">
                                    <tr><td class="font-weight-bold text-muted" style="width:140px">CNIC</td><td>{{ $employee->cnic ?? '—' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Date of Birth</td><td>{{ $employee->dob ? \Carbon\Carbon::parse($employee->dob)->format('d M Y') : '—' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Address</td><td>{{ $employee->employeeDetail?->address ?? '—' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Status</td><td>
                                        @if($employee->active)
                                            <span class="label label-lg label-light-success label-inline">Active</span>
                                        @else
                                            <span class="label label-lg label-light-danger label-inline">Inactive</span>
                                        @endif
                                    </td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Employment Details Card --}}
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-briefcase text-primary mr-2"></i>Employment Details</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        @php $detail = $employee->employeeDetail; @endphp
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless table-sm mb-0">
                                    <tr><td class="font-weight-bold text-muted" style="width:160px">Department</td><td>{{ $detail?->department?->name ?? '—' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Designation</td><td>{{ $detail?->designation?->name ?? '—' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Hire Date</td><td>{{ $detail?->hire_date ? $detail->hire_date->format('d M Y') : '—' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Employment Type</td><td>{{ $detail?->employment_type?->label() ?? '—' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Shift Hours</td><td>{{ $detail?->shift_hours !== null ? rtrim(rtrim((string) $detail->shift_hours, '0'), '.') . ' hrs' : '—' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Reporting Manager</td><td>{{ $detail?->reportingManager?->name ?? '—' }}</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless table-sm mb-0">
                                    <tr><td class="font-weight-bold text-muted" style="width:160px">Salary</td><td>{{ $detail?->salary ? number_format((float)$detail->salary, 2) : '—' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Bank Name</td><td>{{ $detail?->bank_name ?? '—' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Bank Account</td><td>{{ $detail?->bank_account_number ? '****' . substr($detail->bank_account_number, -4) : '—' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Tax ID</td><td>{{ $detail?->tax_id ?? '—' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Centres</td><td>
                                        @forelse($employee->user_has_locations as $uhl)
                                            <span class="label label-light-primary label-inline mr-1 mb-1">{{ $uhl->location?->name ?? '—' }}</span>
                                        @empty
                                            —
                                        @endforelse
                                    </td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Emergency Contact Card --}}
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-phone text-primary mr-2"></i>Emergency Contact</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <strong class="text-muted">Name:</strong>
                                <span class="ml-2">{{ $detail?->emergency_contact_name ?? '—' }}</span>
                            </div>
                            <div class="col-md-4">
                                <strong class="text-muted">Phone:</strong>
                                <span class="ml-2">{{ $detail?->emergency_contact_phone ?? '—' }}</span>
                            </div>
                            <div class="col-md-4">
                                <strong class="text-muted">Relationship:</strong>
                                <span class="ml-2">{{ $detail?->emergency_contact_relation ?? '—' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Documents Card --}}
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-file text-primary mr-2"></i>Documents</h3>
                        </div>
                        @can('hr_documents_manage')
                        <div class="card-toolbar">
                            <a href="javascript:void(0);" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modal_upload_document">
                                <i class="la la-upload"></i> Upload Document
                            </a>
                        </div>
                        @endcan
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-head-custom table-vertical-center table-head-bg">
                                <thead>
                                    <tr>
                                        <th>File Name</th>
                                        <th>Uploaded By</th>
                                        <th>Date</th>
                                        <th class="text-center" style="width: 150px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($documents as $doc)
                                        <tr>
                                            <td>
                                                <i class="la la-file-{{ in_array(pathinfo($doc->file_name, PATHINFO_EXTENSION), ['pdf']) ? 'pdf' : (in_array(pathinfo($doc->file_name, PATHINFO_EXTENSION), ['jpg','jpeg','png']) ? 'image' : 'alt') }} text-primary mr-2"></i>
                                                {{ $doc->file_name }}
                                            </td>
                                            <td>{{ $doc->uploader?->name ?? '—' }}</td>
                                            <td>{{ $doc->created_at->format('d M Y') }}</td>
                                            <td class="text-center">
                                                <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon btn-preview-doc"
                                                   data-preview="{{ route('admin.hr.documents.preview', $doc) }}"
                                                   data-download="{{ route('admin.hr.documents.download', $doc) }}"
                                                   data-name="{{ $doc->file_name }}" title="Preview">
                                                    <i class="la la-eye"></i>
                                                </a>
                                                <a href="{{ route('admin.hr.documents.download', $doc) }}" class="btn btn-sm btn-clean btn-icon" title="Download">
                                                    <i class="la la-download"></i>
                                                </a>
                                                @can('hr_documents_manage')
                                                <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon delete-document"
                                                   data-url="{{ route('admin.hr.documents.destroy', $doc) }}" title="Delete">
                                                    <i class="la la-trash text-danger"></i>
                                                </a>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No documents uploaded.</td>
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

    {{-- Document Preview Modal --}}
    <div class="modal fade" id="modal_doc_preview" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-3" style="background:#F3F6F9;border-bottom:2px solid #E4E6EF;">
                    <h5 class="modal-title font-weight-bolder"><i class="la la-file text-primary mr-2"></i><span id="preview-doc-name">Document Preview</span></h5>
                    <div>
                        <a id="preview-download" href="#" class="btn btn-sm btn-light-primary mr-2"><i class="la la-download mr-1"></i>Download</a>
                        <button type="button" class="close ml-2" data-dismiss="modal" aria-label="Close"><i class="la la-times"></i></button>
                    </div>
                </div>
                <div class="modal-body p-0">
                    <iframe id="preview-iframe" src="" style="width:100%;height:75vh;border:none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    @include('admin.hr.employees.partials.edit-modal', [
        'employee' => $employee,
        'departments' => $departments,
        'designations' => $designations,
        'managers' => $managers,
        'locations' => $locations,
    ])

    @include('admin.hr.employees.partials.upload-modal', ['employee' => $employee])

    @push('js')
    <script>
        $(function () {
            $('.select2').select2({ dropdownParent: $('#modal_edit_employee') });

            // Update Employee Details
            $('#form_edit_employee').on('submit', function (e) {
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
                            Object.values(errors).forEach(function (msgs) { toastr.error(msgs[0]); });
                        } else {
                            toastr.error('Something went wrong, please try again.');
                        }
                    },
                    complete: function () {
                        btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right');
                    }
                });
            });

            // Upload Document
            $('#form_upload_document').on('submit', function (e) {
                e.preventDefault();
                let form = $(this);
                let btn = form.find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');

                let formData = new FormData(this);

                $.ajax({
                    url: form.attr('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
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
                            Object.values(errors).forEach(function (msgs) { toastr.error(msgs[0]); });
                        } else {
                            toastr.error('Something went wrong, please try again.');
                        }
                    },
                    complete: function () {
                        btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right');
                    }
                });
            });

            // Preview Document in Modal
            $(document).on('click', '.btn-preview-doc', function () {
                var previewUrl = $(this).data('preview');
                var downloadUrl = $(this).data('download');
                var fileName = $(this).data('name');

                $('#preview-doc-name').text(fileName);
                $('#preview-download').attr('href', downloadUrl);
                $('#preview-iframe').attr('src', previewUrl);
                $('#modal_doc_preview').modal('show');
            });

            $('#modal_doc_preview').on('hidden.bs.modal', function () {
                $('#preview-iframe').attr('src', '');
            });

            // Delete Document
            $(document).on('click', '.delete-document', function () {
                let url = $(this).data('url');
                if (!confirm('Are you sure you want to delete this document?')) return;

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
