<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_document_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Upload Document</h2>
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
        <form id="modal_add_documents_form" method="post" enctype="multipart/form-data">
            <!--begin::Scroll-->

            <input type="hidden" name="patient_id" id="patientId">

            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_resources_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">

                        <div class="fv-row col-md-12 mt-5">
                            <label class="fw-bold fs-6 mb-2 pl-0">Document Type <span class="text text-danger">*</span></label>
                            <select name="document_type" id="add_document_type" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="consent_form">Consent Form</option>
                                <option value="consultation_form">Consultation Form</option>
                                <option value="others">Others</option>
                            </select>
                        </div>

                        <div class="fv-row col-md-12 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0"> File <span class="text text-danger">*</span></label>
                            <div class="dropzone-container" id="document_dropzone">
                                <div class="dropzone-area" onclick="document.getElementById('document_file').click();">
                                    <input type="file" name="file" class="d-none" id="document_file" accept=".jpg,.jpeg,.png,.pdf,.docx,.xlsx">
                                    <div class="dropzone-content">
                                        <i class="la la-cloud-upload-alt" style="font-size: 48px; color: #3699FF;"></i>
                                        <p class="mb-1 mt-2">Drag & drop file here or <span class="text-primary" style="cursor: pointer;">browse</span></p>
                                        <p class="text-muted small mb-0">Supported: JPG, PNG, PDF, DOCX, XLSX (Max 10MB)</p>
                                    </div>
                                    <div class="dropzone-preview d-none">
                                        <i class="la la-file" id="preview_icon" style="font-size: 32px;"></i>
                                        <span id="preview_filename" class="ml-2"></span>
                                        <button type="button" class="btn btn-sm btn-icon btn-light-danger ml-2" onclick="event.stopPropagation(); clearFileInput();">
                                            <i class="la la-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <style>
                    .dropzone-area {
                        border: 2px dashed #E4E6EF;
                        border-radius: 8px;
                        padding: 30px;
                        text-align: center;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        background: #F9FAFB;
                    }
                    .dropzone-area:hover, .dropzone-area.dragover {
                        border-color: #3699FF;
                        background: #F1FAFF;
                    }
                    .dropzone-preview {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                </style>

            </div>
            <!--end::Scroll-->
            <!--begin::Actions-->
            <hr>
            <div class="text-center">
                <button type="reset" class="btn btn-light me-3 popup-close" data-kt-users-modal-action="cancel">Cancel</button>
                <button type="submit" class="btn btn-primary spinner-button" id="submit_document_btn">
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
