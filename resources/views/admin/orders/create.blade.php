<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_add_user_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Create Order</h2>
        <!--end::Modal title-->
        <!--begin::Close-->
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" data-kt-users-modal-action="close"
            onclick="formRest()">
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
    <div class="modal-body scroll-y mx-5 mx-xl-6 my-7">
        <!--begin::Form-->
        <form id="modal_create_order_form" method="post" action="{{ route('admin.orders.store') }}">
            <!--begin::Scroll-->
            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_add_user_scroll" data-kt-scroll="true"
                data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto"
                data-kt-scroll-dependencies="#kt_modal_add_user_header"
                data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">
                <div class="form-group">
                    <input type="hidden" id="add_price">
                    <input type="hidden" id="add_product_type" name="product_type">
                    <input type="hidden" id="add_order_location_type" name="location_type">
                    <input type="hidden" id="total_products">
                    <input type="hidden" id="grand_total" name="grand_total">
                    <input type="hidden" id="discount" name="discount">
                    <div class="row mt-2">
                        <div class="fv-row col-md-12">
                            <label class="fw-bold fs-6 mb-2 pl-0">Location <span
                                    class="text text-danger">*</span></label>
                            <select class="form-control select2" name="location_id" id="add_order_location"
                                onchange="productSearch(this.value, 'add', 'order')">
                            </select>
                        </div>
                    </div>
                    <div class="row mt-2">
                    <div class="fv-row col-md-12">
                        <label class="fw-bold fs-6 mb-2 pl-0">Sold To <span
                            class="text text-danger">*</span></label>
                        <select class="form-control" id="sold_to" name="sold_to" onchange="SelectEmployee()">
                            <option value="patient">Patient</option>
                            <option value="employee">Employee</option>
                           
                        </select>
                    </div>
                    </div>
                    <div class="row mt-2" id="patientDropDown">
                        <div class="fv-row col-md-12">
                            <label class="fw-bold fs-6 mb-2 pl-0">Patient Search <span
                                    class="text text-danger">*</span></label>
                            <input class="form-control user_search patient_search_id search_field"
                                placeholder="Patients Search" required>

                            <input type="hidden" id="create_order_patient_search" name="patient_id"
                                class="filter-field search_field">
                            <span onclick="addUsers()" class="croxcli"
                                style="position:absolute; padding-left: 0% !important; top:37px; right:20px;"><i
                                    class="fa fa-times" aria-hidden="true"></i></span>
                            <div class="suggesstion-box" style="display: none;">
                                <ul class="suggestion-list"></ul>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-2" id="walkinDiv">
                        <div class="fv-row col-md-6">
                            <label class="fw-bold fs-6 mb-2 pl-0">Name<span
                                    class="text text-danger">*</span></label>
                            <input class="form-control"
                                placeholder="Name" type="text" name="name">

                            
                        </div>
                        <div class="fv-row col-md-6">
                            <label class="fw-bold fs-6 mb-2 pl-0">Phone<span
                                    class="text text-danger">*</span></label>
                            <input class="form-control"
                                placeholder="Phone" type="text" name="phone">

                            
                        </div>
                    </div>
                    <div class="row mt-2" style="display: none;" id="employeeDropDown">
                        <div class="fv-row col-md-12">
                            <label class="fw-bold fs-6 mb-2 pl-0">Employee <span
                                    class="text text-danger">*</span></label>
                            <select class="form-control select2" name="employee_id" id="add_employee_id">
                            </select>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="fv-row col-10">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Product</label>
                            <select id="add_order_product" class="form-control form-control-solid mb-3 mb-lg-0 select2"
                                onchange="productSelect(this.value, 'add')">
                            </select>
                        </div>

                        <div class="fv-row col-2">
                            <button class="btn btn-primary btn-block mt-8" type="button" onclick="addRow()"
                                id="add_service_btn"><i class="la la-plus"></i>
                                Add
                            </button>
                        </div>
                    </div>
                    <div class="row mt-2" id="prescribedBy">
                        <div class="fv-row col-md-12">
                            <label class="fw-bold fs-6 mb-2 pl-0">Prescribed By <span
                                    class="text text-danger">*</span></label>
                            <select class="form-control select2" name="doctor_id" id="add_doctor_ids">
                            </select>
                        </div>
                    </div>
                    <div class="row mt-5">
                        <div class="fv-row col-md-12">
                            <table class="table table-bordered order_list_table">
                                <thead class="text-left">
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Sale Price</th>
                                        <th>Quantity</th>
                                        <th>Discount</th>
                                        <th>SubTotal</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="product_list" class="text-left">
                                </tbody>
                                <tfoot id="footHtml">
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td id="product_discount" class="discount"></td>
                                        <td id="total_product_price"><strong>0</strong></td>
                                        
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                    </div>

                    <div class="row mt-2">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Payment Mode <span
                                    class="text text-danger">*</span></label>
                            <select id="create_payment_mode"
                                class="form-control form-control-solid mb-3 mb-lg-0 select2 select2-hidden-accessible"
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
                <button type="submit" class="btn btn-primary spinner-button" data-kt-users-modal-action="submit" onclick="orderSubmit()">
                    <span class="indicator-label">Place Order</span>
                </button>
            </div>
            <!--end::Actions-->
        </form>
        <!--end::Form-->
    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->
