<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Convert</h2>
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
        <form id="modal_convert_form" method="post" action="{{route('admin.appointments.store')}}">
            <!--begin::Scroll-->

            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_user_type_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <input type="hidden" id="appointment_type" name="appointment_type" value="consulting">
                <input type="hidden" id="convert_lead_id" name="lead_id">
                <input type="hidden" id="convert_patient_id" name="patient_id">
                <input type="hidden" id="convert_patient_phone" name="phone">
                <input type="hidden" id="convert_patient_name" name="name">
                <input type="hidden" id="convert_patient_cnic" name="cnic">
                <input type="hidden" id="convert_patient_email" name="email">
                <input type="hidden" id="convert_patient_dob" name="dob">
                <input type="hidden" id="convert_patient_address" name="address">
                <input type="hidden" id="convert_lead_source_id" name="lead_source_id">
                <input type="hidden" id="convert_referred_by" name="referred_by">

                <div class="form-group">
                    <div class="row">

                        <input type="hidden" id="lead_id">

                        <div class="fv-row col-md-12 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0"> City <span class="text text-danger">*</span></label>
                            <select id="convert_city" name="city_id" onchange="loadLocations($(this).val());" class="form-control form-control-solid mb-3 mb-lg-0 select2">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0"> Location <span class="text text-danger">*</span></label>
                            <select id="convert_location_id" name="location_id" onchange="loadDoctors($(this).val());" class="form-control form-control-solid mb-3 mb-lg-0 select2">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0"> Doctor <span class="text text-danger">*</span></label>
                            <select id="convert_doctor_id" name="doctor_id" class="form-control form-control-solid mb-3 mb-lg-0 select2">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0"> Treatment <span class="text text-danger">*</span></label>
                            <select id="convert_treatment_id" name="service_id" class="form-control form-control-solid mb-3 mb-lg-0 select2">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0"> Consultancy Type <span class="text text-danger">*</span></label>
                            <select id="convert_consultancy_type_id" name="consultancy_type_id" class="form-control form-control-solid mb-3 mb-lg-0 select2">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Date </label>
                            <input type="text" name="scheduled_date" value="{{date('Y-m-d')}}" id="schedule_date" class="form-control scheduled_date form-control-solid mb-3 mb-lg-0">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Time </label>
                            <input id="schedule_time" name="scheduled_time" value="{{date('h:i A')}}" class="form-control scheduled_time">
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



