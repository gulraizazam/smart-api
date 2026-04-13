<div class="modal fade" id="modal_review_leave" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered form-popup">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder"><span id="review_action_label">Review</span> Leave Application</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-dismiss="modal">
                    <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="form_review_leave">
                    <input type="hidden" id="review_action_url" value="">
                    <div class="form-group">
                        <div class="fv-row">
                            <label class="fw-bold fs-6 mb-2">Notes (optional)</label>
                            <textarea id="review_notes" name="review_notes" class="form-control form-control-solid" rows="3" placeholder="Add notes..."></textarea>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <button type="reset" class="btn btn-light me-3" data-dismiss="modal">Cancel</button>
                        <button type="submit" id="review_submit_btn" class="btn btn-success">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
