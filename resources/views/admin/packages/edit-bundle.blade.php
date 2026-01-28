<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_edit_bundle_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Edit Bundle Plan</h2>
        <!--end::Modal title-->
        <!--begin::Close-->
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" 
     data-kt-users-modal-action="close" 
     onclick="resetVoucherEditBundle(event); return false;">
    <span class="svg-icon svg-icon-1">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
        </svg>
    </span>
</div>
        <!--end::Close-->
    </div>
    <!--end::Modal header-->
    <!--begin::Modal body-->
    <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
        <div id="edit_bundle_successMessage" class="alert alert-success display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Bundle plan successfully updated
        </div>
        <div id="edit_bundle_wrongMessage" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Something went wrong!
        </div>

        <form id="update_bundle_form">
            <input type="hidden" name="edit_bundle_random_id" id="edit_bundle_random_id" class="form-control">
            <input type="hidden" id="edit_bundle_parent_id" name="parent_id">
            <input type="hidden" id="edit_bundle_location_id" name="location_id">
            <input type="hidden" id="edit_bundle_package_id" name="package_id">
            
            <div class="d-flex flex-column scroll-y me-n7 pe-7">
                <div class="form-group">
                    <div class="row">
                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Patient Name</label>
                            <h3 id="edit-bundle-patient-name"></h3>
                        </div>
                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Membership</label>
                            <h4 id="edit-bundle-membership-name" style="font-size:15px"></h4>
                        </div>
                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Location</label>
                            <h3 id="edit-bundle-location-name"></h3>
                        </div>
                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Appointment</label>
                            <select id="edit_bundle_appointment_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="appointment_id" required>
                                <option value="">Select Appointment</option>
                            </select>
                            <small class="text-danger error-class"><b id='edit_bundle_appointment_id_error' class="error-msg"></b></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="separator separator-dashed my-5"></div>

            <div id="edit_bundle_services_section">
                <h3>Bundle Services</h3>
                <div class="table-responsive">
                    <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                        <thead>
                            <tr class="fw-bolder text-muted">
                                <th>Service Name</th>
                                <th>Regular Price</th>
                                <th>Amount</th>
                                <th>Tax</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="edit_bundle_services">
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="separator separator-dashed my-5"></div>

            <div class="form-group">
                <div class="row">
                    <div class="fv-row col-md-3 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Total</label>
                        <input type="text" id="edit_bundle_package_total" name="total" class="form-control form-control-solid mb-3 mb-lg-0" readonly>
                    </div>
                    <div class="fv-row col-md-3 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Payment Mode</label>
                        <select id="edit_bundle_payment_mode_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="payment_mode_id">
                            <option value="">Select Payment Mode</option>
                        </select>
                    </div>
                    <div class="fv-row col-md-3 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Cash Amount</label>
                        <input type="number" id="edit_bundle_cash_amount" name="cash_amount" class="form-control form-control-solid mb-3 mb-lg-0" disabled>
                    </div>
                    <div class="fv-row col-md-3 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Grand Total</label>
                        <input type="text" id="edit_bundle_grand_total" name="grand_total" class="form-control form-control-solid mb-3 mb-lg-0" readonly>
                    </div>
                </div>
            </div>

            <hr>
            <div class="text-center">
                <button type="button" class="btn btn-light me-3" onclick="resetVoucherEditBundle(event)">Cancel</button>
                <button id="EditBundleFinal" type="button" class="btn btn-primary">
                    <span class="indicator-label">Update</span>
                </button>
            </div>
        </form>
    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->
