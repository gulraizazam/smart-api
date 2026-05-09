

<!--begin::Modal content-->
<div class="modal-content">

    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder rota-title">{{ ucfirst($patient->name) }}@if(isset($doctor) && $doctor) - {{ $Invoiceinfo->appointment_type_id == 1 ? 'Consultation' : 'Treatment' }} with <span style="color: #3699FF;">{{ $doctor->name }}</span>@endif</h2>
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
                    <img style="width: 235px; margin-bottom: 10px;" class="img-responsive logo" src="{{asset('assets/media/new_logo.png')}}" alt=""/>
                    <p class="logo_caption">{{$location_info->address}}.</p>
                    <p class="logo_caption logo_caption2">Phone. {{$location_info->fdo_phone}}  &nbsp; |  &nbsp; Email. {{$account->email}}  &nbsp; | &nbsp;  www.alluraesthetics.pk  &nbsp; | &nbsp; NTN. {{$location_info->ntn}} &nbsp; | &nbsp; STN. {{$location_info->stn}}</p>
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
                    <img src="{{ asset('logo_final.png') }}" style="width: 50%;" class="img-responsive " alt=""/>
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
                                <th>Discount Price</th>
                                <th>Subtotal</th>
                                <th>Tax %</th>
                                <th>Tax</th>
                                <th>Total</th>
                            </tr>
                            </thead>

                            <tbody>
                                <tr>
                                <td>1</td>
                                <td>{{$service->name}} </td>
                                <td>
                                    {{number_format($service_price)}}
                                </td>
                                <td>
                                    @if($Invoiceinfo->discount_name)
                                        {{$Invoiceinfo->discount_name}}
                                    @elseif($discount != null)
                                        {{$discount->name}}
                                    @else
                                        -
                                    @endif
                                </td>
                               
                                <td>
                                     {{number_format($Invoiceinfo->tax_including_price)}}
                                </td>
                                <td>
                                    {{number_format($Invoiceinfo->tax_exclusive_serviceprice)}}
                                </td>
                                <td>
                                    {{$Invoiceinfo->tax_percentage}}
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
                            @if($Invoiceinfo->appointment_type_id == 1)

                                <a class="btn btn-success blue hidden-print margin-bottom-5" target="_blank"
                                href="{{ route('admin.invoices.invoice_pdf',[$Invoiceinfo->id, 'print', 1]) }}">Print Invoice
                                    <i class="fa fa-print"></i>
                                </a>

                                <a class="btn btn-info blue hidden-print margin-bottom-5" target="_blank"
                                href="{{ route('admin.invoices.invoice_pdf',[$Invoiceinfo->id]) }}">Print Consultation Form
                                    <i class="fa fa-print"></i>
                                </a>

                            @else

                                <a class="btn btn-success blue hidden-print margin-bottom-5" target="_blank"
                                href="{{ route('admin.invoices.invoice_pdf',[$Invoiceinfo->id]) }}">Print Invoice
                                    <i class="fa fa-print"></i>
                                </a>

                            @endif
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



