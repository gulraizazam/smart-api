<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Update Appointment Status</h2>
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
    <div class="modal-body scroll-y ">
        <!--begin::Form-->
        <form id="modal_update_status_form" method="post" action="">

            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_update_status_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                @method('put')

                <input type="hidden" name="id" id="appointment_id">
                <input type="hidden" name="appointment_type_id" id="appointment_type_id">
                <input type="hidden" name="appointment_status_not_show" value="" id="appointment_status_not_show">
                <input type="hidden" name="cancellation_reason_other_reason" value="" id="cancellation_reason_other_reason">

                <div class="form-group">
                    <div class="row">

                        <div class="fv-row col-md-12 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Status </label>
                            <select type="text" onchange="loadChildStatuses($(this).val());" name="base_appointment_status_id" id="base_appointment_status_id" class="form-control form-control-solid mb-3 mb-lg-0 select2">
                            </select>
                        </div>

                        <div class="fv-row col-md-12 mt-5 appointment_status_id" id="appointment_status_id_section">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Child Status </label>
                            <select id="appointment_status_id" onchange="statusListener($(this).val());" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="appointment_status_id">
                            </select>
                        </div>

                        <div class="fv-row col-md-12 mt-5 reason" id="appointment_reason">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Comment</label>
                            <textarea id="reason" name="reason" rows="3" class="form-control mb-3 mb-lg-0" placeholder="Type your comment.."></textarea>
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
