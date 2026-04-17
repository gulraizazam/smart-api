@php $detail = $employee->employeeDetail; @endphp
<div class="modal fade" id="modal_edit_employee" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Edit HR Details — {{ $employee->name }}</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <form id="form_edit_employee" method="post" action="{{ route('admin.hr.employees.update', $employee) }}">
                    @csrf
                    @method('PUT')

                    {{-- Personal Info Section --}}
                    <h5 class="font-weight-bold text-dark mb-4"><i class="la la-user mr-2"></i>Personal Information</h5>
                    <div class="row mb-5">
                        <div class="col-md-6 mb-4">
                            <label class="required fw-bold fs-6 mb-2">Full Name</label>
                            <input type="text" name="name" class="form-control form-control-solid" value="{{ $employee->name }}" required>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="fw-bold fs-6 mb-2">Email</label>
                            <input type="email" name="email" class="form-control form-control-solid" value="{{ $employee->email }}">
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="fw-bold fs-6 mb-2">Phone</label>
                            <input type="text" name="phone" class="form-control form-control-solid" value="{{ $employee->phone }}">
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="fw-bold fs-6 mb-2">CNIC</label>
                            <input type="text" name="cnic" class="form-control form-control-solid" value="{{ $employee->cnic }}">
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="fw-bold fs-6 mb-2">Date of Birth</label>
                            <input type="date" name="dob" class="form-control form-control-solid" value="{{ $employee->dob }}">
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="fw-bold fs-6 mb-2">Gender</label>
                            <select name="gender" class="form-control form-control-solid">
                                <option value="">— Select —</option>
                                <option value="1" {{ $employee->gender == 1 ? 'selected' : '' }}>Male</option>
                                <option value="2" {{ $employee->gender == 2 ? 'selected' : '' }}>Female</option>
                            </select>
                        </div>
                        <div class="col-md-8 mb-4">
                            <label class="fw-bold fs-6 mb-2">Address</label>
                            <input type="text" name="address" class="form-control form-control-solid" value="{{ $employee->address }}">
                        </div>
                    </div>

                    <hr>

                    {{-- Employment Section --}}
                    <h5 class="font-weight-bold text-dark mb-4"><i class="la la-briefcase mr-2"></i>Employment</h5>
                    <div class="row mb-5">
                        <div class="col-md-6 mb-4">
                            <label class="fw-bold fs-6 mb-2">Department</label>
                            <select name="department_id" class="form-control form-control-solid select2" style="width: 100%;">
                                <option value="">— Select —</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ $detail?->department_id == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="fw-bold fs-6 mb-2">Designation</label>
                            <select name="designation_id" class="form-control form-control-solid select2" style="width: 100%;">
                                <option value="">— Select —</option>
                                @foreach($designations as $desig)
                                    <option value="{{ $desig->id }}" {{ $detail?->designation_id == $desig->id ? 'selected' : '' }}>{{ $desig->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="fw-bold fs-6 mb-2">Hire Date</label>
                            <input type="date" name="hire_date" class="form-control form-control-solid" value="{{ $detail?->hire_date?->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="fw-bold fs-6 mb-2">Employment Type</label>
                            <select name="employment_type" class="form-control form-control-solid">
                                <option value="">— Select —</option>
                                @foreach(\App\Enums\EmploymentType::cases() as $type)
                                    <option value="{{ $type->value }}" {{ $detail?->employment_type === $type ? 'selected' : '' }}>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="fw-bold fs-6 mb-2">Reporting Manager</label>
                            <select name="reporting_manager_id" class="form-control form-control-solid select2" style="width: 100%;">
                                <option value="">— Select —</option>
                                @foreach($managers as $mgr)
                                    <option value="{{ $mgr->id }}" {{ $detail?->reporting_manager_id == $mgr->id ? 'selected' : '' }}>{{ $mgr->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="fw-bold fs-6 mb-2">Shift Hours</label>
                            <input type="number" step="0.25" min="0" max="24" name="shift_hours" class="form-control form-control-solid" value="{{ $detail?->shift_hours }}" placeholder="e.g. 8">
                            <span class="form-text text-muted">Length of working day. Enables hour-based leave deductions.</span>
                        </div>
                        <div class="col-md-4 mb-4">
                            @php $assignedLocationIds = $employee->user_has_locations->pluck('location_id')->toArray(); @endphp
                            <label class="fw-bold fs-6 mb-2">Centres</label>
                            <select name="location_ids[]" class="form-control form-control-solid select2" multiple style="width: 100%;">
                                @foreach($locations as $loc)
                                    <option value="{{ $loc->id }}" {{ in_array($loc->id, $assignedLocationIds) ? 'selected' : '' }}>{{ $loc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <hr>

                    {{-- Financial Section --}}
                    <h5 class="font-weight-bold text-dark mb-4"><i class="la la-money-bill mr-2"></i>Financial</h5>
                    <div class="row mb-5">
                        <div class="col-md-4 mb-4">
                            <label class="fw-bold fs-6 mb-2">Salary</label>
                            <input type="number" step="0.01" name="salary" class="form-control form-control-solid" value="{{ $detail?->salary }}">
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="fw-bold fs-6 mb-2">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control form-control-solid" value="{{ $detail?->bank_name }}">
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="fw-bold fs-6 mb-2">Bank Account Number</label>
                            <input type="text" name="bank_account_number" class="form-control form-control-solid" value="{{ $detail?->bank_account_number }}">
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="fw-bold fs-6 mb-2">Tax ID</label>
                            <input type="text" name="tax_id" class="form-control form-control-solid" value="{{ $detail?->tax_id }}">
                        </div>
                    </div>

                    <hr>

                    {{-- Emergency Contact Section --}}
                    <h5 class="font-weight-bold text-dark mb-4"><i class="la la-phone mr-2"></i>Emergency Contact</h5>
                    <div class="row mb-5">
                        <div class="col-md-4 mb-4">
                            <label class="fw-bold fs-6 mb-2">Contact Name</label>
                            <input type="text" name="emergency_contact_name" class="form-control form-control-solid" value="{{ $detail?->emergency_contact_name }}">
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="fw-bold fs-6 mb-2">Contact Phone</label>
                            <input type="text" name="emergency_contact_phone" class="form-control form-control-solid" value="{{ $detail?->emergency_contact_phone }}">
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="fw-bold fs-6 mb-2">Relationship</label>
                            <input type="text" name="emergency_contact_relation" class="form-control form-control-solid" value="{{ $detail?->emergency_contact_relation }}">
                        </div>
                    </div>

                    <hr>
                    <div class="text-center">
                        <button type="reset" class="btn btn-light me-3" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Save Changes</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
