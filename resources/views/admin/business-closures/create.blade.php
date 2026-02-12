<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title">Add Business Closed Period</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <i aria-hidden="true" class="ki ki-close"></i>
        </button>
    </div>
    <form id="form_add_business_closure" class="form" novalidate="novalidate">
        @csrf
        <div class="modal-body pt-5">
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group">
                        <label>Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_title" name="title" placeholder="Enter title" required />
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group">
                        <label>Locations <span class="text-danger">*</span></label>
                        <select class="form-control select2" id="add_location_ids" name="location_ids[]" multiple="multiple" data-placeholder="Select Locations" required>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Start Date <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_start_date" name="start_date" placeholder="Select start date" readonly required />
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>End Date <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_end_date" name="end_date" placeholder="Select end date" readonly required />
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer justify-content-center">
            <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary font-weight-bold" id="btn_add_business_closure">
                <span class="indicator-label">Submit</span>
                <span class="indicator-progress" style="display: none;">Please wait...
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                </span>
            </button>
        </div>
    </form>
</div>
