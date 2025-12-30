<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_add_user_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Add Product</h2>
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
        <form id="modal_add_products_form" method="post" action="{{route('admin.products.store')}}">
            <!--begin::Scroll-->
            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_add_user_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">
                <div class="form-group">
                    <div class="row">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Brand <span class="text text-danger">*</span></label>
                            <select id="add_products_brand" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="brand_id">
                            </select>
                        </div>
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">SKU <span class="text text-danger">*</span></label>
                            <select id="sku" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="sku">
                            </select>
                            
                        </div>
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Name <span class="text text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control form-control-lg form-control-solid mb-2">
                        </div>
                        <!-- Sale price is now set at inventory allocation level -->
                        <input type="hidden" id="sale_price" name="sale_price" value="0">
                    </div>
                    <div class="row">
                        <!-- <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Purchase Price<small> (per unit)</small> <span class="text text-danger">*</span></label>
                            <input type="number" id="purchase_price" name="purchase_price" class="form-control form-control-lg form-control-solid mb-2">
                        </div> -->
                       
                    </div>
                    <!-- <div class="row">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Quantity <span class="text text-danger">*</span></label>
                            <input type="number" id="quantity" name="quantity" class="form-control form-control-lg form-control-solid mb-2">
                        </div>
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Total Purchase Price <span class="text text-danger">*</span></label>
                            <input type="text" id="total_purchase_price" name="total_purchase_price" class="form-control form-control-lg form-control-solid mb-2" readonly="readonly">
                        </div>
                    </div> -->
                    <!-- <div class="row">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0" >Product Type <span class="text text-danger">*</span></label>
                            <select id="add_product_type" class="form-control mb-3 mb-lg-0" name="product_type">
                                <option value="">Select Product Type</option>
                                <option value="in_house_use">In House Use</option>
                                <option value="for_sale">For Sale</option>
                            </select>
                        </div>
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Warehouse  <span class="text text-danger">*</span></label>
                            <select id="add_product_warehouse" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="warehouse_id">
                            </select>
                        </div>
                    </div> -->
                   
                </div>

            </div>
            <!--end::Scroll-->
            <!--begin::Actions-->
            <hr>
            <div class="text-center">
                <button type="reset" class="btn btn-light me-3 popup-close" data-kt-users-modal-action="cancel">Cancel</button>
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