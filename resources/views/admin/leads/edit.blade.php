<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Edit Lead</h2>
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
        <form id="modal_edit_leads_form" method="post">
            <!--begin::Scroll-->
            @method('put')

            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_user_type_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Services <span class="text text-danger">*</span> </label>
                            <select id="edit_service_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="service_id">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Patient Search <span class="text text-danger">*</span></label>
                            <select disabled readonly id="edit_patient_id" class="form-control form-control-solid mb-3 mb-lg-0 patient_id" name="patient_id">
                            </select>
                        </div>

                        <div class="fv-row col-md-12 mt-10">
                            <label class="custom_checkbox">
                                <input class="new_patient" onclick="newPatient();" type="checkbox">
                                <strong></strong>
                               <span class="ml-5"> New Patient ?</span>
                            </label>
                        </div>

                        <div class="fv-row col-md-12 mt-5">
                            <h2 class="text-center text text-danger msg_new_patient" style="display: none;">You are going to create new patient</h2>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Phone <span class="text text-danger">*</span></label>
                           <input oninput="phoneField(this);" type="text" id="edit_phone" name="phone" class="form-control">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Full Name <span class="text text-danger">*</span></label>
                            <input type="text" id="edit_full_name" name="name" class="form-control">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">CNIC </label>
                            <input type="text" id="edit_cnic" name="cnic" class="form-control cnic-mask">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Email </label>
                            <input type="text" id="edit_email" name="email" class="form-control">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Date of Birth </label>
                            <input type="text" id="edit_dob" name="dob" class="form-control custom-datepicker">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Address </label>
                            <input type="text" id="edit_address" name="address" class="form-control">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Gender</label>
                            <select id="edit_gender_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="gender">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">City</label>
                            <select id="edit_city_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="city_id">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Town</label>
                            <select id="edit_town_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="town_id">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Lead Source</label>
                            <select id="edit_lead_source_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="lead_source_id">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Lead Status</label>
                            <select id="edit_lead_status_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="lead_status_id">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Referred By</label>
                            <select id="edit_referred_by_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="referred_by">
                            </select>
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



