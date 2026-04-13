@extends('admin.layouts.master')
@section('title', 'Convert Candidate — ' . $candidate->name)
@section('content')

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

        @include('admin.partials.breadcrumb', ['module' => 'HRM', 'title' => 'Convert to Employee'])

        <div class="d-flex flex-column-fluid">
            <div class="container">

                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon"><i class="la la-user-plus icon-lg text-primary"></i></span>
                            <h3 class="card-label">Convert <strong>{{ $candidate->name }}</strong> to Employee</h3>
                        </div>
                        <div class="card-toolbar">
                            <a href="{{ route('admin.hr.recruitment.show', $candidate) }}" class="btn btn-light btn-sm">
                                <i class="la la-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="form_convert_candidate">
                            <div class="row">
                                {{-- Personal Info --}}
                                <div class="col-md-12 mb-3">
                                    <h5 class="font-weight-bold text-primary">Personal Information</h5>
                                    <hr class="mt-1">
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="required fw-bold fs-6 mb-2">Full Name</label>
                                    <input type="text" name="name" class="form-control form-control-solid" value="{{ $candidate->name }}" required>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="fw-bold fs-6 mb-2">Email</label>
                                    <input type="email" name="email" class="form-control form-control-solid" value="{{ $candidate->email }}">
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="fw-bold fs-6 mb-2">Phone</label>
                                    <input type="text" name="phone" class="form-control form-control-solid" value="{{ $candidate->phone }}">
                                </div>

                                {{-- Employment Details --}}
                                <div class="col-md-12 mb-3 mt-3">
                                    <h5 class="font-weight-bold text-primary">Employment Details</h5>
                                    <hr class="mt-1">
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="required fw-bold fs-6 mb-2">User Type</label>
                                    <select name="user_type_id" class="form-control form-control-solid" required>
                                        <option value="2">Application User</option>
                                        <option value="5">Practitioner</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="fw-bold fs-6 mb-2">Designation</label>
                                    <input type="text" class="form-control form-control-solid bg-light" value="{{ $candidate->designation?->name ?? '---' }}" readonly>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="fw-bold fs-6 mb-2">Department</label>
                                    <select name="department_id" class="form-control form-control-solid select2" style="width:100%;">
                                        <option value="">--- Select ---</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="fw-bold fs-6 mb-2">Hire Date</label>
                                    <input type="date" name="hire_date" class="form-control form-control-solid" value="{{ date('Y-m-d') }}">
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="fw-bold fs-6 mb-2">Employment Type</label>
                                    <select name="employment_type" class="form-control form-control-solid">
                                        <option value="">--- Select ---</option>
                                        @foreach(\App\Enums\EmploymentType::cases() as $type)
                                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="fw-bold fs-6 mb-2">Salary</label>
                                    <input type="number" name="salary" class="form-control form-control-solid" min="0" step="0.01">
                                </div>

                                {{-- Access & Security --}}
                                <div class="col-md-12 mb-3 mt-3">
                                    <h5 class="font-weight-bold text-primary">Access & Security</h5>
                                    <hr class="mt-1">
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="required fw-bold fs-6 mb-2">Password</label>
                                    <input type="password" name="password" class="form-control form-control-solid" minlength="8" required>
                                    <small class="text-muted">Minimum 8 characters.</small>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="fw-bold fs-6 mb-2">Role</label>
                                    <select name="role_id" class="form-control form-control-solid select2" style="width:100%;">
                                        <option value="">--- Select ---</option>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Location Info (readonly) --}}
                                <div class="col-md-4 mb-4">
                                    <label class="fw-bold fs-6 mb-2">Branch / Centre</label>
                                    <input type="text" class="form-control form-control-solid bg-light" value="{{ $candidate->location?->name ?? '---' }}" readonly>
                                </div>
                            </div>

                            <hr>
                            <div class="text-center">
                                <a href="{{ route('admin.hr.recruitment.show', $candidate) }}" class="btn btn-light me-3">Cancel</a>
                                <button type="submit" class="btn btn-success">
                                    <i class="la la-check"></i> Convert to Employee
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    @push('js')
    <script>
        $(function () {
            $('.select2').select2();

            $('#form_convert_candidate').on('submit', function (e) {
                e.preventDefault();
                if (!confirm('Are you sure you want to convert this candidate to an employee? This will create a new user account.')) return;

                let form = $(this);
                let btn = form.find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');

                $.ajax({
                    url: '{{ route("admin.hr.recruitment.convert.store", $candidate) }}',
                    method: 'POST',
                    data: form.serialize(),
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) {
                        if (res.status) {
                            toastr.success(res.message);
                            window.location.href = '{{ route("admin.hr.recruitment.show", $candidate) }}';
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
        });
    </script>
    @endpush

@endsection
