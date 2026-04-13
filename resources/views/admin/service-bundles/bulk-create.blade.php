<div class="modal-content">
    <div class="modal-header">
        <h2 class="fw-bolder">Bulk Create Bundles</h2>
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" data-kt-users-modal-action="close">
            <span class="svg-icon svg-icon-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/>
                    <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/>
                </svg>
            </span>
        </div>
    </div>
    <div class="modal-body scroll-y mx-3 mx-xl-10 my-5">
        <form id="modal_bulk_bundles_form" method="post" action="{{route('admin.service-bundles.bulk_store')}}">
            <div class="form-group">
                <div class="row">
                    <div class="fv-row col-md-6">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Sessions <span class="text text-danger">*</span></label>
                        <input type="number" id="bulk_sessions" name="sessions" min="1" max="999"
                               class="form-control form-control-lg form-control-solid mb-2" placeholder="e.g. 6">
                    </div>
                    <div class="fv-row col-md-6">
                        <label class="required fw-bold fs-6 mb-2 pl-0">Discount % <span class="text text-danger">*</span></label>
                        <input type="number" id="bulk_discount" name="discount_percentage" min="0" max="100" step="0.1"
                               class="form-control form-control-lg form-control-solid mb-2" placeholder="e.g. 20">
                    </div>
                </div>

                <div class="row mt-5">
                    <div class="col-md-12">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <label class="required fw-bold fs-6 mb-0">Select Services</label>
                            <label class="form-check form-check-sm form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="bulk_select_all">
                                <span class="form-check-label fw-bold">Select All</span>
                            </label>
                        </div>
                        <div id="bulk_services_list" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                            <div class="text-muted text-center py-5">Loading services...</div>
                        </div>
                        <div class="text-muted mt-1 fs-7"><span id="bulk_selected_count">0</span> services selected</div>
                    </div>
                </div>

                <div class="row mt-5" id="bulk_preview_container" style="display:none;">
                    <div class="col-md-12">
                        <label class="fw-bold fs-6 mb-2">Preview</label>
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Unit Price</th>
                                    <th>Regular Total</th>
                                    <th>Bundle Price</th>
                                </tr>
                            </thead>
                            <tbody id="bulk_preview_body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <button type="button" class="btn btn-light me-3 popup-close">Cancel</button>
                <button type="button" class="btn btn-info mr-2" id="bulk-preview-btn">
                    <i class="la la-eye"></i> Preview
                </button>
                <button type="submit" class="btn btn-primary spinner-button" id="bulk-submit-btn">
                    <span class="indicator-label">Create All</span>
                </button>
            </div>
        </form>
    </div>
</div>
