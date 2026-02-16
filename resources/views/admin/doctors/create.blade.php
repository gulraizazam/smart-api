<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_add_user_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Add Doctor</h2>
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
        <form id="modal_add_user_form" method="post" action="{{route('admin.doctors.store')}}">
            <!--begin::Scroll-->
            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_add_user_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Name <span class="text text-danger">*</span></label>
                            <input type="text" id="add_user_name" name="name" class="form-control form-control-lg form-control-solid mb-2">
                        </div>

                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Email <span class="text text-danger">*</span></label>
                            <input type="email" id="add_user_email" name="email" class="form-control form-control-lg form-control-solid" />
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="row">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Phone <span class="text text-danger">*</span></label>
                            <input oninput="phoneField(this)" type="text" id="add_user_phone" name="phone" class="form-control form-control-lg form-control-solid mb-2" />
                        </div>

                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Gender <span class="text text-danger">*</span></label>
                            <select id="add_user_gender" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="gender">
                                <option value="">Select</option>
                                <option value="1">Male</option>
                                <option value="2">Female</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="row">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Commission <span class="text text-danger">*</span></label>
                            <div class="input-group">
                                <input id="add_user_commission" type="number" min="0" max="100" name="commission" class="form-control form-control-lg form-control-solid mb-2" style="padding-right: 5px;">
                                <div class="input-group-append popup-percentage">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>

                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Password <span class="text text-danger">*</span></label>
                            <input id="add_user_password" type="password" name="password" class="form-control form-control-lg form-control-solid mb-2" />
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="row">
                        <div class="fv-row col-md-12">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Roles <span class="text text-danger">*</span></label>
                            <select id="add_user_roles" class="form-control form-control-solid mb-3 mb-lg-0 select2" multiple="multiple" name="roles[]">

                            </select>
                        </div>
                    </div>
                    <div class="row mt-10" id="add_can_perform_consultation_row" style="display: none;">
                        <div class="fv-row col-md-12">
                            <label class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="can_perform_consultation" id="add_doctor_can_perform_consultation" value="1" />
                                <span class="form-check-label fw-bold fs-6">Can Perform Consultation</span>
                            </label>
                            <small class="text-muted d-block mt-2">Enable this if the doctor (aesthetic) can also perform consultations</small>
                        </div>
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
