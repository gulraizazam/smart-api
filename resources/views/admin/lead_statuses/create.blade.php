<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_add_user_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Add Lead Status</h2>
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
        <form id="modal_add_lead_statuses_form" method="post" action="{{route('admin.lead_statuses.store')}}">
            <!--begin::Scroll-->
            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_add_user_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">
                <div class="form-group">
                    <div class="row">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Name</label>
                            <input type="text" id="add_lead_statuses_name" name="name" class="form-control form-control-lg form-control-solid mb-2">
                        </div>
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Parent</label>
                            <select name="parent_id" id="add_lead_statuses_parent_id" class="form-control select2">
                                <option value="">Choose Parent</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-5">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Default for Open Leads</label>
                            <div class="radio-inline">
                                <label class="radio"><input name="is_default" value="1" type="radio"/><span></span>Yes</label>
                                <label class="radio"><input name="is_default" value="0" checked type="radio"><span></span>No</label>
                            </div>
                        </div>
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Default for Booked Leads</label>
                            <div class="radio-inline">
                                <label class="radio"><input name="is_booked" value="1" type="radio"/><span></span>Yes</label>
                                <label class="radio"><input name="is_booked" value="0" checked type="radio"><span></span>No</label>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-5">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Default for Arrived Leads</label>
                            <div class="radio-inline">
                                <label class="radio"><input name="is_arrived" value="1" type="radio"/><span></span>Yes</label>
                                <label class="radio"><input name="is_arrived" value="0" checked type="radio"><span></span>No</label>
                            </div>
                        </div>
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Default for Converted Leads</label>
                            <div class="radio-inline">
                                <label class="radio"><input name="is_converted" value="1" type="radio"/><span></span>Yes</label>
                                <label class="radio"><input name="is_converted" value="0" checked type="radio"><span></span>No</label>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-5">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Default for Junk Leads</label>
                            <div class="radio-inline">
                                <label class="radio"><input name="is_junk" value="1" type="radio"/><span></span>Yes</label>
                                <label class="radio"><input name="is_junk" value="0" checked type="radio"><span></span>No</label>
                            </div>
                        </div>
                        <div class="fv-row col-md-6">
                            <div class="checkbox-inline mt-8">
                                <label class="checkbox"><input id="add_lead_statuses_is_comment" name="is_comment" value="1" type="checkbox"/><span></span>Ask for Comments</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Scroll-->
            <!--begin::Actions-->
            <hr>
            <div class="text-center">
                <button type="reset" class="btn btn-light me-3 popup-close" data-kt-users-modal-action="cancel">Cancel</button>
                <button type="submit" class="btn btn-primary spinner-button" data-kt-users-modal-action="submit">
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
