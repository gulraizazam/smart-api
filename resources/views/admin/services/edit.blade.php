<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Edit Service</h2>
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
        <form id="modal_edit_services_form" method="post" action="">
            <!--begin::Scroll-->
            @csrf
            @method('put')

            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_user_type_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Parent Services <span class="text text-danger">*</span></label>
                            <select id="edit_parent_service" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="parent_id" onchange="getEditColor()">

                            </select>
                        </div>
                        
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Service Name <span class="text text-danger">*</span></label>
                            <input id="edit_service_name" type="text" name="name" class="form-control form-control-lg form-control-solid mb-2">
                        </div>

                        <div class="fv-row col-md-6 mt-5 " >
                            <label class="required fw-bold fs-6 mb-2 pl-0">Duration <span class="text text-danger">*</span></label>
                            <select id="edit_duration" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="duration">

                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Color <span class="text text-danger">*</span></label>
                            <input id="edit_color" class="form-control" type="color" name="color" value="#000" >
                        </div>

                        <div class="fv-row col-md-12 mt-5 "  >
                            <label class="required fw-bold fs-6 mb-2 pl-0">Price <span class="text text-danger">*</span></label>
                            <input id="edit_price" name="price" class="form-control" type="number">
                        </div>

                        <div class="fv-row col-md-12 mt-5">
                            <label class="fw-bold fs-6 mb-2 pl-0">Description</label>
                            <input id="edit_description" type="hidden" name="description">
                            <trix-editor input="edit_description"></trix-editor>
                        </div>

                        <div class="fv-row col-md-6 mt-5 " >
                            <label class="required fw-bold fs-6 mb-2 pl-0">End Node? <span class="text text-danger">*</span></label>
                            <label class="checkbox checkbox-single">
                                <input id="edit_end_node" name="end_node" value="1" type="checkbox">&nbsp;
                                <span></span>
                            </label>
                        </div>

                        <div class="fv-row col-md-6 mt-5" id="main_edit_complimentary" style="display:none">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Complimentary?</label>
                            <label class="checkbox checkbox-single">
                                <input id="edit_complimentory" name="complimentory" value="1" type="checkbox">&nbsp;<span></span>
                            </label>
                        </div>


                        <div class="fv-row col-md-12 mt-5 ">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Tax</label>
                            <div class="radio-inline tax-radios">
                                <label class="radio">
                                    <input type="radio" name="1">
                                    <span></span>
                                    Both
                                </label>

                                <label class="radio">
                                    <input type="radio" name="2">
                                    <span></span>
                                    Is exclusive
                                </label>

                                <label class="radio">
                                    <input type="radio" name="3">
                                    <span></span>
                                    Is Inclusive
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



