<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Add Discount</h2>
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
        <form id="modal_add_discounts_form" method="post" action="{{route('admin.discounts.store')}}">
            <input type="hidden" name="slug" value="default">
            <!--begin::Scroll-->

            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_discounts_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">
                        <!-- Discount Type Selector -->
                        <div class="fv-row col-md-12 mt-3">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Discount Type <span class="text text-danger">*</span></label>
                            <select id="add_discount_type" class="form-control form-control-solid mb-3 mb-lg-0" name="type" onchange="toggleDiscountTypeFields()">
                                <option value="Simple">Simple Discount</option>
                                <option value="Configurable">Configurable (Buy X Get Y)</option>
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Discount Name <span class="text text-danger">*</span></label>
                            <input id="add_name" class="form-control" type="text" name="name" placeholder="Name">
                        </div>
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Discount Applicable On <span class="text text-danger">*</span></label>
                            <select id="add_amount_types" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="discount_type">
                                <option value="">Select </option>
                                <option value="Treatment">Treatment</option>
                                <option value="Consultancy">Consultancy</option>
                                <option value="Inventory">Inventory</option>
                            </select>
                        </div>

                        <!-- Configurable Discount Fields (hidden by default) -->
                        <div class="col-md-12 mt-4 configurable-discount-fields" style="display: none;">
                            <!-- BUY Section -->
                            <div class="card card-bordered">
                                <div class="card-header bg-light-primary py-3">
                                    <h5 class="card-title mb-0"><i class="la la-shopping-cart mr-2"></i>BUY (Customer Pays For)</h5>
                                </div>
                                <div class="card-body py-3">
                                    <div class="row align-items-end">
                                        <div class="col-md-12 mb-3">
                                            <label class="fw-bold fs-6 mb-2">Apply To</label>
                                            <div class="d-flex align-items-center">
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input buy-mode-radio" type="radio" name="buy_mode" value="service" id="add_buy_mode_service" checked>
                                                    <label class="form-check-label" for="add_buy_mode_service">Specific Service</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input buy-mode-radio" type="radio" name="buy_mode" value="category" id="add_buy_mode_category">
                                                    <label class="form-check-label" for="add_buy_mode_category">Service Category</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="fw-bold fs-6 mb-2">Sessions <span class="text text-danger">*</span></label>
                                            <select class="form-control form-control-solid" name="sessions_buy" id="add_sessions_buy">
                                                <option value="">Select</option>
                                                @for($i = 1; $i <= 10; $i++)
                                                    <option value="{{ $i }}">{{ $i }}</option>
                                                @endfor
                                            </select>
                                        </div>
                                        <div class="col-md-1 text-center d-flex align-items-center justify-content-center" style="padding-bottom: 8px;">
                                            <span class="fw-bold">of</span>
                                        </div>
                                        <div class="col-md-5 add-buy-service-wrap">
                                            <label class="fw-bold fs-6 mb-2"><span class="add-buy-label">Service</span> <span class="text text-danger">*</span></label>
                                            <select class="form-control form-control-solid select2" name="base_service" id="add_base_service">
                                                <option value="">Select Service</option>
                                            </select>
                                        </div>
                                        <div class="col-md-5 add-buy-category-wrap" style="display:none;">
                                            <label class="fw-bold fs-6 mb-2">Categories <span class="text text-danger">*</span></label>
                                            <select class="form-control form-control-solid select2" name="base_service[]" id="add_base_category" multiple>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- GET Section -->
                            <div class="card card-bordered mt-3">
                                <div class="card-header bg-light-success py-3">
                                    <h5 class="card-title mb-0"><i class="la la-gift mr-2"></i>GET (Customer Receives)</h5>
                                </div>
                                <div class="card-body py-3" id="add_get_services_container">
                                    <!-- GET rows will be added here dynamically -->
                                </div>
                            </div>
                        </div>

                        <div class="fv-row col-md-6 mt-5 input-daterange current-datepicker">
                            <label for="refund_note" class="required fw-bold fs-6 mb-2 pl-0">From <span class="text text-danger">*</span></label>
                            <input type="text" id="add_start" class="form-control datatable-input" name="start">
                        </div>

                        <div class="fv-row col-md-6 mt-5 input-daterange current-datepicker">
                            <label for="refund_note" class="required fw-bold fs-6 mb-2 pl-0">To <span class="text text-danger">*</span></label>
                            <input type="text" id="add_end" class="form-control datatable-input" name="end">
                        </div>

                        <div class="fv-row col-md-12 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Roles <span class="text text-danger">*</span></label>
                            <select id="add_user_roles" class="form-control form-control-solid mb-3 mb-lg-0 select2" multiple="multiple" name="roles[]">

                            </select>
                        </div>

                        <div class="fv-row col-md-12 mt-5">
                            <label class="fw-bold fs-6 mb-2 pl-0">Customer Type </label>
                            <select id="add_customer_type" class="form-control form-control-solid mb-3 mb-lg-0" name="customer_type_id">
                                <option value="">All Patients</option>
                            </select>
                        </div>
                    
                        <span class="switch switch-icon mt-5">
                           <label for="add_active" class="fw-bold fs-6">
                            <input id="add_active" value="1" type="checkbox" name="active">
                            <span></span>
                           </label>
                           <span class="fs-6 pl-2">Active</span>
                        </span>

                    </div>
                    
                </div>

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



