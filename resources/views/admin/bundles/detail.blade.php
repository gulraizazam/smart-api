<div class="modal-content">
    <div class="modal-header" style="display: flex; align-items: center; justify-content: space-between;">
        <h2 class="fw-bolder" style="font-size: 1.25rem; word-break: break-word; margin: 0;">Package Details</h2>
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" data-kt-users-modal-action="close">
            <span class="svg-icon svg-icon-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                    <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                </svg>
            </span>
        </div>
    </div>
    <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
        <div id="bundle_detail_content" style="min-height: 100px; padding: 10px; word-wrap: break-word;">
            <table class="table table-bordered table-sm">
                <tbody>
                <tr>
                    <th>Name</th>
                    <td id="detail_name"></td>
                    <th>Total Services</th>
                    <td id="detail_total_services"></td>
                </tr>
                <tr>
                    <th>Regular Price</th>
                    <td id="detail_services_price"></td>
                    <th>Package Price</th>
                    <td id="detail_price"></td>
                </tr>
                <tr>
                    <th>You Save</th>
                    <td id="detail_you_save" class="text-success" style="font-weight:600;"></td>
                    <th>Created At</th>
                    <td id="detail_created_at"></td>
                </tr>
                </tbody>
            </table>
            <table class="table table-bordered table-sm mt-3">
                <thead>
                <tr>
                    <th>Service</th>
                    <th>Price</th>
                </tr>
                </thead>
                <tbody id="detail-service-body">
                </tbody>
            </table>
        </div>
    </div>
    <div class="modal-footer" style="padding: 10px 20px;">
        <button type="button" class="btn btn-light popup-close">Close</button>
    </div>
</div>
