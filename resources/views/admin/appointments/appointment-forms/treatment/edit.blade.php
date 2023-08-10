<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Edit</h2>
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
        <form id="modal_edit_treatment_form" method="post" action="{{route('admin.appointments.store_service')}}">

            @method('put')

            <input type="hidden" id="appointment_manager" value="{{Config::get('constants.appointment_type_service_string')}}">

            <input type="hidden" name="lead_id" id="treatment_leadId">
            <input type="hidden" id="treatment_appointment_id" >
            <input type="hidden" id="treatment_resourceRotaDayID" >
            <input type="hidden" id="treatment_machineRotaDayID" >
            <input type="hidden" id="treatment_start_time" >
            <input type="hidden" id="treatment_end_time"  >
            <input type="hidden" id="treatment_service_id"  name="treatment_service_id">

            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_appointment_type_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Treatment <span class="text text-danger">*</span> </label>
                            <select id="edit_treatment_service_id" disabled readonly class="form-control select2" name="service_id"></select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">City <span class="text text-danger">*</span> </label>
                            <select id="edit_treatment_city_id" onchange="loadEditTreatmentLocations($(this).val());" class="form-control select2" name="city_id"></select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Location <span class="text text-danger">*</span> </label>
                            <select id="edit_treatment_location_id" onchange="loadEditTreatmentDoctors($(this).val());" class="form-control select2" name="location_id"></select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Doctor <span class="text text-danger">*</span> </label>
                            <select id="edit_treatment_doctor_id" onchange="doctorListener($(this).val());" class="form-control select2" name="doctor_id"> </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Machine <span class="text text-danger">*</span> </label>
                            <select id="edit_treatment_machine_id" onchange="machineListener($(this).val());" class="form-control select2" name="machine_id"> </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="fw-bold fs-6 mb-2 pl-0">Scheduled Date <span class="text text-danger">*</span> </label>
                            <input readonly id="edit_treatment_scheduled_date" class="form-control current-datepicker" name="scheduled_date">

                            <input type="hidden" id="edit_treatment_scheduled_date_old">

                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Scheduled Time <span class="text text-danger">*</span> </label>
                            <input readonly id="edit_treatment_scheduled_time" class="form-control default-timepicker" name="scheduled_time">
                            <input type="hidden" id="scheduled_treatment_time_old">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Patient Phone <span class="text text-danger">*</span> </label>
                            <input readonly id="edit_treatment_patient_phone" class="form-control" name="phone" oninput="phoneField(this);">
                            <input id="edit_old_treatment_patient_phone" name="old_phone" type="hidden">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Patient Name <span class="text text-danger">*</span> </label>
                            <input readonly id="edit_treatment_patient_name" class="form-control" name="name">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Gender <span class="text text-danger">*</span> </label>
                            <select id="edit_treatment_patient_gender" class="form-control select2" name="gender"></select>
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



