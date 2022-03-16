<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_add_user_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Create Order</h2>
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
        <!--begin::Form-->
        <form id="modal_add_plan_form" method="post" action="#">
            <!--begin::Scroll-->

            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_discounts_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">

                        <div class="fv-row col-md-12 mt-12">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Customer <span class="text text-danger">*</span></label>
                            <select  id="add_location_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="location_id">
                                <option value="">Select Customer</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">

                        <div class="fv-row col-md-6 mt-6 select2-search">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Product <span class="text text-danger">*</span></label>
                            <select id="add_patient_id" class="form-control form-control-solid mb-3 mb-lg-0 patient_id select2" name="patient_id">
                                <option value="">Select Product</option>
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Discount <span class="text text-danger">*</span></label>
                            <select id="add_appointment_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="appointment_id">
                                <option value="">Select Discount</option>
                            </select>
                        </div>


                    </div>
                </div>

                
                    <div class="row">

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Quantity </label>
                            <input type="number" name="discount_value" class="form-control" id="add_discount_value">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Price</label>
                            <input type="number" name="price" class="form-control" id="add_price">
                        </div>

                    </div>
                    <div class="text-center"">
                            <div class="text-center mt-10">
                                <button type="button" id="AddPackage" class="btn btn-primary spinner-button">
                                    <span class="indicator-label">Add</span>
                                </button>
                            </div>
                    </div>


                <hr>

                <div class="table-responsive add_center_target_table">
                    <table id="add_centre_target_location" class="table table-striped table-bordered table-advance table-hover">

                        <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Discount</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Action</th>
                        </tr>
                        </thead>

                        <tbody class="plan_services"><tr class="text-center"><td colspan="8">No record found</td></tr></tbody>

                    </table>
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
