<!DOCTYPE html>
<html>
<head>
    <style>
        .date {
            text-align: right;
        }

        .logo {
            width: 200px;
            text-align: left;
        }

        .layout-fixed {
            table-layout: fixed;
        }

        table {
            font-family: arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }

        .table th {
            border: 1px solid #f5f5f5;
            text-align: left;
            padding: 8px;
        }

        td, th {
            text-align: left;
            padding: 2px 8px;
            font-size: 12px;
        }

        .table td, .table th {
            text-align: left;
            padding: 8px;
            font-size: 12px;
        }

        .table tr:nth-child(even) {
            background-color: #f5f5f5;
        }

        .treat-recommend tr:nth-child(even) {
            background-color: #fff;
        }
        .treat-recommend td, .treat-recommend th {
            border: 1px solid #000;
        }
        .treat-recommend td {
            padding: 50px;
        }
        /*.treat-recommend td {
            border-bottom: 0;
            border-top: 0;
        }*/
        /*.treat-recommend  {
            border-bottom: 1px solid #000;
        }*/
        .danger-alert{
            color: #000;
            border:1px solid #f5c6cb;
            padding: 8px 10px;
            text-align: center;
            margin: 10px 0 0;
        }
        .mt-0 {
            margin-top: 0;
        }
        .checkbox {
            width: 15px;
            height: 15px;
            display: inline-block;
            border: 1px solid #000;
            border-radius: 2px;
        }
        .check-form th {
            padding: 8px;
        }
        .check-form td {
            padding: 0;
        }
        .check-form .checkbox + span {
            line-height: 21px;
            padding-left: 3px;
            /*transform: translateY(-4px);*/
            display: inline-block;
        }
        .check-form tbody tr:first-child .checkbox {
            border-top: 1px solid #000;
        }
        .check-form tbody tr:first-child td {
            padding-top: 15px;
        }
        .medication-list tbody td{
            border-bottom: 1px solid #000;
            padding: 15px;
        }

        .client-info td{
            padding-top: 8px
        }
    </style>
</head>
<body>
<div class="invoice-pdf">
    <table class="mt-0 layout-fixed" style="margin-top: -20px;">
        <tr>
            <td></td>
            <td style="text-align: right;">Phone: {{$company_phone_number->data}} &nbsp;&nbsp;&nbsp; Email: {{$account->email}}</td>
        </tr>
    </table>
    @if($invoicestatus->slug == 'cancelled')
        <table class="">
            <tr style="padding-left: 50%">
                <img src="{{ url('metronic/assets/pages/media/invoice/cancld.png') }}" style="width: 20%;text-align: center;padding-left:43%" class="img-responsive" alt=""/>
            </tr>
        </table>
    @endif
    <table class="mt-0">
        <tr>
            <td><img class="logo" src="{{asset('centre_logo/')}}/{{$location_info->image_src}}" class="img-responsive" alt=""/></td>
            <td><h4 class="date">#{{$Invoiceinfo->id}}
                    / <?php echo \Carbon\Carbon::parse($Invoiceinfo->created_at)->format('F j,Y'); ?></h4></td>
        </tr>
    </table>
    <table class="layout-fixed client-info" style="margin-top: 10px;">
        <!--  <tr>
             <th colspan="2">Client</th>
             <th>Company</th>
         </tr> -->
        <tr>
            <td><strong style="width: 50px; display: inline-block;">Name:</strong><span style="padding-left: 5px;">{{$patient->name}}</span></td>
            <td><strong style="width: 50px; display: inline-block;">Height:</strong><span style="padding-left: 5px;display: inline-block;transform: translateY(-5px);">____________________</span></td>
            <td><strong>Consultant:</strong><span style="padding-left: 5px;">{{$appointment_info->doctor->name}}</span><</td>
        </tr>
        <tr>
            <td><strong style="width: 75px; display: inline-block;">Patient ID:</strong> <span style="padding-left: 5px;">{{'C-'.$patient->id}}</span></td>
            <td><strong style="width: 50px; display: inline-block;">Weight:</strong><span style="padding-left: 5px;display: inline-block;transform: translateY(-5px);">____________________</span></td>
            <td><strong>Clinic Name:</strong> <span style="padding-left: 5px;">{{$location_info->name}}</span></td>
        </tr>
        <tr>
            <td><strong style="width: 50px; display: inline-block;">Age:</strong> <span style="padding-left: 5px;display: inline-block;transform: translateY(-5px);">____________________</span></td>
            <td><strong style="width: 50px; display: inline-block;">BMI:</strong> <span style="padding-left: 5px;display: inline-block;transform: translateY(-5px);">____________________</span></td>
            <td><strong>Clinic Contact:</strong> <span style="padding-left: 5px;">{{$location_info->fdo_phone}}</span></td>
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
{{--            <th> Subtotal</th>--}}
{{--            <th> Tax</th>--}}
{{--            <th> Total</th>--}}
            <th> Amount</th>
        </tr>
        <tr>
            <td> 1</td>
            <td>{{$service->name}} </td>
            <td>
                @if($Invoiceinfo->is_exclusive == '0')
                    @if($Invoiceinfo->service_price == '0')
                        {{number_format($Invoiceinfo->tax_including_price)}}
                    @else
                        {{number_format(($Invoiceinfo->service_price)-($Invoiceinfo->tax_price))}}
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
{{--            <td>--}}
{{--                @if($Invoiceinfo->is_exclusive == '0')--}}
{{--                    @if($Invoiceinfo->discount_price == null)--}}
{{--                        {{number_format(($Invoiceinfo->service_price)-($Invoiceinfo->tax_price))}}--}}
{{--                    @else--}}
{{--                        {{number_format($Invoiceinfo->tax_exclusive_serviceprice)}}--}}
{{--                    @endif--}}
{{--                @elseif($Invoiceinfo->is_exclusive == '1')--}}
{{--                    {{number_format($Invoiceinfo->tax_exclusive_serviceprice)}}--}}
{{--                @endif--}}
{{--            </td>--}}
{{--            <td>--}}
{{--                {{$Invoiceinfo->tax_price}}--}}
{{--            </td>--}}
            <td>
                {{number_format($Invoiceinfo->tax_including_price)}}
            </td>
        </tr>
    <!--  <tfoot>
        <tr>
            <td colspan="9" style="text-align: right; "><strong style="font-size: 14px;">Grand-total:</strong> <?php echo number_format($Invoiceinfo->total_price);?>/-</td>
        </tr>
        </tfoot> -->
    </table>
    <table class="layout-fixed check-form" style="margin-top: 10px;">
        <thead>
        <tr>
            <th style="text-align: center; padding: 8px;background: #f5f5f5;" colspan="3">Health History (Please check all that apply.)</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td><span class="checkbox"></span> <span>Illness or injury within 5 years</span></td>
            <td><span class="checkbox"></span> <span>History of heart disease </span></td>
            <td><span class="checkbox"></span> <span>History of seizures or epilepsy </span></td>
        </tr>
        <tr>
            <td><span class="checkbox"></span> <span>Any surgeries done</span></td>
            <td><span class="checkbox"></span> <span>Heart surgery/prosthesis/stents </span></td>
            <td><span class="checkbox"></span> <span>Skin disease </span></td>
        </tr>
        <tr>
            <td><span class="checkbox"></span> <span>History of cardiovascular problems </span></td>
            <td><span class="checkbox"></span> <span>Dental implants/bridge/ti plates </span></td>
            <td><span class="checkbox"></span> <span>High blood pressure </span></td>
        </tr>
        <tr>
            <td><span class="checkbox"></span> <span>Anemia</span></td>
            <td><span class="checkbox"></span> <span>History of hernia/hernia surgery </span></td>
            <td><span class="checkbox"></span> <span>Hormonal disorders/hormonal therapy </span></td>
        </tr>
        <tr>
            <td><span class="checkbox"></span> <span>Kidney disease or dialysis</span></td>
            <td><span class="checkbox"></span> <span>Psychiatric disorders/depression </span></td>
            <td><span class="checkbox"></span> <span>Polycystic ovaries </span></td>
        </tr>
        <tr>
            <td><span class="checkbox"></span> <span>Nervous disorders </span></td>
            <td><span class="checkbox"></span> <span>HIV Aids </span></td>
            <td><span class="checkbox"></span> <span>Fibroids </span></td>
        </tr>
        <tr>
            <td><span class="checkbox"></span> <span>Thyroid disorders</span></td>
            <td><span class="checkbox"></span> <span>Hepatitis </span></td>
            <td><span class="checkbox"></span> <span>Pregnancy</span></td>
        </tr>
        <tr>
            <td><span class="checkbox"></span> <span>Liver disease </span></td>
            <td><span class="checkbox"></span> <span>Cushing's Syndrome </span></td>
            <td><span class="checkbox"></span> <span>Cancer </span></td>
        </tr>
        <tr>
            <td><span class="checkbox"></span> <span>History of drug or alcohol use </span></td>
            <td><span class="checkbox"></span> <span>Diabetes </span></td>
            <td><span class="checkbox"></span> <span>Others </span></td>
        </tr>
        </tbody>
    </table>

<!--   <div class="grand-total" style="float: right; margin-top:0px;">
        <P><strong style="font-size: 14px;">Grand-total:</strong> <?php //echo number_format($Invoiceinfo->total_price);?>
        /-</P>
</div> -->

    <table class="medication-list" style="margin-top: 10px;">
        <thead>
        <tr>
            <th style="text-align: center; padding: 8px;background: #f5f5f5; font-weight: 400;">Please explain any marked answer. Fully describe diagnosis, physician, treatment, medication, and so on. In addition, please list all medications that you currently take, or have recently used. </th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td></td>
        </tr>
        <tr>
            <td></td>
        </tr>
        <tr>
            <td></td>
        </tr>
        </tbody>
    </table>
    <table class="">
        <tr>
            <th style="text-align: center; padding: 8px;background: #f5f5f5;">Treatment Recommended</th>
        </tr>
    </table>
    <table class="table layout-fixed treat-recommend" style="margin-top: 10px;">
        <tr>
            <th colspan="2">Treatment Advised </th>
            <th> No. of Sessions</th>
            <th> Retail Price</th>
            <th> Discount %</th>
            <th> Price Offered</th>
            <th> What is Customer Willing to Pay? </th>
            <th> Was Client Converted?</th>
        </tr>
        <tr>
            <td colspan="2"></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <table class="mt-0" style="width: 100%;position: fixed;bottom: 40px;">
        <tr>
            <td>&nbsp;</td>
            <td style="text-align: right;">{{$location_info->address}}</td>
        </tr>
        <tr>
            <td><strong>Consultant Signature: </strong><span>__________________________</span></td>
            <td style="text-align: right;"><strong>NTN:</strong> <span>{{$location_info->ntn}}</span> <strong style="padding-left: 5px;">STN:</strong> <span>{{$location_info->stn}}</span></td>
        </tr>
    </table>
</div>

{{--<table style="width: 100%;" class="mt-0">
    <tr>
        <td><div class="danger-alert">Invoice is not Refundable</div></td>
    </tr>
</table>--}}
</body>

</html>