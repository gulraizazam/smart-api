<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_add_user_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Edit Order</h2>
        <!--end::Modal title-->
        <!--begin::Close-->
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" data-kt-users-modal-action="close">
            <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
            <span class="svg-icon svg-icon-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1"
                        transform="rotate(-45 6 17.3137)" fill="black" />
                    <rect x="7.41422" y="6" width="16" height="2" rx="1"
                        transform="rotate(45 7.41422 6)" fill="black" />
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
        <form id="modal_edit_order_form" method="post" action="">
            <!--begin::Scroll-->
            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_add_user_scroll" data-kt-scroll="true"
                data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto"
                data-kt-scroll-dependencies="#kt_modal_add_user_header"
                data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">
                <div class="form-group">
                    <input type="hidden" id="edit_price" >
                    <input type="hidden" id="edit_product_type" name="product_type">
                    <input type="hidden" class="edit_old_product" name="old_product">
                    <div class="row mt-2">
                        <div class="fv-row col-md-12">
                            <label class="fw-bold fs-6 mb-2 pl-0">Patient Search </label>
                            <input class="form-control edit_order_patient_search_id patient_search_id search_field" placeholder="Patients Search" required>

                            <input type="hidden" id="edit_order_patient" name="patient_id"
                                class="filter-field search_field">
                            <span onclick="addUsers()" class="croxcli"
                                style="position:absolute; padding-left: 0% !important; top:37px; right:20px;"><i
                                    class="fa fa-times" aria-hidden="true"></i></span>
                            <div class="suggesstion-box" style="display: none;">
                                <ul class="suggestion-list"></ul>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Order From <span
                                    class="text text-danger">*</span></label>
                            <select id="edit_order_type_option" class="form-control form-control mb-3 mb-lg-0"
                                name="product_type_option">
                                <option value="">Select Option</option>
                                <option value="in_warehouse">Warehouse</option>
                                <option value="in_branch">Branch</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <div class="fv-row select_centre" style="display: none">
                                <label class="required fw-bold fs-6 mb-2 pl-0">Centre From</label>
                                <select id="edit_order_centre"
                                    class="form-control form-control-solid mb-3 mb-lg-0 select2"
                                    name="location_id" onchange="productSearch(this.value, 'location_id', 'edit', 'order')">
                                </select>
                            </div>
                            <div class="fv-row select_warehouse" style="display: none">
                                <label class="required fw-bold fs-6 mb-2 pl-0">Warehouse From</label>
                                <select id="edit_order_warehouse"
                                    class="form-control form-control-solid mb-3 mb-lg-0 select2"
                                    name="warehouse_id" onchange="productSearch(this.value, 'warehouse_id', 'edit', 'order')">
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-2">
                        <div class="fv-row col-md-6">
                                <label class="required fw-bold fs-6 mb-2 pl-0">Product</label>
                                <select id="edit_order_product"
                                    class="form-control form-control-solid mb-3 mb-lg-0 select2"
                                    name="product_id" onchange="productSelect(this.value, 'edit')">
                                </select>
                        </div>
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Available Quantity </label>
                            <input type="number" id="edit_available_quantity"
                                class="form-control form-control-lg form-control-solid mb-2" readonly>
                        </div>
                    </div>

                    <div class="row mt-2">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Price (per unit) </label>
                                <input type="number" id="edit_total_price"
                                class="form-control form-control-lg form-control-solid mb-2" readonly name="total_price">
                        </div>
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Quantity <span
                                    class="text text-danger">*</span></label>
                            <input type="number" id="edit_quantity" name="quantity"
                                class="form-control form-control-lg form-control-solid mb-2">
                        </div>
                    </div>
                    <div class="row">
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Payment Mode <span
                                    class="text text-danger">*</span></label>
                            <select id="edit_payment_mode"
                                class="form-control form-control-solid mb-3 mb-lg-0 select2 select2-hidden-accessible"
                                name="payment_mode">
                                <option value="">Select Payment Mode</option>
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="bank_wire">Bank/Wire Transfer</option>
                            </select>
                        </div>
                    </div>
                </div>

            </div>
            <!--end::Scroll-->
            <!--begin::Actions-->
            <hr>
            <div class="text-center">
                <button type="reset" class="btn btn-light me-3 popup-close"
                    data-kt-users-modal-action="cancel">Cancel</button>
                <button type="submit" class="btn btn-primary spinner-button" data-kt-users-modal-action="submit">
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
