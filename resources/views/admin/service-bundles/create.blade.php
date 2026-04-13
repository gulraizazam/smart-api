<div class="modal-content">
    <div class="modal-header" id="kt_modal_bundle_header">
        <h2 class="fw-bolder" id="bundle-model-title">Create Bundle</h2>
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" data-kt-users-modal-action="close">
            <span class="svg-icon svg-icon-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/>
                    <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/>
                </svg>
            </span>
        </div>
    </div>
    <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
        <form id="modal_service_bundles_form" method="post" action="{{route('admin.service-bundles.store')}}">
            <div id="put_input"></div>
            <div class="d-flex flex-column scroll-y me-n7 pe-7">
                <div class="form-group">
                    <div class="row">
                        <div class="fv-row col-md-12">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Service <span class="text text-danger">*</span></label>
                            <select id="bundle_service_id" name="service_id" class="form-control form-control-lg form-control-solid select2">
                                <option value="">Select Service</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-5">
                        <div class="fv-row col-md-4">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Sessions <span class="text text-danger">*</span></label>
                            <input type="number" id="bundle_sessions" name="sessions" min="1" max="999"
                                   class="form-control form-control-lg form-control-solid mb-2" placeholder="e.g. 6">
                        </div>
                        <div class="fv-row col-md-4">
                            <label class="fw-bold fs-6 mb-2 pl-0">Discount %</label>
                            <input type="number" id="bundle_discount" name="discount_percentage" min="0" max="100" step="0.1"
                                   class="form-control form-control-lg form-control-solid mb-2" placeholder="e.g. 20">
                        </div>
                        <div class="fv-row col-md-4">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Bundle Price <span class="text text-danger">*</span></label>
                            <input type="number" id="bundle_price" name="price" min="0" step="0.01"
                                   class="form-control form-control-lg form-control-solid mb-2">
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <div class="alert alert-light-info p-3 mb-0" id="bundle_price_info" style="display:none;">
                                <small>
                                    Unit price: <strong id="info_unit_price">-</strong> |
                                    Regular total: <strong id="info_regular_total">-</strong> |
                                    You save: <strong id="info_savings" class="text-success">-</strong>
                                </small>
                            </div>
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
