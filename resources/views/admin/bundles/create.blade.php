<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_add_user_header">
        <h2 class="fw-bolder" id="model-title">Add Package</h2>
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" data-kt-users-modal-action="close">
            <span class="svg-icon svg-icon-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/>
                    <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/>
                </svg>
            </span>
        </div>
    </div>
    <!--end::Modal header-->
    <!--begin::Modal body-->
    <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
        <form id="modal_bundles_form" method="post" action="{{route('admin.bundles.store')}}">
            <div id="put_input"></div>
            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_add_user_scroll" data-kt-scroll="true"
                 data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto"
                 data-kt-scroll-dependencies="#kt_modal_add_user_header"
                 data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">
                <div class="form-group">

                    {{-- Row 1: Name --}}
                    <div class="row">
                        <div class="fv-row col-md-12">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Package Name <span class="text text-danger">*</span></label>
                            <input type="text" id="bundles_name" name="name" class="form-control form-control-lg form-control-solid mb-2">
                        </div>
                    </div>

                    {{-- Row 2: Add Service or Bundle --}}
                    <div class="row mt-5">
                        <div class="fv-row col-md-5">
                            <label class="fw-bold fs-6 mb-2 pl-0">Add Service</label>
                            <select id="services" class="form-control form-control-lg select2 form-control-solid"></select>
                        </div>
                        <div class="fv-row col-md-1 d-flex align-items-end">
                            <button class="btn btn-primary btn-sm btn-block mb-2" style="width: 36px;" type="button" onclick="addRow()"><i class="la la-plus p-0"></i></button>
                        </div>
                        <div class="fv-row col-md-5">
                            <label class="fw-bold fs-6 mb-2 pl-0">Add Bundle</label>
                            <select id="bundles_dropdown" class="form-control form-control-lg select2 form-control-solid"></select>
                        </div>
                        <div class="fv-row col-md-1 d-flex align-items-end">
                            <button class="btn btn-info btn-sm btn-block mb-2" style="width: 36px;" type="button" onclick="addBundleRow()"><i class="la la-plus p-0"></i></button>
                        </div>
                    </div>

                    {{-- Row 3: Services table --}}
                    <div class="row mt-5">
                        <div class="fv-row col-md-12">
                            <table class="table table-bordered table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th>Service Name</th>
                                        <th style="width:120px;">Price</th>
                                        <th style="width:60px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="service_body"></tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Row 4: Regular Price + Total Services --}}
                    <div class="row mt-3">
                        <div class="fv-row col-md-4">
                            <label class="fw-bold fs-6 mb-2 pl-0">Regular Price</label>
                            <input type="number" id="service_price" readonly class="form-control form-control-solid mb-2" style="background:#f5f8fa;">
                        </div>
                        <div class="fv-row col-md-4">
                            <label class="fw-bold fs-6 mb-2 pl-0">Total Services</label>
                            <input type="number" id="total_services" readonly name="total_services" class="form-control form-control-solid mb-2" style="background:#f5f8fa;">
                        </div>
                        <div class="fv-row col-md-4">
                            <label class="fw-bold fs-6 mb-2 pl-0 text-success">You Save</label>
                            <input type="text" id="you_save_display" readonly class="form-control form-control-solid mb-2 text-success font-weight-bold" style="background:#f5f8fa;">
                        </div>
                    </div>

                    {{-- Row 5: Pricing mode --}}
                    <div class="row mt-3">
                        <div class="fv-row col-md-4">
                            <label class="fw-bold fs-6 mb-2 pl-0">Pricing</label>
                            <div class="radio-inline mt-1">
                                <label class="radio radio-primary">
                                    <input type="radio" name="pricing_mode" value="discount" checked/>
                                    <span></span>Discount %
                                </label>
                                <label class="radio radio-primary">
                                    <input type="radio" name="pricing_mode" value="net"/>
                                    <span></span>Net Price
                                </label>
                            </div>
                        </div>
                        <div class="fv-row col-md-4" id="discount_field">
                            <label class="fw-bold fs-6 mb-2 pl-0">Discount %</label>
                            <input type="number" id="bundles_discount" step="0.01" min="0" max="100" class="form-control form-control-lg form-control-solid mb-2" placeholder="e.g. 20">
                        </div>
                        <div class="fv-row col-md-4">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Package Price <span class="text text-danger">*</span></label>
                            <input type="number" id="bundles_price" name="price" step="0.01" min="0" class="form-control form-control-lg form-control-solid mb-2">
                        </div>
                    </div>

                    {{-- Row 6: Valid From / To --}}
                    <div class="row mt-5">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Valid From <span class="text text-danger">*</span></label>
                            <input type="text" id="start" name="start" readonly class="current-datepicker form-control form-control-lg form-control-solid mb-2">
                        </div>
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Valid To <span class="text text-danger">*</span></label>
                            <input type="text" id="end" name="end" readonly class="current-datepicker form-control form-control-lg form-control-solid mb-2">
                        </div>
                    </div>

                    {{-- Row 7: Apply Discounts + Tax --}}
                    <div class="row mt-5">
                        <div class="fv-row col-md-6">
                            <label class="fw-bold fs-6 mb-2 pl-0">Apply Discounts on this Package?</label>
                            <div class="checkbox-inline">
                                <label class="checkbox"><input id="add_bundles_is_comment" name="apply_discount" value="1" type="checkbox"/><span></span>Apply Discounts</label>
                            </div>
                        </div>
                        <div class="fv-row col-md-6 not-have-parent">
                            <label class="fw-bold fs-6 mb-2 pl-0">Tax</label>
                            <div class="radio-list" id="bundles_tax"></div>
                        </div>
                    </div>

                </div>
            </div>
            <hr>
            <div class="text-center">
                <button type="reset" class="btn btn-light me-3 popup-close" data-kt-users-modal-action="cancel">Cancel</button>
                <button type="submit" class="btn btn-primary spinner-button" data-kt-users-modal-action="submit">
                    <span class="indicator-label">Submit</span>
                </button>
            </div>
        </form>
    </div>
</div>
<!--end::Modal content-->
