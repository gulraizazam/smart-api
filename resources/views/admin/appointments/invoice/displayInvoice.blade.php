

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

        <table style="margin-top: 20px;">
            <tr>
                <td>
                    <img style="width: 235px; margin-bottom: 10px;" class="img-responsive logo" src="{{asset('assets/media/logos/logo.svg')}}" alt=""/>
                    <p class="logo_caption">{{$location_info->address}}.</p>
                    <p class="logo_caption logo_caption2">Phone. {{$location_info->fdo_phone}}  &nbsp; |  &nbsp; Email. {{$account->email}}  &nbsp; | &nbsp;  www.cutera.pk  &nbsp; | &nbsp; NTN. {{$location_info->ntn}} &nbsp; | &nbsp; STN. {{$location_info->stn}}</p>
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
                <td class="main_heading"><?php echo \Carbon\Carbon::parse($Invoiceinfo->created_at)->format('F j,Y'); ?>, {{\Carbon\Carbon::parse($Invoiceinfo->created_at)->format('h:i a')}}</td>
            </tr>
            <tr>
                <td class="main_heading">Consumption Invoice <strong>#{{$Invoiceinfo->id}}</strong></td>
            </tr>
            <tr>
                <td class="main_heading">{{ucfirst($patient->name)}}, <strong>C-{{$patient->id}}</strong></td>
            </tr>
        </table>


        {{--<div class="form-group">

            <div class="row">

                <div class="col-md-6 col-sm-6 col-xs-12 invoice-logo-space">
                    <img src="{{asset('assets/media/logos/logo.svg')}}" style="width: 50%;" class="img-responsive " alt=""/>
                </div>

                <div class="col-md-6 col-sm-6 col-xs-12 invoice-logo-space text-right">
                    <h3>#<span>{{$Invoiceinfo->id}}</span> / <?php echo \Carbon\Carbon::parse($Invoiceinfo->created_at)->format('F j,Y'); ?></h3>
                </div>

            </div>
        </div>--}}

       {{-- <div class="form-group mt-10">

            <div class="row">
                <div class="col-md-8 col-sm-8 col-xs-12 invoice-logo-space">
                    <h4>Client:</h4>
                    <ul class="list-unstyled">
                        <li>
                            <strong>Name:</strong> {{$patient->name}}
                        </li>
                        <li>
                            <strong>Patient ID:</strong> {{'C-'.$patient->id}}
                        </li>
                        <li>
                            <strong>Email:</strong> {{$patient->email}}
                        </li>
                    </ul>
                </div>

                <div class="col-md-4 col-sm-4 col-xs-12 invoice-logo-space">
                    <h4>Company:</h4>
                    <ul class="list-unstyled">
                        <li>
                            <strong>Name:</strong> {{$account->name}}
                        </li>

                        <li>
                            <strong>Contact:</strong>{{$company_phone_number->data}}
                        </li>
                        <li>
                            <strong>Email:</strong> {{$account->email}}
                        </li>
                        <li>
                            <strong>Clinic Name:</strong> {{$location_info->name}}
                        </li>
                        <li>
                            <strong>Clinic Contact:</strong> {{$location_info->fdo_phone}}
                        </li>
                        <li>
                            <strong>Address:</strong> {{$location_info->address}}
                        </li>
                        <li>
                            <strong>NTN:</strong> {{$location_info->ntn}}
                        </li>
                        <li>
                            <strong>STN:</strong> {{ $location_info->stn }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>--}}

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
                                <th>Discount Type</th>
                                <th>Discount Price</th>
                                <th>Subtotal</th>
                                <th>Tax %</th>
                                <th>Tax Price</th>
                                <th>Total</th>
                            </tr>
                            </thead>

                            <tbody>
                                <tr>
                                <td>1</td>
                                <td>{{$service->name}} </td>
                                <td>
                                    @if($Invoiceinfo->is_exclusive == '0' && $bundle->type == 'single')
                                        @if($Invoiceinfo->service_price == '0')
                                            {{number_format($Invoiceinfo->tax_including_price)}}
                                        @else
                                            {{number_format(($Invoiceinfo->service_price)-($Invoiceinfo->tax_price))}}
                                        @endif
                                    @elseif($Invoiceinfo->is_exclusive == '0' && $bundle->type == 'multiple')
                                        @if($Invoiceinfo->service_price == '0')
                                            {{number_format($Invoiceinfo->tax_including_price)}}
                                        @else
                                            {{number_format($Invoiceinfo->service_price)}}
                                        @endif
                                    @elseif($Invoiceinfo->is_exclusive == '1')
                                        @if($Invoiceinfo->service_price == '0')
                                            {{number_format($Invoiceinfo->tax_including_price)}}
                                        @else
                                            {{number_format($Invoiceinfo->service_price)}}
                                        @endif
                                    @endif

                                </td>
                                <td>
                                    @if($discount != null)
                                        {{$discount->name}}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($Invoiceinfo->discount_type != null)
                                        {{$Invoiceinfo->discount_type}}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($Invoiceinfo->discount_price != null)
                                        {{number_format($Invoiceinfo->discount_price)}}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($Invoiceinfo->is_exclusive == '0')
                                        @if($Invoiceinfo->discount_price == null && $bundle->type == 'single')
                                            {{number_format(($Invoiceinfo->service_price)-($Invoiceinfo->tax_price))}}
                                        @else
                                            {{number_format($Invoiceinfo->tax_exclusive_serviceprice)}}
                                        @endif
                                    @elseif($Invoiceinfo->is_exclusive == '1')
                                        {{number_format($Invoiceinfo->tax_exclusive_serviceprice)}}
                                    @endif
                                </td>
                                <td>
                                    {{$Invoiceinfo->tax_percenatage}}
                                </td>
                                <td>{{$Invoiceinfo->tax_price}}</td>
                                <td>
                                    {{number_format($Invoiceinfo->tax_including_price)}}
                                </td>
                            </tr>
                            </tbody>

                        </table>
                    </div>

                </div>

                <div class="row">
                    <div class="col-md-12 col-sm-12 col-xs-12 mt-10">
                        <ul class="list-unstyled amounts float-right">
                            <li>
                                <strong>Total:</strong> <?php echo number_format($Invoiceinfo->total_price);?>/-
                            </li>
                        </ul>
                        <br/>

                        <div class="text-center">
                            <a style="width: 200px;" class="btn btn-success blue hidden-print margin-bottom-5" target="_blank"
                               href="{{ route('admin.invoices.invoice_pdf',[$Invoiceinfo->id]) }}">Print
                                <i class="fa fa-print"></i>
                            </a>

                            <a class="btn btn-sm btn-primary blue hidden-print margin-bottom-5" target="_blank"
                               href="{{ route('admin.invoices.invoice_pdf',[$Invoiceinfo->id, 'download']) }}">Download
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



