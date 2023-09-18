<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_add_user_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder" id="model-title" >Add Configurable Package</h2>
        <!--end::Modal title-->
        <!--begin::Close-->
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" data-kt-users-modal-action="close">
            <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
            <span class="svg-icon svg-icon-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)"
                          fill="black"/>
                    <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/>
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
        <form id="modal_bundles_form" method="post" action="{{route('admin.bundles.store')}}">
            <div id="put_input">
            </div>
            <input type="hidden" name="package_type" value="configurable">
            <!--begin::Scroll-->
            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_add_user_scroll" data-kt-scroll="true"
                 data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto"
                 data-kt-scroll-dependencies="#kt_modal_add_user_header"
                 data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">
                <div class="form-group">
                    <div class="row">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Name <span class="text text-danger">*</span>
                            </label>
                            <input type="text" id="bundles_name" name="name"
                                   class="form-control form-control-lg form-control-solid mb-2">
                        </div>
                        <div class="discount_wrap w-100"  id="configurable_fields">
                            <div class="fv-row col-12 discount_type_wrap get_discount_type d-flex mt-3">    
                                <label class="fw-bold fs-6 pl-0 pr-4 pt-2">Buy</label>
                                <select class="form-control form-control-solid mb-3" name="sessions_buy">
                                    <option value="">Select Session</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                </select>
                               
                                <label class="fw-bold fs-6 px-5 text-nowrap pt-2">Sessions of</label>
                                <select class="form-control form-control-solid mb-3 select2" name="base_service" id="base_service">
                                    
                                </select>
                            </div>
                            <div class="fv-row col-12 discount_type_wrap get_discount_type mt-3">
                                <div class="d-flex">
                                    <a class="btn p btn-primary px-3 mr-4 py-0  d-flex justify-content-center align-items-center add_new_discount_field add_new_discount"><i class="la la-plus p-0 m-0"></i></a>
                                    <label class="fw-bold fs-6 pl-0 pr-4 pt-2 mb-0">Get</label>
                                    <select class="form-control form-control-solid mb-0" name="sessions[]">
                                        <option value="">Select Session</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                    </select>
                                    <label class="fw-bold fs-6 px-5 text-nowrap pt-2 mb-0">Sessions of</label>
                                    <select class="form-control form-control-solid mb-0 " name="services_name[]" id="services_sessions">
                                    </select>
                                    <div class="d-flex align-items-center ml-5">
                                        <div class="radio-inline tax-radios mb-0 mr-3">
                                            <label class="radio">
                                                <input type="radio" class="group_slug" value="complimentory" name="disc_type[]">
                                                <span class="mr-2"></span>
                                                Complimentory
                                            </label>
                                        </div>

                                        <div class="radio-inline tax-radios mb-0" id="custom">
                                            <label class="radio">
                                                <input type="radio" class="group_slug percentage" value="custom" name="disc_type[]">
                                                <span class="mr-2"></span>
                                                Percentage
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    
                    
                    <div class="row mt-5">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Valid From <span
                                    class="text text-danger">*</span> </label>
                            <input type="text" id="start" name="start" readonly
                                   class="current-datepicker form-control form-control-lg form-control-solid mb-2">
                        </div>
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Valid To <span
                                    class="text text-danger">*</span> </label>
                            <input type="text" id="end" name="end" readonly
                                   class="current-datepicker form-control form-control-lg form-control-solid mb-2">
                        </div>
                    </div>
                   
                </div>
            </div>
            <!--end::Scroll-->
            <!--begin::Actions-->
            <hr>
            <div class="text-center">
                <button type="reset" class="btn btn-light me-3 popup-close" data-kt-users-modal-action="cancel">Cancel
                </button>
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
