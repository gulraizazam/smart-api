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
            <input type="hidden" id="edit_membership_has_edit_permission" value="{{ Gate::allows('plans.edit') ? '1' : '0' }}">
            <input type="hidden" id="edit_membership_has_edit_sold_by_permission" value="{{ Gate::allows('plans.sold_by.edit') ? '1' : '0' }}">
            
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
                                @if(Gate::allows('plans.edit'))
                                <th>Actions</th>
                                @endif
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
                            <select id="edit_membership_payment_mode_id" class="form-control form-control-solid mb-3 mb-lg-0" name="payment_mode_id">
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

                <!-- Student Document Upload Section (only shown for student memberships) -->
                <div id="edit_student_document_section" class="card card-custom mt-3" style="display: none;">
                    <input type="hidden" id="edit_membership_type_id" value="">
                    <input type="hidden" id="edit_is_student_membership" value="0">
                    
                    <div class="card-header py-2">
                        <h3 class="card-title fs-6">
                            <i class="la la-file-image text-primary"></i>
                            Student Verification Documents
                        </h3>
                    </div>
                    <div class="card-body py-3">
                        <div class="alert alert-light-info d-flex align-items-center p-3 mb-3">
                            <i class="la la-info-circle fs-3 me-2"></i>
                            <small class="mb-0">Upload student card and ID card images (Max 4 documents, JPG/PNG only)</small>
                        </div>

                        <!-- Existing Documents -->
                        <div id="edit_existing_documents" class="mb-3" style="display: none;">
                            <label class="fw-bold fs-6 mb-2 text-success">Previously Uploaded Documents</label>
                            <div id="edit_existing_documents_list" class="d-flex flex-wrap gap-2"></div>
                            <hr>
                        </div>

                        <div id="edit_document_upload_container">
                            <!-- Document Upload Item 1 (Default) -->
                            <div class="edit-document-upload-item mb-2" data-index="0">
                                <div class="d-flex align-items-start gap-2">
                                    <div class="flex-grow-1">
                                        <input type="file" 
                                               name="edit_student_documents[]" 
                                               class="form-control form-control-sm edit-student-document-input" 
                                               accept="image/jpeg,image/png,image/jpg"
                                               data-index="0"
                                               onchange="previewEditDocument(this, 0)">
                                        <div class="mt-1 edit-document-preview" id="edit_document_preview_0" style="display: none;">
                                            <img src="" class="img-thumbnail" style="max-height: 60px;">
                                        </div>
                                    </div>
                                    <button type="button" id="edit_add_document_btn" class="btn btn-sm btn-icon btn-primary" onclick="addEditDocumentField()" title="Add Document" style="margin-left: 7px; margin-right: 5px;">
                                        <i class="la la-plus"></i>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-sm btn-icon btn-light-danger edit-remove-document-btn" 
                                            data-index="0"
                                            onclick="removeEditDocumentField(0)"
                                            style="display: none;"
                                            title="Remove">
                                        <i class="la la-trash"></i>
                                    </button>
                                    <small class="text-muted" id="edit_document_count" style="margin-left: 3px;">1 of 4</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
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
