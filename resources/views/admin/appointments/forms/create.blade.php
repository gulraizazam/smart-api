<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Create</h2>
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
        <form id="modal_edit_appointment_form" method="post" action="{{route('admin.appointments.store')}}">

            @method('put')

            <input type="hidden" id="consultancy_lead_id" name="lead_id">
            <input type="hidden" id="consultancy_patient_id" name="patient_id">
            <input type="hidden" id="consultancy_city_id" name="city_id">
            <input type="hidden" id="consultancy_location_id" name="location_id">
            <input type="hidden" id="consultancy_doctor_id" name="doctor_id">
            <input type="hidden" id="consultancy_start" name="start">
            <input type="hidden" id="consultancy_resource_id" name="resource_id">
            <input type="hidden" id="consultancy_appointment_type" name="appointment_type" value="consulting">
            <input type="hidden" id="consultancy_cnic" name="cnic">
            <input type="hidden" id="consultancy_email" name="email">
            <input type="hidden" id="consultancy_dob" name="dob">
            <input type="hidden" id="consultancy_address" name="address">
            <input type="hidden" id="consultancy_town_id" name="town_id">


            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_appointment_type_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">

                        <div class="fv-row col-md-12 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Consultancy Type <span class="text text-danger">*</span> </label>
                            <select id="create_consultancy_type" class="form-control select2" name="consultancy_type"></select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Treatment <span class="text text-danger">*</span> </label>
                            <select disabled readonly="" id="edit_treatment" class="form-control select2" name="treatment_id"></select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">City <span class="text text-danger">*</span> </label>
                            <select id="edit_city" onchange="loadLocations($(this).val());" class="form-control select2" name="city_id"></select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Location <span class="text text-danger">*</span> </label>
                            <select id="edit_location" onchange="loadDoctors($(this).val());" class="form-control select2" name="location_id"></select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Doctor <span class="text text-danger">*</span> </label>
                            <select id="edit_doctor" onchange="doctorListener($(this).val());" class="form-control select2" name="doctor_id"></select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Scheduled Date <span class="text text-danger">*</span></label>
                            <input type="text" id="edit_scheduled_date" name="scheduled_date" class="form-control custom-datepicker">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Scheduled Time <span class="text text-danger">*</span></label>
                            <input type="text" id="edit_scheduled_time" name="scheduled_time" class="form-control scheduled_time">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Patient Phone <span class="text text-danger">*</span> </label>
                            <input oninput="phoneField(this);" type="text" name="phone" id="edit_patient_phone" class="form-control">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Patient Name <span class="text text-danger">*</span> </label>
                            <input type="text" name="name" id="edit_patient_name" class="form-control">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Gender <span class="text text-danger">*</span></label>
                            <select id="edit_gender_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="gender">
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



