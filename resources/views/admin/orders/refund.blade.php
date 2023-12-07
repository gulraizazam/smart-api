<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_add_user_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Refund Order</h2>
        <!--end::Modal title-->
        <!--begin::Close-->
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" data-kt-users-modal-action="close">
            <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
            <span class="svg-icon svg-icon-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1"
                        transform="rotate(-45 6 17.3137)" fill="black" />
                    <rect x="7.41422" y="6" width="16" height="2" rx="1"
                        transform="rotate(45 7.41422 6)" fill="black" />
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
        <form id="modal_order_refund_form" method="post" action="">
            <!--begin::Scroll-->
            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_add_user_scroll" data-kt-scroll="true"
                data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto"
                data-kt-scroll-dependencies="#kt_modal_add_user_header"
                data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">
                <div class="form-group">
                    
                    <input type="hidden" id="refund_location_id" name="refund_location_id">
                    <div class="row mt-2">
                        <div class="fv-row col-md-12">
                            <label class="fw-bold fs-6 mb-2 pl-0">Location <span
                                    class="text text-danger">*</span></label>
                            <select class="form-control" name="location_id" id="refund_order_location">
                            </select>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="fv-row col-md-12">
                            <label class="fw-bold fs-6 mb-2 pl-0">Patient Search <span
                                    class="text text-danger">*</span></label>
                                    <input
                                class="form-control user_search patient_search_id search_field refund_order_patient_search_id"
                                placeholder="Patients Search" required>

                            <input type="hidden" id="refund_order_patient_search" name="patient_id"
                                class="filter-field search_field">
                            <span onclick="addUsers()" class="croxcli"
                                style="position:absolute; padding-left: 0% !important; top:37px; right:20px;"></span>
                            <div class="suggesstion-box" style="display: none;">
                                <ul class="suggestion-list"></ul>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-5">
                        <div class="fv-row col-md-12">
                            <table class="table table-bordered order_list_table">
                                <thead class="text-left">
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Sale Price</th>
                                        <th>Stock</th>
                                        <th>Quantity</th>
                                        <th>SubTotal</th>
                                    </tr>
                                </thead>
                                <tbody id="refund_product_list" class="text-left">
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        
                                        <td></td>
                                        <td id="refund_total_product_price"><strong>0</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                    </div>

                    <div class="row mt-2">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Payment Mode <span
                                    class="text text-danger">*</span></label>
                            <select id="refund_payment_mode" class="form-control form-control-solid mb-3 mb-lg-0 select2"
                                name="payment_mode">
                                <option value="">Select Payment Mode</option>
                                <option value="1">Cash</option>
                                <option value="2">Card</option>
                                <option value="3">Bank/Wire Transfer</option>
                            </select>
                        </div>
                    </div>



                </div>

            </div>
            <!--end::Scroll-->
            <!--begin::Actions-->
            <hr>
            <div class="text-center">
                <button type="reset" class="btn btn-light me-3 popup-close"
                    data-kt-users-modal-action="cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary spinner-button" data-kt-users-modal-action="submit"
                    onclick="orderSubmit()">
                    <span class="indicator-label">Refund Order</span>
                </button>
            </div>
            <!--end::Actions-->
        </form>
        <!--end::Form-->
    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->

