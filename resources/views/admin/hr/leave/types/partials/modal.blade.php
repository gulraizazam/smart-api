{{-- Add Leave Type Modal --}}
<div class="modal fade" id="modal_add_leave_type" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered form-popup">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Add Leave Type</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-dismiss="modal">
                    <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="form_add_leave_type" method="post" action="{{ route('admin.hr.leave-types.store') }}">
                    @csrf
                    <div class="form-group">
                        <div class="fv-row mb-5">
                            <label class="required fw-bold fs-6 mb-2">Name</label>
                            <input type="text" name="name" class="form-control form-control-lg form-control-solid" placeholder="e.g. Annual Leave" required>
                        </div>
                        <div class="fv-row">
                            <label class="form-check form-check-custom form-check-solid">
                                <input type="hidden" name="is_paid" value="0">
                                <input class="form-check-input" type="checkbox" name="is_paid" value="1" checked>
                                <span class="form-check-label fw-bold fs-6">Paid Leave</span>
                            </label>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <button type="reset" class="btn btn-light me-3" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Edit Leave Type Modal --}}
<div class="modal fade" id="modal_edit_leave_type" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered form-popup">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Edit Leave Type</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-dismiss="modal">
                    <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="form_edit_leave_type" method="post" action="">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <div class="fv-row mb-5">
                            <label class="required fw-bold fs-6 mb-2">Name</label>
                            <input type="text" id="edit_leave_type_name" name="name" class="form-control form-control-lg form-control-solid" required>
                        </div>
                        <div class="fv-row mb-3">
                            <label class="form-check form-check-custom form-check-solid">
                                <input type="hidden" name="is_paid" value="0">
                                <input class="form-check-input" type="checkbox" name="is_paid" value="1" id="edit_leave_type_is_paid">
                                <span class="form-check-label fw-bold fs-6">Paid Leave</span>
                            </label>
                        </div>
                        <div class="fv-row">
                            <label class="form-check form-check-custom form-check-solid">
                                <input type="hidden" name="active" value="0">
                                <input class="form-check-input" type="checkbox" name="active" value="1" id="edit_leave_type_active">
                                <span class="form-check-label fw-bold fs-6">Active</span>
                            </label>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <button type="reset" class="btn btn-light me-3" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
