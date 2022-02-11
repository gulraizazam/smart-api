<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Add Leads</h2>
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


        <div id="duplicateErr" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Duplicate record found, please select another one.
        </div>
        <div id="successMessage" class="alert alert-success display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Plan successfully created
        </div>
        <div id="inputfieldMessage" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Kindly enter required fields or you enter wrong value.
        </div>
        <div id="wrongMessage" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Something went wrong!
        </div>
        <div id="percentageMessage" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Your discount limit exceeded.
        </div>
        <div id="AlreadyExitMessage" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Unable to enter same service with different price.
        </div>
        <div id="datanotexist" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            That center not have any service.
        </div>
        <div id="DiscountRange" class="alert alert-danger" style="display: none;">
            <button class="close" data-close="alert"></button>
            Your discount limit exceeded.
        </div>

        <!--begin::Form-->
        <form id="modal_add_plan_form" method="post" action="{{route('admin.packages.store')}}">
            <!--begin::Scroll-->

            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_discounts_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">

                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Centers <span class="text text-danger">*</span></label>
                            <select onchange="getServices();" id="add_location_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="location_id">
                                <option value="">Select Centre</option>
                            </select>
                        </div>

                        <div class="fv-row col-md-3 mt-5 select2-search">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Patients <span class="text text-danger">*</span></label>
                            <select id="add_patient_id" class="form-control form-control-solid mb-3 mb-lg-0 patient_id select2" name="patient_id">
                                <option value="">Select Patient</option>
                            </select>
                        </div>

                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Appointment <span class="text text-danger">*</span></label>
                            <select id="add_appointment_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="appointment_id">
                                <option value="">Select Appointment</option>
                            </select>
                        </div>

                        <div class="fv-row col-md-3 mt-5">
                            <div class="checkbox-inline mt-12">
                                <span>Is Exclusive </span> &nbsp;
                                <label for="is_exclusive" class="checkbox checkbox-rounded">
                                    <input id="is_exclusive" type="checkbox" checked="checked" name="is_exclusive">
                                    <span></span>
                                </label>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="form-group">
                    <div class="row">

                        <div class="fv-row col-md-4 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Services <span class="text text-danger">*</span></label>
                            <select id="add_service_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="service_id">
                                <option value="">Select Service</option>
                            </select>
                        </div>

                        <div class="fv-row col-md-4 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Discounts</label>
                            <select id="add_discount_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="discount_id">
                                <option value="">Select Discount</option>
                            </select>
                        </div>

                        <div class="fv-row col-md-4 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Discount Type</label>
                            <select id="add_discount_type" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="discount_type">
                                <option value="">Select Discount Type</option>
                            </select>
                        </div>

                        <div class="fv-row col-md-4 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Discount Type </label>
                            <input type="number" name="discount_type" class="form-control" id="add_discount_type">
                        </div>

                        <div class="fv-row col-md-4 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Price</label>
                            <input type="number" name="price" class="form-control" id="add_price">
                        </div>

                        <div class="fv-row col-md-4 mt-5">
                            <div class="text-center mt-10">
                                <button type="button" id="AddPackage" class="btn btn-primary float-right spinner-button">
                                    <span class="indicator-label">Add</span>
                                </button>
                            </div>
                        </div>

                    </div>

                </div>

                <hr>

                <div class="table-responsive add_center_target_table">
                    <table id="add_centre_target_location" class="table table-striped table-bordered table-advance table-hover">

                        <thead>
                        <tr>
                            <th>Service Name</th>
                            <th>Service/Bundle Price</th>
                            <th>Discount Name</th>
                            <th>Discount Price</th>
                            <th>Amount</th>
                            <th>Tax %</th>
                            <th>Tax Amt.</th>
                            <th>Action</th>
                        </tr>
                        </thead>

                        <tbody class="plan_services"><tr class="text-center"><td colspan="8">No record found</td></tr></tbody>

                    </table>
                </div>

                <div class="form-group">
                    <div class="row">

                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Total </label>
                            <input type="number" id="add_package_total" class="form-control" name="package_total_1">
                        </div>

                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Payment Mode <span class="text text-danger">*</span></label>
                            <select id="add_payment_mode_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="payment_mode_id">
                                <option value="">Select Payment Mode</option>
                            </select>
                        </div>

                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Cash Amount</label>
                            <input type="number" min="0" id="add_cash_amount" class="form-control" value="0" name="cash_amount">
                        </div>


                        <div class="fv-row col-md-3 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Cash Received Remain</label>
                            <input type="number" min="0" name="total_price" value="0" class="form-control" id="add_total_price">
                        </div>

                    </div>

                </div>

                <hr>

            </div>
            <!--end::Scroll-->
            <!--begin::Actions-->
            <hr>
            <div class="text-center">
                <button type="reset" class="btn btn-light me-3 popup-close" data-kt-users-modal-action="cancel">Cancel</button>
                <button type="submit" class="btn btn-primary spinner-button">
                    <span class="indicator-label">Submit</span>
                </button>
            </div>
            <!--end::Actions-->
        </form>
        <!--end::Form-->
    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->



