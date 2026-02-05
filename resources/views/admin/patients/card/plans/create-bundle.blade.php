<!--begin::Modal content-->
<div class="modal-content" id="add_patient_bundle">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Add Bundle</h2>
        <!--end::Modal title-->
        <!--begin::Close-->
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" data-kt-users-modal-action="close">
            <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
            <span class="svg-icon svg-icon-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                    <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                </svg>
            </span>
            <!--end::Svg Icon-->
        </div>
        <!--end::Close-->
    </div>
    <!--end::Modal header-->
    <!--begin::Modal body-->
    <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">

        <div id="duplicateErrBundle" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Duplicate record found, please select another one.
        </div>
        <div id="successMessageBundle" class="alert alert-success display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Bundle successfully created
        </div>
        <div id="inputfieldMessageBundle" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Kindly enter required fields or you enter wrong value.
        </div>
        <div id="wrongMessageBundle" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Something went wrong!
        </div>
        <div id="percentageMessageBundle" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Your discount limit exceeded.
        </div>
        <div id="AlreadyExitMessageBundle" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Unable to enter same service with different price.
        </div>
        <div id="datanotexistBundle" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            That center not have any service.
        </div>
        <div id="DiscountRangeBundle" class="alert alert-danger" style="display: none;">
            <button class="close" data-close="alert"></button>
            Your discount limit exceeded.
        </div>

        <!--begin::Form-->
        <input type="hidden" name="random_id_bundle" id="random_id_bundle" class="form-control">
        <input type="hidden" name="patient_id_bundle" id="add_patient_id_bundle">
        <input type="hidden" name="location_id_bundle" id="add_bundle_location_id">

        <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_bundle_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

            <div class="form-group">
                <div class="row">

                    <div class="fv-row col-md-2 mt-5">
                        <p class="required fw-bold fs-6 mb-2 pl-0">Patient Name</p>
                        <h3 class="bundlePatientName">{{getPatientName(request('id'))}}</h3>
                    </div>

                    <div class="fv-row col-md-2 mt-5">
                        <p class="required fw-bold fs-6 mb-2 pl-0">Membership</p>
                        <h3 class="bundleMembershipInfo"></h3>
                    </div>

                    <div class="fv-row col-md-3 mt-5">
                        <p class="required fw-bold fs-6 mb-2 pl-0">Location</p>
                        <h3 class="bundleLocationName"></h3>
                    </div>

                    <div class="fv-row col-md-5 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Appointment <span class="text text-danger">*</span></label>
                        <select id="add_appointment_id_bundle" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="appointment_id_bundle">
                            <option value="">Select Appointment</option>
                        </select>
                        <small class="text-danger ml-1 mt-1"><b id="add_appointment_id_bundle_error" class="create-bundle-error"></b></small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="row">
                    <div class="fv-row col-md-4 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Bundles <span class="text text-danger">*</span></label>
                        <select id="add_service_id_bundle" onchange="getServiceDiscountBundle($(this));" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="service_id_bundle">
                            <option value="">Select Bundle</option>
                        </select>
                        <small class="text-danger ml-1 mt-1"><b id="add_service_id_bundle_error" class="create-bundle-error"></b></small>
                    </div>
                    <div class="fv-row col-md-3 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Price</label>
                        <input type="text" readonly name="net_amount_bundle" class="form-control" id="net_amount_bundle">
                    </div>
                    <div class="fv-row col-md-3 mt-5" id="sold_by_div_bundle">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Sold By <span class="text text-danger">*</span></label>
                        <select id="add_sold_by_bundle" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="sold_by_bundle">
                        </select>
                        <small class="text-danger ml-1 mt-1"><b id="add_sold_by_bundle_error" class="create-bundle-error"></b></small>
                    </div>
                    <div class="fv-row col-md-2 mt-1">
                        <div class="text-center mt-10">
                            <button type="button" id="AddPackageBundle" class="btn btn-primary float-right spinner-button-add">
                                <span class="indicator-label">Add</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <hr>

            <div class="table-responsive add_center_target_table">
                <table id="appointment_detail_bundle" class="table table-striped table-bordered table-advance table-hover">
                    <thead>
                        <tr>
                            <th>Service Name</th>
                            <th>Regular Price</th>
                            <th>Amount</th>
                            <th>Tax</th>
                            <th>Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="bundle_services"></tbody>
                </table>
            </div>

            <div class="form-group">
                <div class="row">
                    <div class="fv-row col-md-3 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Total</label>
                        <input type="text" readonly id="package_total_bundle" class="form-control" value="0" name="package_total_bundle">
                    </div>
                    <div class="fv-row col-md-3 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Payment Mode <span class="text text-danger">*</span></label>
                        <select id="payment_mode_id_bundle" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="payment_mode_id_bundle">
                            <option value="">Select Payment Mode</option>
                        </select>
                    </div>
                    <div class="fv-row col-md-3 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Cash Amount</label>
                        <input type="number" id="cash_amount_bundle" class="form-control" placeholder="Enter Amount" name="cash_amount_bundle" min="0" oninput="validity.valid||(value='');">
                        <small class="text-danger ml-1 mt-1"><b id="cash_amount_bundle_error" class="create-bundle-error"></b></small>
                    </div>
                    <div class="fv-row col-md-3 mt-5">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Cash Received Remain</label>
                        <input type="text" readonly min="0" name="total_price_bundle" value="0" class="form-control" id="grand_total_bundle">
                    </div>
                </div>
            </div>

            <hr>
        </div>

        <hr>
        <div class="text-center">
            <button type="button" class="btn btn-light me-3 popup-close">Cancel</button>
            <button id="AddPackageFinalBundle" type="submit" class="btn btn-primary spinner-button-save">
                <span class="indicator-label">Save</span>
            </button>
        </div>
    </div>
</div>
