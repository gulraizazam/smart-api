<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Add Discounts</h2>
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
            <!--begin::Scroll-->

            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_discounts_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Discount Name <span class="text text-danger">*</span></label>
                            <input id="add_name" class="form-control" type="text" name="name" placeholder="Name">
                        </div>
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Discount Applicable On <span class="text text-danger">*</span></label>
                            <select id="add_amount_types" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="discount_type" onchange="discountType(this,'add')">
                                <option value="">Select </option>
                                <option value="Treatment">Treatment</option>
                                <option value="Consultancy">Consultancy</option>
                                <option value="Inventory">Inventory</option>
                            </select>
                        </div>
                    
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Discount Type <span class="text text-danger">*</span></label>
                            <select id="add_amount_type" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="type" onchange="SetFields()">
                                <option value="">Select Amount Type</option>
                                <option value="Fixed">Fixed</option>
                                <option value="Percentage">Percentage</option>
                                <option value="Configurable">Configurable</option>
                            </select>
                        </div>
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Type</label>
                            <div class="radio-inline tax-radios mb-3">
                                <label class="radio">
                                    <input type="radio" class="group_slug" value="default" checked name="slug">
                                    <span></span>
                                    Fixed
                                </label>
                            </div>

                            <div class="radio-inline tax-radios mb-3" id="custom">
                                <label class="radio">
                                    <input type="radio" class="group_slug" value="custom" name="slug">
                                    <span></span>
                                    Custom
                                </label>

                            </div>

                        </div>
                        <div class="discount_wrap w-100">
                            <div class="fv-row col-12 discount_type_wrap d-flex mt-3">    
                                <label class="fw-bold fs-6 pl-0 pr-4 pt-2">Buy</label>
                                <select class="form-control form-control-solid mb-3" name="type">
                                    <option value="">Select Session</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                </select>
                                <label class="fw-bold fs-6 px-5 text-nowrap pt-2">Sessions of</label>
                                <select class="form-control form-control-solid mb-3" name="base_service" id="base_service">
                                    
                                </select>
                            </div>
                            <div class="fv-row col-12 discount_type_wrap get_discount_type d-flex mt-3">    
                                <a class="btn p btn-primary px-3 mr-4 py-0 add_new_discount d-flex justify-content-center align-items-center"><i class="la la-plus p-0 m-0"></i></a>
                                <label class="fw-bold fs-6 pl-0 pr-4 pt-2 mb-0">Get</label>
                                <select class="form-control form-control-solid mb-0" name="type">
                                    <option value="">Select Session</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                </select>
                                <label class="fw-bold fs-6 px-5 text-nowrap pt-2 mb-0">Sessions of</label>
                                <select class="form-control form-control-solid mb-0" name="services" id="services">
                                    
                                </select>
                                <div class="d-flex align-items-center ml-5">
                                    <div class="radio-inline tax-radios mb-0 mr-3">
                                        <label class="radio">
                                            <input type="radio" class="group_slug" value="default" checked name="complimentory">
                                            <span class="mr-2"></span>
                                            Complimentory
                                        </label>
                                    </div>

                                    <div class="radio-inline tax-radios mb-0" id="custom">
                                        <label class="radio">
                                            <input type="radio" class="group_slug" value="custom" name="percentage">
                                            <span class="mr-2"></span>
                                            Percentage
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="fv-row col-md-12 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Amount <span class="text text-danger">*</span></label>
                            <input min="0" id="add_amount" class="form-control" type="number" name="amount">
                        </div>
                    </div>
                   
                    <div class="row">

                        <div class="fv-row col-md-6 mt-5 input-daterange current-datepicker">
                            <label for="refund_note" class="required fw-bold fs-6 mb-2 pl-0">From <span class="text text-danger">*</span></label>
                            <input type="text" id="add_start" class="form-control datatable-input" name="start">
                        </div>

                        <div class="fv-row col-md-6 mt-5 input-daterange current-datepicker">
                            <label for="refund_note" class="required fw-bold fs-6 mb-2 pl-0">To <span class="text text-danger">*</span></label>
                            <input type="text" id="add_end" class="form-control datatable-input" name="end">
                        </div>

                        <span class="switch switch-icon mt-5">
                           <label for="add_active" class="fw-bold fs-6">
                            <input id="add_active" value="1" type="checkbox" name="active">
                            <span></span>
                           </label>
                           <span class="fs-6 pl-2">Active</span>
                        </span>

                    </div>
                    <!-- <div class="row">

                        <div class="fv-row col-md-5 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Centre <span class="text text-danger">*</span></label>
                            <select id="locations" onchange="getCentreServices($(this));" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="location_id">
                                <option value="">Select Centre</option>
                            </select>
                        </div>

                        <div class="fv-row col-md-5 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Services <span class="text text-danger">*</span></label>
                            <select id="services" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="service_id">
                                <option value="">Select Services</option>
                            </select>
                        </div>

                        <div class="fv-row col-md-2 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0" style="opacity: 0;">Add <span class="text text-danger">*</span></label>
                            <button type="submit" class="btn btn-primary spinner-button">
                                <span class="indicator-label">Add</span>
                            </button>
                        </div>

                    </div> -->
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



