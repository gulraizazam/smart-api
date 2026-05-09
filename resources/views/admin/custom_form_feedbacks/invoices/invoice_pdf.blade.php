<!DOCTYPE html>
<html>
<head>
    <style>
        table {
            font-family: arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
        }
        .invoice_btn{
            float:right;
        }
        .invoice_btn span {
            text-align: center;
            font-size:18px;
            font-weight:lighter;
            background:#1d1d1b;
            padding:6px 26px;
            color:#fff;
            display: inline-block;
            vertical-align:middle;
            text-transform:capitalize;
            letter-spacing:4px;
        }
        .main_heading{
            text-align: left;
            font-size:17px;
            padding:5px 0px;
            letter-spacing:.7px;
        }
        .main_heading  strong{
            font-size:16px;
        }
        .main_heading span{
            font-size:12px;
            text-transform:capitalize;
        }
        .logo {
            text-align: left;
            margin-left:-6px;
            margin-bottom:-5px;
            width:320px;
        }

        .table th {
            border: 1px solid #ddd;
            text-align: left;
            padding: 8px;
        }

        td, th {
            text-align: left;
            padding: 8px;
            font-size: 12px;
        }

        .table td, .table th {
            text-align: left;
            padding: 8px;
            font-size: 12px;
        }

        .table tr:nth-child(even) {
            background-color: #f7f7f7;
        }
        .danger-alert{
            color: #000;
            border:1px solid #f5c6cb;
            padding: 8px 10px;
            text-align: center;
            margin: 10px 0 0;
        }
        .grand-tax {
            margin-top: 0;
        }
        .grand-tax tr:first-child td {
            padding-bottom: 0;
        }
        .grand-tax tr:last-child td {
            padding-top: 0;
        }
        .grand-tax td {
            padding-left: 0;
            padding-right: 0;
        }
        .signature_section{
            margin-top:80px;
            margin-left:-9px;
        }
        .signature_section p{
            /* float:left; */
            border-top:1px solid #1d1d1b;
            padding:12px 0px 0px;
            font-size:15px;
            margin:0px 0px -5px;
        }
        .signature_section span{
            font-size:12px;
            font-weight:bold;
            display:block;
            width:100%;
        }
        .static_text h4{
            text-align: left;
            font-size:17px;
            padding:5px 0px 38px;
            margin:0px;
            font-weight:lighter;
        }
        .static_text2.static_text h4{
            padding:5px 0px 5px;
        }
        .static_text2 span{
            text-align: left;
            font-size:17px;
            font-weight:lighter;
            float:left;
            padding-top:6px;
            margin-right:7px;
        }
        .static_text2 span.high{
            margin-left:7px;
        }
        .static_text p{
            margin:0px 0px 0px;
            padding:0px;
            border-bottom:1px dotted #8d8d8d;
        }
        .logo_caption{
            font-size:11px;
            font-weight:lighter;
            margin-left:-6px;
        }
        .logo_caption2{
            margin-top:-9px;
        }
        .static_text2 .counter{
            width:36px;
            height:36px;
            border: 0.5px solid #ddd;
            margin:0px 6px;
            border-radius:100%;
            float:left;
            text-align:center;
            font-size:17px;
            font-weight:lighter;
            vertical-align: middle;
        }
        .static_text2 .counter strong{
            font-weight:lighter;
            padding-top:7px;
            display:inline-block;
            width:100%;
            text-align:center;
        }
    </style>
</head>
<body>
<div class="invoice-pdf">
    <!-- <table style="display:none;">
        <tr style="padding-left: 50%"> -->
            @if($invoicestatus->slug == 'cancelled')
                <img src="{{ url('metronic/assets/pages/media/invoice/cancld.png') }}" style="width: 20%;text-align: center;padding-left:43% display:none;" class="img-responsive" alt=""/>
            @endif
        <!-- </tr>
    </table> -->

    <table>
        <tr>
            <td>
                <img class="logo" src="{{asset('assets/media/logos/smart-invoice-logo.png')}}" class="img-responsive" alt=""/>
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
    <table style="display:none;">
        <tr style="padding-top: 30px;">
            <th>Client</th>
            <th><!-- left empty --></th>
            <th><!-- left empty --></th>
            <th><!-- left empty --></th>
            <th><!-- left empty --></th>
            <th><!-- left empty --></th>
            <th><!-- left empty --></th>
            <th><!-- left empty --></th>
            <th><!-- left empty --></th>
            <th><!-- left empty --></th>
            <th colspan="3" style="width: 250px;">Company</th>
        </tr>
        <tr>
            <td style="width:200px"><strong>Name:</strong><span style="padding-left: 10px;">{{$patient->name}}</span></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td colspan="3"><strong>Name:</strong><span style="padding-left: 10px;">{{$account->name}}</span><</td>
        </tr>
        <tr>
            <td><strong>Patient ID:</strong> <span style="padding-left: 10px;">{{'C-'.$patient->id}}</span></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td colspan="3"><strong>Contact:</strong> <span style="padding-left: 10px;">{{$company_phone_number->data}}</span></td>
        </tr>
        <tr>
            <td><strong>Email:</strong> <span style="padding-left: 10px;">{{$patient->email}}</span></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td colspan="3"><strong>Email:</strong> <span style="padding-left: 10px;">{{$account->email}}</span></td>

        </tr>
        <tr>
            <td></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td colspan="3" style="width:130px"><strong>Clinic Name:</strong> <span style="padding-left: 10px;">{{$location_info->name}}</span></td>
        </tr>
        <tr>
            <td></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td colspan="3" style="width:130px"><strong>Clinic Contact:</strong> <span
                        style="padding-left: 10px;">{{$location_info->fdo_phone}}</span></td>
        </tr>
        <tr>
            <td></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td colspan="3"><strong>Address:</strong> <span style="padding-left: 10px;">{{$location_info->address}}</span></td>
        </tr>
        <tr>
            <td></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td colspan="3"><strong>NTN:</strong> <span style="padding-left: 10px;">{{$location_info->ntn}}</span></td>
        </tr>
        <tr>
            <td></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td colspan="3"><strong>STN:</strong> <span style="padding-left: 10px;">{{$location_info->stn}}</span></td>
        </tr>

    </table>
    <table class="table">
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
        <tr>
            <td> 1</td>
            <td>{{$service->name}} </td>
            <td>
                @if($Invoiceinfo->is_exclusive == '0' && $bundle->type == 'single')
                    {{number_format(($Invoiceinfo->service_price)-($Invoiceinfo->tax_price))}}
                @elseif($Invoiceinfo->is_exclusive == '0' && $bundle->type == 'multiple')
                    {{number_format($Invoiceinfo->service_price)}}
                @elseif($Invoiceinfo->is_exclusive == '1')
                    {{number_format($Invoiceinfo->service_price)}}
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
            <td>{{$Invoiceinfo->tax_percentage}}</td>
            <td>
                {{$Invoiceinfo->tax_price}}
            </td>
            <td>
                {{number_format($Invoiceinfo->tax_including_price)}}
            </td>
        </tr>
    </table>
    <table class="grand-tax" style="display:none;">
        <tbody>
        <tr>
            <td style="text-align: right;"><strong>Total:</strong> <?php echo number_format($Invoiceinfo->total_price);?>/-</td>
        </tr>
        <tr>
            <td><strong>Note:</strong> All treatment prices are inclusive of taxes.</td>
        </tr>
        </tbody>
    </table>
    <table style="margin:25px 0px 0px -9px;" class="static_text static_text2">
        <tr>
            <td>
                <h4>How satisfied are you with quality of service(s) provided?</h4>
            </td>
        </tr>
        <tr>
            <td style="padding-bottom:40px;">
                <span>>> Low</span>
                <div class="counter"> <strong>1 </strong></div>
                <div class="counter"> <strong>2</strong></div>
                <div class="counter"> <strong>3</strong></div>
                <div class="counter"> <strong>4</strong></div>
                <div class="counter"> <strong>5</strong></div>
                <span class="high">High >></span>
            </td>
        </tr>
    </table>

    <table style="margin:32px 0px 0px -8px;" class="static_text">
        <tr>
            <td>
                <h4>Your feedback:</h4>
                <p></p><br><br>
                <p></p><br><br>
                <p></p><br><br>
                <p></p><br><br>
            </td>
        </tr>
    </table>

    <table class="signature_section">
        <tr>
            <td>
                <p>Customer Signature</p> <br>
                <span>{{ucfirst($patient->name)}}</span>
            </td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td>
                <p>Doctor's Signature</p> <br>
                <span>{{$appointment_info?->doctor?->name}}</span>
            </td>
        </tr>
    </table>
</div>
{{--<table style="width: 100%;">
    <tr>
        <td><div class="danger-alert">Invoice is not Refundable</div></td>
    </tr>
</table>--}}
</body>

</html>
