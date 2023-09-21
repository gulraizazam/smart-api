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
    <div class="modal-body scroll-y mx-5 mx-xl-15">

        <table style="margin-top: 20px;">
            <tr>
                <td>
                    <img style="width: 235px; margin-bottom: 10px;" class="img-responsive logo"
                        src="{{ asset('assets/media/new_logo.png') }}" alt="" />
                    <p class="logo_caption">{{ $location_info->address }}.</p>
                    <p class="logo_caption logo_caption2">Phone. {{ $location_info->fdo_phone }} &nbsp; | &nbsp; Email.
                        {{ $account->email }} &nbsp; | &nbsp; www.cuteraesthetics.com &nbsp; | &nbsp; NTN.
                        {{ $location_info->ntn }} &nbsp; | &nbsp; STN. {{ $location_info->stn }}</p>
                </td>
                <td style="padding:0px !important; float:right; width:120px; text-align:right;">
                    <div class="invoice_btn" style="width:120px; float:right; text-align:right;">
                        <span>INVOICE</span>
                    </div>
                </td>
            </tr>
        </table>
        <table style="margin:19px 0px 30px;">
            <tr>
                <td class="main_heading"><?php echo \Carbon\Carbon::parse($invoice_info->created_at)->format('F j,Y'); ?>,
                    {{ \Carbon\Carbon::parse($invoice_info->created_at)->format('h:i a') }}</td>
            </tr>
            <tr>
                <td class="main_heading">Order Invoice <strong>#{{ $invoice_info->id }}</strong></td>
            </tr>
            <tr>
                <td class="main_heading">{{ ucfirst($patient->name) }}, <strong>C-{{ $patient->id }}</strong></td>
            </tr>
        </table>

        <!--begin::Form-->
        <div class="d-flex flex-column scroll-y me-n7 pe-7 mt-10" id="kt_modal_resourcerotas_scroll">

            <div class="form-group">

                <div class="row">


                    <div class="table-responsive">
                        <table id="allocate_services" class="table table-bordered table-advance">

                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product Name</th>
                                    <th>Product Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td>{{ $product->name }} </td>
                                    <td>{{ $product->sale_price }}</td>
                                    <td>{{ $invoice_info->orderDetail->quantity }}</td>
                                    <td>{{ $invoice_info->total_price }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 col-sm-12 col-xs-12 mt-10">
                        <ul class="list-unstyled amounts float-right">
                            <li>
                                <strong>Total:</strong> <?php echo number_format($invoice_info->total_price); ?>/-
                            </li>
                        </ul>
                        <br />
                        <div class="text-center">
                            <a class="btn btn-success blue hidden-print margin-bottom-5" target="_blank"
                                href="{{ route('admin.orders.invoice_pdf', [$invoice_info->id]) }}">Print Invoice
                                <i class="fa fa-print"></i>
                            </a>

                            <a class="btn  btn-primary blue hidden-print margin-bottom-5" target="_blank"
                                href="{{ route('admin.orders.invoice_pdf', [$invoice_info->id, 'download']) }}">Download
                                <i class="fa fa-download"></i>
                            </a>

                        </div>

                    </div>
                </div>


            </div>

        </div>
        <!--end::Scroll-->


    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->
