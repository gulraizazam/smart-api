<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder rota-title">Refund</h2>
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

    <!--begin::Modal body-->
    <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
        <!--begin::Form-->
        <form id="modal_refund_refunds_form" method="post" action="{{route('admin.refundpatient.store')}}">
            <!--begin::Scroll-->
            @csrf

            <input type="hidden" name="package_id" id="package_id" value="" class="form-control">
            <input type="hidden" id="is_adjustment_amount" name="is_adjustment_amount" value="" class="form-control">
            <input type="hidden" id="return_tax_amount" name="return_tax_amount" value="" class="form-control">
            <input type="hidden" name="date_backend" id="date_backend" value="" class="form-control">


            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_resources_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">

                        <div class="fv-row col-md-12 mt-5">
                            <label for="refund_note" class="required fw-bold fs-6 mb-2 pl-0">Refund Note <span class="text text-danger">*</span></label>
                            <textarea id="refund_note" class="form-control" name="refund_note" rows="5" placeholder="Enter Reason Here"></textarea>
                        </div>

                        <div class="fv-row col-md-12 mt-5">
                            <label id="document-label" for="refund_note" class="required fw-bold fs-6 mb-2 pl-0">Documentation Charges</label>
                            <input type="text" readonly="readonly" id="documentationcharges" class="form-control disable-filed" name="documentationcharges">
                        </div>

                        <div class="fv-row col-md-12 mt-5">
                            <label for="refund_note" class="required fw-bold fs-6 mb-2 pl-0">Balance</label>
                            <input readonly="readonly" type="text" id="balance" class="form-control disable-filed" name="balance">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label for="refund_note" class="required fw-bold fs-6 mb-2 pl-0">Refund Amount</label>
                            <input type="number" id="refund_amount" class="form-control" name="refund_amount">
                        </div>

                        <div class="fv-row col-md-6 mt-5 input-daterange to-from-datepicker">
                            <label for="refund_note" class="required fw-bold fs-6 mb-2 pl-0">Date <span class="text text-danger">*</span></label>
                            <input type="text" id="created_at" class="form-control datatable-input" name="created_at">
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
