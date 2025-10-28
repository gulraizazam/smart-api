<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Refund</h2>
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
        <form id="edit_refunds_form" method="post" action="">
            <!--begin::Scroll-->
            @csrf
            <input type="hidden" name="package_id" id="edit_package_id" class="form-control">
            <input type="hidden" id="edit_is_adjustment_amount" name="is_adjustment_amount" class="form-control">
            <input type="hidden" id="edit_return_tax_amount" name="return_tax_amount" class="form-control">
            <input type="hidden" name="date_backend" id="edit_date_backend"  class="form-control">
            <input type="hidden" name="record_id" id="record_id"  class="form-control">
            
            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_resources_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">
                    
                    <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Patients <span class="text text-danger">*</span></label>
                            <input type="text" readonly="readonly" class="form-control filter-field " id="patient_info">
                            <input type="hidden" class="filter-field search_field" id="edit_patients_id" >
                        </div>
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Plans <span class="text text-danger">*</span></label>
                            <input type="text" readonly="readonly" class="form-control filter-field " id="plan_id_1">
                            <input type="hidden" class="filter-field search_field" id="edit_plan_id_1" name="plan_id_1">
                        </div>
                      
                        <div class="fv-row col-md-6 mt-5">
                            <label for="refund_note" class="required fw-bold fs-6 mb-2 pl-0">Total Received Amount</label>
                            <input readonly="readonly" type="text" id="edit_received_amount" class="form-control disable-filed" name="received_amount">
                        </div>
                        <div class="fv-row col-md-6 mt-5">
                            <label for="refund_note" class="required fw-bold fs-6 mb-2 pl-0">Balance</label>
                            <input readonly="readonly" type="text" id="edit_balance" class="form-control disable-filed" name="balance">
                        </div>
                        <div class="fv-row col-md-6 mt-5">
                            <label for="refund_note" class="required fw-bold fs-6 mb-2 pl-0">Refund Amount <span class="text text-danger">*</span></label>
                            <input readonly="readonly" type="number" id="edit_refund_amount" class="form-control" name="refund_amount">
                        </div>
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Payment Mode <span class="text text-danger">*</span></label>
                            <select id="edit_refund_payment_mode_id" class="form-control form-control-solid mb-3 mb-lg-0 " name="payment_mode_id">
                                <option value="">Select Payment Mode</option>
                                
                            </select>
                        </div>
                      

                        <div class="fv-row col-md-6 mt-5 input-daterange to-from-datepicker">
                            <label for="refund_note" class="required fw-bold fs-6 mb-2 pl-0">Date <span class="text text-danger">*</span></label>
                            <input type="text" id="edit_created_at" class="form-control datatable-input" name="created_at">
                        </div>
                        <div class="fv-row col-md-6 mt-15 ">
                            <label for="refund_note" class="required fw-bold fs-6 mb-2 pl-0">Case Setteled </label>
                            <input type="hidden" name="case_setteled" value="0">
                            <input type="checkbox" id="edit_case_setteled" name="case_setteled" value="1">
                        </div>
                        <div class="fv-row col-md-12 mt-5">
                            <label for="refund_note" class="required fw-bold fs-6 mb-2 pl-0">Refund Note <span class="text text-danger">*</span></label>
                            <textarea id="edit_refund_note" class="form-control" name="refund_note" rows="5" placeholder="Enter Reason Here"></textarea>
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



