<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_edit_warehouse_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Edit Warehouse</h2>
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
        <form id="modal_edit_warehouse_form" method="put" action="">
            <!--begin::Scroll-->
            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_edit_warehouse_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_edit_user_header" data-kt-scroll-wrappers="#kt_modal_edit_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Warehouse Name <span class="text text-danger">*</span></label>
                            <input type="text" id="edit_name" name="name" class="form-control form-control-lg form-control-solid mb-2">
                        </div>

                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">City <span class="text text-danger">*</span></label>
                            <select id="edit_warehouse_cities" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="city_id">

                            </select>
                        </div>

                        <!-- <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0 mt-5">Manager Name <span class="text text-danger">*</span></label>
                            <input type="text" id="edit_manager_name" name="manager_name" class="form-control form-control-lg form-control-solid" />
                        </div>

                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0 mt-5">Manager Phone <span class="text text-danger">*</span></label>
                            <input type="text" id="edit_manager_phone" name="manager_phone" class="form-control form-control-lg form-control-solid mb-2" />
                        </div>

                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0 mt-5">Google Map <span class="text text-danger">*</span></label>
                            <input type="text" id="edit_google_map" name="google_map" class="form-control form-control-lg form-control-solid mb-2">
                        </div>

                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0 mt-5">Address <span class="text text-danger">*</span></label>
                            <input type="text" id="edit_address" name="address" class="form-control form-control-lg form-control-solid mb-2">
                        </div> -->
                    </div>
                    {{-- <div class="row">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0 mt-5">Select Logo</label>
                            <div class="col-lg-9 col-xl-6">
                                <div class="image-input image-input-outline" id="kt_image_1">
                                    <div class="image-input-wrapper" id="edit_warehouse_image"></div>
                                    <label class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow" data-action="change" data-toggle="tooltip" title="" data-original-title="Change avatar">
                                        <i class="fa fa-pen icon-sm text-muted"></i>
                                        <input id="file" type="file" name="file" accept=".png, .jpg, .jpeg" />
                                        <input type="hidden" name="file" />
                                    </label>
                                    <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow" data-action="cancel" data-toggle="tooltip" title="Cancel avatar">
                                        <i class="ki ki-bold-close icon-xs text-muted"></i>
                                    </span>
                                </div>

                            </div>
                        </div>

                    </div> --}}
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