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
        <form id="modal_add_transfer_products_form" method="post" action="{{ route('admin.transfer_product.store') }}">
            <!--begin::Scroll-->
            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_add_user_scroll" data-kt-scroll="true"
                data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto"
                data-kt-scroll-dependencies="#kt_modal_add_user_header"
                data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">
                <div class="form-group">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="fv-row select_centre_from" >
                                <label class="required fw-bold fs-6 mb-2 pl-0">Centre From</label>
                                <select id="add_product_centre_from"
                                    class="form-control form-control-solid mb-3 mb-lg-0 select2 product_search_id"
                                    name="from_location_id" onchange="productSearch(this.value, 'add', 'order')">
                                </select>
                            </div>
                           
                        </div>


                        <div class="col-md-6">
                            <div class="fv-row select_centre_to">
                                <label class="required fw-bold fs-6 mb-2 pl-0">Centre To</label>
                                <select id="add_product_centre_to"
                                    class="form-control form-control-solid mb-3 mb-lg-0 select2" name="to_location_id">
                                </select>
                            </div>
                            
                        </div>
                    </div>

                    <div class="row mt-6">
                        <div class="col-md-6">
                            <div class="fv-row">
                                <label class="required fw-bold fs-6 mb-2 pl-0">Product</label>
                                <select id="add_transfer_product"
                                    class="form-control form-control-solid mb-3 mb-lg-0 select2"
                                    name="product_id" onchange="productSelectTransfer(this.value, 'add')">
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 mb-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Transfer Date <span
                                class="text text-danger">*</span></label>
                            <input type="text" id="add_transfer_date" class="custom-datepicker form-control filter-field datatable-input" name="transfer_date" placeholder="Transfer Date" data-col-index="5">
                        </div>
                    </div>

                    <div class="row ">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Total Stock </label>
                            <input type="number" id="add_total_stock"
                                class="form-control form-control-lg form-control-solid mb-2" disabled>
                        </div>
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Transfer Stock Quantity <span
                                    class="text text-danger">*</span></label>
                            <input type="number" id="add_quantity" name="quantity"
                                class="form-control form-control-lg form-control-solid mb-2">
                        </div>
                    </div>
                    <div class="row mb-2">
                       
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