<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_edit_configurable_discount_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Edit Configurable Discount (Buy X Get Y)</h2>
        <!--end::Modal title-->
        <!--begin::Close-->
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" data-kt-users-modal-action="close">
            <span class="svg-icon svg-icon-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/>
                    <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/>
                </svg>
            </span>
        </div>
        <!--end::Close-->
    </div>
    <!--end::Modal header-->
    <!--begin::Modal body-->
    <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
        <!--begin::Form-->
        <form id="modal_edit_configurable_discount_form" method="post" action="">
            <input type="hidden" name="_method" value="PUT">
            <input type="hidden" name="type" value="Configurable">
            <input type="hidden" name="slug" value="default">
            <input type="hidden" name="discount_type" value="Treatment">
            <input type="hidden" name="amount" value="0">
            <!--begin::Scroll-->
            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_edit_configurable_discount_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_edit_configurable_discount_header" data-kt-scroll-wrappers="#kt_modal_edit_configurable_discount_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">
                        <!-- Discount Name -->
                        <div class="fv-row col-md-12 mt-3">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Discount Name <span class="text text-danger">*</span></label>
                            <input type="text" id="edit_conf_discount_name" name="name" class="form-control form-control-lg form-control-solid mb-2" placeholder="e.g., Buy 3 Laser Get 1 Facial Free">
                        </div>

                        <!-- BUY Section -->
                        <div class="col-md-12 mt-5">
                            <div class="card card-bordered">
                                <div class="card-header bg-light-primary">
                                    <h4 class="card-title mb-0"><i class="la la-shopping-cart mr-2"></i>BUY (Customer Pays For)</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-3">
                                            <label class="fw-bold fs-6 mb-2">Sessions <span class="text text-danger">*</span></label>
                                            <select class="form-control form-control-solid" name="edit_sessions_buy" id="edit_conf_sessions_buy">
                                                <option value="">Select</option>
                                                <option value="1">1</option>
                                                <option value="2">2</option>
                                                <option value="3">3</option>
                                                <option value="4">4</option>
                                                <option value="5">5</option>
                                                <option value="6">6</option>
                                                <option value="7">7</option>
                                                <option value="8">8</option>
                                                <option value="9">9</option>
                                                <option value="10">10</option>
                                            </select>
                                        </div>
                                        <div class="col-md-1 text-center pt-8">
                                            <span class="fw-bold">of</span>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="fw-bold fs-6 mb-2">Service <span class="text text-danger">*</span></label>
                                            <select class="form-control form-control-solid select2" name="edit_base_service" id="edit_conf_base_service">
                                                <option value="">Select Service</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- GET Section -->
                        <div class="col-md-12 mt-4">
                            <div class="card card-bordered">
                                <div class="card-header bg-light-success">
                                    <h4 class="card-title mb-0"><i class="la la-gift mr-2"></i>GET (Customer Receives)</h4>
                                </div>
                                <div class="card-body" id="edit_conf_get_services_container">
                                    <!-- Dynamic rows will be populated here -->
                                </div>
                            </div>
                        </div>

                        <!-- Validity Period -->
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Valid From <span class="text text-danger">*</span></label>
                            <input type="text" id="edit_conf_start" name="start" readonly class="current-datepicker form-control form-control-lg form-control-solid mb-2">
                        </div>
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Valid To <span class="text text-danger">*</span></label>
                            <input type="text" id="edit_conf_end" name="end" readonly class="current-datepicker form-control form-control-lg form-control-solid mb-2">
                        </div>

                        <!-- Active Status -->
                        <div class="fv-row col-md-12 mt-5">
                            <span class="switch switch-icon">
                                <label for="edit_conf_active" class="fw-bold fs-6">
                                    <input id="edit_conf_active" value="1" type="checkbox" name="active">
                                    <span></span>
                                </label>
                                <span class="fs-6 pl-2">Active</span>
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
                <button type="submit" class="btn btn-primary spinner-button" data-kt-users-modal-action="submit">
                    <span class="indicator-label">Update Discount</span>
                </button>
            </div>
            <!--end::Actions-->
        </form>
        <!--end::Form-->
    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->
