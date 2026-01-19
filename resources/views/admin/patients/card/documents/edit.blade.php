<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_document_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Edit Document</h2>
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
        <form id="modal_edit_documents_form" method="post" action="" enctype="multipart/form-data">
            <!--begin::Scroll-->

            <input type="hidden" name="patient_id" id="edit_patient_id">

            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_resources_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">

                        <div class="fv-row col-md-12 mt-5">
                            <label class="fw-bold fs-6 mb-2 pl-0">Document Type <span class="text text-danger">*</span></label>
                            <select name="document_type" id="edit_document_type" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="consent_form">Consent Form</option>
                                <option value="consultation_form">Consultation Form</option>
                                <option value="others">Others</option>
                            </select>
                        </div>

                        <div class="fv-row col-md-12 mt-5">
                            <label class="fw-bold fs-6 mb-2 pl-0">Current File</label>
                            <div id="current_file_preview" class="border rounded p-3 bg-light">
                                <div id="image_preview_container" class="text-center mb-2" style="display: none;">
                                    <a id="current_file_link_img" href="#" target="_blank">
                                        <img id="current_file_image" src="" alt="Document Preview" class="img-fluid rounded" style="max-height: 200px; max-width: 100%;">
                                    </a>
                                </div>
                                <a id="current_file_link" href="#" target="_blank" class="d-flex align-items-center text-primary">
                                    <i id="file_icon" class="la la-file-alt fs-2 me-2 mr-2"></i>
                                    <span id="current_file_name">No file</span>
                                </a>
                            </div>
                        </div>

                        <div class="fv-row col-md-12 mt-5">
                            <label class="fw-bold fs-6 mb-2 pl-0">Upload New File <span class="text-muted">(Optional - leave empty to keep current file)</span></label>
                            <div class="dropzone-container" id="edit_document_dropzone">
                                <div class="dropzone-area edit-dropzone" onclick="document.getElementById('edit_document_file').click();">
                                    <input type="file" name="file" class="d-none" id="edit_document_file" accept=".jpg,.jpeg,.png,.pdf,.docx,.xlsx">
                                    <div class="edit-dropzone-content">
                                        <i class="la la-cloud-upload-alt" style="font-size: 48px; color: #3699FF;"></i>
                                        <p class="mb-1 mt-2">Drag & drop file here or <span class="text-primary" style="cursor: pointer;">browse</span></p>
                                        <p class="text-muted small mb-0">Supported: JPG, PNG, PDF, DOCX, XLSX (Max 10MB)</p>
                                    </div>
                                    <div class="edit-dropzone-preview d-none">
                                        <i class="la la-file" id="edit_preview_icon" style="font-size: 32px;"></i>
                                        <span id="edit_preview_filename" class="ml-2"></span>
                                        <button type="button" class="btn btn-sm btn-icon btn-light-danger ml-2" onclick="event.stopPropagation(); clearEditFileInput();">
                                            <i class="la la-times"></i>
                                        </button>
                                    </div>
                                </div>
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
