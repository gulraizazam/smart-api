<div class="modal fade" id="change_modal" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered form-popup" id="user_operator_settings_edit">
        <!--begin::Modal content-->
        <div class="modal-content">
            <!--begin::Modal header-->
            <div class="modal-header" id="kt_modal_password_header">
                <!--begin::Modal title-->
                <h2 class="fw-bolder">Operator Setting Edit</h2>
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
                <form id="modal_user_operator_settings_form" method="post" action="#">
                    <!--begin::Scroll-->
                    @method('put')
                    @csrf
                    <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_user_type_scroll"
                         data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}"
                         data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header"
                         data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                        <div class="form-group">
                            <div class="row">
                                <div class="fv-row col-md-12">
                                    <label class="required fw-bold fs-6 mb-2 pl-0">URL</label>
                                    <input type="text" class="form-control form-control-lg bg-secondary form-control-solid" disabled id="form_url">
                                </div>
                            </div>
                            <div class="row">
                                <div class="fv-row col-md-6">
                                    <label class="required fw-bold fs-6 mb-2 pl-0">Username</label>
                                    <input type="text" class="form-control form-control-lg bg-secondary form-control-color" disabled id="form_username">
                                </div>
                                <div class="fv-row col-md-6">
                                    <label class="required fw-bold fs-6 mb-2 pl-0">Password</label>
                                    <input type="text" class="form-control form-control-lg bg-secondary form-control-solid" disabled id="form_password">
                                </div>
                            </div>
                            <div class="row">
                                <div class="fv-row col-md-6">
                                    <label class="required fw-bold fs-6 mb-2 pl-0">Mask</label>
                                    <input type="text" class="form-control form-control-lg form-control-solid bg-secondary" disabled id="form_mask">
                                </div>
                                <div class="fv-row col-md-6">
                                    <label class="required fw-bold fs-6 mb-2 pl-0">Enable Test Mode</label>
                                    <select name="test_mode" class="form-control" id="form_test_mode">
                                        <option value="">Select</option>
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="fv-row col-md-6">
                                    <label class="required fw-bold fs-6 mb-2 pl-0">Custom Field 1</label>
                                    <input type="text" class="form-control form-control-lg form-control-solid bg-secondary" disabled id="form_string_1">
                                </div>
                                <div class="fv-row col-md-6">
                                    <label class="required fw-bold fs-6 mb-2 pl-0">Custom Field 2</label>
                                    <input type="text" class="form-control form-control-lg bg-secondary" disabled id="form_string_2">
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
</div>
<!--end::Modal dialog-->
</div>
