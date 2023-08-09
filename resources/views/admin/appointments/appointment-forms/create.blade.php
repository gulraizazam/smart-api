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
        <form id="modal_create_consultancy_form" method="post" action="{{route('admin.appointments.store')}}">

            <input type="hidden" id="consultancy_lead_id" name="lead_id">
            <input type="hidden" id="consultancy_city_id" name="city_id">
            <input type="hidden" id="consultancy_location_id" name="location_id">
            <input type="hidden" id="consultancy_doctor_id" name="doctor_id">
            <input type="hidden" id="consultancy_start" name="start">
            <input type="hidden" id="consultancy_resource_id" name="resource_id">
            <input type="hidden" id="consultancy_appointment_type" name="appointment_type" value="consulting">
            <input type="hidden" id="consultancy_town_id" name="town_id">

            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_appointment_type_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">

                        <div class="fv-row col-md-6 mt-5 consult-type">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Consultancy Type <span class="text text-danger">*</span> </label>
                            <select id="create_consultancy_types" class="form-control select2" name="consultancy_type"></select>
                        </div>

                        <div class="fv-row col-md-6 mt-5 consultancy-service">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Consultancy <span class="text text-danger">*</span> </label>
                            <select id="create_consultancy_service" class="form-control select2" name="service_id"></select>
                        </div>

                        <div class="fv-row col-md-12 mt-5" id="lead_id">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Lead Search </label>
                            <input class="form-control lead_search_id"  placeholder="Patients Search">

                            <input type="hidden" onchange="getLeadDetail($(this))"  name="lead_id" class="filter-field search_field" id="create_lead_search">
                            <span onclick="addLeads()" class="croxcli" style="position:absolute; padding-left: 0% !important; top:37px; right:20px;"><i class="fa fa-times" aria-hidden="true"></i></span>
                            <div class="suggesstion-box" style="display: none;">
                                <ul class="suggestion-list"></ul>
                            </div>
                        </div>
                        <div class="fv-row col-md-12 new_patient_text mt-10" style="display: none;">
                            <h3 style="color: red; text-align: center;">You are going to create new patient</h3>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Phone <span class="text text-danger">*</span> </label>
                            <input readonly id="create_consultancy_phone" class="form-control" name="phone" oninput="phoneField(this);">
                            <input type="hidden" id="create_old_consultancy_phone" class="form-control" name="old_phone">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">User Name <span class="text text-danger">*</span> </label>
                            <input id="create_patient_name" class="form-control" name="name">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Gender <span class="text text-danger">*</span></label>
                            <select id="create_consultancy_gender" class="form-control" name="gender"></select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Lead Source </label>
                            <select id="create_consultancy_lead" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="lead_source">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Referred By</label>
                            <select id="create_consultancy_referred_by" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="referred_by">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <span class="switch switch-icon mt-10">
                                <label>
                                    <input onclick="newPatient($(this))" id="new_patient" type="checkbox" value="1" name="new_patient">
                                    <span></span>
                                </label>
                                <span>&nbsp; I would like to</span><strong>&nbsp; Create New Patient</strong>
                            </span>
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



