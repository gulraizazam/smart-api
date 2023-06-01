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

            <input type="hidden" class="form_type" value="edit_">
            <input type="hidden" name="id" id="edit_lead_id" value="">
            <input type="hidden" name="old_service" id="edit_old_service" value="">

            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_user_type_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <table class="table">
                        <thead>
                          <tr>
                            <th>Services</th>
                            <th>Treatment</th>
                            <th>Edit</th>
                          </tr>
                        </thead>
                        <tbody id="service_list_table">

                        </tbody>
                      </table>
                    <div class="row">
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Services </label>
                            <select name="service_id" id="edit_service_id" class="form-control select2 select2-hidden-accessible" data-select2-id="edit_service_id" tabindex="-1" aria-hidden="true" onchange="loadEditChildServices()">
                            </select>
                        </div>
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Treatment </label>
                            <select name="child_service_id[]" multiple="" id="edit_child_service_id" class="form-control select2 select2-hidden-accessible" data-select2-id="edit_child_service_id" tabindex="-1" aria-hidden="true">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Phone <span class="text text-danger">*</span></label>
                            <input type="text" oninput="phoneField(this);" id="edit_phone" name="phone" autocomplete="off" class="form-control search-phone" placeholder="Enter Phone" />
                            <input type="hidden" id="edit_old_phone" name="old_phone">

                            <div class="suggesstion-box">
                                <ul class="suggestion-list"></ul>
                            </div>

                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Full Name <span class="text text-danger">*</span></label>
                            <input type="text" id="edit_full_name" name="name" class="form-control">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Gender <span class="text text-danger">*</span></label>
                            <select id="edit_gender_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="gender">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">City <span class="text text-danger">*</span></label>
                            <select id="edit_city_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="city_id" onchange="loadEditLocation()">
                            </select>
                        </div>
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Centre <span class="text text-danger">*</span></label>
                            <select id="edit_location_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="location_id">
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



