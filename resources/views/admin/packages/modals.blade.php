{{-- Plans/Packages Shared Modals --}}

{{-- SMS Logs Modal --}}
<div class="modal fade" id="modal_sms_logs" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mediam-modal" id="invoices_add">
        @include('admin.packages.sms_logs')
    </div>
</div>

{{-- Display Modal --}}
<div class="modal fade" id="modal_display" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered very-big-modal" id="invoices_display">
        @include('admin.packages.display')
    </div>
</div>

{{-- Add Plan Modal --}}
<div class="modal fade" id="modal_add_plan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered very-big-modal" id="packages_add">
        @include('admin.packages.create', ['isPatientCard' => $isPatientCard ?? false])
    </div>
</div>

{{-- Add Bundle Modal --}}
<div class="modal fade" id="modal_add_bundle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered very-big-modal" id="bundles_add">
        @include('admin.packages.create-bundle', ['isPatientCard' => $isPatientCard ?? false])
    </div>
</div>

{{-- Add Membership Modal --}}
<div class="modal fade" id="modal_add_membership" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered very-big-modal" id="memberships_add">
        @include('admin.packages.create-membership', ['isPatientCard' => $isPatientCard ?? false])
    </div>
</div>

{{-- Edit Membership Modal --}}
<div class="modal fade" id="modal_edit_membership" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered very-big-modal" id="memberships_edit">
        @include('admin.packages.edit-membership')
    </div>
</div>

{{-- Create Bundle Modal --}}
<div class="modal fade" id="modal_create_bundle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered very-big-modal" id="bundles_add">
        @include('admin.packages.create-bundle', ['isPatientCard' => $isPatientCard ?? false])
    </div>
</div>

{{-- Edit Bundle Modal --}}
<div class="modal fade" id="modal_edit_bundle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered very-big-modal" id="bundles_edit">
        @include('admin.packages.edit-bundle')
    </div>
</div>

{{-- Edit Plan Modal --}}
<div class="modal fade" id="modal_edit_plan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered very-big-modal" id="packages_edit">
        @include('admin.packages.edit')
    </div>
</div>

{{-- Plan Edit Cash Modal --}}
<div class="modal fade" id="plan_edit_cash" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered very-big-modal" id="plan_edit">
        @include('admin.packages.plane-edit')
    </div>
</div>

{{-- Edit Refunds Modal --}}
<div class="modal fade" id="modal_edit_refunds" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered form-popup" id="refunds_edit">
        @include('admin.refunds.refund')
    </div>
</div>

{{-- Edit Sold By Modal --}}
<div class="modal fade" id="modal_edit_sold_by" tabindex="-1" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Edit Sold By</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" onclick="closeSoldByModal()">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="edit_sold_by_form">
                    <input type="hidden" id="package_service_id" name="package_service_id">
                    <div class="fv-row mb-7">
                        <label class="required fw-bold fs-6 mb-2">Sold By</label>
                        <select id="sold_by_dropdown" name="sold_by" class="form-control form-control-solid" required>
                            <option value="">Select</option>
                        </select>
                        <small class="text-danger"><b id="sold_by_error" class="error-msg"></b></small>
                    </div>
                    <div class="text-center pt-15">
                        <button type="button" class="btn btn-light me-3" onclick="closeSoldByModal()">Cancel</button>
                        <button type="button" id="update_sold_by_btn" class="btn btn-primary">
                            <span class="indicator-label">Update</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
