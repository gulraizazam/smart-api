<div class="invoice">
    <div class="row">
        <div class="col-md-12" style="text-align: center">
            @if($invoicestatus->slug == 'cancelled')
                <img src="{{ url('metronic/assets/pages/media/invoice/cancld.png') }}"
                     style="width: 20%;text-align: center;margin: 0 auto;" class="img-responsive" alt=""/>
            @endif
        </div>
    </div>
    <div class="row invoice-logo">
        <div class="col-md-6 col-sm-6 col-xs-12 invoice-logo-space">
            <img src="{{asset('centre_logo/')}}/{{$location_info->image_src}}" style="width: 50%;"
                 class="img-responsive" alt=""/>
        </div>

        <div class="col-md-6 col-sm-6 col-xs-12">
            <p> #{{$Invoiceinfo->id}} / <?php echo \Carbon\Carbon::parse($Invoiceinfo->created_at)->format('F j,Y'); ?>

            </p>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 col-sm-6 col-xs-12">
            <h3>Client:</h3>
            <ul class="list-unstyled">
                <li>
                    <strong>Name:</strong> {{$patient->name}} </li>
                <li>
                    <strong>Patient ID:</strong> {{'C-'.$patient->id}}</li>
                <li>
                    <strong>Email:</strong> {{$patient->email}} </li>
            </ul>
        </div>
        <div class="col-md-6 col-sm-6 col-xs-12 invoice-payment">
            <div class="float-right">
                <h3>Company:</h3>
                <ul class="list-unstyled">
                    <li>
                        <strong>Name:</strong> {{$account->name}} </li>
                    <li>
                        <strong>Contact:</strong>{{$company_phone_number->data}}</li>
                    <li>
                        <strong>Email:</strong> {{$account->email}} </li>
                    <li>
                        <strong>Clinic Name:</strong> {{$location_info->name}} </li>
                    <li>
                    <li>
                        <strong>Clinic Contact:</strong> {{$location_info->fdo_phone}} </li>
                    <li>
                        <strong>Address:</strong> {{$location_info->address}} </li>
                    <li>
                        <strong>NTN:</strong> {{$location_info->ntn}} </li>
                    <li>
                        <strong>STN:</strong> {{ $location_info->stn }}</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12 col-sm-12 col-xs-12 table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                <tr>
                    <th> #</th>
                    <th>Consultancy\Service</th>
                    <th> Service Price</th>
                    <th> Discount Name</th>
                    <th> Discount Type</th>
                    <th> Discount Price</th>
                    <th> Subtotal</th>
                    <th> Tax %</th>
                    <th> Tax</th>
                    <th> Total</th>
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
        <div class="col-md-4 col-sm-4 col-xs-12">
        </div>
        <div class="col-md-8 col-sm-8 col-xs-12 invoice-block">
            <ul class="list-unstyled amounts">
                <li>
                    <strong>Total:</strong> <?php echo number_format($Invoiceinfo->total_price);?>/-
                </li>
            </ul>
            <br/>

            <a class="btn btn-lg blue hidden-print margin-bottom-5" target="_blank"
               href="{{ route('admin.invoices.invoice_pdf',[$Invoiceinfo->id]) }}">@lang('global.app_pdf')
                <i class="fa fa-print"></i>
            </a>
        </div>
    </div>
    {{--<table class="table table-striped table-hover">
        <tr>
            <td style="text-align: center; color: #856404; background-color: #fff3cd; border-color: #ffeeba;">NOTE:
                Invoice is not Refundable
            </td>
        </tr>
    </table>--}}
</div>