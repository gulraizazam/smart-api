<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_add_user_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Transfer Product</h2>
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
        <form id="modal_transfer_products_form_submit" method="post" action="{{ route('admin.products.transfer_product') }}">
            <!--begin::Scroll-->
            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_add_user_scroll" data-kt-scroll="true"
                data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto"
                data-kt-scroll-dependencies="#kt_modal_add_user_header"
                data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">
                <div class="form-group">
                    <input type="hidden" id="transfer_product_id" name="product_id">
                    <input type="hidden" id="transfer_location_id_from" name="from_location_id">
                    <input type="hidden" id="transfer_warehouse_id_from" name="from_warehouse_id">
                    <div class="row">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Transfer From <span
                                    class="text text-danger">*</span></label>
                            <select id="transfer_product_type_option_from" class="form-control form-control mb-3 mb-lg-0"
                                name="product_type_option_from">
                                <option value="">Select Option</option>
                                <option value="in_warehouse">Warehouse</option>
                                <option value="in_branch">Branch</option>
                            </select>
                        </div>
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Transfer To <span
                                    class="text text-danger">*</span></label>
                            <select id="transfer_product_type_option_to" class="form-control form-control mb-3 mb-lg-0"
                                name="product_type_option_to">
                                <option value="">Select Option</option>
                                <option value="in_warehouse">Warehouse</option>
                                <option value="in_branch">Branch</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="fv-row select_centre_from" style="display: none">
                                <label class="required fw-bold fs-6 mb-2 pl-0">Centre From</label>
                                <select id="transfer_product_centre_from"
                                    class="form-control form-control-solid mb-3 mb-lg-0"
                                    name="from_location_id" onchange="productSearch(this.value, 'location_id', 'transfer', 'transfer')">
                                </select>
                            </div>
                            <div class="fv-row select_warehouse_from" style="display: none">
                                <label class="required fw-bold fs-6 mb-2 pl-0">Warehouse From</label>
                                <select id="transfer_product_warehouse_from"
                                    class="form-control form-control-solid mb-3 mb-lg-0"
                                    name="from_warehouse_id" onchange="productSearch(this.value, 'warehouse_id', 'transfer', 'transfer')">
                                </select>
                            </div>
                        </div>


                        <div class="col-md-6">
                            <div class="fv-row select_centre_to" style="display: none">
                                <label class="required fw-bold fs-6 mb-2 pl-0">Centre To</label>
                                <select id="transfer_product_centre_to"
                                    class="form-control form-control-solid mb-3 mb-lg-0 select2" name="to_location_id">
                                </select>
                            </div>
                            <div class="fv-row select_warehouse_to" style="display: none">
                                <label class="required fw-bold fs-6 mb-2 pl-0">Warehouse To</label>
                                <select id="transfer_product_warehouse_to"
                                    class="form-control form-control-solid mb-3 mb-lg-0 select2" name="to_warehouse_id">
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-md-6">
                            <div class="fv-row">
                                <label class="required fw-bold fs-6 mb-2 pl-0">Product</label>
                                <select id="transfer_transfer_product" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="product_id">
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Total Stock </label>
                            <input type="number" id="transfer_total_stock"
                                class="form-control form-control-lg form-control-solid mb-2" disabled>
                        </div>
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Transfer Stock Quantity <span
                                    class="text text-danger">*</span></label>
                            <input type="number" id="transfer_quantity" name="quantity"
                                class="form-control form-control-lg form-control-solid mb-2" min="1">
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-6 mb-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Transfer Date <span
                                class="text text-danger">*</span></label>
                            <input type="text" id="transfer_transfer_date" class="custom-datepicker form-control filter-field datatable-input" name="transfer_date" placeholder="Transfer Date" data-col-index="5">
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
