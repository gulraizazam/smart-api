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

        <div class="form-group">

            <div class="row">

                <div class="col-md-6 col-sm-6 col-xs-12 invoice-logo-space">
                    <img src="" style="width: 50%;" class="img-responsive invoice-image" alt=""/>
                </div>

                <div class="col-md-6 col-sm-6 col-xs-12 invoice-logo-space text-right">
                    <h3>#<span id="invoice_info_id"></span> / <span id="invoice_info_created_at"></span></h3>
                </div>

            </div>
        </div>

        <div class="form-group mt-10">

            <div class="row">
                <div class="col-md-8 col-sm-8 col-xs-12 invoice-logo-space">
                    <h4>Client:</h4>
                    <ul class="list-unstyled">
                        <li>
                            <strong>Name:</strong> <span id="client_name"></span>
                        </li>
                        <li>
                            <strong>Patient ID:</strong> <span id="client_id"></span>
                        </li>
                        <li id="client_email_li">
                            <strong>Email:</strong> <span id="client_email"></span>
                        </li>
                    </ul>
                </div>

                <div class="col-md-4 col-sm-4 col-xs-12 invoice-logo-space">
                    <h4>Company:</h4>
                    <ul class="list-unstyled">
                        <li>
                            <strong>Name:</strong> <span id="company_name"></span>
                        </li>

                        <li>
                            <strong>Contact:</strong> <span id="contact_no"></span>
                        </li>
                        <li>
                            <strong>Email:</strong> <span id="company_email"></span>
                        </li>
                        <li>
                            <strong>Clinic Name:</strong> <span id="clinic_name"></span>
                        </li>
                        <li>
                            <strong>Clinic Contact:</strong> <span id="clinic_contact"></span>
                        </li>
                        <li>
                            <strong>Address:</strong> <span id="clinic_address"></span>
                        </li>
                        <li>
                            <strong>NTN:</strong> <span id="clinic_ntn"></span>
                        </li>
                        <li>
                            <strong>STN:</strong> <span id="clinic_stn"></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!--begin::Form-->
        <div class="d-flex flex-column scroll-y me-n7 pe-7 mt-10" id="kt_modal_resourcerotas_scroll">

            <div class="form-group">

                <div class="row">


                    <div class="table-responsive">
                        <table id="allocate_services" class="table table-bordered table-advance">

                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Consultancy\Service</th>
                                <th>Service Price</th>
                                <th>Discount Name</th>
                                
                                <th>Discount Price</th>
                                <th>Subtotal</th>
                                
                                <th>Tax</th>
                                <th>Total</th>
                            </tr>
                            </thead>

                            <tbody>
                                <tr>
                                <td>1</td>
                                <td id="service_name"></td>
                                <td id="service_price"></td>
                                <td id="discount_name"></td>
                              
                                <td id="discount_price"></td>
                                <td id="invoice_subtotal"></td>
                               
                                <td id="invoice_tax_price"></td>
                                <td id="total_price"></td>
                            </tr>
                            </tbody>

                        </table>
                    </div>

                </div>

                <div class="row float-right">
                    <div class="col-md-12 col-sm-12 col-xs-12 mt-10">
                        <ul class="list-unstyled amounts">
                            <li>
                                <strong>Total:</strong> <span id="grand_total_price"></span>/-
                            </li>
                        </ul>
                        <br/>

                        <a id="invoice-pdf" class="btn btn-lg btn-primary blue hidden-print margin-bottom-5" target="_blank"
                           href="">Print
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



