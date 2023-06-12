<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Add Lead</h2>
        <!--end::Modal title-->
        <!--begin::Close-->
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" data-kt-users-modal-action="close" onclick="cencleLead($(this))">
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
        <form id="modal_add_leads_form" method="post" action="{{route('admin.leads.store')}}">


            <input type="hidden" class="form_type" value="add_">

            <input type="hidden" name="lead_id" id="add_lead_id" value="">
            <input type="hidden" name="id" id="add_lead_id" value="">
            <input type="hidden" id="add_old_phone" name="old_phone">

            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_user_type_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">
                        <div class="fv-row col-md-12 mt-5" id="lead_id">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Lead Search </label>
                            <input class="form-control lead_search_id">

                            <input type="hidden" onchange="getLeadDetail($(this))" name="lead_id" class="filter-field search_field" id="create_lead_search">
                            <span onclick="addLeads()" class="croxcli" style="position:absolute; padding-left: 0% !important; top:37px; right:20px;"><i class="fa fa-times" aria-hidden="true"></i></span>
                            <div class="suggesstion-box" style="display: none;">
                                <ul class="suggestion-list"></ul>
                            </div>
                        </div>


                        <div class="fv-row col-md-12 mt-10">
                            <label class="custom_checkbox">
                                <input class="new_lead" name="new_lead" onclick="newLead();" type="checkbox" >
                               
                                <strong></strong>
                               <span class="ml-5"> New Lead ?</span>
                            </label>
                        </div>
                        <div class="fv-row col-md-12 mt-5">
                            <h2 class="text-center text text-danger msg_new_lead" style="display: none;">You are going to create new lead</h2>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Services <span class="text text-danger">*</span> </label>
                            <select id="add_service_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="service_id" onchange="loadChildServices()">
                            </select>
                        </div>
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Treatment </label>
                            <select id="add_child_service_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="child_service_id">
                            </select>
                        </div>
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Phone <span class="text text-danger">*</span></label>

                            <input type="text" oninput="phoneField(this);" id="add_phone" name="phone" autocomplete="off" class="form-control" placeholder="Enter Phone" />
                            {{--<div class="suggesstion-box" style="display: none;">
                                <ul class="suggestion-list"></ul>
                            </div>--}}

                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Full Name <span class="text text-danger">*</span></label>
                            <input type="text" id="add_full_name" name="name" class="form-control">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Gender <span class="text text-danger">*</span></label>
                            <select id="add_gender_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="gender">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">City <span class="text text-danger">*</span></label>
                            <select id="add_city_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="city_id" onchange="loadLocation()">
                            </select>
                        </div>
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Centre <span class="text text-danger">*</span></label>
                            <select id="add_location_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="location_id">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Lead Source </label>
                            <select id="add_lead_source_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="lead_source_id">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Lead Status</label>
                            <select id="add_lead_status_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="lead_status_id">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Referred By</label>
                            <select id="add_referred_by_id" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="referred_by">
                            </select>
                        </div>

                    </div>
                </div>

            </div>
            <!--end::Scroll-->
            <!--begin::Actions-->
            <hr>
            <div class="text-center">
                <button type="reset" class="btn btn-light me-3 popup-close" data-kt-users-modal-action="cancel" onclick="cencleLead($(this))">Cancel</button>
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

