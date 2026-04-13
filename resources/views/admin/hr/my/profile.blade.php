@extends('admin.layouts.master')
@section('title', 'My Profile')
@section('content')

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'My HR', 'title' => 'My Profile'])

        <div class="d-flex flex-column-fluid">
            <div class="container">

                @php $detail = $user->employeeDetail; @endphp

                {{-- Personal Info Card --}}
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-user text-primary mr-2"></i>Personal Information</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4 d-md-none">
                            <div class="symbol symbol-80 symbol-circle">
                                @if($user->image_src)
                                    <img src="{{ asset($user->image_src) }}" alt="{{ $user->name }}">
                                @else
                                    <img src="{{ asset('assets/media/logos/avatar.jpg') }}" alt="{{ $user->name }}">
                                @endif
                            </div>
                            <div class="font-weight-bolder font-size-h5 mt-3">{{ $user->name }}</div>
                        </div>
                        <div class="row">
                            <div class="col-md-2 text-center mb-4 d-none d-md-block">
                                <div class="symbol symbol-100 symbol-circle">
                                    @if($user->image_src)
                                        <img src="{{ asset($user->image_src) }}" alt="{{ $user->name }}">
                                    @else
                                        <img src="{{ asset('assets/media/logos/avatar.jpg') }}" alt="{{ $user->name }}">
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-10">
                                <div class="row">
                                    <div class="col-6 col-md-4 mb-3 d-none d-md-block">
                                        <div class="text-muted font-size-sm">Name</div>
                                        <div class="font-weight-bold">{{ $user->name }}</div>
                                    </div>
                                    <div class="col-6 col-md-4 mb-3">
                                        <div class="text-muted font-size-sm">Email</div>
                                        <div class="font-weight-bold">{{ $user->email ?? '---' }}</div>
                                    </div>
                                    <div class="col-6 col-md-4 mb-3">
                                        <div class="text-muted font-size-sm">Phone</div>
                                        <div class="font-weight-bold">{{ $user->phone ?? '---' }}</div>
                                    </div>
                                    <div class="col-6 col-md-4 mb-3">
                                        <div class="text-muted font-size-sm">Gender</div>
                                        <div class="font-weight-bold">{{ $user->gender == 1 ? 'Male' : ($user->gender == 2 ? 'Female' : '---') }}</div>
                                    </div>
                                    <div class="col-6 col-md-4 mb-3">
                                        <div class="text-muted font-size-sm">CNIC</div>
                                        <div class="font-weight-bold">{{ $user->cnic ?? '---' }}</div>
                                    </div>
                                    <div class="col-6 col-md-4 mb-3">
                                        <div class="text-muted font-size-sm">Date of Birth</div>
                                        <div class="font-weight-bold">{{ $user->dob ? \Carbon\Carbon::parse($user->dob)->format('d M Y') : '---' }}</div>
                                    </div>
                                    <div class="col-12 col-md-4 mb-3">
                                        <div class="text-muted font-size-sm">Address</div>
                                        <div class="font-weight-bold">{{ $user->address ?? '---' }}</div>
                                    </div>
                                </div>
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
                        <div class="row">
                            <div class="col-6 col-md-4 mb-3">
                                <div class="text-muted font-size-sm">Department</div>
                                <div class="font-weight-bold">{{ $detail?->department?->name ?? '---' }}</div>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <div class="text-muted font-size-sm">Designation</div>
                                <div class="font-weight-bold">{{ $detail?->designation?->name ?? '---' }}</div>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <div class="text-muted font-size-sm">Hire Date</div>
                                <div class="font-weight-bold">{{ $detail?->hire_date ? $detail->hire_date->format('d M Y') : '---' }}</div>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <div class="text-muted font-size-sm">Employment Type</div>
                                <div class="font-weight-bold">{{ $detail?->employment_type?->label() ?? '---' }}</div>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <div class="text-muted font-size-sm">Reporting Manager</div>
                                <div class="font-weight-bold">{{ $detail?->reportingManager?->name ?? '---' }}</div>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <div class="text-muted font-size-sm">Salary</div>
                                <div class="font-weight-bold">{{ $detail?->salary ? number_format((float)$detail->salary, 2) : '---' }}</div>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <div class="text-muted font-size-sm">Bank Name</div>
                                <div class="font-weight-bold">{{ $detail?->bank_name ?? '---' }}</div>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <div class="text-muted font-size-sm">Bank Account</div>
                                <div class="font-weight-bold">{{ $detail?->bank_account_number ? '****' . substr($detail->bank_account_number, -4) : '---' }}</div>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <div class="text-muted font-size-sm">Tax ID</div>
                                <div class="font-weight-bold">{{ $detail?->tax_id ?? '---' }}</div>
                            </div>
                            <div class="col-12 col-md-4 mb-3">
                                <div class="text-muted font-size-sm">Centres</div>
                                <div class="font-weight-bold">
                                    @forelse($user->user_has_locations as $uhl)
                                        <span class="label label-light-primary label-inline mr-1 mb-1">{{ $uhl->location?->name ?? '---' }}</span>
                                    @empty
                                        ---
                                    @endforelse
                                </div>
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
                            <div class="col-6 col-md-4 mb-3">
                                <div class="text-muted font-size-sm">Name</div>
                                <div class="font-weight-bold">{{ $detail?->emergency_contact_name ?? '---' }}</div>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <div class="text-muted font-size-sm">Phone</div>
                                <div class="font-weight-bold">{{ $detail?->emergency_contact_phone ?? '---' }}</div>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <div class="text-muted font-size-sm">Relationship</div>
                                <div class="font-weight-bold">{{ $detail?->emergency_contact_relation ?? '---' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Documents Card --}}
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-file text-primary mr-2"></i>My Documents</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        {{-- Desktop table --}}
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-head-custom table-vertical-center table-head-bg">
                                <thead>
                                    <tr>
                                        <th>File Name</th>
                                        <th>Uploaded By</th>
                                        <th>Date</th>
                                        <th class="text-center" style="width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($documents as $doc)
                                        <tr>
                                            <td>
                                                @php $ext = strtolower(pathinfo($doc->file_name, PATHINFO_EXTENSION)); @endphp
                                                <i class="la la-file-{{ $ext === 'pdf' ? 'pdf' : (in_array($ext, ['jpg','jpeg','png']) ? 'image' : 'alt') }} text-primary mr-2"></i>
                                                {{ $doc->file_name }}
                                            </td>
                                            <td>{{ $doc->uploader?->name ?? '---' }}</td>
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
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No documents uploaded yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Mobile list --}}
                        <div class="d-md-none">
                            @forelse($documents as $doc)
                                @php $ext = strtolower(pathinfo($doc->file_name, PATHINFO_EXTENSION)); @endphp
                                <div class="d-flex align-items-center justify-content-between py-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                                    <div class="d-flex align-items-center mr-3" style="min-width:0;">
                                        <i class="la la-file-{{ $ext === 'pdf' ? 'pdf' : (in_array($ext, ['jpg','jpeg','png']) ? 'image' : 'alt') }} text-primary mr-3" style="font-size:1.5rem;"></i>
                                        <div style="min-width:0;">
                                            <div class="font-weight-bold text-truncate">{{ $doc->file_name }}</div>
                                            <div class="text-muted font-size-xs">{{ $doc->created_at->format('d M Y') }}</div>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-nowrap">
                                        <a href="javascript:void(0);" class="btn btn-sm btn-icon btn-light-primary mr-2 btn-preview-doc"
                                           data-preview="{{ route('admin.hr.documents.preview', $doc) }}"
                                           data-download="{{ route('admin.hr.documents.download', $doc) }}"
                                           data-name="{{ $doc->file_name }}">
                                            <i class="la la-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.hr.documents.download', $doc) }}" class="btn btn-sm btn-icon btn-light-success">
                                            <i class="la la-download"></i>
                                        </a>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted py-4">No documents uploaded yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Change Password Card --}}
                <div class="card card-custom mb-10">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-lock text-primary mr-2"></i>Change Password</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="form_change_password" method="post" action="{{ route('admin.update_password') }}">
                            @csrf
                            <div class="form-group">
                                <label class="font-weight-bold font-size-sm mb-1">Current Password <span class="text-danger">*</span></label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold font-size-sm mb-1">New Password <span class="text-danger">*</span></label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold font-size-sm mb-1">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" name="new_password_confirmation" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block btn-sm">Update Password</button>
                        </form>
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
                    <h5 class="modal-title font-weight-bolder text-truncate"><i class="la la-file text-primary mr-2"></i><span id="preview-doc-name">CV Preview</span></h5>
                    <div class="d-flex flex-nowrap ml-2">
                        <a id="preview-download" href="#" class="btn btn-sm btn-light-primary mr-2"><i class="la la-download"></i><span class="d-none d-sm-inline ml-1">Download</span></a>
                        <button type="button" class="close ml-0" data-dismiss="modal" aria-label="Close"><i class="la la-times"></i></button>
                    </div>
                </div>
                <div class="modal-body p-0">
                    <iframe id="preview-iframe" src="" style="width:100%;height:75vh;border:none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    @push('js')
    <script>
        $(function () {
            $(document).on('click', '.btn-preview-doc', function () {
                $('#preview-doc-name').text($(this).data('name'));
                $('#preview-download').attr('href', $(this).data('download'));
                $('#preview-iframe').attr('src', $(this).data('preview'));
                $('#modal_doc_preview').modal('show');
            });

            $('#modal_doc_preview').on('hidden.bs.modal', function () {
                $('#preview-iframe').attr('src', '');
            });

            $('#form_change_password').on('submit', function (e) {
                e.preventDefault();
                var form = $(this), btn = form.find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');

                $.ajax({
                    url: form.attr('action'),
                    method: 'POST',
                    data: form.serialize(),
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) {
                        if (res.status) {
                            toastr.success(res.message || 'Password updated successfully.');
                            form[0].reset();
                        } else {
                            toastr.error(res.message || 'Something went wrong.');
                        }
                    },
                    error: function (xhr) {
                        if (xhr.status === 422) {
                            var errors = xhr.responseJSON.errors || {};
                            Object.values(errors).forEach(function (m) { toastr.error(m[0]); });
                        } else {
                            toastr.error('Something went wrong.');
                        }
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
