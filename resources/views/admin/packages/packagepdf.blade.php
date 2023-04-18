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
            letter-spacing:3.5px;
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
            font-size:21px;
            padding:5px 0px 13px;
            margin:0px;
            font-weight:bold;
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

    <table>
        <tr>
            <td style="float:left">
                <img style="margin-bottom: 10px;" class="logo" src="{{asset('assets/media/new_logo.png')}}" class="img-responsive" alt=""/>
                <p class="logo_caption">{{$location_info->address}}.</p>
                <p class="logo_caption logo_caption2">Phone. {{$location_info->fdo_phone}} &nbsp;| &nbsp; Email. {{$account_info->email}}  &nbsp; | &nbsp;  www.cuteraesthetics.com  &nbsp; | &nbsp; NTN. {{$location_info->ntn}} &nbsp; | &nbsp; STN. {{$location_info->stn}}</p>
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
            <td class="main_heading"><?php echo \Carbon\Carbon::parse($package->created_at)->format('F j,Y'); ?>, {{\Carbon\Carbon::parse($package->created_at)->format('h:i a')}}</td>
        </tr>
        <!--tr>
            <td class="main_heading">Consumption Invoice <strong>{{$packageadvances}}</strong></td>
        </tr-->
        <tr>
            <td class="main_heading">Package# <strong>{{$package->name}}</strong></td>
        </tr>
        <tr>
            <td class="main_heading">{{ucfirst($package->user->name)}}, <strong>C-{{$package->user->id}}</strong></td>
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
            <td style="width:200px"><strong>Name:</strong> <span
                        style="padding-left: 10px;">{{$package->user->name}}</span></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td colspan="3"><strong>Name:</strong><span style="padding-left: 10px;">{{$account_info->name}}</span><</td>
        </tr>
        <tr>
            <td><strong>Patient ID:</strong> <span style="padding-left: 10px;">{{'C-'.$package->user->id}}</span></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td colspan="3"><strong>Contact:</strong> <span
                        style="padding-left: 10px;">{{$company_phone_number->data}}</span></td>
        </tr>
        <tr>
            <td><strong>Email:</strong> <span style="padding-left: 10px;">{{$package->user->email}}</span></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td colspan="3"><strong>Email:</strong> <span style="padding-left: 10px;">{{$account_info->email}}</span>
            </td>
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
            <td colspan="3" style="width:130px"><strong>Clinic Name:</strong> <span
                        style="padding-left: 10px;">{{$location_info->name}}</span></td>
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
            <td colspan="3"><strong>Address:</strong> <span
                        style="padding-left: 10px;">{{$location_info->address}}</span></td>
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
            <th>Service Name</th>
            <th>Service Price</th>
            <th>Discount Name</th>
            <th>Discount Type</th>
            <th>Discount Price</th>
            <th>Subtotal</th>
            <th>Tax %</th>
            <th>Tax Price</th>
            <th>Total</th>
        </tr>
        @if($packagebundles)
            @foreach($packagebundles as $packagebundles)
                <tr>
                    <td><?php echo $packagebundles->bundle->name; ?></td>
                    <td>{{number_format($packagebundles->service_price)}}</td>
                    <td>
                        @if($packagebundles->discount_id == null)
                            {{'-'}}
                        @elseif($packagebundles->discount_name)
                            {{$packagebundles->discount_name}}
                        @else
                            {{$packagebundles->discount->name}}
                        @endif
                    </td>
                    <td><?php if ($packagebundles->discount_type == null) {
                            echo '-';
                        } else {
                            echo $packagebundles->discount_type;
                        } ?>
                    </td>
                    <td><?php if ($packagebundles->discount_price == null) {
                            echo '0.00';
                        } else {
                            echo $packagebundles->discount_price;
                        } ?>
                    </td>
                    <td>{{$packagebundles->tax_exclusive_net_amount}}</td>
                    <td>{{$packagebundles->tax_percenatage}}</td>
                    <td>{{$packagebundles->tax_price}}</td>
                    <td>{{$packagebundles->tax_including_price}}</td>
                </tr>
            @endforeach
        @endif
    </table>
    <table class="grand-tax">
        <tbody>
        <tr>
            <td style="text-align: right;"><strong>Total:</strong> <?php echo $grand_total;?>/-</td>
        </tr>
        <tr>
            <td><strong>Note:</strong> All treatment prices are inclusive of taxes</td>
        </tr>
        </tbody>
    </table>
{{--    <div class="inclu-tax" style="float: left; margin-top:20px; width: 50%;">--}}
{{--        <strong>Note:</strong> All treatment prices are inclusive of taxes--}}
{{--    </div>--}}
{{--    <div class="grand-total" style="float: right; margin-top:20px;">--}}
{{--        --}}
{{--    </div>--}}


    <table style="margin:32 px 0px 0px -8px;" class="static_text">
        <tr>
            <td>
                <h4>Cash Received</h4>
            </td>
        </tr>
    </table>


    <table class="table">
        <tr>
            <th>Payment Mode</th>
            <th>Cash Flow</th>
            <th>Cash Amount</th>
            <th>Created At</th>
        </tr>
        @if($packageadvances)
            <?php $total_received = 0; ?>
            @foreach($packageadvances as $packageadvances)
                @if($packageadvances->cash_amount != '0' && $packageadvances->cash_flow == 'in')
                    <tr>
                        <td><?php echo $packageadvances->paymentmode->name; ?></td>
                        <td><?php echo $packageadvances->cash_flow; ?></td>
                        <td><?php echo number_format($packageadvances->cash_amount) ?>/-</td>
                        <td><?php echo \Carbon\Carbon::parse($packageadvances->created_at)->format('F j,Y h:i A'); ?></td>
                    </tr>
                    <?php $total_received += $packageadvances->cash_amount; ?>
                @endif
            @endforeach
            <tr>
                <td><b>Total</b></td>
                <td></td>
                <td><b>{{number_format($total_received)}}/-</b></td>
                <td></td>
            </tr>
        @endif
    </table>
    <table class="grand-tax" style="margin-top: 18px;">
        <tr>
            <td style="font-size:15px;">Thank you for your business with Cutera Aesthetics.</td>
        </tr>

        <tr>
            <td></td>
        </tr>
        <tr>
            <td style="font-size:15px;">
                <strong>Note: </strong>For Privacy, Cancellation,
                Late and Refund policies, please visit
                <a href="https://cuteraesthetics.com/" target="_blank">www.cuteraesthetics.com</a>
            </td>
        </tr>

    </table>
</div>
</body>
</html>
