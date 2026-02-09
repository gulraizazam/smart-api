<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_edit_membership_header">
        <h2 class="fw-bolder">Edit Membership</h2>
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" 
             data-kt-users-modal-action="close" 
             onclick="closeEditMembershipModal();">
            <span class="svg-icon svg-icon-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                    <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                </svg>
            </span>
        </div>
    </div>
    <!--end::Modal header-->
    
    <!--begin::Modal body-->
    <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
        <div id="edit_membership_successMessage" class="alert alert-success display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Record updated successfully
        </div>
        <div id="edit_membership_wrongMessage" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Something went wrong!
        </div>

        <form id="edit_membership_form">
            <input type="hidden" name="package_id" id="edit_package_id_membership">
            <input type="hidden" name="random_id" id="edit_random_id_membership">
            <input type="hidden" name="patient_id" id="edit_patient_id_membership">
            <input type="hidden" name="location_id" id="edit_location_id_membership">
            
            <div class="d-flex flex-column scroll-y me-n7 pe-7">
                <!-- Patient and Location Info (Read-only) -->
                <div class="form-group">
                    <div class="row">
                        <div class="fv-row col-md-2 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Patient Name</label>
                            <h3 id="edit_patient_name_membership"></h3>
                        </div>
                        <div class="fv-row col-md-2 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Membership</label>
                            <h4 id="edit_patient_membership_membership" style="font-size:15px">-</h4>
                        </div>
                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Location</label>
                            <h3 id="edit_location_name_membership"></h3>
                        </div>
                        <div class="fv-row col-md-4 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Appointment <span class="text text-danger">*</span></label>
                            <select id="edit_membership_appointment_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="appointment_id">
                                <option value="">Select Appointment</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Services Section (Read-only for membership) -->
                <div class="form-group">
                    <div class="row">
                        <div class="fv-row col-md-4 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Services <span class="text text-danger">*</span></label>
                            <select id="edit_membership_service_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="service_id" disabled>
                                <option value="">Select Service</option>
                            </select>
                        </div>
                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Price</label>
                            <input type="text" readonly name="net_amount" class="form-control" id="edit_membership_net_amount">
                        </div>
                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Sold By <span class="text text-danger">*</span></label>
                            <select id="edit_membership_sold_by" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="sold_by" disabled>
                                <option value="">Select</option>
                            </select>
                        </div>
                        <div class="fv-row col-md-2 mt-5">
                            <div class="text-center mt-10">
                                <button type="button" class="btn btn-primary float-right" disabled>
                                    <span class="indicator-label">Add</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <!-- Membership Items Table -->
                <div class="table-responsive add_center_target_table">
                    <table class="table table-striped table-bordered table-advance table-hover">
                        <thead>
                            <tr class="fw-bolder text-muted">
                                <th>Service Name</th>
                                <th>Regular Price</th>
                                <th>Amount</th>
                                <th>Tax</th>
                                <th>Total</th>
                                <th>Sold By</th>
                            </tr>
                        </thead>
                        <tbody id="edit_membership_services"></tbody>
                    </table>
                </div>

                <!-- Payment Section -->
                <div class="form-group">
                    <div class="row">
                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Total</label>
                            <input type="text" readonly id="edit_package_total_membership" class="form-control" value="0" name="package_total">
                        </div>
                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Payment Mode</label>
                            <select id="edit_membership_payment_mode_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="payment_mode_id">
                                <option value="">Select Payment Mode</option>
                            </select>
                        </div>
                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Cash Amount</label>
                            <input type="number" min="0" id="edit_membership_cash_amount" class="form-control" value="0" name="cash_amount" disabled>
                        </div>
                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Cash Received Remain</label>
                            <input type="text" readonly min="0" name="grand_total" value="0" class="form-control" id="edit_membership_grand_total">
                        </div>
                    </div>
                </div>
                <hr>
            </div>
            
            <!--begin::Actions-->
            <hr>
            <div class="text-center">
                <button type="button" class="btn btn-light me-3" onclick="closeEditMembershipModal();">Cancel</button>
                <button id="EditMembershipFinal" type="button" class="btn btn-primary spinner-button-edit-save">
                    <span class="indicator-label">save</span>
                </button>
            </div>
            <!--end::Actions-->
        </form>

        <!-- History Section -->
        <div class="row mt-5">
            <div class="table-responsive">
                <h4>History</h4>
                <table class="table table-bordered table-advance">
                    <thead>
                        <tr>
                            <th>Payment Mode</th>
                            <th>Cash Flow</th>
                            <th>Cash Amount</th>
                            <th>Created At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="edit_membership_payment_history"></tbody>
                </table>
            </div>
        </div>

    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->
