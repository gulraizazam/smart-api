<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder rota-title">Display</h2>
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
    <div class="modal-body scroll-y mx-5 mx-xl-15">

        <!--begin::Form-->
        <div class="d-flex flex-column scroll-y me-n7 pe-7 mt-10" id="kt_modal_plans_scroll">

            <div class="form-group">

                <div class="row">
                    <div class="form-group col-md-4">
                        <label style="font-size: 14px;">Patient</label>
                        <strong style="font-size:18px;display: block;" id="user_name"></strong>
                    </div>
                    <div class="form-group col-md-4">
                        <label style="font-size: 14px;">Membership</label>
                        <strong style="font-size:18px;display: block;" id="membership_name"></strong>
                    </div>
                    <div class="form-group col-md-4">
                        <label style="font-size: 14px;">Centre</label>
                        <strong style="font-size:18px;display: block;" id="location_name"></strong>
                    </div>
                </div>
            </div>

            <div class="form-group">

                <div class="row">
                    <div class="table-responsive">
                        <table id="plans_service" class="table table-bordered table-advance">

                            <thead>
                                <tr>
                                    <th>Service Name</th>
                                    <th>Regular Price</th>
                                    <th>Discount Name</th>
                                    <th>Type</th>
                                    <th>Discount Value</th>
                                    <th>Subtotal</th>

                                    <th>Tax</th>
                                    <th>Total</th>
                                    <th>Sold By</th>
                                </tr>
                            </thead>

                            <tbody class="display_plans"></tbody>

                        </table>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-10 col-xs-8"></div>
                    <div class="col-md-2 col-xs-4 invoice-block">
                        <ul class="list-unstyled amounts">
                            <li>
                                <strong style="font-weight: bold;">Total:</strong> <span class="package_total_price"></span>/-
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="row">
                    <div class="table-responsive">
                        <h4>History</h4>
                        <table id="plan_history" class="table table-bordered table-advance">

                            <thead>
                                <tr>
                                    <th>Payment Mode</th>
                                    <th>Cash Flow</th>
                                    <th>Cash Amount</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>

                            <tbody class="plan_history">
                                <tr>
                                    <td id="payment_mode"></td>
                                    <td id="cash_flow"></td>
                                    <td id="cash_amount"></td>
                                    <td id="Created At"></td>
                                </tr>
                            </tbody>

                        </table>
                    </div>
                </div>

                <div class="row float-right">
                    <div class="col-md-12 col-sm-12 col-xs-12 mt-10">
                        <a id="package_pdf" class="btn btn-lg btn-primary blue hidden-print margin-bottom-5" target="_blank" href="">Print
                            <i class="fa fa-print"></i>
                        </a>
                    </div>
                </div>


            </div>

        </div>
        <!--end::Scroll-->
    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->



