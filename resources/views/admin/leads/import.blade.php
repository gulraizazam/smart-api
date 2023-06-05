<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Import Lead</h2>
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
        <form id="modal_import_leads_form" method="post" action="{{route('admin.leads.upload')}}" enctype="multipart/form-data">
            <!--begin::Scroll-->
            @csrf

            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_user_type_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">
                <div class="form-group">
                    <div class="row">
                        <div class="fv-row col-md-12 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">File <span class="text text-danger">*</span></label>
                            <input type="file" id="file" name="leads_file" class="form-control leads_file">
                            <p class="help-block">To download sample file <a href="{{ asset('assets/files/SampleLeadsnew.xlsx') }}" target="_blank">click here</a> .</p>

                            <span class="text text-danger lead_file_msg d-none">Please choose a file first.</span>
                        </div>
                        <div class="fv-row col-md-12 mt-5">
                            <div class="mt-checkbox-inline">
                                <label class="custom_checkbox mt-5 update_records">
                                    <input onchange="skipStatus($(this));" type="checkbox" value="1" id="update_records" name="update_records">
                                    <strong></strong>
                                    <span class="ml-5">Update existing records</span>
                                </label>
                                <label class="custom_checkbox mt-5 skip_lead_status" style="opacity: 0.7">
                                    <input type="checkbox" disabled value="1" id="skip_lead_statuses" name="skip_lead_statuses" onchange="skipUpdateStatus($(this));">
                                    <strong></strong>
                                    <span class="ml-5">Skip Lead Statuses</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <!--end::Scroll-->
            <!--begin::Actions-->
            <hr>
            <div class="text-center">
                <button type="reset" class="btn btn-light me-3 popup-close" data-kt-users-modal-action="cancel" onclick="cencleImport($(this));">Cancel</button>
                <button type="button" onclick="importLead();" class="btn btn-primary spinner-button">
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



