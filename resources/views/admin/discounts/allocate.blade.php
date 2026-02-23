<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Allocation <span id="allocate_discount_name" class="text-primary"></span> Discount</h2>
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
    <div class="modal-body scroll-y mx-3 my-5">
        <!--begin::Form-->
        <form id="modal_allocate_discounts_form" method="post" action="">
            <input type="hidden" name="id" id="discount_id">
            <input type="hidden" name="discount_type" id="discount_type_hidden">

            <div class="form-group">
                <!-- Info message for configurable discounts -->
                <div class="row mb-4 configurable-info-row" style="display: none;">
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <i class="la la-info-circle"></i> 
                            <strong>Configurable Discount:</strong> Services and discount amounts are already defined in this discount. You only need to select which centre(s) this discount applies to.
                        </div>
                    </div>
                </div>

                <!-- First row: Centre and Service -->
                <div class="row mb-4">
                    <div class="fv-row col-md-6">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Centre <span class="text text-danger">*</span></label>
                        <select id="locations" onchange="getDesrvice($(this));" class="form-control form-control-solid select2" name="location_id">
                            <option value="">Select Centre</option>
                        </select>
                    </div>

                    <div class="fv-row col-md-6 service-field-row">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Service <span class="text text-danger">*</span></label>
                        <select id="services" class="form-control form-control-solid select2" name="service_id[]" multiple="multiple">
                        </select>
                    </div>

                    <!-- Add button for configurable discounts (shown inline with centre) -->
                    <div class="fv-row col-md-6 configurable-add-btn" style="display: none;">
                        <label class="required fw-bold fs-6 mb-2 pl-0" style="visibility:hidden;">Add</label>
                        <button type="submit" class="btn btn-primary spinner-button" style="height: 38px; width: 100%;">
                            <i class="la la-plus"></i> Allocate to Centre
                        </button>
                    </div>
                </div>

                <!-- Second row: Type, Amount, Slug and Add button (hidden for configurable) -->
                <div class="row align-items-end regular-allocation-row">
                    <div class="fv-row col-md-3">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Discount Type <span class="text text-danger">*</span></label>
                        <select id="allocation_type" class="form-control form-control-solid select2" name="allocation_type">
                            <option value="">Select Type</option>
                            <option value="Fixed">Amount</option>
                            <option value="Percentage">Percentage</option>
                        </select>
                    </div>

                    <div class="fv-row col-md-3">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Amount <span class="text text-danger">*</span></label>
                        <input type="number" min="0" step="0.01" id="allocation_amount" class="form-control" name="allocation_amount" placeholder="Amount">
                    </div>

                    <div class="fv-row col-md-3">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Group <span class="text text-danger" style="visibility:hidden;">*</span></label>
                        <select id="allocation_slug" class="form-control form-control-solid select2" name="allocation_slug">
                            <option value="default">Fixed</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>

                    <div class="fv-row col-md-3">
                        <label class="required fw-bold fs-6 mb-2 pl-0" style="visibility:hidden;">Add <span class="text text-danger">*</span></label>
                        <button type="submit" class="btn btn-primary btn-sm spinner-button" style="height: 38px;">
                            <i class="la la-plus"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <hr class="my-4">

        <div class="table-responsive">
            <table id="allocate_services" class="table table-striped table-bordered table-advance table-hover">
                <thead>
                <tr>
                    <th>Location</th>
                    <th>Service</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Slug</th>
                    <th>Action</th>
                </tr>
                </thead>
            </table>
        </div>

        <!--end::Form-->
    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->



