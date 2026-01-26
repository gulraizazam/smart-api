<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">New Treatment with <span id="treatment_modal_doctor_name" style="color: #3699FF;"></span> - <span id="treatment_modal_date"></span></h2>
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
        <form id="modal_create_treatment_form" method="post" action="{{route('admin.treatments.store')}}">

            <input type="hidden" id="treatment_lead_id" name="lead_id">
            <input type="hidden" id="treatment_patient_id" value="0">
            <input type="hidden" id="treatment_city_id" name="city_id">
            <input type="hidden" id="treatment_location_id" name="location_id">
            <input type="hidden" id="treatment_doctor_id" name="doctor_id">
            <input type="hidden" id="treatment_start" name="start">
            <input type="hidden" id="treatment_resource_id" name="resource_id">
            <input type="hidden" id="treatment_appointment_type" name="appointment_type" value="treatment">
            <input type="hidden" id="treatment_cnic" name="cnic">
            <input type="hidden" id="treatment_email" name="email">
            <input type="hidden" id="treatment_dob" name="dob">
            <input type="hidden" id="treatment_address" name="address">
            <input type="hidden" id="treatment_town_id" name="town_id">


            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_appointment_type_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                {{-- Warning div for doctor mismatch --}}
                <div id="treatment_doctor_warning" class="alert alert-warning d-none" style="background-color: #FFEB3B; border-color: #FDD835; color: #000000;">
                    <div class="mb-3">
                        <strong>Attention:</strong> <span id="warning_message"></span>
                    </div>
                    <div class="form-check mb-2" style="display: flex; align-items: center;">
                        <input class="form-check-input" type="radio" id="use_previous_doctor" name="doctor_choice" value="previous" style="width: 20px; height: 20px; cursor: pointer; margin: 0; flex-shrink: 0;">
                        <label class="form-check-label" for="use_previous_doctor" style="cursor: pointer; margin-left: 10px; margin-bottom: 0;">
                            <span id="previous_doctor_option" style="margin-left:15px"></span>
                        </label>
                    </div>
                    <div class="form-check d-none" style="display: flex; align-items: center;">
                        <input class="form-check-input" type="radio" id="use_selected_doctor" name="doctor_choice" value="selected" style="width: 20px; height: 20px; cursor: pointer; margin: 0; flex-shrink: 0;">
                        <label class="form-check-label" for="use_selected_doctor" style="cursor: pointer; margin-left: 24px; margin-bottom: 0;">
                            Proceed with <span id="selected_doctor_option"></span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="row">

                        {{-- Hide base service dropdown - validation removed since it's auto-populated from service_id --}}
                        <input type="hidden" id="create_treatment_base_service" name="base_service_id">

                        <div class="fv-row col-md-12 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Patient Search <span class="text text-danger">*</span></label>
                            <select class="form-control select2-patient-search-treatment" id="create_treatment_patient_id" name="patient_id" onchange="getTreatmentPatientDetailFromSelect(this)">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Service <span class="text text-danger">*</span> </label>
                            <select id="create_treatment_service" class="form-control select2" name="service_id"></select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Scheduled Time <span class="text text-danger">*</span> </label>
                            <input type="text" id="create_treatment_scheduled_time" name="scheduled_time" class="form-control treatment-timepicker">
                        </div>

                        <input type="hidden" id="create_treatment_phone" name="phone">
                        <input type="hidden" id="create_old_treatment_phone" name="old_phone">
                        <input type="hidden" id="create_treatment_patient_name" name="name">
                        <input type="hidden" id="create_treatment_gender" name="gender">
                        <input type="hidden" id="create_treatment_c_id" name="client_id">

                        {{--<div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Lead Source</label>
                            <select id="create_treatment_lead" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="lead_source">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Referred By</label>
                            <select id="create_treatment_referred_by" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="referred_by">
                            </select>
                        </div>--}}

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
