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
        <div id="inputfieldMessage" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Kindly enter required fields or you enter wrong value.
        </div>
        <div id="inputExistMessage" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Product already exist
        </div>
        <div id="inputEmptyMessage" class="alert alert-danger display-hide" style="display: none;">
            <button class="close" data-close="alert"></button>
            Product not exist. Please add first then save
        </div>
        <!--begin::Form-->
        <form id="modal_add_order_form" method="post" action="{{route('admin.orders.store')}}">
            <!--begin::Scroll-->
            <input type="hidden" id="add_unit_price" />
            <input type="hidden" id="discount_price" />
            <input type="hidden" id="available_quantity" />
            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_discounts_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">

                        <div class="fv-row col-md-12 mt-12">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Patient <span class="text text-danger">*</span></label>
                            <input class="form-control filter-field search_patient">
                            <input type="hidden" class="filter-field search_field" id="add_patient_id">
                            <span onclick="addUsers();" class="croxcli" style="padding-left: 0% !important; top:36px; right:22px; position: absolute;"><i class="fa fa-times" aria-hidden="true"></i></span>
                            <div class="suggesstion-box" style="display: none;">
                                <ul class="suggestion-list"></ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">

                        <div class="fv-row col-md-6 mt-6 select2-search">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Product <span class="text text-danger">*</span></label>
                            <select id="add_product_id" class="form-control product_id form-control-solid mb-3 mb-lg-0 select2" name="product_id">
                                <option value="">Select Product</option>
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Quantity </label>
                            <input type="number" name="quantity" class="form-control" id="add_quantity">
                        </div>


                    </div>
                </div>

                
                    <div class="row">

                        <div class="fv-row col-md-6 mt-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Discount <span class="text text-danger">*</span></label>
                            <select id="add_disccount_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="discount_id">
                                <option value="">Select Discount</option>
                            </select>
                        </div>
                       

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Price</label>
                            <input type="number" name="price" class="form-control" id="add_price" readonly>
                        </div>

                    </div>
                    <div class="text-center"">
                            <div class="text-center mt-10">
                                <button type="button" id="add_order" class="btn btn-primary spinner-button">
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
                <button type="button" class="btn btn-primary spinner-button" onclick="saveOrder();">
                    <span class="indicator-label">Save</span>
                </button>
            </div>
            <!--end::Actions-->
        </form>
        <!--end::Form-->
    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->
