<div class="modal-content">
    <div class="modal-header" id="kt_modal_password_header">
        <h2 class="fw-bolder">Assign Membership</h2>
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" 
     data-kt-users-modal-action="close" 
     onclick="resetVoucherAddMembership(event); return false;">
    <span class="svg-icon svg-icon-1">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
        </svg>
            </span>
        </div>
    </div>
    <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
        <div id="duplicateErrMembership" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Duplicate record found, please select another one.
        </div>
        <div id="successMessageMembership" class="alert alert-success display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Membership successfully created
        </div>
        <div id="inputfieldMessageMembership" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Kindly enter required fields or you enter wrong value.
        </div>
        <div id="wrongMessageMembership" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Something went wrong!
        </div>
        <div id="percentageMessageMembership" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Your discount limit exceeded.
        </div>
        <div id="AlreadyExitMessageMembership" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Unable to enter same service with different price.
        </div>
        <div id="datanotexistMembership" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            That center not have any service.
        </div>
        <div id="DiscountRangeMembership" class="alert alert-danger" style="display: none;">
            <button class="close" data-close="alert"></button>
            Your discount limit exceeded.
        </div>
        <input type="hidden" name="random_id_membership" id="random_id_membership" class="form-control">
        <input type="hidden" name="slug_membership" id="slug_membership" class="form-control">
        <input type="hidden" id="client_id_membership" class="form-control">
        <input type="hidden" name="patient_id_membership" id="parent_id_membership" class="form-control">
        <div class="d-flex flex-column scroll-y me-n7 pe-7" id="modal_appointment_membership_section" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">
            <div class="form-group">
                <div class="row">
                    @if(isset($isPatientCard) && $isPatientCard)
                        {{-- Patient Card Context: Show patient info as static text like edit modal --}}
                        <div class="fv-row col-md-2 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Patient Name</label>
                            <h3 id="add-patient-name-membership"></h3>
                            <input type="hidden" id="add_patient_id_membership" name="patient_id_membership">
                        </div>
                        <div class="fv-row col-md-2 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Membership</label>
                            <h4 id="patient_membership_membership" style="font-size:15px">No Membership</h4>
                        </div>
                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Location <span class="text text-danger">*</span></label>
                            <select onchange="getServicesMembership();" id="add_membership_location_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="location_id_membership">
                                <option value="">Select Centre</option>
                            </select>
                            <small class="text-danger ml-1 mt-1"><b id="add_membership_location_id_error" class="create-membership-error"></b></small>
                        </div>
                        <div class="fv-row col-md-4 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Appointment <span class="text text-danger">*</span></label>
                            <select id="add_appointment_id_membership" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="appointment_id_membership">
                                <option value="">Select Appointment</option>
                            </select>
                            <small class="text-danger ml-1 mt-1"><b id="add_appointment_id_membership_error" class="create-membership-error"></b></small>
                        </div>
                    @else
                        {{-- Main Plans Module: Show patient search dropdown --}}
                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Location <span class="text text-danger">*</span></label>
                            <select onchange="getServicesMembership();" id="add_membership_location_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="location_id_membership">
                                <option value="">Select Centre</option>
                            </select>
                            <small class="text-danger ml-1 mt-1"><b id="add_membership_location_id_error" class="create-membership-error"></b></small>
                        </div>
                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Patient Search <span class="text text-danger">*</span></label>
                            <select id="add_patient_id_membership" class="form-control form-control-solid mb-3 mb-lg-0 select2-patient-search" name="patient_id_membership">
                                <option value="">Search Patient by Name or Phone</option>
                            </select>
                            <small class="text-danger ml-1 mt-1"><b id="add_patient_id_membership_error" class="create-membership-error"></b></small>
                        </div>
                        <div class="fv-row col-md-2 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Membership </label>
                            <input type="text" id="patient_membership_membership" class="form-control form-control-solid mb-3 mb-lg-0" disabled placeholder="No data">
                        </div>
                        <div class="fv-row col-md-4 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Appointment <span class="text text-danger">*</span></label>
                            <select id="add_appointment_id_membership" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="appointment_id_membership">
                                <option value="">Select Appointment</option>
                            </select>
                            <small class="text-danger ml-1 mt-1"><b id="add_appointment_id_membership_error" class="create-membership-error"></b></small>
                        </div>
                    @endif
                </div>
            </div>
            <div class="form-group">
                <div class="row">
                    <div class="fv-row col-md-3 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Membership Type <span class="text text-danger">*</span></label>
                        <select id="add_service_id_membership" onchange="getServiceDiscountMembership($(this));" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="service_id_membership">
                            <option value="">Select Service</option>
                        </select>
                        <small class="text-danger ml-1 mt-1"><b id="add_service_id_membership_error" class="create-membership-error"></b></small>
                    </div>
                    <div class="fv-row col-md-2 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Code <span class="text text-danger">*</span></label>
                        <select id="add_membership_code" class="form-control form-control-solid mb-3 mb-lg-0" name="membership_code">
                            <option value="">Search Code</option>
                        </select>
                        <small class="text-danger ml-1 mt-1"><b id="add_membership_code_error" class="create-membership-error"></b></small>
                    </div>
                    <div class="fv-row col-md-2 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Price</label>
                        <div class="blockui input-spinner" style="display: none; background: transparent; box-shadow: none; position: absolute;margin-top: 28px;margin-left: 15%;">
                            <span>Please wait...</span>
                            <span>
                                <div class="spinner spinner-primary"></div>
                            </span>
                        </div>
                        <input type="text" readonly name="net_amount_membership" class="form-control" id="net_amount_membership">
                    </div>
                    <div class="fv-row col-md-3 mt-5" id="sold_by_div_membership">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Sold By <span class="text text-danger">*</span></label>
                        <select  id="add_sold_by_membership" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="sold_by_membership">

                        </select>
                        <small class="text-danger ml-1 mt-1"><b id="add_sold_by_membership_errorr" class="create-membership-error"></b></small>
                    </div>
                    <div class="fv-row col-md-2 mt-1">
                        <div class="text-center mt-10">
                            <button type="button" id="AddPackageMembership" class="btn btn-primary float-right spinner-button-add">
                                <span class="indicator-label">Add</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <hr>
            <div class="table-responsive add_center_target_table">
                <table id="appointment_detail_membership" class="table table-striped table-bordered table-advance table-hover">
                    <thead>
                        <tr>
                            <th>Service Name</th>
                            <th>Regular Price</th>
                            <th>Amount</th>
                            <th>Tax </th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="membership_services"></tbody>
                </table>
            </div>

            <div class="form-group">
                <div class="row">
                    <div class="fv-row col-md-3 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Total </label>
                        <input type="text" readonly oninput="phoneField(this)" id="package_total_membership" class="form-control" value="0" name="package_total_membership">
                    </div>
                    <div class="fv-row col-md-3 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Payment Mode <span class="text text-danger">*</span></label>
                        <select id="payment_mode_id_membership" class="form-control form-control-solid mb-3 mb-lg-0" name="payment_mode_id_membership">
                            <option value="">Select Payment Mode</option>
                        </select>
                    </div>
                    <div class="fv-row col-md-3 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Cash Amount</label>
                        <input type="number" id="cash_amount_membership" class="form-control" placeholder="Enter Amount" name="cash_amount_membership" disabled min="0">
                        <small class="text-danger ml-1 mt-1"><b id="cash_amount_membership_error" class="create-membership-error"></b></small>
                    </div>
                    <div class="fv-row col-md-3 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Cash Received Remain</label>
                        <input type="text" readonly min="0" name="total_price_membership" value="0" class="form-control" id="grand_total_membership">
                    </div>
                </div>
            </div>

            <!-- Student Membership Document Upload Section (moved below payment fields) -->
            <div id="student_document_section" class="card card-custom mt-3" style="display: none;">
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

                    <div id="document_upload_container">
                        <!-- Document Upload Item 1 (Default) -->
                        <div class="document-upload-item mb-2" data-index="0">
                            <div class="row">
                                <div class="col-md-10">
                                    <input type="file" 
                                           name="student_documents[]" 
                                           class="form-control form-control-sm student-document-input" 
                                           accept="image/jpeg,image/png,image/jpg"
                                           data-index="0">
                                    <div class="mt-1 document-preview" id="preview_0" style="display: none;">
                                        <img src="" class="img-thumbnail" style="max-height: 60px;">
                                        <button type="button" class="btn btn-sm btn-light-danger ms-1 remove-preview" data-index="0">
                                            <i class="la la-times"></i>
                                        </button>
                                    </div>
                                    <small class="text-danger d-block"><b class="document-error" id="document_error_0"></b></small>
                                </div>
                                <div class="col-md-2 text-end">
                                    <button type="button" 
                                            class="btn btn-sm btn-icon btn-light-danger remove-document-btn" 
                                            data-index="0"
                                            style="display: none;">
                                        <i class="la la-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add More Button -->
                    <div class="text-start mt-2">
                        <button type="button" id="add_document_btn" class="btn btn-sm btn-light-primary">
                            <i class="la la-plus"></i>
                            Add Document
                        </button>
                        <small class="text-muted ms-2" id="document_count_text">1 of 4</small>
                    </div>
                </div>
            </div>
            <hr>
        </div>
        <hr>
        <div class="text-center">
            <button type="button" class="btn btn-light me-3"  onclick="resetVoucherAddMembership(event)">Cancel</button>
            <button id="AddPackageFinalMembership" type="submit" class="btn btn-primary spinner-button-save">
                <span class="indicator-label">Save</span>
            </button>
        </div>
    </div>
</div>
