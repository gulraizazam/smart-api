<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_edit_user_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Edit</h2>
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
        <form id="plane_edit_form" method="post" action="{{route('admin.packages.edit_cash.store')}}">

        @method('put')

            <input type="hidden" id="edit_package_advances_id" name="package_advances_id">
            <input type="hidden" id="edit_package_id" name="package_id">

        <!--begin::Scroll-->
            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_edit_user_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">
                        
                        <div class="fv-row col-md-6 append_payment_mode">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Payment Mode <span class="text text-danger">*</span></label>
                            @if(Gate::allows('plans_cash_edit_payment_mode'))
                            <select id="plane_cash_payment_mode" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="payment_mode_id">

                            </select>
                            
                            @endif
                        </div>
                       
                        
                        <div class="fv-row col-md-6 append_cash_amount">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Amount <span class="text text-danger">*</span></label>
                            @if(Gate::allows('plans_cash_edit_amount'))
                                <input oninput="phoneField(this)" name="cash_amount" type="text" id="plane_cash_amount" class="form-control">
                            
                            @endif
                        </div>
                       
                        
                        <div class="fv-row col-md-6   append_cash_date">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Date <span class="text text-danger">*</span></label>
                            @if(Gate::allows('plans_cash_edit_date'))
                            <input type="text" id="plane_cash_date" name="created_at" class="form-control custom-datepicker">
                            
                            @endif
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
